<?php

namespace Database\Factories;

use App\Models\LogTimbang;

/**
 * @extends BaseStationLogFactory<LogTimbang>
 */
class LogTimbangFactory extends BaseStationLogFactory
{
    protected $model = LogTimbang::class;

    private static int $spbSeq = 0;

    public function definition(): array
    {
        return [
            'user_id' => null, // diisi DemoDataSeeder (operator per stasiun)
            'plant_id' => (string) config('poms.plant_id', 'PKS_01'),
            'no_spb' => sprintf('SPB-%s-%04d', now()->format('ymd'), ++self::$spbSeq),
            'tonase_bruto' => self::randFromRule('timbang', 'tonase_bruto'),
            'tonase_tarra' => self::randFromRule('timbang', 'tonase_tarra'),
            'potongan_persen' => self::randFromRule('timbang', 'potongan_persen'),
            'operator_name' => $this->demoOperatorName(),
        ];
    }
}
