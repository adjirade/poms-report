<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
class LogKernel extends Model {
    use HasFactory, StationLogTrait;
    protected $table = 'log_kernel';
    protected $fillable = ['user_id', 'plant_id', 'suhu_silo_celcius', 'losses_inti_persen', 'kadar_kotoran_persen', 'timestamp_kirim', 'timestamp_server', 'is_flagged', 'is_verified', 'verified_by', 'notes', 'hq_synced_at', 'hq_source_id', 'operator_name'];
    protected $casts = ['suhu_silo_celcius' => 'integer', 'losses_inti_persen' => 'decimal:2', 'kadar_kotoran_persen' => 'decimal:2', 'timestamp_kirim' => 'datetime', 'timestamp_server' => 'datetime', 'is_flagged' => 'boolean', 'is_verified' => 'boolean', 'hq_synced_at' => 'datetime', 'hq_source_id' => 'integer'];
}
