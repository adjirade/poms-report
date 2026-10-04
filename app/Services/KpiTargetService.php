<?php

namespace App\Services;

use App\Models\KpiTarget;
use App\Support\KpiTargetConfig;
use App\Support\StationChartConfig;
use Illuminate\Support\Collection;

/**
 * Resolusi target KPI: default (KpiTargetConfig + config poms) yang ditimpa
 * oleh baris override di tabel kpi_targets (diedit lewat UI manager/developer).
 *
 * Satu sumber kebenaran untuk StationAnalyticsService (perbaikan stasiun) dan
 * CommandCenterService (KPI plant-wide). Juga menangani penyimpanan/reset
 * override serta ekspor/impor massal (CSV) dari UI Target KPI.
 */
class KpiTargetService
{
    /** Station sentinel untuk KPI plant-wide (Command Center). */
    public const PLANT_SCOPE = '_plant';

    /**
     * Target efektif satu stasiun (default + override DB).
     *
     * @return array<string, array{label?: string, unit?: string, direction: string, min?: float, max?: float}>
     */
    public function stationTargets(string $plantId, string $station): array
    {
        return $this->merge(KpiTargetConfig::forStation($station), $this->rows($plantId, $station));
    }

    /**
     * Target efektif KPI plant-wide.
     *
     * @return array<string, array{label: string, unit?: string, direction: string, min?: float, max?: float}>
     */
    public function plantTargets(string $plantId): array
    {
        return $this->merge(KpiTargetConfig::plantTargets(), $this->rows($plantId, self::PLANT_SCOPE));
    }

    /**
     * Definisi target yang diizinkan untuk sebuah station (dipakai form editor
     * dan validasi impor). `_plant` memetakan ke KPI plant-wide.
     *
     * @return array<string, array{label?: string, unit?: string, direction: string, min?: float, max?: float}>
     */
    public function definitionsFor(string $station): array
    {
        return $station === self::PLANT_SCOPE
            ? KpiTargetConfig::plantTargets()
            : KpiTargetConfig::forStation($station);
    }

    /**
     * Apakah stasiun ini punya baris override di DB (untuk indikator di UI).
     *
     * @return array<string, bool> parameter => true
     */
    public function overriddenParameters(string $plantId, string $station): array
    {
        return $this->rows($plantId, $station)
            ->map(fn () => true)
            ->all();
    }

    /**
     * Daftar baris target efektif (default + override) untuk ekspor CSV.
     *
     * @return array<int, array{station: string, parameter: string, direction: string, min_value: ?float, max_value: ?float, unit: string, label: string, source: string}>
     */
    public function exportRows(string $plantId): array
    {
        $rows = [];

        foreach (StationChartConfig::stationKeys() as $station) {
            $overridden = $this->overriddenParameters($plantId, $station);
            foreach ($this->stationTargets($plantId, $station) as $parameter => $target) {
                $rows[] = $this->flatRow($station, $parameter, $target, isset($overridden[$parameter]));
            }
        }

        $overridden = $this->overriddenParameters($plantId, self::PLANT_SCOPE);
        foreach ($this->plantTargets($plantId) as $parameter => $target) {
            $rows[] = $this->flatRow(self::PLANT_SCOPE, $parameter, $target, isset($overridden[$parameter]));
        }

        return $rows;
    }

    /**
     * Simpan/nimpa satu target sebagai baris override (default + normalisasi).
     *
     * @param  array<string, mixed>  $values  direction/min/max/unit/label
     * @param  array<string, mixed>  $default  definisi default parameter
     */
    public function save(string $plantId, string $station, string $parameter, array $values, array $default): void
    {
        $direction = in_array($values['direction'] ?? '', ['lower', 'higher', 'range'], true)
            ? $values['direction']
            : ($default['direction'] ?? 'lower');

        // Terima baik key form (`min`/`max`) maupun key CSV (`min_value`/`max_value`).
        $min = $this->num($values['min'] ?? $values['min_value'] ?? null);
        $max = $this->num($values['max'] ?? $values['max_value'] ?? null);

        // Normalisasi sesuai arah target.
        if ($direction === 'lower') {
            $min = null;
        } elseif ($direction === 'higher') {
            $max = null;
        } elseif ($min === null && $max === null) {
            $min = isset($default['min']) ? (float) $default['min'] : null;
            $max = isset($default['max']) ? (float) $default['max'] : null;
        }

        $unit = $this->text($values['unit'] ?? null);
        $label = $this->text($values['label'] ?? null);

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
    }

    /** Hapus override -> kembali ke default sistem. */
    public function reset(string $plantId, string $station, string $parameter): void
    {
        KpiTarget::where('plant_id', $plantId)
            ->where('station', $station)
            ->where('parameter', $parameter)
            ->delete();
    }

    /**
     * Baris override, keyed by parameter.
     *
     * @return Collection<string, KpiTarget>
     */
    protected function rows(string $plantId, string $station): Collection
    {
        return KpiTarget::query()
            ->where('plant_id', $plantId)
            ->where('station', $station)
            ->get()
            ->keyBy('parameter');
    }

    /**
     * Gabungkan default dengan override (hanya field terisi yang menimpa).
     *
     * @param  array<string, array<string, mixed>>  $defaults
     * @param  Collection<string, KpiTarget>  $rows
     * @return array<string, array<string, mixed>>
     */
    protected function merge(array $defaults, Collection $rows): array
    {
        foreach ($rows as $parameter => $row) {
            $defaults[$parameter] = array_merge($defaults[$parameter] ?? [], $row->toTargetArray());
        }

        return $defaults;
    }

    /**
     * @param  array<string, mixed>  $target
     * @return array<string, mixed>
     */
    protected function flatRow(string $station, string $parameter, array $target, bool $isOverride): array
    {
        return [
            'station' => $station,
            'parameter' => $parameter,
            'direction' => $target['direction'] ?? 'lower',
            'min_value' => $target['min'] ?? null,
            'max_value' => $target['max'] ?? null,
            'unit' => (string) ($target['unit'] ?? ''),
            'label' => (string) ($target['label'] ?? ''),
            'source' => $isOverride ? 'override' : 'default',
        ];
    }

    /** Nilai numerik dari input form/impor; '' / null -> null. */
    private function num(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (float) $value : null;
    }

    /** Teks bersih; kosong -> null. */
    private function text(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));

        return $text === '' ? null : $text;
    }
}
