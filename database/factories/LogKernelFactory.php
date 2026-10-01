<?php

namespace Database\Factories;

use App\Models\LogKernel;

/**
 * @extends BaseStationLogFactory<LogKernel>
 */
class LogKernelFactory extends BaseStationLogFactory
{
    protected $model = LogKernel::class;

    public function definition(): array
    {
        return [
            'user_id' => null,
            'plant_id' => (string) config('poms.plant_id', 'PKS_01'),
            'suhu_silo_celcius' => self::randIntFromRule('kernel', 'suhu_silo_celcius'),
            'losses_inti_persen' => self::randFromRule('kernel', 'losses_inti_persen'),
            'kadar_kotoran_persen' => self::randFromRule('kernel', 'kadar_kotoran_persen'),
            'operator_name' => $this->demoOperatorName(),
        ];
    }
}
