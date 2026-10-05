<?php

namespace App\Services;

use App\Models\LogKernel;
use App\Models\LogKlarifikasi;
use App\Models\LogLab;
use App\Models\LogMaintenance;
use App\Models\LogPress;
use App\Models\LogSortasi;
use App\Models\LogSterilizer;
use App\Models\LogTimbang;
use App\Models\MaintenanceTicket;
use App\Support\StationChartConfig;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Rekap operasional harian pabrik — dipakai command telegram:daily-recap
 * (rekap teks + lampiran PDF) dan bisa dipakai ulang untuk kebutuhan lain.
 */
class DailyRecapService
{
    /**
     * Mapping stasiun -> model (sumber data rekap).
     *
     * @return array<string, class-string>
     */
    public static function stationModels(): array
    {
        return [
            'timbang' => LogTimbang::class,
            'sortasi' => LogSortasi::class,
            'sterilizer' => LogSterilizer::class,
            'press' => LogPress::class,
            'klarifikasi' => LogKlarifikasi::class,
            'kernel' => LogKernel::class,
            'lab' => LogLab::class,
            'maintenance' => LogMaintenance::class,
        ];
    }

    /**
     * Ringkasan per stasiun untuk satu tanggal + agregat kualitas.
     *
     * @return array{date: Carbon, plant: string, stations: array<string, array{label: string, total: int, flagged: int, unverified: int}>, ffa: ?float, losses_fiber: ?float, tonnage: float, efficiency: int}
     */
    public function summary(string $plantId, Carbon $date): array
    {
        $start = $date->copy()->startOfDay();
        $end = $date->copy()->endOfDay();

        $stations = [];
        $labels = StationChartConfig::titles();

        foreach (self::stationModels() as $station => $modelClass) {
            $row = $modelClass::query()
                ->where('plant_id', $plantId)
                ->whereBetween('timestamp_kirim', [$start, $end])
                ->selectRaw(implode(', ', [
                    'COUNT(*) as total',
                    'SUM(CASE WHEN is_flagged THEN 1 ELSE 0 END) as flagged',
                    'SUM(CASE WHEN NOT is_verified THEN 1 ELSE 0 END) as unverified',
                ]))
                ->first();

            $stations[$station] = [
                'label' => $labels[$station] ?? ucfirst($station),
                'total' => (int) ($row->total ?? 0),
                'flagged' => (int) ($row->flagged ?? 0),
                'unverified' => (int) ($row->unverified ?? 0),
            ];
        }

        $lab = LogLab::query()
            ->where('plant_id', $plantId)
            ->whereBetween('timestamp_kirim', [$start, $end])
            ->selectRaw('AVG(kadar_alb_cpo) as ffa, AVG(losses_fiber_persen) as fiber')
            ->first();

        $tonnage = LogTimbang::query()
            ->where('plant_id', $plantId)
            ->whereBetween('timestamp_kirim', [$start, $end])
            ->sum('tonase_bruto');

        return [
            'date' => $date,
            'plant' => $plantId,
            'stations' => $stations,
            'ffa' => $lab->ffa !== null ? round((float) $lab->ffa, 2) : null,
            'losses_fiber' => $lab->fiber !== null ? round((float) $lab->fiber, 2) : null,
            'tonnage' => $tonnage !== null ? round((float) $tonnage / 1000, 2) : 0.0,
            'efficiency' => $this->efficiency($plantId, $start, $end),
        ];
    }

    /**
     * Skor efisiensi sederhana untuk rentang tanggal (press + sterilizer).
     */
    protected function efficiency(string $plantId, Carbon $start, Carbon $end): int
    {
        $pressAvg = LogPress::where('plant_id', $plantId)
            ->whereBetween('timestamp_kirim', [$start, $end])
            ->avg('tekanan_hidrolik');
        $suhuAvg = LogSterilizer::where('plant_id', $plantId)
            ->whereBetween('timestamp_kirim', [$start, $end])
            ->avg('suhu_celcius');

        $score = 0;
        $factors = 0;

        if ($pressAvg !== null) {
            $score += ($pressAvg >= 60 && $pressAvg <= 75) ? 50 : max(20, 50 - abs($pressAvg - 67.5) * 2);
            $factors++;
        }
        if ($suhuAvg !== null) {
            $score += ($suhuAvg >= 110 && $suhuAvg <= 145) ? 50 : max(20, 50 - abs($suhuAvg - 127.5) * 0.75);
            $factors++;
        }

        return $factors > 0 ? (int) round($score / $factors) : 0;
    }

