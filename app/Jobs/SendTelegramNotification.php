<?php

namespace App\Jobs;

use App\Services\TelegramService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Kirim satu pesan notifikasi Telegram via queue.
 *
 * Dipisahkan dari job input (ProcessTelegramMessage) supaya notifikasi tidak
 * pernah memperlambat/menggagalkan alur utama (simpan record, verifikasi).
 * Gagal kirim dicatat ke log oleh TelegramService; job tidak melempar error
 * agar tidak masuk retry loop tanpa akhir.
 */
class SendTelegramNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public string $chatId,
        public string $text,
        public string $parseMode = 'Markdown',
    ) {
        // Queue khusus telegram (sama dengan job input) agar worker terpisah.
        $this->onQueue(config('telegram.queue.name', 'telegram'));
    }

    public function handle(TelegramService $telegram): void
    {
        $options = $this->parseMode !== '' ? ['parse_mode' => $this->parseMode] : [];

        $sent = $telegram->sendMessage($this->chatId, $this->text, $options);

        if (! $sent) {
            // TelegramService sudah mencatat error detail ke log.
            Log::warning('Notifikasi Telegram gagal terkirim', ['chat_id' => $this->chatId]);
        }
    }
}
