<?php

namespace App\Http\Controllers;

use App\Exports\StationLogsExport;
use App\Models\LogLab;
use App\Models\LogPress;
use App\Models\LogSterilizer;
use App\Models\LogTimbang;
use App\Models\MaintenanceTicket;
use App\Services\CommandCenterService;
use App\Services\StationAnalyticsService;
use App\Services\ValidationService;
use App\Support\StationChartConfig;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class ExportController extends Controller
{
    protected ValidationService $validation;

    public function __construct(ValidationService $validation)
    {
        $this->validation = $validation;
    }

    /**
     * Export station logs to PDF
     */
    public function pdf(Request $request, string $station)
    {
        $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
        ]);

        // Default ke hari ini. filled() dipakai (bukan input(x, default)) karena
        // parameter yang dikirim TAPI kosong ("") dinilai null oleh middleware
        // ConvertEmptyStringsToNull — whereDate(..., null) melempar exception
        // "Illegal operator and value combination".
        $dateFrom = $request->filled('date_from')
            ? $request->input('date_from')
            : now()->format('Y-m-d');
        $dateTo = $request->filled('date_to')
            ? $request->input('date_to')
            : now()->format('Y-m-d');

        $modelClass = $this->validation->getStationModel($station);
        $user = auth()->user();

        $logs = $modelClass::query()
            ->with(['user', 'verifier'])
            ->where('plant_id', $user->plant_id)
            ->whereDate('timestamp_kirim', '>=', $dateFrom)
            ->whereDate('timestamp_kirim', '<=', $dateTo)
            ->orderBy('timestamp_kirim', 'asc')
            ->get();

        $pdf = Pdf::loadView('exports.station-logs-pdf', [
            'logs' => $logs,
            'station' => $station,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'plant' => $user->plant_id,
            'preparedBy' => $user->name,
            'columns' => $this->stationColumns($station),
        ])
            ->setPaper(config('export.paper_size', env('EXPORT_PAPER_SIZE', 'a4')), env('EXPORT_PAPER_ORIENTATION', 'portrait'))
            ->setOption(['isPhpEnabled' => true, 'defaultFont' => 'Arial']);

        $filename = "{$station}_{$dateFrom}_to_{$dateTo}.pdf";

        return $pdf->download($filename);
    }

    /**
     * Laporan Performa Stasiun (PDF): ringkasan status, grafik tren harian
     * (di-render Chart.js di browser lalu dikirim sebagai PNG base64),
     * perbandingan harian, dan rekapitulasi per hari.
     */
    public function stationPerformancePdf(Request $request, StationAnalyticsService $analytics)
    {
        $validated = $request->validate([
            'station' => ['required', 'string'],
            'range' => ['required', 'integer'],
            // Gambar chart dari canvas browser (data URI PNG base64).
            'chart_image' => ['nullable', 'string', 'max:4000000'],
        ]);

        $station = $validated['station'];
        if (! StationChartConfig::exists($station)) {
            abort(404);
        }

        $range = (int) $validated['range'];
        if (! in_array($range, StationChartConfig::RANGES, true)) {
            $range = 7;
        }

        $user = auth()->user();
        $from = now()->subDays($range - 1)->startOfDay();
        $until = now()->endOfDay();

        $chart = $analytics->dailySeries($user->plant_id, $station, $from, $until);
        $status = $analytics->statusBreakdown($user->plant_id, $station, $from, $until);
        $comparison = $analytics->dayComparison($user->plant_id, $station);
        $targetProgress = $analytics->targetProgress($user->plant_id, $station, $from, $until);
        $shiftBreakdown = $analytics->shiftBreakdown($user->plant_id, $station, $from, $until);

        // Baris rekap harian untuk tabel PDF (paramter + volume + flag).
        $dailyRows = [];
        foreach ($chart['labels'] as $i => $label) {
            $row = ['label' => $label, 'total' => $chart['counts']['total'][$i], 'flagged' => $chart['counts']['flagged'][$i]];
            foreach ($chart['series'] as $s) {
                $val = $s['data'][$i];
                $row[$s['key']] = $val === null ? '—' : number_format($val, 2);
            }
            $dailyRows[] = $row;
        }

        // Gambar chart opsional: hanya data URI PNG yang diterima (bukan path/URL).
        $chartImage = $validated['chart_image'] ?? null;
        if ($chartImage !== null && ! str_starts_with($chartImage, 'data:image/png;base64,')) {
            $chartImage = null;
        }

        $pdf = Pdf::loadView('exports.station-performance-pdf', [
            'station' => $station,
            'stationTitle' => StationChartConfig::title($station),
            'plant' => $user->plant_id,
            'preparedBy' => $user->name,
            'from' => Carbon::parse($from),
            'until' => Carbon::parse($until),
            'range' => $range,
            'status' => $status,
            'comparison' => $comparison,
            'targetProgress' => $targetProgress,
            'shiftBreakdown' => $shiftBreakdown,
            'seriesConfig' => StationChartConfig::series($station),
            'chartSeries' => $chart['series'],
            'dailyRows' => $dailyRows,
            'chartImage' => $chartImage,
        ])
            ->setPaper(config('export.paper_size', env('EXPORT_PAPER_SIZE', 'a4')), 'portrait')
            ->setOption(['isPhpEnabled' => true, 'defaultFont' => 'Arial']);

        $filename = "performa_{$station}_{$from->format('Ymd')}_to_{$until->format('Ymd')}.pdf";

        return $pdf->download($filename);
    }

    /**
     * Laporan Command Center (PDF): KPI + target vs realisasi, status 8 stasiun,
     * dan tren 7 hari. Data diambil dari CommandCenterService (sumber sama
     * dengan tampilan web).
     */
    public function commandCenterPdf(CommandCenterService $commandCenter)
    {
        $user = auth()->user();
        $data = $commandCenter->build($user->plant_id);

        $pdf = Pdf::loadView('exports.command-center-pdf', array_merge($data, [
            'plant' => $user->plant_id,
            'plantName' => config('poms.plant_name'),
            'preparedBy' => $user->name,
        ]))
            ->setPaper(config('export.paper_size', env('EXPORT_PAPER_SIZE', 'a4')), 'portrait')
            ->setOption(['isPhpEnabled' => true, 'defaultFont' => 'Arial']);

        $filename = "command_center_{$user->plant_id}_".now()->format('Ymd_His').'.pdf';

        return $pdf->download($filename);
    }

    /**
     * B3 — Laporan tiket maintenance per mesin (arsip workshop).
     *
     * Filter opsional: `kode_mesin` (riwayat satu mesin) dan `status`.
     * Tanpa filter, seluruh tiket plant dicetak dengan ringkasan per mesin.
     */
    public function maintenanceTicketsPdf(Request $request)
    {
        $validated = $request->validate([
            'kode_mesin' => ['nullable', 'string', 'max:50'],
            'status' => ['nullable', 'in:open,dikerjakan,selesai'],
        ]);

        $user = auth()->user();
        $kodeMesin = trim((string) ($validated['kode_mesin'] ?? ''));
        $status = $validated['status'] ?? null;

        $query = MaintenanceTicket::forPlant($user->plant_id)
            ->with(['reporter:id,name', 'assignee:id,name'])
            // Urutkan status (open dulu) secara portable (hindari FIELD() MySQL).
            ->orderByRaw("case status when 'open' then 0 when 'dikerjakan' then 1 else 2 end")
            ->orderByDesc('id');

        if ($kodeMesin !== '') {
            $query->where('kode_mesin', 'like', "%{$kodeMesin}%");
        }

        if ($status !== null && $status !== '') {
            $query->where('status', $status);
        }

        $tickets = $query->get();

        // Ringkasan per mesin (arsip workshop).
        $perMachine = $tickets->groupBy('kode_mesin')->map(fn ($group) => [
            'total' => $group->count(),
            'open' => $group->where('status', 'open')->count(),
            'dikerjakan' => $group->where('status', 'dikerjakan')->count(),
            'selesai' => $group->where('status', 'selesai')->count(),
        ]);

        $pdf = Pdf::loadView('exports.maintenance-tickets-pdf', [
            'tickets' => $tickets,
            'perMachine' => $perMachine,
            'kodeMesin' => $kodeMesin,
            'statusFilter' => $status,
            'plant' => $user->plant_id,
            'preparedBy' => $user->name,
        ])
            ->setPaper(config('export.paper_size', env('EXPORT_PAPER_SIZE', 'a4')), 'portrait')
            ->setOption(['isPhpEnabled' => true, 'defaultFont' => 'Arial']);

        $slug = $kodeMesin !== '' ? preg_replace('/[^A-Za-z0-9_-]+/', '_', $kodeMesin) : 'semua-mesin';
        $filename = "tiket_maintenance_{$slug}_{$user->plant_id}_".now()->format('Ymd_His').'.pdf';

        return $pdf->download($filename);
    }

    /**
     * Export station logs to Excel
     */
    public function excel(Request $request, string $station)
    {
        $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
        ]);

        // Sama seperti method pdf(): hindari whereDate dengan value null.
        $dateFrom = $request->filled('date_from')
            ? $request->input('date_from')
            : now()->format('Y-m-d');
        $dateTo = $request->filled('date_to')
            ? $request->input('date_to')
            : now()->format('Y-m-d');

        $filename = "{$station}_{$dateFrom}_to_{$dateTo}.xlsx";

        return Excel::download(
            new StationLogsExport(
                $station,
                $dateFrom,
                $dateTo,
                auth()->user()->plant_id,
                auth()->user()->can('view-all-plants')
            ),
            $filename
        );
    }

    /**
     * Generate daily production report
     */
    public function dailyReport(Request $request)
    {
        $request->validate([
            'date' => 'nullable|date',
        ]);

        $date = $request->filled('date') ? $request->input('date') : now()->format('Y-m-d');
        $user = auth()->user();

        // Aggregate data from all stations
        $data = [
            'timbang' => LogTimbang::where('plant_id', $user->plant_id)
                ->whereDate('timestamp_kirim', $date)
                ->selectRaw('
                    COUNT(*) as total_entries,
                    SUM(tonase_bruto) as total_bruto,
                    SUM(tonase_tarra) as total_tarra,
                    AVG(potongan_persen) as avg_potongan
                ')
                ->first(),

            'sterilizer' => LogSterilizer::where('plant_id', $user->plant_id)
                ->whereDate('timestamp_kirim', $date)
                ->selectRaw('
                    COUNT(*) as total_entries,
                    AVG(tekanan_bar) as avg_tekanan,
                    AVG(suhu_celcius) as avg_suhu,
                    AVG(durasi_menit) as avg_durasi
                ')
                ->first(),

            'press' => LogPress::where('plant_id', $user->plant_id)
                ->whereDate('timestamp_kirim', $date)
                ->selectRaw('
                    COUNT(*) as total_entries,
                    AVG(tekanan_hidrolik) as avg_tekanan,
                    AVG(ampere_motor) as avg_ampere
                ')
                ->first(),

            'lab' => LogLab::where('plant_id', $user->plant_id)
                ->whereDate('timestamp_kirim', $date)
                ->selectRaw('
                    AVG(kadar_alb_cpo) as avg_ffa,
                    AVG(losses_fiber_persen) as avg_losses_fiber,
                    AVG(losses_jankos_persen) as avg_losses_jankos
                ')
                ->first(),
        ];

        $pdf = Pdf::loadView('exports.daily-report-pdf', [
            'data' => $data,
            'date' => $date,
            'plant' => $user->plant_id,
            'preparedBy' => $user->name,
        ])
            ->setPaper(config('export.paper_size', env('EXPORT_PAPER_SIZE', 'a4')), 'portrait')
            ->setOption(['isPhpEnabled' => true, 'defaultFont' => 'Arial']);

        $filename = "daily_report_{$user->plant_id}_{$date}.pdf";

        return $pdf->download($filename);
    }

    /**
     * Kolom tabel PDF per stasiun — sumber kebenaran tunggal dari
     * StationChartConfig (label + key + satuan), konsisten dengan chart.
     *
     * @return array<int, array{key: string, label: string, unit?: string}>
     */
    protected function stationColumns(string $station): array
    {
        return StationChartConfig::tableColumns($station);
    }
}
