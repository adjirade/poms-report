<?php

namespace App\Support;

/**
 * Satu sumber kebenaran untuk target KPI (A6 — Target vs Realisasi).
 *
 * Definisi target per parameter stasiun (dan KPI plant-wide) dengan tiga arah:
 *  - `lower`  : makin kecil makin baik (mis. FFA < 5%).
 *  - `higher` : makin besar makin baik (mis. buah matang >= 85%).
 *  - `range`  : aman bila berada di antara min..max (mis. tekanan 60-75).
 *
 * Nilai default dapat ditimpa lewat config('poms.targets') agar pabrik dapat
 * menyesuaikan tanpa mengubah kode (dan dapat diisi lewat editor .env/config).
 */
class KpiTargetConfig
{
    /**
     * Target per parameter stasiun. Kunci = key series di StationChartConfig.
     *
     * @var array<string, array<string, array{label?: string, unit?: string, direction: string, min?: float, max?: float}>>
     */
    public const TARGETS = [
        'timbang' => [
            'potongan_persen' => ['direction' => 'lower', 'max' => 10.0, 'unit' => '%'],
        ],
        'sortasi' => [
            'buah_mentah_persen' => ['direction' => 'lower', 'max' => 3.0, 'unit' => '%'],
            'buah_matang_persen' => ['direction' => 'higher', 'min' => 85.0, 'unit' => '%'],
            'jankos_persen' => ['direction' => 'lower', 'max' => 2.0, 'unit' => '%'],
            'tangkai_panjang_persen' => ['direction' => 'lower', 'max' => 5.0, 'unit' => '%'],
        ],
        'sterilizer' => [
            'tekanan_bar' => ['direction' => 'range', 'min' => 2.5, 'max' => 3.5, 'unit' => 'bar'],
            'suhu_celcius' => ['direction' => 'range', 'min' => 120.0, 'max' => 145.0, 'unit' => '°C'],
            'durasi_menit' => ['direction' => 'range', 'min' => 60.0, 'max' => 100.0, 'unit' => 'menit'],
        ],
        'press' => [
            'tekanan_hidrolik' => ['direction' => 'range', 'min' => 60.0, 'max' => 75.0, 'unit' => 'kg/cm²'],
            'tambah_air_persen' => ['direction' => 'lower', 'max' => 10.0, 'unit' => '%'],
        ],
        'klarifikasi' => [
            'kadar_air_persen' => ['direction' => 'lower', 'max' => 0.5, 'unit' => '%'],
        ],
        'kernel' => [
            'losses_inti_persen' => ['direction' => 'lower', 'max' => 2.0, 'unit' => '%'],
            'kadar_kotoran_persen' => ['direction' => 'lower', 'max' => 6.0, 'unit' => '%'],
        ],
        'lab' => [
            'kadar_alb_cpo' => ['label' => 'FFA / ALB CPO', 'direction' => 'lower', 'max' => 5.0, 'unit' => '%'],
            'losses_fiber_persen' => ['direction' => 'lower', 'max' => 5.0, 'unit' => '%'],
            'losses_jankos_persen' => ['direction' => 'lower', 'max' => 2.0, 'unit' => '%'],
        ],
        // maintenance: tidak ada target absolut (jam jalan bergantung mesin).
    ];

    /**
     * KPI plant-wide untuk Command Center. Kunci bebas (dipetakan di controller).
     *
     * @var array<string, array{label: string, unit?: string, direction: string, min?: float, max?: float}>
     */
    public const PLANT_TARGETS = [
        'ffa' => ['label' => 'FFA / ALB CPO', 'direction' => 'lower', 'max' => 5.0, 'unit' => '%'],
        'losses_fiber' => ['label' => 'Losses Fiber', 'direction' => 'lower', 'max' => 5.0, 'unit' => '%'],
        'efficiency' => ['label' => 'Skor Efisiensi', 'direction' => 'higher', 'min' => 75.0, 'unit' => '/100'],
    ];

    /**
     * Target efektif satu stasiun: default ditimpa config('poms.targets.{station}').
     *
     * @return array<string, array{label?: string, unit?: string, direction: string, min?: float, max?: float}>
     */
    public static function forStation(string $station): array
    {
        $defaults = self::TARGETS[$station] ?? [];
        $overrides = (array) config("poms.targets.{$station}", []);

        return array_replace_recursive($defaults, $overrides);
    }

    /**
     * Target efektif KPI plant-wide.
     *
     * @return array<string, array{label: string, unit?: string, direction: string, min?: float, max?: float}>
     */
    public static function plantTargets(): array
    {
        return array_replace_recursive(self::PLANT_TARGETS, (array) config('poms.plant_targets', []));
    }

    public static function hasStationTargets(string $station): bool
    {
        return self::forStation($station) !== [];
    }

    /**
     * Evaluasi satu nilai terhadap definisi target.
     *
     * @param  array{label?: string, unit?: string, direction: string, min?: float, max?: float}  $target
     * @return array{key: string, label: string, unit: string, direction: string, target_text: string, actual: ?float, achieved: ?bool, progress: int}
     */
    public static function evaluate(string $key, array $target, ?float $actual): array
    {
        $direction = $target['direction'] ?? 'lower';
        $min = isset($target['min']) ? (float) $target['min'] : null;
        $max = isset($target['max']) ? (float) $target['max'] : null;

        $achieved = null;
        $progress = 0;

        if ($actual !== null) {
            if ($direction === 'lower' && $max !== null) {
                $achieved = $actual <= $max;
                $progress = $actual <= 0 ? 100 : (int) round(min(100, ($max / $actual) * 100));
            } elseif ($direction === 'higher' && $min !== null) {
                $achieved = $actual >= $min;
                $progress = $min <= 0 ? 100 : (int) round(min(100, ($actual / $min) * 100));
            } elseif ($direction === 'range' && $min !== null && $max !== null) {
                $achieved = $actual >= $min && $actual <= $max;
                if ($achieved) {
                    $progress = 100;
                } elseif ($actual < $min) {
                    $progress = $min <= 0 ? 100 : (int) round(min(100, ($actual / $min) * 100));
                } else {
                    $progress = $actual <= 0 ? 100 : (int) round(min(100, ($max / $actual) * 100));
                }
            }
        }

        return [
            'key' => $key,
            'label' => $target['label'] ?? $key,
            'unit' => $target['unit'] ?? '',
            'direction' => $direction,
            'target_text' => self::targetText($direction, $min, $max),
            'actual' => $actual,
            'achieved' => $achieved,
            'progress' => max(0, min(100, $progress)),
        ];
    }

    private static function targetText(string $direction, ?float $min, ?float $max): string
    {
        return match ($direction) {
            'higher' => '≥ '.self::num($min),
            'range' => self::num($min).' – '.self::num($max),
            default => '≤ '.self::num($max),
        };
    }

    private static function num(?float $value): string
    {
        if ($value === null) {
            return '—';
        }

        return rtrim(rtrim(number_format($value, 2, ',', '.'), '0'), ',');
    }
}
