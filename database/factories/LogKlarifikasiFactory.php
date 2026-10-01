<?php

namespace Database\Factories;

use App\Models\LogKlarifikasi;

/**
 * @extends BaseStationLogFactory<LogKlarifikasi>
 */
class LogKlarifikasiFactory extends BaseStationLogFactory
{
    protected $model = LogKlarifikasi::class;

    public function definition(): array
    {
        return [
            'user_id' => null,
            'plant_id' => (string) config('poms.plant_id', 'PKS_01'),
            'no_tangki' => self::randIntFromRule('klarifikasi', 'no_tangki'),
            'suhu_tangki_celcius' => self::randIntFromRule('klarifikasi', 'suhu_tangki_celcius'),
            'level_minyak_cm' => self::randFromRule('klarifikasi', 'level_minyak_cm'),
            'kadar_air_persen' => self::randFromRule('klarifikasi', 'kadar_air_persen', 3),
            'operator_name' => $this->demoOperatorName(),
        ];
    }
}