    /**
     * Susun pesan teks rekap (Markdown) untuk dikirim via bot.
     */
    public function recapText(array $summary): string
    {
        $lines = [];
        $lines[] = '*📊 REKAP HARIAN POMS*';
        $lines[] = 'Pabrik: `'.$summary['plant'].'`';
        $lines[] = 'Tanggal: '.$summary['date']->format('d/m/Y');
        $lines[] = '';

        foreach ($summary['stations'] as $st) {
            if ($st['total'] === 0) {
                $lines[] = '▫️ '.$st['label'].': _tidak ada data_';

                continue;
            }
            $marks = [];
            if ($st['flagged'] > 0) {
                $marks[] = '🚩'.$st['flagged'];
            }
            if ($st['unverified'] > 0) {
                $marks[] = '⌛'.$st['unverified'];
            }
            $lines[] = '▫️ '.$st['label'].': *'.$st['total'].'* record'
                .(count($marks) > 0 ? ' ('.implode(' ', $marks).')' : '');
        }

        $lines[] = '';
        $lines[] = '*Tonnage Bruto:* '.number_format($summary['tonnage'], 2).' ton';
        $lines[] = '*FFA (ALB CPO):* '.($summary['ffa'] !== null ? $summary['ffa'].'%' : '—');
        $lines[] = '*Losses Fiber:* '.($summary['losses_fiber'] !== null ? $summary['losses_fiber'].'%' : '—');
        $lines[] = '*Skor Efisiensi:* '.$summary['efficiency'].'/100';

        return implode("\n", $lines);
    }

    /**
     * Generate PDF rekap harian (plant-wide) dan simpan sementara.
     *
     * @return array{path: string, filename: string} path relatif di storage disk lokal
     */
    public function generatePdf(array $summary): array
    {
        $data = [
            'timbang' => LogTimbang::where('plant_id', $summary['plant'])
                ->whereBetween('timestamp_kirim', [
                    $summary['date']->copy()->startOfDay(),
                    $summary['date']->copy()->endOfDay(),
                ])
                ->selectRaw('COUNT(*) as total_entries, SUM(tonase_bruto) as total_bruto, SUM(tonase_tarra) as total_tarra, AVG(potongan_persen) as avg_potongan')
                ->first(),
            'sterilizer' => LogSterilizer::where('plant_id', $summary['plant'])
                ->whereBetween('timestamp_kirim', [
                    $summary['date']->copy()->startOfDay(),
                    $summary['date']->copy()->endOfDay(),
                ])
                ->selectRaw('COUNT(*) as total_entries, AVG(tekanan_bar) as avg_tekanan, AVG(suhu_celcius) as avg_suhu, AVG(durasi_menit) as avg_durasi')
                ->first(),
            'press' => LogPress::where('plant_id', $summary['plant'])
                ->whereBetween('timestamp_kirim', [
                    $summary['date']->copy()->startOfDay(),
                    $summary['date']->copy()->endOfDay(),
                ])
                ->selectRaw('COUNT(*) as total_entries, AVG(tekanan_hidrolik) as avg_tekanan, AVG(ampere_motor) as avg_ampere')
                ->first(),
            'lab' => LogLab::where('plant_id', $summary['plant'])
                ->whereBetween('timestamp_kirim', [
                    $summary['date']->copy()->startOfDay(),
                    $summary['date']->copy()->endOfDay(),
                ])
                ->selectRaw('AVG(kadar_alb_cpo) as avg_ffa, AVG(losses_fiber_persen) as avg_losses_fiber, AVG(losses_jankos_persen) as avg_losses_jankos')
                ->first(),
        ];

        $pdf = Pdf::loadView('exports.daily-report-pdf', [
            'data' => $data,
            'date' => $summary['date']->format('Y-m-d'),
            'plant' => $summary['plant'],
            'preparedBy' => 'Sistem (rekap otomatis)',
        ])->setOption(['isPhpEnabled' => true, 'defaultFont' => 'Arial']);

        $filename = "daily_recap_{$summary['plant']}_{$summary['date']->format('Ymd')}.pdf";
        $path = 'recaps/'.$filename;

        Storage::disk('local')->put($path, $pdf->output());

        return ['path' => $path, 'filename' => $filename];
    }

    /**
     * =====================================================================
     * Rekap mingguan (agregat rentang beberapa hari)
     * =====================================================================
     */

