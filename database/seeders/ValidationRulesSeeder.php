<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ValidationRulesSeeder extends Seeder
{
    /**
     * Seed validation rules for all stations based on PRD specifications
     */
    public function run(): void
    {
        $plantIds = ['PKS_01', 'PKS_02', 'PKS_03']; // Add more as needed

        $rules = [
            // A. Stasiun Timbang (Weightbridge)
            ['station' => 'timbang', 'parameter' => 'tonase_bruto', 'min' => 5000, 'max' => 45000, 'type' => 'numeric'],
            ['station' => 'timbang', 'parameter' => 'tonase_tarra', 'min' => 3000, 'max' => 15000, 'type' => 'numeric'],
            ['station' => 'timbang', 'parameter' => 'potongan_persen', 'min' => 1.0, 'max' => 10.0, 'type' => 'numeric'],

            // B. Stasiun Sortasi (Grading Ramp)
            ['station' => 'sortasi', 'parameter' => 'buah_mentah_persen', 'min' => 0.0, 'max' => 15.0, 'type' => 'numeric'],
            ['station' => 'sortasi', 'parameter' => 'buah_matang_persen', 'min' => 70.0, 'max' => 100.0, 'type' => 'numeric'],
            ['station' => 'sortasi', 'parameter' => 'jankos_persen', 'min' => 0.0, 'max' => 5.0, 'type' => 'numeric'],
            ['station' => 'sortasi', 'parameter' => 'tangkai_panjang_persen', 'min' => 0.0, 'max' => 10.0, 'type' => 'numeric'],

            // C. Stasiun Sterilizer (Perebusan)
            ['station' => 'sterilizer', 'parameter' => 'no_rebusan', 'min' => 1, 'max' => 10, 'type' => 'numeric'],
            ['station' => 'sterilizer', 'parameter' => 'tekanan_bar', 'min' => 1.5, 'max' => 3.2, 'type' => 'numeric'],
            ['station' => 'sterilizer', 'parameter' => 'suhu_celcius', 'min' => 110, 'max' => 145, 'type' => 'numeric'],
            ['station' => 'sterilizer', 'parameter' => 'durasi_menit', 'min' => 45, 'max' => 90, 'type' => 'numeric'],

            // D. Stasiun Press (Screw Press)
            ['station' => 'press', 'parameter' => 'no_press', 'min' => 1, 'max' => 12, 'type' => 'numeric'],
            ['station' => 'press', 'parameter' => 'tekanan_hidrolik', 'min' => 60, 'max' => 75, 'type' => 'numeric'],
            ['station' => 'press', 'parameter' => 'ampere_motor', 'min' => 35, 'max' => 45, 'type' => 'numeric'],
            ['station' => 'press', 'parameter' => 'tambah_air_persen', 'min' => 5.0, 'max' => 15.0, 'type' => 'numeric'],

            // E. Stasiun Klarifikasi (Clarification Tank)
            ['station' => 'klarifikasi', 'parameter' => 'no_tangki', 'min' => 1, 'max' => 5, 'type' => 'numeric'],
            ['station' => 'klarifikasi', 'parameter' => 'suhu_tangki_celcius', 'min' => 85, 'max' => 98, 'type' => 'numeric'],
            ['station' => 'klarifikasi', 'parameter' => 'level_minyak_cm', 'min' => 50, 'max' => 300, 'type' => 'numeric'],
            ['station' => 'klarifikasi', 'parameter' => 'kadar_air_persen', 'min' => 0.10, 'max' => 0.50, 'type' => 'numeric'],

            // F. Stasiun Kernel (Nut & Kernel)
            ['station' => 'kernel', 'parameter' => 'suhu_silo_celcius', 'min' => 60, 'max' => 85, 'type' => 'numeric'],
            ['station' => 'kernel', 'parameter' => 'losses_inti_persen', 'min' => 0.5, 'max' => 2.5, 'type' => 'numeric'],
            ['station' => 'kernel', 'parameter' => 'kadar_kotoran_persen', 'min' => 4.0, 'max' => 8.0, 'type' => 'numeric'],

            // G. Stasiun Lab (Laboratory QC)
            ['station' => 'lab', 'parameter' => 'kadar_alb_cpo', 'min' => 2.0, 'max' => 5.0, 'type' => 'numeric'],
            ['station' => 'lab', 'parameter' => 'losses_fiber_persen', 'min' => 1.0, 'max' => 5.0, 'type' => 'numeric'],
            ['station' => 'lab', 'parameter' => 'losses_jankos_persen', 'min' => 0.1, 'max' => 1.0, 'type' => 'numeric'],

            // H. Stasiun Maintenance (Workshop)
            ['station' => 'maintenance', 'parameter' => 'status_kondisi', 'min' => 0, 'max' => 0, 'type' => 'enum', 'allowed' => 'normal,breakdown,maintenance'],
        ];

        // Insert rules for each plant (idempotent: safe to re-run)
        foreach ($plantIds as $plantId) {
            foreach ($rules as $rule) {
                DB::table('validation_rules')->updateOrInsert(
                    [
                        'plant_id' => $plantId,
                        'station_name' => $rule['station'],
                        'parameter_name' => $rule['parameter'],
                    ],
                    [
                        'min_value' => $rule['min'],
                        'max_value' => $rule['max'],
                        'data_type' => $rule['type'],
                        'allowed_values' => $rule['allowed'] ?? null,
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );
            }
        }

        $this->command->info('✓ ' . (count($plantIds) * count($rules)) . ' validation rules seeded.');
    }
}
