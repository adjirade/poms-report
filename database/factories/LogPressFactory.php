<?php

namespace Database\Factories;

use App\Models\LogPress;

/**
 * @extends BaseStationLogFactory<LogPress>
 */
class LogPressFactory extends BaseStationLogFactory
{
    protected $model = LogPress::class;

    public function definition(): array
    {
        return [
            'user_id' => null,
            'plant_id' => (string) config('poms.plant_id', 'PKS_01'),
            'no_press' => self::randIntFromRule('press', 'no_press'),
            'tekanan_hidrolik' => self::randFromRule('press', 'tekanan_hidrolik'),
            'ampere_motor' => self::randFromRule('press', 'ampere_motor'),
            'tambah_air_persen' => self::randFromRule('press', 'tambah_air_persen'),
            'operator_name' => $this->demoOperatorName(),
        ];
    }
}
