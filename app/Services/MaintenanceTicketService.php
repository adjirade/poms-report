<?php

namespace App\Services;

use App\Models\MaintenanceTicket;
use App\Models\User;

/**
 * B3 — Alur tiket maintenance: buat tiket dari laporan kerusakan, ubah status
 * (open -> dikerjakan -> selesai), dan kirim notifikasi Telegram.
 *
 * Notifikasi bersifat non-blocking (di-queue) dan tidak boleh menggagalkan
 * penyimpanan tiket — lihat TelegramNotificationService.
 */
class MaintenanceTicketService
{
    public function __construct(protected TelegramNotificationService $notifications) {}

    /**
     * Buat tiket baru dari laporan kerusakan. Status awal selalu `open`.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $reporter): MaintenanceTicket
    {
        $priority = in_array($data['prioritas'] ?? '', MaintenanceTicket::PRIORITIES, true)
            ? $data['prioritas']
            : 'sedang';

        $ticket = MaintenanceTicket::create([
            'plant_id' => $data['plant_id'] ?? $reporter->plant_id,
            'kode_mesin' => trim((string) $data['kode_mesin']),
            'judul' => trim((string) $data['judul']),
            'deskripsi' => trim((string) $data['deskripsi']),
            'prioritas' => $priority,
            'status' => 'open',
            'reported_by' => $reporter->id,
        ]);

        $this->notifications->notifyMaintenanceTicket($ticket, 'created');

        return $ticket;
    }

    /**
     * Ubah status tiket + catatan penyelesaian / penugasan teknisi.
     */
    public function changeStatus(
        MaintenanceTicket $ticket,
        string $status,
        ?string $note = null,
        ?int $assignedTo = null,
    ): MaintenanceTicket {
        if (! in_array($status, MaintenanceTicket::STATUSES, true)) {
            return $ticket;
        }

        $ticket->status = $status;

        if ($assignedTo !== null) {
            $ticket->assigned_to = $assignedTo;
        }

        if ($note !== null && trim($note) !== '') {
            $ticket->resolution_note = trim($note);
        }

        // Selesai -> tandai waktu penyelesaian; kembali ke status lain -> kosongkan.
        $ticket->resolved_at = $status === 'selesai' ? now() : null;
        $ticket->save();

        $this->notifications->notifyMaintenanceTicket($ticket, 'status');

        return $ticket;
    }
}
