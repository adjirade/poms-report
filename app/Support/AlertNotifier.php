<?php

namespace App\Support;

use App\Services\TelegramService;
use Illuminate\Support\Facades\Log;

/**
 * Notifikasi alert operasional ke developer/IT via Telegram.
 *
 * Chat ID tujuan dikonfigurasi via env POMS_ALERT_TELEGRAM_CHAT_ID.
 * Jika kosong, alert hanya masuk ke log — aplikasi tidak pernah gagal
 * hanya karena notifikasi gagal terkirim.
 */
class AlertNotifier
{
    /**
     * Kirim alert (fire-and-forget). Selalu mencatat ke log error.
     */
    public static function send(string $title, string $detail = ''): void
    {
        Log::error('ALERT: '.$title.($detail !== '' ? ' — '.$detail : ''));

        $chatId = (string) config('poms.alert_telegram_chat_id', '');
        $token = (string) config('telegram.bot_token', '');

        if ($chatId === '' || $token === '') {
            return;
        }

        try {
            $plant = (string) config('poms.plant_id', 'PKS');
            $text = "🚨 *{$title}*\n🏭 Plant: {$plant}\n🕒 "
                .now()->timezone('Asia/Jakarta')->format('d M Y H:i').' WIB'
                .($detail !== '' ? "\n\n{$detail}" : '');

            app(TelegramService::class)->sendMessage($chatId, $text);
        } catch (\Throwable $e) {
            Log::warning('Gagal mengirim alert Telegram: '.$e->getMessage());
        }
    }
}
