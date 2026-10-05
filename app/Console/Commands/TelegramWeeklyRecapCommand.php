<?php

namespace App\Console\Commands;

use App\Services\DailyRecapService;
use App\Services\TelegramService;
use Illuminate\Support\Carbon;

/**
 * Rekap mingguan otomatis ke Telegram: agregat operasional Senin–Minggu
 * minggu sebelumnya (teks + lampiran PDF). Dijalankan via scheduler
 * (Schedule::command('telegram:weekly-recap')) — lihat routes/console.php.
 *
 * Mewarisi logika penerima & pengiriman dari TelegramRecapCommand.
 */
class TelegramWeeklyRecapCommand extends TelegramRecapCommand
{
    protected $signature = 'telegram:weekly-recap
                            {--date= : Tanggal acuan (Y-m-d); yang direkap adalah minggu sebelum tanggal ini}
                            {--no-pdf : Kirim teks saja tanpa lampiran PDF}';

    protected $description = 'Kirim rekap mingguan POMS (teks + PDF) ke pengelola pabrik via Telegram';

    public function handle(DailyRecapService $recap, TelegramService $telegram): int
    {
        if (config('telegram.recap.enabled') === false
            || config('telegram.recap.weekly_enabled') === false) {
            $this->info('Rekap mingguan Telegram dinonaktifkan (TELEGRAM_RECAP_ENABLED / TELEGRAM_WEEKLY_RECAP_ENABLED=false).');

            return self::SUCCESS;
        }

        if (config('telegram.bot_token') === '') {
            $this->warn('TELEGRAM_BOT_TOKEN belum diisi — rekap mingguan dilewati.');

            return self::SUCCESS;
        }

        $reference = $this->option('date')
            ? Carbon::parse($this->option('date'))
            : now();
        [$start, $end] = DailyRecapService::lastWeekRange($reference);

        $recipients = $this->recipients();
        $extraChats = $this->extraChats();

        if ($recipients->isEmpty() && $extraChats->isEmpty()) {
            $this->warn('Tidak ada penerima rekap (user terhubung bot / TELEGRAM_RECAP_CHAT_IDS kosong).');

            return self::SUCCESS;
        }

        foreach ($this->plants() as $plantId) {
            $summary = $recap->weeklySummary($plantId, $start, $end);
            $text = $recap->weeklyRecapText($summary);
            $pdf = $this->option('no-pdf') ? null : $recap->generateWeeklyPdf($summary);

            $sent = $this->broadcast($telegram, $recipients, $extraChats, $text, $pdf,
                'Laporan Produksi Mingguan '.$summary['start']->format('d/m').'–'.$summary['end']->format('d/m/Y'));

            $this->info("Rekap mingguan {$plantId} ({$start->format('d/m/Y')} – {$end->format('d/m/Y')}) dikirim ke {$sent} chat.");
        }

        return self::SUCCESS;
    }
}
