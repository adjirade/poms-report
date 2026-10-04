<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ValidationRule extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'plant_id',
        'station_name',
        'parameter_name',
        'min_value',
        'max_value',
        'data_type',
        'allowed_values',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'min_value' => 'decimal:2',
            'max_value' => 'decimal:2',
        ];
    }

    /**
     * Get rules for specific plant and station
     */
    public static function getRulesForStation(string $plantId, string $stationName): array
    {
        return static::where('plant_id', $plantId)
            ->where('station_name', $stationName)
            ->get()
            ->keyBy('parameter_name')
            ->toArray();
    }

    /**
     * Validate a parameter value against rule
     */
    public function validateValue($value): bool
    {
        if ($this->data_type === 'enum') {
            $allowedValues = explode(',', $this->allowed_values);

            return in_array($value, $allowedValues);
        }

        // Numeric validation
        $numericValue = (float) $value;

        return $numericValue >= $this->min_value && $numericValue <= $this->max_value;
    }

    /**
     * Get validation error message
     */
    public function getErrorMessage($value): string
    {
        if ($this->data_type === 'enum') {
            return "Parameter {$this->parameter_name} harus salah satu dari: {$this->allowed_values}. Nilai '{$value}' tidak valid.";
        }

        return "Parameter {$this->parameter_name} bernilai {$value} melebihi batas standar (Min: {$this->min_value}, Max: {$this->max_value}).";
    }
}
