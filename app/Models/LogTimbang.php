<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LogTimbang extends Model
{
    use HasFactory, StationLogTrait;

    protected $table = 'log_timbang';

    protected $fillable = [
        'user_id', 'plant_id', 'no_spb', 'tonase_bruto', 'tonase_tarra',
        'potongan_persen', 'timestamp_kirim', 'timestamp_server',
        'is_flagged', 'is_verified', 'verified_by', 'notes',
        'hq_synced_at', 'hq_source_id', 'operator_name',
    ];

    protected $casts = [
        'tonase_bruto' => 'decimal:2',
        'tonase_tarra' => 'decimal:2',
        'potongan_persen' => 'decimal:2',
        'timestamp_kirim' => 'datetime',
        'timestamp_server' => 'datetime',
        'is_flagged' => 'boolean',
        'is_verified' => 'boolean',
        'hq_synced_at' => 'datetime',
        'hq_source_id' => 'integer',
    ];
}
