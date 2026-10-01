<?php

namespace Database\Factories;

use App\Models\LogMaintenance;

/**
 * @extends BaseStationLogFactory<LogMaintenance>
 */
class LogMaintenanceFactory extends BaseStationLogFactory
{
    protected $model = LogMaintenance::class;

    private static array $machineCodes = [
        'STER-01', 'STER-02', 'PRESS-01', 'PRESS-02', 'PRESS-03',
        'CLR-01', 'CLR-02', 'GENSET-01', 'PUMP-01', 'CONV-01',
    ];

    public function definition(): array
    {
        // Bobot realistis: 70% normal, 20% maintenance, 10% breakdown.
        $roll = random_int(1, 100);
        $status = match (true) {
            $roll <= 70 => 'normal',
            $roll <= 90 => 'maintenance',
            default => 'breakdown',
        };

        return [
            'user_id' => null,
            'plant_id' => (string) config('poms.plant_id', 'PKS_01'),
            'kode_mesin' => fake()->randomElement(self::$machineCodes),
            'jam_jalan_hm' => (float) random_int(100, 8000) + (mt_rand() / mt_getrandmax()),
            'status_kondisi' => $status,
            'keterangan_perbaikan' => $status === 'normal'
                ? null
                : fake('id_ID')->randomElement([
                    'Ganti seal hidrolik', 'Perbaikan sistem pendingin',
                    'Servis berkala 250 HM', 'Ganti bearing screw press',
                    'Perbaikan panel listrik', 'Ganti oli gearbox',
                ]),
            'operator_name' => $this->demoOperatorName(),
        ];
    }
}
