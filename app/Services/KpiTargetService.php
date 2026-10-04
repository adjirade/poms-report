<?php

namespace App\Services;

use App\Models\KpiTarget;
use App\Support\KpiTargetConfig;
use Illuminate\Support\Collection;

/**
 * Resolusi target KPI: default (KpiTargetConfig + config poms) yang ditimpa
 * oleh baris override di tabel kpi_targets (diedit lewat UI manager/developer).
 *
 * Satu sumber kebenaran untuk StationAnalyticsService (perbaikan stasiun) dan
 * CommandCenterService (KPI plant-wide).
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
}
