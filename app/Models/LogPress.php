<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
class LogPress extends Model {
    use HasFactory, StationLogTrait;
    protected $table = 'log_press';
    protected $fillable = ['user_id', 'plant_id', 'no_press', 'tekanan_hidrolik', 'ampere_motor', 'tambah_air_persen', 'timestamp_kirim', 'timestamp_server', 'is_flagged', 'is_verified', 'verified_by', 'notes', 'hq_synced_at', 'hq_source_id', 'operator_name'];
    protected $casts = ['no_press' => 'integer', 'tekanan_hidrolik' => 'decimal:2', 'ampere_motor' => 'decimal:2', 'tambah_air_persen' => 'decimal:2', 'timestamp_kirim' => 'datetime', 'timestamp_server' => 'datetime', 'is_flagged' => 'boolean', 'is_verified' => 'boolean', 'hq_synced_at' => 'datetime', 'hq_source_id' => 'integer'];
}
