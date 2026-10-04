<?php

namespace App\Support;

/**
 * Satu sumber kebenaran untuk chart "Performa Stasiun".
 *
 * Mendefinisikan, per stasiun:
 *  - `series`: parameter numerik yang diplot sebagai line chart multi-series
 *    (dengan label tampilan + satuan). Warna diambil berurutan dari COLORS.
 *  - `table`: kolom tabel detail di bawah chart (termasuk field bebas seperti
 *    no_spb / kode_mesin yang tidak bisa diplot).
 *
 * Dipakai bersama oleh DashboardController (halaman analytics) dan
 * StationController (tab grafik di halaman stasiun).
 */
class StationChartConfig
{
    /** Palet warna series (konsisten dengan PomsChart.palette di app.js). */
    public const COLORS = ['#0ea5e9', '#10b981', '#f59e0b', '#8b5cf6', '#ef4444', '#14b8a6'];

    /**
     * @var array<string, array{title: string, series: array<int, array{key: string, label: string, unit?: string}>, table: array<int, array{key: string, label: string, unit?: string}>}>
     */
    public const STATIONS = [
        'timbang' => [
            'title' => 'Timbang (Weightbridge)',
            'series' => [
                ['key' => 'tonase_bruto', 'label' => 'Tonase Bruto', 'unit' => 'kg'],
                ['key' => 'tonase_tarra', 'label' => 'Tonase Tarra', 'unit' => 'kg'],
                ['key' => 'potongan_persen', 'label' => 'Potongan', 'unit' => '%'],
            ],
            'table' => [
                ['key' => 'no_spb', 'label' => 'No. SPB'],
                ['key' => 'tonase_bruto', 'label' => 'Bruto', 'unit' => 'kg'],
                ['key' => 'tonase_tarra', 'label' => 'Tarra', 'unit' => 'kg'],
                ['key' => 'potongan_persen', 'label' => 'Potongan', 'unit' => '%'],
            ],
        ],
        'sortasi' => [
            'title' => 'Sortasi (Grading Ramp)',
            'series' => [
                ['key' => 'buah_mentah_persen', 'label' => 'Buah Mentah', 'unit' => '%'],
                ['key' => 'buah_matang_persen', 'label' => 'Buah Matang', 'unit' => '%'],
                ['key' => 'jankos_persen', 'label' => 'Jankos', 'unit' => '%'],
                ['key' => 'tangkai_panjang_persen', 'label' => 'Tangkai Panjang', 'unit' => '%'],
            ],
            'table' => [
                ['key' => 'no_spb', 'label' => 'No. SPB'],
                ['key' => 'buah_mentah_persen', 'label' => 'Mentah', 'unit' => '%'],
                ['key' => 'buah_matang_persen', 'label' => 'Matang', 'unit' => '%'],
                ['key' => 'jankos_persen', 'label' => 'Jankos', 'unit' => '%'],
                ['key' => 'tangkai_panjang_persen', 'label' => 'Tangkai', 'unit' => '%'],
            ],
        ],
        'sterilizer' => [
            'title' => 'Sterilizer (Perebusan)',
            'series' => [
                ['key' => 'tekanan_bar', 'label' => 'Tekanan', 'unit' => 'bar'],
                ['key' => 'suhu_celcius', 'label' => 'Suhu', 'unit' => '°C'],
                ['key' => 'durasi_menit', 'label' => 'Durasi', 'unit' => 'menit'],
            ],
            'table' => [
                ['key' => 'no_rebusan', 'label' => 'No. Rebusan'],
                ['key' => 'tekanan_bar', 'label' => 'Tekanan', 'unit' => 'bar'],
                ['key' => 'suhu_celcius', 'label' => 'Suhu', 'unit' => '°C'],
                ['key' => 'durasi_menit', 'label' => 'Durasi', 'unit' => 'menit'],
            ],
        ],
        'press' => [
            'title' => 'Press (Screw Press)',
            'series' => [
                ['key' => 'tekanan_hidrolik', 'label' => 'Tekanan Hidrolik', 'unit' => 'kg/cm²'],
                ['key' => 'ampere_motor', 'label' => 'Ampere Motor', 'unit' => 'A'],
                ['key' => 'tambah_air_persen', 'label' => 'Tambah Air', 'unit' => '%'],
            ],
            'table' => [
                ['key' => 'no_press', 'label' => 'No. Press'],
                ['key' => 'tekanan_hidrolik', 'label' => 'Tekanan', 'unit' => 'kg/cm²'],
                ['key' => 'ampere_motor', 'label' => 'Ampere', 'unit' => 'A'],
                ['key' => 'tambah_air_persen', 'label' => 'Tambah Air', 'unit' => '%'],
            ],
        ],
        'klarifikasi' => [
            'title' => 'Klarifikasi',
            'series' => [
                ['key' => 'suhu_tangki_celcius', 'label' => 'Suhu Tangki', 'unit' => '°C'],
                ['key' => 'level_minyak_cm', 'label' => 'Level Minyak', 'unit' => 'cm'],
                ['key' => 'kadar_air_persen', 'label' => 'Kadar Air', 'unit' => '%'],
            ],
            'table' => [
                ['key' => 'no_tangki', 'label' => 'No. Tangki'],
                ['key' => 'suhu_tangki_celcius', 'label' => 'Suhu', 'unit' => '°C'],
                ['key' => 'level_minyak_cm', 'label' => 'Level', 'unit' => 'cm'],
                ['key' => 'kadar_air_persen', 'label' => 'Kadar Air', 'unit' => '%'],
            ],
        ],
        'kernel' => [
            'title' => 'Kernel (Nut & Kernel)',
            'series' => [
                ['key' => 'suhu_silo_celcius', 'label' => 'Suhu Silo', 'unit' => '°C'],
                ['key' => 'losses_inti_persen', 'label' => 'Losses Inti', 'unit' => '%'],
                ['key' => 'kadar_kotoran_persen', 'label' => 'Kadar Kotoran', 'unit' => '%'],
            ],
            'table' => [
                ['key' => 'suhu_silo_celcius', 'label' => 'Suhu Silo', 'unit' => '°C'],
                ['key' => 'losses_inti_persen', 'label' => 'Losses Inti', 'unit' => '%'],
                ['key' => 'kadar_kotoran_persen', 'label' => 'Kotoran', 'unit' => '%'],
            ],
        ],
        'lab' => [
            'title' => 'Laboratorium (QC)',
            'series' => [
                ['key' => 'kadar_alb_cpo', 'label' => 'Kadar ALB CPO', 'unit' => '%'],
                ['key' => 'losses_fiber_persen', 'label' => 'Losses Fiber', 'unit' => '%'],
                ['key' => 'losses_jankos_persen', 'label' => 'Losses Jankos', 'unit' => '%'],
            ],
            'table' => [
                ['key' => 'kadar_alb_cpo', 'label' => 'Kadar ALB', 'unit' => '%'],
                ['key' => 'losses_fiber_persen', 'label' => 'Losses Fiber', 'unit' => '%'],
                ['key' => 'losses_jankos_persen', 'label' => 'Losses Jankos', 'unit' => '%'],
            ],
        ],
        'maintenance' => [
            'title' => 'Maintenance',
            'series' => [
                ['key' => 'jam_jalan_hm', 'label' => 'Jam Jalan', 'unit' => 'HM'],
            ],
            'table' => [
                ['key' => 'kode_mesin', 'label' => 'Kode Mesin'],
                ['key' => 'jam_jalan_hm', 'label' => 'Jam Jalan', 'unit' => 'HM'],
                ['key' => 'status_kondisi', 'label' => 'Status'],
                ['key' => 'keterangan_perbaikan', 'label' => 'Keterangan'],
            ],
        ],
    ];

    /** Rentang waktu (hari) yang didukung time range picker. */
    public const RANGES = [7, 30, 90];

    public static function exists(string $station): bool
    {
        return array_key_exists($station, self::STATIONS);
    }

    public static function title(string $station): string
    {
        return self::STATIONS[$station]['title'] ?? ucfirst($station);
    }

    /** @return array<int, string> */
    public static function stationKeys(): array
    {
        return array_keys(self::STATIONS);
    }

    /**
     * Series chart untuk satu stasiun, lengkap dengan warna per series.
     *
     * @return array<int, array{key: string, label: string, unit: string, color: string}>
     */
    public static function series(string $station): array
    {
        return array_map(
            fn (int $i, array $s) => [
                'key' => $s['key'],
                'label' => $s['label'],
                'unit' => $s['unit'] ?? '',
                'color' => self::COLORS[$i % count(self::COLORS)],
            ],
            array_keys(self::STATIONS[$station]['series'] ?? []),
            array_values(self::STATIONS[$station]['series'] ?? []),
        );
    }

    /** Kolom tabel detail untuk satu stasiun. */
    public static function tableColumns(string $station): array
    {
        return self::STATIONS[$station]['table'] ?? [];
    }
}
