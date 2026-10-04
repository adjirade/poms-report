<?php

namespace App\Console\Commands;

use App\Services\TelegramService;
use Illuminate\Console\Command;

/**
 * Kelola webhook Telegram (mode produksi) — pengganti `telegram:poll`.
 *
 *   php artisan telegram:webhook --set          pasang ke APP_URL/api/telegram/webhook
 *   php artisan telegram:webhook --set --url=https://...  URL kustom
 *   php artisan telegram:webhook --remove       lepas webhook
 *   php artisan telegram:webhook                lihat status webhook
 *
 * Wajib set TELEGRAM_WEBHOOK_SECRET di .env sebelum --set (dikirim Telegram
 * pada header X-Telegram-Bot-Api-Secret-Token dan divalidasi route webhook).
 */
class TelegramWebhookCommand extends Command
{
    protected $signature = 'telegram:webhook
                            {--set : Pasang webhook}
                            {--remove : Lepas webhook}
                            {--url= : URL webhook kustom (default: APP_URL/api/telegram/webhook)}';

    protected $description = 'Set/remove/show Telegram webhook for this bot';

    public function handle(TelegramService $telegram): int
    {
        if ($this->option('set')) {
            return $this->set($telegram);
        }

        if ($this->option('remove')) {
            return $this->remove($telegram);
        }

        return $this->showStatus($telegram);
    }

    protected function set(TelegramService $telegram): int
    {
        $url = $this->option('url') ?: rtrim((string) config('app.url'), '/').'/api/telegram/webhook';

        if (! str_starts_with($url, 'https://')) {
            $this->warn('⚠️  Telegram mensyaratkan webhook ber-HTTPS. URL: '.$url);
        }

        $secret = (string) config('telegram.webhook.secret');
        if ($secret === '') {
            $this->error('TELEGRAM_WEBHOOK_SECRET belum di-set di .env — route webhook menolak tanpa secret.');

            return Command::FAILURE;
        }

        $result = $telegram->setWebhook($url, $secret);

        if (($result['ok'] ?? false) === true) {
            $this->info("✅ Webhook terpasang: {$url}");

            return Command::SUCCESS;
        }

        $this->error('❌ Gagal pasang webhook: '.($result['description'] ?? 'unknown error'));

        return Command::FAILURE;
    }

    protected function remove(TelegramService $telegram): int
    {
        $result = $telegram->deleteWebhook();

        if (($result['ok'] ?? false) === true) {
            $this->info('✅ Webhook dilepas. Bot kembali ke mode polling (telegram:poll).');

            return Command::SUCCESS;
        }

        $this->error('❌ Gagal lepas webhook: '.($result['description'] ?? 'unknown error'));

        return Command::FAILURE;
    }

    protected function showStatus(TelegramService $telegram): int
    {
        $result = $telegram->getWebhookInfo();

        if (($result['ok'] ?? false) !== true) {
            $this->error('❌ Gagal ambil info webhook: '.($result['description'] ?? 'unknown error'));

            return Command::FAILURE;
        }

        $info = $result['result'] ?? [];
        $this->line('URL          : '.($info['url'] ?: '(belum diset — mode polling)'));
        $this->line('Pending      : '.($info['pending_update_count'] ?? 0));
        $this->line('Last error   : '.($info['last_error_message'] ?? '-'));
        $this->newLine();
        $this->line('Ubah: telegram:webhook --set | --remove');

        return Command::SUCCESS;
    }
}