    /**
     * Rentang "minggu lalu" (Senin s.d. Minggu) sebelum tanggal acuan.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public static function lastWeekRange(Carbon $reference): array
    {
        $start = $reference->copy()->subWeek()->startOfWeek();
        $end = $start->copy()->addDays(6)->endOfDay();

        return [$start, $end];
    }

    /**
     * Agregat operasional untuk rentang tanggal (rekap mingguan 7 hari).
     *
     * @return array{start: Carbon, end: Carbon, plant: string, stations: array<string, array{label: string, total: int, flagged: int, unverified: int}>, records: int, flagged: int, unverified: int, ffa: ?float, losses_fiber: ?float, tonnage: float, efficiency: int, daily: array<int, array{date: Carbon, records: int, tonnage: float}>, tickets: array{created: int, done: int, active: int}}
     */
    public function weeklySummary(string $plantId, Carbon $start, ?Carbon $end = null): array
    {
        $start = $start->copy()->startOfDay();
        $end = ($end ?? $start->copy()->addDays(6))->copy()->endOfDay();

        $labels = StationChartConfig::titles();
        $stations = [];
        $records = 0;
        $flagged = 0;
        $unverified = 0;

        foreach (self::stationModels() as $station => $modelClass) {
            $row = $modelClass::query()
                ->where('plant_id', $plantId)
                ->whereBetween('timestamp_kirim', [$start, $end])
                ->selectRaw(implode(', ', [
                    'COUNT(*) as total',
                    'SUM(CASE WHEN is_flagged THEN 1 ELSE 0 END) as flagged',
                    'SUM(CASE WHEN NOT is_verified THEN 1 ELSE 0 END) as unverified',
                ]))
                ->first();

            $total = (int) ($row->total ?? 0);
            $records += $total;
            $flagged += (int) ($row->flagged ?? 0);
            $unverified += (int) ($row->unverified ?? 0);

            $stations[$station] = [
                'label' => $labels[$station] ?? ucfirst($station),
                'total' => $total,
                'flagged' => (int) ($row->flagged ?? 0),
                'unverified' => (int) ($row->unverified ?? 0),
            ];
        }

        $lab = LogLab::query()
            ->where('plant_id', $plantId)
            ->whereBetween('timestamp_kirim', [$start, $end])
            ->selectRaw('AVG(kadar_alb_cpo) as ffa, AVG(losses_fiber_persen) as fiber')
            ->first();

        $tonnage = (float) LogTimbang::query()
            ->where('plant_id', $plantId)
            ->whereBetween('timestamp_kirim', [$start, $end])
            ->sum('tonase_bruto');

        // Tren harian: jumlah record per hari (agregasi SQL, bukan loop harian).
        $perDayRecords = collect();
        foreach (self::stationModels() as $modelClass) {
            foreach (
                $modelClass::query()
                    ->where('plant_id', $plantId)
                    ->whereBetween('timestamp_kirim', [$start, $end])
                    ->selectRaw("DATE(timestamp_kirim) as d, COUNT(*) as total")
                    ->groupBy('d')
                    ->pluck('total', 'd')
                as $dayKey => $count
            ) {
                $perDayRecords[$dayKey] = ($perDayRecords[$dayKey] ?? 0) + (int) $count;
            }
        }

        $tonnagePerDay = LogTimbang::query()
            ->where('plant_id', $plantId)
            ->whereBetween('timestamp_kirim', [$start, $end])
            ->selectRaw("DATE(timestamp_kirim) as d, SUM(tonase_bruto) as tonnage")
            ->groupBy('d')
            ->pluck('tonnage', 'd');

        $daily = [];
        for ($day = $start->copy(); $day->lte($end); $day->addDay()) {
            $key = $day->format('Y-m-d');
            $daily[] = [
                'date' => $day->copy(),
                'records' => (int) ($perDayRecords[$key] ?? 0),
                'tonnage' => round((float) ($tonnagePerDay[$key] ?? 0) / 1000, 2),
            ];
        }

        $days = (int) max(1, $start->diffInDays($end->copy()->startOfDay()) + 1);

        return [
            'start' => $start,
            'end' => $end,
            'plant' => $plantId,
            'stations' => $stations,
            'records' => $records,
            'flagged' => $flagged,
            'unverified' => $unverified,
            'ffa' => $lab->ffa !== null ? round((float) $lab->ffa, 2) : null,
            'losses_fiber' => $lab->fiber !== null ? round((float) $lab->fiber, 2) : null,
            'tonnage' => round($tonnage / 1000, 2),
            'efficiency' => $this->efficiency($plantId, $start, $end),
            'daily' => $daily,
            'tickets' => $this->ticketStats($plantId, $start, $end),
            'days' => $days,
        ];
    }

