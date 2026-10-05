<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\DailyRecapService;
use App\Services\TelegramService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Rekap harian otomatis ke Telegram: pesan teks ringkasan per stasiun +
 * lampiran PDF laporan produksi. Dijalankan via scheduler
 * (Schedule::command('telegram:daily-recap')) — lihat routes/console.php.
 */
class TelegramRecapCommand extends Command
{
    protected $signature = 'telegram:daily-recap
                            {--date= : Tanggal rekap (Y-m-d), default kemarin}
                            {--no-pdf : Kirim teks saja tanpa lampiran PDF}';

    protected $description = 'Kirim rekap harian POMS (teks + PDF) ke pengelola pabrik via Telegram';

    public function handle(DailyRecapService $recap, TelegramService $telegram): int
    {
        if (config('telegram.recap.enabled') === false) {
            $this->info('Rekap Telegram dinonaktifkan (TELEGRAM_RECAP_ENABLED=false).');

            return self::SUCCESS;
        }

        if (config('telegram.bot_token') === '') {
            $this->warn('TELEGRAM_BOT_TOKEN belum diisi — rekap dilewati.');

            return self::SUCCESS;
        }

        $date = $this->option('date')
            ? Carbon::parse($this->option('date'))
            : now()->subDay();

        $recipients = $this->recipients();
        $extraChats = $this->extraChats();

        if ($recipients->isEmpty() && $extraChats->isEmpty()) {
            $this->warn('Tidak ada penerima rekap (user terhubung bot / TELEGRAM_RECAP_CHAT_IDS kosong).');

            return self::SUCCESS;
        }

        // Satu rekap per plant (data rekap scoping plant_id masing-masing user).
        foreach ($this->plants() as $plantId) {
            $summary = $recap->summary($plantId, $date);
            $text = $recap->recapText($summary);
            $pdf = $this->option('no-pdf') ? null : $recap->generatePdf($summary);

            $sent = $this->broadcast($telegram, $recipients, $extraChats, $text, $pdf,
                'Laporan Produksi Harian '.$summary['date']->format('d/m/Y'));

            $this->info("Rekap {$plantId} ({$date->format('d/m/Y')}) dikirim ke {$sent} chat.");
        }

        return self::SUCCESS;
    }

    /**
     * Penerima personal: role >= asisten, terhubung bot, master opt-in.
     */
    protected function recipients(): \Illuminate\Support\Collection
    {
        return User::query()
            ->where('status', 'active')
            ->whereIn('role', ['asisten', 'askep', 'manager', 'hq_admin', 'developer'])
            ->whereNotNull('telegram_user_id')
            ->where('telegram_notif_enabled', true)
            ->get(['telegram_user_id']);
    }

    /**
     * Chat tambahan (grup/arsip) dari .env, dipisah koma.
     */
    protected function extraChats(): \Illuminate\Support\Collection
    {
        return collect(explode(',', (string) config('telegram.recap.chat_ids', '')))
            ->map(fn ($c) => trim($c))
            ->filter()
            ->values();
    }

    /**
     * Daftar plant unik milik user terdaftar.
     */
    protected function plants(): \Illuminate\Support\Collection
    {
        return User::query()->distinct()->pluck('plant_id')->filter()->values();
    }

    /**
     * Kirim satu rekap (teks + opsional PDF) ke semua penerima, lalu hapus
     * file PDF sementara. Mengembalikan jumlah penerima personal yang sukses.
     */
    protected function broadcast(
        TelegramService $telegram,
        \Illuminate\Support\Collection $recipients,
        \Illuminate\Support\Collection $extraChats,
        string $text,
        ?array $pdf,
        string $docCaption,
    ): int {
        $sent = 0;

        foreach ($recipients as $user) {
            $ok = $telegram->sendFormattedMessage($user->telegram_user_id, $text);
            if ($ok && $pdf) {
                $ok = $telegram->sendDocument(
                    $user->telegram_user_id,
                    Storage::disk('local')->get($pdf['path']),
                    $pdf['filename'],
                    $docCaption,
                );
            }
            if ($ok) {
                $sent++;
            }
        }

        foreach ($extraChats as $chatId) {
            $ok = $telegram->sendFormattedMessage($chatId, $text);
            if ($ok && $pdf) {
                $telegram->sendDocument(
                    $chatId,
                    Storage::disk('local')->get($pdf['path']),
                    $pdf['filename'],
                    $docCaption,
                );
            }
        }

        // Bersihkan file PDF sementara setelah semua pengiriman.
        if ($pdf) {
            Storage::disk('local')->delete($pdf['path']);
        }

        return $sent;
    }
}
