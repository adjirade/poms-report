<?php

namespace Database\Factories;

use App\Models\LogSterilizer;

/**
 * @extends BaseStationLogFactory<LogSterilizer>
 */
class LogSterilizerFactory extends BaseStationLogFactory
{
    protected $model = LogSterilizer::class;

    public function definition(): array
    {
        return [
            'user_id' => null,
            'plant_id' => (string) config('poms.plant_id', 'PKS_01'),
            'no_rebusan' => self::randIntFromRule('sterilizer', 'no_rebusan'),
            'tekanan_bar' => self::randFromRule('sterilizer', 'tekanan_bar'),
            'suhu_celcius' => self::randIntFromRule('sterilizer', 'suhu_celcius'),
            'durasi_menit' => self::randIntFromRule('sterilizer', 'durasi_menit'),
            'operator_name' => $this->demoOperatorName(),
        ];
    }
}
