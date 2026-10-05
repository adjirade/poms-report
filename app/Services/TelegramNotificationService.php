<?php

namespace App\Services;

use App\Jobs\SendTelegramNotification;
use App\Models\MaintenanceTicket;
use App\Models\User;
use App\Support\StationLogDepartmentTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Notifikasi event via Telegram (AGENDA §3 — arah "notifikasi event").
 *
 * Event yang dinotifikasi:
 *  1. Record FLAGGED masuk  -> semua asisten departemen pemilik stasiun.
 *  2. Record DIVERIFIKASI   -> operator pengirim data.
 *  3. Tiket maintenance dibuat -> asisten/askep/manager departemen maintenance.
 *  4. Status tiket maintenance berubah -> pelapor tiket.
 *
 * Semua kirim di-queue (SendTelegramNotification) dan menghormati preferensi
 * per-user (opt-out) — lihat users.telegram_notif_* dan /notif di bot.
 */
class TelegramNotificationService
{
    use StationLogDepartmentTrait;

    public function __construct(protected TelegramService $telegram) {}

    /**
     * Notifikasi record flagged ke asisten departemen pemilik stasiun.
     * Aman dipanggil untuk record apa pun: no-op jika tidak flagged.
     */
    public function notifyFlagged(Model $record, string $station): void
    {
        try {
            if (! (bool) ($record->is_flagged ?? false)) {
                return;
            }

            $department = $this->departmentForStation($station);
            if ($department === null) {
                return;
            }

            User::query()
                ->where('plant_id', $record->plant_id)
                ->where('role', 'asisten')
                ->where('department', $department)
                ->where('status', 'active')
                ->whereNotNull('telegram_user_id')
                ->get()
                ->filter(fn (User $asisten) => $asisten->wantsTelegramNotification('flagged'))
                ->each(fn (User $asisten) => $this->dispatchTo(
                    $asisten->telegram_user_id,
                    $this->flaggedMessage($record, $station),
                ));
        } catch (Throwable $e) {
            // Notifikasi tidak boleh menggagalkan alur penyimpanan data.
            Log::warning('Gagal menyiapkan notifikasi flagged', [
                'station' => $station,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Notifikasi ke pengirim data bahwa recordnya sudah diverifikasi.
     */
    public function notifyVerified(Model $record, string $station, User $verifier): void
    {
        try {
            if (! (bool) ($record->is_verified ?? false)) {
                return;
            }

            $submitter = $record->user;

            if (! $submitter || ! $submitter->telegram_user_id) {
                return;
            }

            // Tidak ada gunanya memberi tahu orang atas verifikasinya sendiri.
            if ($verifier->id === $submitter->id) {
                return;
            }

            if (! $submitter->wantsTelegramNotification('verified')) {
                return;
            }

            $this->dispatchTo(
                $submitter->telegram_user_id,
                $this->verifiedMessage($record, $station, $verifier),
            );
        } catch (Throwable $e) {
            Log::warning('Gagal menyiapkan notifikasi verifikasi', [
                'station' => $station,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Notifikasi tiket maintenance (B3).
     *
     * @param  string  $event  'created' (default) atau 'status'
     */
    public function notifyMaintenanceTicket(MaintenanceTicket $ticket, string $event = 'created'): void
    {
        try {
            if ($event === 'status') {
                $reporter = $ticket->reporter;
                $recipients = $reporter ? collect([$reporter]) : collect();
                $text = $this->maintenanceStatusMessage($ticket);
            } else {
                $recipients = User::query()
                    ->where('plant_id', $ticket->plant_id)
                    ->where('department', 'maintenance')
                    ->whereIn('role', ['asisten', 'askep', 'manager'])
                    ->where('status', 'active')
                    ->whereNotNull('telegram_user_id')
                    ->get();
                $text = $this->maintenanceCreatedMessage($ticket);
            }

            $recipients
                ->filter(fn (User $user) => $user->telegram_user_id && $user->wantsTelegramNotification('maintenance'))
                ->each(fn (User $user) => $this->dispatchTo($user->telegram_user_id, $text));
        } catch (Throwable $e) {
            Log::warning('Gagal menyiapkan notifikasi tiket maintenance', [
                'ticket' => $ticket->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function maintenanceCreatedMessage(MaintenanceTicket $ticket): string
    {
        return implode("\n", [
            "🛠️ *Tiket Maintenance Baru*\n",
            '*Mesin:* `'.$ticket->kode_mesin.'`',
            '*Masalah:* '.$ticket->judul,
            '*Prioritas:* '.$ticket->priorityLabel(),
            '*Pelapor:* '.($ticket->reporter?->name ?? '-'),
            '*ID Tiket:* #'.$ticket->id,
            "\n_Kelola status tiket lewat menu Tiket Maintenance di POMS._",
        ]);
    }

    protected function maintenanceStatusMessage(MaintenanceTicket $ticket): string
    {
        return implode("\n", [
            "🔧 *Status Tiket Maintenance Diperbarui*\n",
            '*Mesin:* `'.$ticket->kode_mesin.'`',
            '*Masalah:* '.$ticket->judul,
            '*Status:* '.$ticket->statusLabel(),
            '*ID Tiket:* #'.$ticket->id,
            "\n_Terima kasih, laporan Anda sedang ditindaklanjuti._",
        ]);
    }

    protected function dispatchTo(string $chatId, string $text): void
    {
        try {
            SendTelegramNotification::dispatch($chatId, $text);
        } catch (Throwable $e) {
            Log::warning('Gagal dispatch notifikasi Telegram', ['error' => $e->getMessage()]);
        }
    }

    protected function flaggedMessage(Model $record, string $station): string
    {
        $lines = [
            "🚩 *Data Flagged — Perlu Verifikasi*\n",
            '*Stasiun:* '.ucfirst($station),
            '*ID Record:* #'.$record->id,
        ];

        if ($record->user) {
            $lines[] = '*Operator:* '.$record->user->name;
        }

        if (! empty($record->timestamp_kirim)) {
            $lines[] = '*Waktu kirim:* '.$record->timestamp_kirim->format('d/m/Y H:i');
        }

        $lines[] = "\n_Selisih waktu kirim vs server melebihi 4 jam. Silakan periksa dan verifikasi lewat dashboard POMS._";

        return implode("\n", $lines);
    }

    protected function verifiedMessage(Model $record, string $station, User $verifier): string
    {
        return implode("\n", [
            "✅ *Data Diverifikasi*\n",
            '*Stasiun:* '.ucfirst($station),
            '*ID Record:* #'.$record->id,
            '*Diverifikasi oleh:* '.$verifier->name,
            '*Waktu:* '.now()->format('d/m/Y H:i'),
            "\n_Terima kasih, data Anda sudah terverifikasi._",
        ]);
    }
}
