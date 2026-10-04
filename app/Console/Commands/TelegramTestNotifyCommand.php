<?php

namespace App\Console\Commands;

use App\Services\TelegramService;
use Illuminate\Console\Command;

/**
 * UAT kecil (AGENDA §3): kirim satu pesan uji ke chat ID tertentu.
 *
 *   php artisan telegram:test-notify 123456789
 *
 * Cara dapat chat ID: kirim pesan apa pun ke bot saat `telegram:poll --once`
 * berjalan — chat ID tercetak di log (storage/logs/laravel.log).
 */
class TelegramTestNotifyCommand extends Command
{
    protected $signature = 'telegram:test-notify
                            {chat_id : Chat ID tujuan (dapat dari log polling)}';

    protected $description = 'Send a test notification to a Telegram chat (UAT)';

    public function handle(TelegramService $telegram): int
    {
        $chatId = (string) $this->argument('chat_id');

        $bot = $telegram->getMe();
        if (! $bot) {
            $this->error('❌ Bot tidak dapat dihubungi. Cek TELEGRAM_BOT_TOKEN.');

            return Command::FAILURE;
        }

        $text = implode("\n", [
            '🧪 *Uji Notifikasi POMS*',
            '',
            'Notifikasi berjalan normal. ✅',
            'Waktu: '.now()->format('d/m/Y H:i:s'),
        ]);

        if ($telegram->sendMessage($chatId, $text)) {
            $this->info("✅ Pesan uji terkirim ke chat {$chatId} via @{$bot['username']}.");

            return Command::SUCCESS;
        }

        $this->error('❌ Gagal kirim. Pastikan user sudah /start ke bot dan chat ID benar (lihat log).');

        return Command::FAILURE;
    }
}
