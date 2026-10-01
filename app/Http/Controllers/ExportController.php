<?php

namespace App\Http\Controllers;

use App\Services\ValidationService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\StationLogsExport;

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

        // Default to today when not provided (station page links)
        $dateFrom = $request->input('date_from', now()->format('Y-m-d'));
        $dateTo = $request->input('date_to', now()->format('Y-m-d'));

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
        ]);

        $filename = "{$station}_{$dateFrom}_to_{$dateTo}.pdf";

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

        // Default to today when not provided (station page links)
        $dateFrom = $request->input('date_from', now()->format('Y-m-d'));
        $dateTo = $request->input('date_to', now()->format('Y-m-d'));

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

        $date = $request->input('date', now()->format('Y-m-d'));
        $user = auth()->user();

        // Aggregate data from all stations
        $data = [
            'timbang' => \App\Models\LogTimbang::where('plant_id', $user->plant_id)
                ->whereDate('timestamp_kirim', $date)
                ->selectRaw('
                    COUNT(*) as total_entries,
                    SUM(tonase_bruto) as total_bruto,
                    SUM(tonase_tarra) as total_tarra,
                    AVG(potongan_persen) as avg_potongan
                ')
                ->first(),

            'sterilizer' => \App\Models\LogSterilizer::where('plant_id', $user->plant_id)
                ->whereDate('timestamp_kirim', $date)
                ->selectRaw('
                    COUNT(*) as total_entries,
                    AVG(tekanan_bar) as avg_tekanan,
                    AVG(suhu_celcius) as avg_suhu,
                    AVG(durasi_menit) as avg_durasi
                ')
                ->first(),

            'press' => \App\Models\LogPress::where('plant_id', $user->plant_id)
                ->whereDate('timestamp_kirim', $date)
                ->selectRaw('
                    COUNT(*) as total_entries,
                    AVG(tekanan_hidrolik) as avg_tekanan,
                    AVG(ampere_motor) as avg_ampere
                ')
                ->first(),

            'lab' => \App\Models\LogLab::where('plant_id', $user->plant_id)
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
        ]);

        $filename = "daily_report_{$user->plant_id}_{$date}.pdf";

        return $pdf->setPaper('a4', 'portrait')->download($filename);
    }

    /**
     * Column labels per station used by the PDF export.
     */
    protected function stationColumns(string $station): array
    {
        return match($station) {
            'timbang' => [
                'No SPB' => 'no_spb',
                'Tonase Bruto (kg)' => 'tonase_bruto',
                'Tonase Tarra (kg)' => 'tonase_tarra',
                'Potongan (%)' => 'potongan_persen',
            ],
            'sortasi' => [
                'No SPB' => 'no_spb',
                'Buah Mentah (%)' => 'buah_mentah_persen',
                'Buah Matang (%)' => 'buah_matang_persen',
                'Jankos (%)' => 'jankos_persen',
                'Tangkai Panjang (%)' => 'tangkai_panjang_persen',
            ],
            'sterilizer' => [
                'No Rebusan' => 'no_rebusan',
                'Tekanan (Bar)' => 'tekanan_bar',
                'Suhu (°C)' => 'suhu_celcius',
                'Durasi (Menit)' => 'durasi_menit',
            ],
            'press' => [
                'No Press' => 'no_press',
                'Tekanan Hidrolik (Kg/cm²)' => 'tekanan_hidrolik',
                'Ampere Motor' => 'ampere_motor',
                'Tambah Air (%)' => 'tambah_air_persen',
            ],
            'klarifikasi' => [
                'No Tangki' => 'no_tangki',
                'Suhu Tangki (°C)' => 'suhu_tangki_celcius',
                'Level Minyak (cm)' => 'level_minyak_cm',
                'Kadar Air (%)' => 'kadar_air_persen',
            ],
            'kernel' => [
                'Suhu Silo (°C)' => 'suhu_silo_celcius',
                'Losses Inti (%)' => 'losses_inti_persen',
                'Kadar Kotoran (%)' => 'kadar_kotoran_persen',
            ],
            'lab' => [
                'Kadar ALB CPO (%)' => 'kadar_alb_cpo',
                'Losses Fiber (%)' => 'losses_fiber_persen',
                'Losses Jankos (%)' => 'losses_jankos_persen',
            ],
            'maintenance' => [
                'Kode Mesin' => 'kode_mesin',
                'Jam Jalan (HM)' => 'jam_jalan_hm',
                'Status' => 'status_kondisi',
                'Keterangan' => 'keterangan_perbaikan',
            ],
            default => [],
        };
    }
}
