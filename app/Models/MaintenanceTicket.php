<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * B3 — Tiket maintenance (laporan kerusakan mesin).
 *
 * Siklus status: open -> dikerjakan -> selesai. Riwayat per mesin diperoleh
 * dengan memfilter kolom `kode_mesin`.
 */
class MaintenanceTicket extends Model
{
    public const STATUSES = ['open', 'dikerjakan', 'selesai'];

    public const PRIORITIES = ['rendah', 'sedang', 'tinggi'];

    protected $fillable = [
        'plant_id',
        'kode_mesin',
        'judul',
        'deskripsi',
        'prioritas',
        'status',
        'reported_by',
        'assigned_to',
        'resolution_note',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'resolved_at' => 'datetime',
        ];
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function scopeForPlant($query, string $plantId)
    {
        return $query->where('plant_id', $plantId);
    }

    public function scopeWithStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'dikerjakan' => 'Dikerjakan',
            'selesai' => 'Selesai',
            default => 'Open',
        };
    }

    public function priorityLabel(): string
    {
        return match ($this->prioritas) {
            'tinggi' => 'Tinggi',
            'rendah' => 'Rendah',
            default => 'Sedang',
        };
    }
}
