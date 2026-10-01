<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
class LogSterilizer extends Model {
    use HasFactory, StationLogTrait;
    protected $table = 'log_sterilizer';
    protected $fillable = ['user_id', 'plant_id', 'no_rebusan', 'tekanan_bar', 'suhu_celcius', 'durasi_menit', 'timestamp_kirim', 'timestamp_server', 'is_flagged', 'is_verified', 'verified_by', 'notes', 'hq_synced_at', 'hq_source_id', 'operator_name'];
    protected $casts = ['no_rebusan' => 'integer', 'tekanan_bar' => 'decimal:2', 'suhu_celcius' => 'integer', 'durasi_menit' => 'integer', 'timestamp_kirim' => 'datetime', 'timestamp_server' => 'datetime', 'is_flagged' => 'boolean', 'is_verified' => 'boolean', 'hq_synced_at' => 'datetime', 'hq_source_id' => 'integer'];
}