    /**
     * Statistik tiket maintenance dalam rentang (baru / selesai / masih aktif).
     *
     * @return array{created: int, done: int, active: int}
     */
    protected function ticketStats(string $plantId, Carbon $start, Carbon $end): array
    {
        $base = MaintenanceTicket::forPlant($plantId);

        return [
            'created' => (clone $base)->whereBetween('created_at', [$start, $end])->count(),
            'done' => (clone $base)->where('status', 'selesai')->whereBetween('updated_at', [$start, $end])->count(),
            'active' => (clone $base)->whereIn('status', ['open', 'dikerjakan'])->count(),
        ];
    }

    /**
     * Susun pesan teks rekap mingguan (Markdown) untuk dikirim via bot.
     */
    public function weeklyRecapText(array $summary): string
    {
        $lines = [
            '🗓️ *REKAP MINGGUAN POMS*',
            'Pabrik: `'.$summary['plant'].'`',
            'Periode: '.$summary['start']->format('d/m').' – '.$summary['end']->format('d/m/Y')
                .' ('.$summary['days'].' hari)',
            '',
            '*🏭 Produksi & Kualitas*',
            '• Tonnage bruto: *'.number_format($summary['tonnage'], 2).' ton*'
                .' (avg '.number_format($summary['tonnage'] / max(1, $summary['days']), 2).'/hari)',
            '• Record masuk: *'.number_format($summary['records']).'*',
            '• FFA (ALB CPO): '.($summary['ffa'] !== null ? '*'.$summary['ffa'].'%*' : '—'),
            '• Losses fiber: '.($summary['losses_fiber'] !== null ? '*'.$summary['losses_fiber'].'%*' : '—'),
            '• Skor efisiensi: *'.$summary['efficiency'].'/100*',
            '',
            '*📋 Per Stasiun*',
        ];

        foreach ($summary['stations'] as $st) {
            if ($st['total'] === 0) {
                $lines[] = '▫️ '.$st['label'].': _tidak ada data_';

                continue;
            }
            $marks = [];
            if ($st['flagged'] > 0) {
                $marks[] = '🚩'.$st['flagged'];
            }
            if ($st['unverified'] > 0) {
                $marks[] = '⌛'.$st['unverified'];
            }
            $lines[] = '▫️ '.$st['label'].': *'.number_format($st['total']).'* record'
                .(count($marks) > 0 ? ' ('.implode(' ', $marks).')' : '');
        }

        $lines[] = '';
        $lines[] = '*📈 Tren Tonnage Harian*';
        foreach ($summary['daily'] as $day) {
            $lines[] = sprintf(
                '▫️ %s: %s ton · %d record',
                $day['date']->translatedFormat('D d/m'),
                number_format($day['tonnage'], 2),
                $day['records'],
            );
        }

        $t = $summary['tickets'];
        $lines[] = '';
        $lines[] = '*🛠️ Tiket Maintenance*';
        $lines[] = '• Baru: *'.$t['created'].'* · Selesai: *'.$t['done'].'* · Aktif: *'.$t['active'].'*';

        $lines[] = '';
        if ($summary['flagged'] > 0) {
            $lines[] = '⚠️ Total flagged minggu ini: *'.$summary['flagged'].'* — mohon verifikasi di dashboard.';
        }
        $lines[] = '💡 Kirim `/pdf_mingguan` untuk versi PDF.';

        return implode("\n", $lines);
    }

    /**
     * Generate PDF rekap mingguan (plant-wide) dan simpan sementara.
     *
     * @return array{path: string, filename: string} path relatif di storage disk lokal
     */
    public function generateWeeklyPdf(array $summary): array
    {
        $pdf = Pdf::loadView('exports.weekly-report-pdf', [
            'summary' => $summary,
            'plant' => $summary['plant'],
            'preparedBy' => 'Sistem (rekap otomatis)',
        ])->setOption(['isPhpEnabled' => true, 'defaultFont' => 'Arial']);

        $filename = "weekly_recap_{$summary['plant']}_{$summary['start']->format('Ymd')}_{$summary['end']->format('Ymd')}.pdf";
        $path = 'recaps/'.$filename;

        Storage::disk('local')->put($path, $pdf->output());

        return ['path' => $path, 'filename' => $filename];
    }
}
