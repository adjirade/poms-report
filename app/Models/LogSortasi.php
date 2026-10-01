<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
class LogSortasi extends Model {
    use HasFactory, StationLogTrait;
    protected $table = 'log_sortasi';
    protected $fillable = ['user_id', 'plant_id', 'no_spb', 'buah_mentah_persen', 'buah_matang_persen', 'jankos_persen', 'tangkai_panjang_persen', 'timestamp_kirim', 'timestamp_server', 'is_flagged', 'is_verified', 'verified_by', 'notes', 'hq_synced_at', 'hq_source_id', 'operator_name'];
    protected $casts = ['buah_mentah_persen' => 'decimal:2', 'buah_matang_persen' => 'decimal:2', 'jankos_persen' => 'decimal:2', 'tangkai_panjang_persen' => 'decimal:2', 'timestamp_kirim' => 'datetime', 'timestamp_server' => 'datetime', 'is_flagged' => 'boolean', 'is_verified' => 'boolean', 'hq_synced_at' => 'datetime', 'hq_source_id' => 'integer'];
}
