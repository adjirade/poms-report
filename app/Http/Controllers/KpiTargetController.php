<?php

namespace App\Http\Controllers;

use App\Models\KpiTarget;
use App\Services\KpiTargetService;
use App\Support\KpiTargetConfig;
use App\Support\StationChartConfig;
use Illuminate\Http\Request;

/**
 * Editor Target KPI per stasiun (role manager/developer).
 *
 * Nilai disimpan di tabel `kpi_targets` (override), sehingga manager dapat
 * menyesuaikan target tanpa mengubah kode/config. Bila override dihapus
 * ("reset"), aplikasi kembali memakai default App\Support\KpiTargetConfig.
 */
class KpiTargetController extends Controller
{
    public function __construct(protected KpiTargetService $targets) {}

    public function index()
    {
        $plantId = (string) auth()->user()->plant_id;
        $stations = [];

        foreach (StationChartConfig::stationKeys() as $station) {
            $defaults = KpiTargetConfig::forStation($station);
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
                'title' => StationChartConfig::title($station),
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

            $allowed = KpiTargetConfig::forStation($station);
            if ($allowed === []) {
                continue; // stasiun tanpa target / input tak dikenal
            }

            foreach ($parameters as $parameter => $values) {
                if (! is_string($parameter) || ! isset($allowed[$parameter]) || ! is_array($values)) {
                    continue;
                }

                // Kembalikan ke default: hapus baris override.
                if (! empty($values['reset'])) {
                    KpiTarget::where('plant_id', $plantId)
                        ->where('station', $station)
                        ->where('parameter', $parameter)
                        ->delete();
                    $reset++;

                    continue;
                }

                $direction = in_array($values['direction'] ?? '', ['lower', 'higher', 'range'], true)
                    ? $values['direction']
                    : $allowed[$parameter]['direction'];

                $min = isset($values['min']) && $values['min'] !== '' ? (float) $values['min'] : null;
                $max = isset($values['max']) && $values['max'] !== '' ? (float) $values['max'] : null;

                // Normalisasi sesuai arah target.
                if ($direction === 'lower') {
                    $min = null;
                } elseif ($direction === 'higher') {
                    $max = null;
                } elseif ($min === null && $max === null) {
                    $min = $allowed[$parameter]['min'] ?? null;
                    $max = $allowed[$parameter]['max'] ?? null;
                }

                $unit = isset($values['unit']) && trim((string) $values['unit']) !== ''
                    ? trim((string) $values['unit'])
                    : null;
                $label = isset($values['label']) && trim((string) $values['label']) !== ''
                    ? trim((string) $values['label'])
                    : null;

                KpiTarget::updateOrCreate(
                    ['plant_id' => $plantId, 'station' => $station, 'parameter' => $parameter],
                    [
                        'direction' => $direction,
                        'min_value' => $min,
                        'max_value' => $max,
                        'unit' => $unit,
                        'label' => $label,
                    ]
                );
                $saved++;
            }
        }

        $message = trim(($saved > 0 ? "{$saved} target disimpan. " : '').($reset > 0 ? "{$reset} target dikembalikan ke default." : ''));

        return back()->with(
            'success',
            $message !== '' ? $message : 'Tidak ada perubahan target yang disimpan.'
        );
    }
}
