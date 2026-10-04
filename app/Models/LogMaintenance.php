<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LogMaintenance extends Model
{
    use HasFactory, StationLogTrait;

    protected $table = 'log_maintenance';

    protected $fillable = ['user_id', 'plant_id', 'kode_mesin', 'jam_jalan_hm', 'status_kondisi', 'keterangan_perbaikan', 'timestamp_kirim', 'timestamp_server', 'is_flagged', 'is_verified', 'verified_by', 'notes', 'hq_synced_at', 'hq_source_id', 'operator_name'];

    protected $casts = ['jam_jalan_hm' => 'decimal:2', 'timestamp_kirim' => 'datetime', 'timestamp_server' => 'datetime', 'is_flagged' => 'boolean', 'is_verified' => 'boolean', 'hq_synced_at' => 'datetime', 'hq_source_id' => 'integer'];
}
