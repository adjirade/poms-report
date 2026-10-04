<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LogLab extends Model
{
    use HasFactory, StationLogTrait;

    protected $table = 'log_lab';

    protected $fillable = ['user_id', 'plant_id', 'kadar_alb_cpo', 'losses_fiber_persen', 'losses_jankos_persen', 'timestamp_kirim', 'timestamp_server', 'is_flagged', 'is_verified', 'verified_by', 'notes', 'hq_synced_at', 'hq_source_id', 'operator_name'];

    protected $casts = ['kadar_alb_cpo' => 'decimal:2', 'losses_fiber_persen' => 'decimal:2', 'losses_jankos_persen' => 'decimal:2', 'timestamp_kirim' => 'datetime', 'timestamp_server' => 'datetime', 'is_flagged' => 'boolean', 'is_verified' => 'boolean', 'hq_synced_at' => 'datetime', 'hq_source_id' => 'integer'];
}
