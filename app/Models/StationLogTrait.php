<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Base trait for all station log models
 */
trait StationLogTrait
{
    /**
     * Get the user who created this log
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the user who verified this log
     */
    public function verifier()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    /**
     * Scope to filter by plant
     */
    public function scopeForPlant($query, string $plantId)
    {
        return $query->where('plant_id', $plantId);
    }

    /**
     * Scope to filter flagged records
     */
    public function scopeFlagged($query)
    {
        return $query->where('is_flagged', true);
    }

    /**
     * Scope to filter unverified records
     */
    public function scopeUnverified($query)
    {
        return $query->where('is_verified', false);
    }

    /**
     * Scope to filter by date range
     */
    public function scopeBetweenDates($query, $startDate, $endDate)
    {
        return $query->whereBetween('timestamp_kirim', [$startDate, $endDate]);
    }

    /**
     * Check if time discrepancy exceeds 4 hours
     */
    public function hasTimeDiscrepancy(): bool
    {
        $diff = $this->timestamp_server->diffInHours($this->timestamp_kirim);
        return $diff > 4;
    }

    /**
     * Boot the trait
     */
    protected static function bootStationLogTrait()
    {
        static::creating(function ($model) {
            // Auto-flag if time discrepancy > 4 hours
            if ($model->timestamp_server && $model->timestamp_kirim) {
                $diff = abs($model->timestamp_server->diffInHours($model->timestamp_kirim));
                if ($diff > 4) {
                    $model->is_flagged = true;
                }
            }
        });
    }
}
