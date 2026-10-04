<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Override target KPI per plant/stasiun (diedit lewat UI manager/developer).
 * Bila baris tidak ada, default dari App\Support\KpiTargetConfig yang dipakai.
 */
class KpiTarget extends Model
{
    protected $fillable = [
        'plant_id',
        'station',
        'parameter',
        'direction',
        'min_value',
        'max_value',
        'unit',
        'label',
    ];

    protected function casts(): array
    {
        return [
            'min_value' => 'float',
            'max_value' => 'float',
        ];
    }

    /**
     * Bentuk array target yang kompatibel dengan KpiTargetConfig::evaluate()
     * (hanya field yang terisi yang dikembalikan, agar default tidak tertimpa
     * dengan null).
     *
     * @return array{label?: string, unit?: string, direction: string, min?: float, max?: float}
     */
    public function toTargetArray(): array
    {
        $target = ['direction' => $this->direction];

        if ($this->label !== null && $this->label !== '') {
            $target['label'] = $this->label;
        }
        if ($this->unit !== null && $this->unit !== '') {
            $target['unit'] = $this->unit;
        }
        if ($this->min_value !== null) {
            $target['min'] = (float) $this->min_value;
        }
        if ($this->max_value !== null) {
            $target['max'] = (float) $this->max_value;
        }

        return $target;
    }
}
