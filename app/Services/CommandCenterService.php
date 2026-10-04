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
use App\Support\KpiTargetConfig;
use App\Support\StationChartConfig;

/**
 * Satu sumber data Command Center (A1) — dipakai oleh DashboardController
 * (tampilan web) DAN ExportController (laporan PDF) agar angka konsisten.
 *
 * Semua agregasi memakai 1 query agregat per stasiun (hindari N+1).
 */
class CommandCenterService
{
    public function __construct(protected KpiTargetService $targets) {}

    /**
     * Susun seluruh data Command Center untuk satu plant.
     *
     * @return array{
     *     kpi: array<string, int|float|null>,
     *     kpiTargets: array<int, array{key: string, label: string, unit: string, direction: string, target_text: string, actual: ?float, achieved: ?bool, progress: int}>,
     *     stationStatus: array<int, array{station: string, title: string, total: int, flagged: int, unverified: int, status: string}>,
     *     dailyTrend: array<int, array{label: string, total: int, flagged: int}>,
     *     tonnageTrend: array<int, array{label: string, bruto: float}>,
     *     recentAlerts: array<int, array{station: string, id: int, user: string, at: mixed, diff: ?float, verified: bool}>
     * }
     */
    public function build(string $plantId): array
    {
        $today = now()->startOfDay();
        $end = now()->endOfDay();

        $recordsToday = 0;
        $flaggedToday = 0;
        $unverifiedToday = 0;
        $stationStatus = [];

        foreach ($this->stationModels() as $station => $model) {
            $row = $model::query()
                ->where('plant_id', $plantId)
                ->whereBetween('timestamp_kirim', [$today, $end])
                ->selectRaw(implode(', ', [
                    'COUNT(*) as total',
                    'SUM(CASE WHEN is_flagged THEN 1 ELSE 0 END) as flagged',
                    'SUM(CASE WHEN NOT is_verified THEN 1 ELSE 0 END) as unverified',
                ]))
                ->first();

            $count = (int) ($row->total ?? 0);
            $flagged = (int) ($row->flagged ?? 0);
            $unverified = (int) ($row->unverified ?? 0);

            $recordsToday += $count;
            $flaggedToday += $flagged;
            $unverifiedToday += $unverified;

            $status = 'idle'; // tidak ada data
            if ($flagged > 0) {
                $status = 'danger';
            } elseif ($count > 0 && $unverified > 0) {
                $status = 'warning';
            } elseif ($count > 0) {
                $status = 'ok';
            }

            $stationStatus[] = [
                'station' => $station,
                'title' => StationChartConfig::exists($station)
                    ? StationChartConfig::title($station)
                    : ucfirst($station),
                'total' => $count,
                'flagged' => $flagged,
                'unverified' => $unverified,
                'status' => $status,
            ];
        }

        // --- Kualitas & produksi hari ini ---
        $ffaToday = LogLab::where('plant_id', $plantId)
            ->whereBetween('timestamp_kirim', [$today, $end])
            ->avg('kadar_alb_cpo');
        $lossesFiberToday = LogLab::where('plant_id', $plantId)
            ->whereBetween('timestamp_kirim', [$today, $end])
            ->avg('losses_fiber_persen');
        $tonnageToday = LogTimbang::where('plant_id', $plantId)
            ->whereBetween('timestamp_kirim', [$today, $end])
            ->sum('tonase_bruto');
        $efficiency = $this->efficiency($plantId);

        $kpi = [
            'records_today' => $recordsToday,
            'flagged_today' => $flaggedToday,
            'unverified_today' => $unverifiedToday,
            'tonnage_today' => $tonnageToday !== null ? round((float) $tonnageToday / 1000, 2) : 0.0,
            'ffa_today' => $ffaToday !== null ? round((float) $ffaToday, 2) : null,
            'losses_fiber_today' => $lossesFiberToday !== null ? round((float) $lossesFiberToday, 2) : null,
            'efficiency' => $efficiency,
        ];

        // --- Target vs Realisasi KPI plant-wide (A6) ---
        $plantValues = [
            'ffa' => $kpi['ffa_today'],
            'losses_fiber' => $kpi['losses_fiber_today'],
            'efficiency' => (float) $efficiency,
        ];
        $kpiTargets = [];
        foreach ($this->targets->plantTargets($plantId) as $key => $target) {
            $kpiTargets[] = KpiTargetConfig::evaluate($key, $target, $plantValues[$key] ?? null);
        }

        // --- Tren 7 hari + tonnage ---
        $start = now()->subDays(6)->startOfDay();
        $byDate = [];
        for ($i = 6; $i >= 0; $i--) {
            $d = now()->subDays($i);
            $byDate[$d->format('Y-m-d')] = ['label' => $d->format('d M'), 'total' => 0, 'flagged' => 0];
        }
        foreach ($this->stationModels() as $model) {
            $rows = $model::query()
                ->where('plant_id', $plantId)
                ->whereBetween('timestamp_kirim', [$start, $end])
                ->selectRaw('DATE(timestamp_kirim) as d, COUNT(*) as total, SUM(CASE WHEN is_flagged THEN 1 ELSE 0 END) as flagged')
                ->groupBy('d')
                ->get();
            foreach ($rows as $row) {
                $key = substr((string) $row->d, 0, 10);
                if (isset($byDate[$key])) {
                    $byDate[$key]['total'] += (int) $row->total;
                    $byDate[$key]['flagged'] += (int) $row->flagged;
                }
            }
        }

        $tonnageRows = LogTimbang::where('plant_id', $plantId)
            ->whereBetween('timestamp_kirim', [$start, $end])
            ->selectRaw('DATE(timestamp_kirim) as d, SUM(tonase_bruto) as bruto')
            ->groupBy('d')
            ->get()
            ->keyBy(fn ($r) => substr((string) $r->d, 0, 10));

        $tonnageTrend = [];
        foreach ($byDate as $key => $day) {
            $tonnageTrend[] = [
                'label' => $day['label'],
                'bruto' => isset($tonnageRows[$key]) ? round((float) $tonnageRows[$key]->bruto / 1000, 2) : 0,
            ];
        }

        // --- Alert terbaru (5 flagged terakhir) ---
        $recentAlerts = collect();
        foreach ($this->stationModels() as $station => $model) {
            $logs = $model::query()
                ->where('plant_id', $plantId)
                ->where('is_flagged', true)
                ->with('user')
                ->latest('timestamp_kirim')
                ->limit(3)
                ->get(['id', 'user_id', 'timestamp_kirim', 'timestamp_server', 'is_verified']);

            foreach ($logs as $log) {
                $recentAlerts->push([
                    'station' => $station,
                    'id' => $log->id,
                    'user' => $log->user->name ?? 'N/A',
                    'at' => $log->timestamp_kirim,
                    'diff' => $log->timestamp_server ? round($log->timestamp_server->diffInHours($log->timestamp_kirim), 1) : null,
                    'verified' => $log->is_verified,
                ]);
            }
        }
        $recentAlerts = $recentAlerts->sortByDesc('at')->take(5)->values()->all();

        return [
            'kpi' => $kpi,
            'kpiTargets' => $kpiTargets,
            'stationStatus' => $stationStatus,
            'dailyTrend' => array_values($byDate),
            'tonnageTrend' => $tonnageTrend,
            'recentAlerts' => $recentAlerts,
        ];
    }

    /**
     * Skor efisiensi plant (press + sterilizer 7 hari terakhir).
     */
    protected function efficiency(string $plantId): int
    {
        $since = now()->subDays(7);

        $pressAvg = LogPress::where('plant_id', $plantId)
            ->whereDate('timestamp_kirim', '>=', $since->toDateString())
            ->avg('tekanan_hidrolik');

        $suhuAvg = LogSterilizer::where('plant_id', $plantId)
            ->whereDate('timestamp_kirim', '>=', $since->toDateString())
            ->avg('suhu_celcius');

        $score = 0;
        $factors = 0;

        if ($pressAvg !== null) {
            $score += $pressAvg >= 60 && $pressAvg <= 75 ? 100 : max(40, 100 - abs($pressAvg - 67.5) * 4);
            $factors++;
        }
        if ($suhuAvg !== null) {
            $score += $suhuAvg >= 110 && $suhuAvg <= 145 ? 100 : max(40, 100 - abs($suhuAvg - 127.5) * 1.5);
            $factors++;
        }

        return $factors > 0 ? (int) round($score / $factors) : 0;
    }

    /**
     * @return array<string, class-string>
     */
    protected function stationModels(): array
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
}
