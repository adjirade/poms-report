<?php

namespace App\Http\Controllers;

use App\Services\KpiTargetService;
use App\Support\StationChartConfig;
use Illuminate\Http\Request;

/**
 * Editor Target KPI per stasiun + plant-wide (role manager/developer).
 *
 * Nilai disimpan di tabel `kpi_targets` (override), sehingga manager dapat
 * menyesuaikan target tanpa mengubah kode/config. Bila override dihapus
 * ("reset"), aplikasi kembali memakai default App\Support\KpiTargetConfig.
 *
 * Halaman ini juga mendukung ekspor/impor CSV agar target satu pabrik dapat
 * disalin ke pabrik lain atau diedit massal di spreadsheet.
 */
class KpiTargetController extends Controller
{
    public function __construct(protected KpiTargetService $targets) {}

    public function index()
    {
        $plantId = (string) auth()->user()->plant_id;
        $stations = [];

        foreach ($this->scopeKeys() as $station) {
            $defaults = $this->targets->definitionsFor($station);
            if ($defaults === []) {
                continue; // stasiun tanpa target (mis. maintenance)
            }

            $series = collect(StationChartConfig::series($station))->keyBy('key');
            $overridden = $this->targets->overriddenParameters($plantId, $station);

            $parameters = [];
            foreach ($defaults as $key => $target) {
                $parameters[] = [
                    'key' => $key,
                    'label' => $target['label'] ?? ($series[$key]['label'] ?? $key),
                    'unit' => $target['unit'] ?? ($series[$key]['unit'] ?? ''),
                    'direction' => $target['direction'],
                    'min' => $target['min'] ?? null,
                    'max' => $target['max'] ?? null,
                    'is_override' => isset($overridden[$key]),
                ];
            }

            $stations[] = [
                'key' => $station,
                'title' => $this->scopeTitle($station),
                'parameters' => $parameters,
            ];
        }

        return view('settings.kpi-targets', compact('stations', 'plantId'));
    }

    public function update(Request $request)
    {
        $plantId = (string) auth()->user()->plant_id;
        $input = $request->input('targets', []);
        if (! is_array($input)) {
            $input = [];
        }

        $saved = 0;
        $reset = 0;

        foreach ($input as $station => $parameters) {
            if (! is_string($station) || ! is_array($parameters)) {
                continue;
            }

            $allowed = $this->targets->definitionsFor($station);
            if ($allowed === []) {
                continue; // stasiun tanpa target / input tak dikenal
            }

            foreach ($parameters as $parameter => $values) {
                if (! is_string($parameter) || ! isset($allowed[$parameter]) || ! is_array($values)) {
                    continue;
                }

                // Kembalikan ke default: hapus baris override.
                if (! empty($values['reset'])) {
                    $this->targets->reset($plantId, $station, $parameter);
                    $reset++;

                    continue;
                }

                $this->targets->save($plantId, $station, $parameter, $values, $allowed[$parameter]);
                $saved++;
            }
        }

        $message = trim(($saved > 0 ? "{$saved} target disimpan. " : '').($reset > 0 ? "{$reset} target dikembalikan ke default." : ''));

        return back()->with(
            'success',
            $message !== '' ? $message : 'Tidak ada perubahan target yang disimpan.'
        );
    }

    /**
     * Ekspor seluruh target efektif (default + override) untuk plant user ke CSV.
     */
    public function export()
    {
        $plantId = (string) auth()->user()->plant_id;
        $rows = $this->targets->exportRows($plantId);
        $filename = 'kpi-targets-'.$plantId.'-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');

            // BOM agar Excel membaca UTF-8 (satuan °C, dsb.) dengan benar.
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['station', 'parameter', 'direction', 'min_value', 'max_value', 'unit', 'label', 'source']);

            foreach ($rows as $row) {
                fputcsv($out, [
                    $row['station'],
                    $row['parameter'],
                    $row['direction'],
                    $row['min_value'],
                    $row['max_value'],
                    $row['unit'],
                    $row['label'],
                    $row['source'],
                ]);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Impor CSV target KPI (upsert ke tabel kpi_targets). Kolom `station` dan
     * `parameter` wajib; baris dengan stasiun/parameter tak dikenal dilewati.
     */
    public function import(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:1024'],
        ]);

        $plantId = (string) auth()->user()->plant_id;
        $handle = fopen($request->file('file')->getRealPath(), 'r');

        if ($handle === false) {
            return back()->with('warning', '❌ Berkas tidak dapat dibaca.');
        }

        $header = fgetcsv($handle);
        $header = $this->normalizeHeader($header);

        if ($header === null) {
            fclose($handle);

            return back()->with('warning', '❌ Berkas tidak memiliki baris header.');
        }

        $saved = 0;
        $skipped = 0;

        while (($row = fgetcsv($handle)) !== false) {
            if ($row === [null] || $row === []) {
                continue; // baris kosong
            }

            $record = $this->mapRow($header, $row);
            $station = (string) ($record['station'] ?? '');
            $parameter = (string) ($record['parameter'] ?? '');

            $allowed = $station !== '' ? $this->targets->definitionsFor($station) : [];
            if ($station === '' || $parameter === '' || ! isset($allowed[$parameter])) {
                $skipped++;

                continue;
            }

            $this->targets->save($plantId, $station, $parameter, $record, $allowed[$parameter]);
            $saved++;
        }

        fclose($handle);

        $message = "{$saved} target diimpor.".($skipped > 0 ? " {$skipped} baris dilewati (tidak dikenal/tidak valid)." : '');

        return back()->with($saved > 0 ? 'success' : 'warning', $message);
    }

    /**
     * Kunci scope pada halaman editor: semua stasiun + plant-wide.
     *
     * @return array<int, string>
     */
    protected function scopeKeys(): array
    {
        return array_merge(StationChartConfig::stationKeys(), [KpiTargetService::PLANT_SCOPE]);
    }

    protected function scopeTitle(string $station): string
    {
        return $station === KpiTargetService::PLANT_SCOPE
            ? 'KPI Plant-Wide (Command Center)'
            : StationChartConfig::title($station);
    }

    /**
     * Normalisasi baris header CSV (hapus BOM, trim, lowercase).
     *
     * @param  array<int, mixed>|false  $header
     * @return array<int, string>|null
     */
    protected function normalizeHeader(array|false $header): ?array
    {
        if (! is_array($header) || $header === []) {
            return null;
        }

        return array_map(
            fn ($col) => strtolower(trim(str_replace("\xEF\xBB\xBF", '', (string) $col))),
            $header
        );
    }

    /**
     * Petakan satu baris CSV ke array asosiatif berdasar header.
     *
     * @param  array<int, string>  $header
     * @param  array<int, mixed>  $row
     * @return array<string, string>
     */
    protected function mapRow(array $header, array $row): array
    {
        $record = [];

        foreach ($header as $i => $key) {
            if ($key === '') {
                continue;
            }
            $record[$key] = trim((string) ($row[$i] ?? ''));
        }

        return $record;
    }
}
