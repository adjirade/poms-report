<?php

namespace Database\Factories;

use App\Models\LogLab;

/**
 * @extends BaseStationLogFactory<LogLab>
 */
class LogLabFactory extends BaseStationLogFactory
{
    protected $model = LogLab::class;

    public function definition(): array
    {
        return [
            'user_id' => null,
            'plant_id' => (string) config('poms.plant_id', 'PKS_01'),
            'kadar_alb_cpo' => self::randFromRule('lab', 'kadar_alb_cpo'),
            'losses_fiber_persen' => self::randFromRule('lab', 'losses_fiber_persen'),
            'losses_jankos_persen' => self::randFromRule('lab', 'losses_jankos_persen'),
            'operator_name' => $this->demoOperatorName(),
        ];
    }
}
