<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
class LogKlarifikasi extends Model {
    use HasFactory, StationLogTrait;
    protected $table = 'log_klarifikasi';
    protected $fillable = ['user_id', 'plant_id', 'no_tangki', 'suhu_tangki_celcius', 'level_minyak_cm', 'kadar_air_persen', 'timestamp_kirim', 'timestamp_server', 'is_flagged', 'is_verified', 'verified_by', 'notes', 'hq_synced_at', 'hq_source_id', 'operator_name'];
    protected $casts = ['no_tangki' => 'integer', 'suhu_tangki_celcius' => 'integer', 'level_minyak_cm' => 'decimal:2', 'kadar_air_persen' => 'decimal:3', 'timestamp_kirim' => 'datetime', 'timestamp_server' => 'datetime', 'is_flagged' => 'boolean', 'is_verified' => 'boolean', 'hq_synced_at' => 'datetime', 'hq_source_id' => 'integer'];
}
