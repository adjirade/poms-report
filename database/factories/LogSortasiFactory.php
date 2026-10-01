<?php

namespace Database\Factories;

use App\Models\LogSortasi;

/**
 * @extends BaseStationLogFactory<LogSortasi>
 */
class LogSortasiFactory extends BaseStationLogFactory
{
    protected $model = LogSortasi::class;

    private static int $spbSeq = 0;

    public function definition(): array
    {
        return [
            'user_id' => null,
            'plant_id' => (string) config('poms.plant_id', 'PKS_01'),
            'no_spb' => sprintf('SPB-%s-%04d', now()->format('ymd'), ++self::$spbSeq),
            'buah_mentah_persen' => self::randFromRule('sortasi', 'buah_mentah_persen'),
            'buah_matang_persen' => self::randFromRule('sortasi', 'buah_matang_persen'),
            'jankos_persen' => self::randFromRule('sortasi', 'jankos_persen'),
            'tangkai_panjang_persen' => self::randFromRule('sortasi', 'tangkai_panjang_persen'),
            'operator_name' => $this->demoOperatorName(),
        ];
    }
}
