<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramService
{
    protected string $botToken;

    protected string $apiUrl;

    public function __construct()
    {
        $this->botToken = config('telegram.bot_token');
        $this->apiUrl = "https://api.telegram.org/bot{$this->botToken}";
    }

    /**
     * Get updates using long polling
     *
     * @param  int|null  $offset  Last update_id + 1
     * @param  int  $timeout  Timeout in seconds for long polling
     */
    public function getUpdates(?int $offset = null, int $timeout = 30): ?array
    {
        try {
            $response = Http::timeout($timeout + 5)
                ->get("{$this->apiUrl}/getUpdates", [
                    'offset' => $offset,
                    'timeout' => $timeout,
                    'allowed_updates' => ['message'],
                ]);

            if ($response->successful()) {
                $data = $response->json();

                return $data['result'] ?? [];
            }

            Log::error('Telegram getUpdates failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('Telegram getUpdates exception', [
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Send message to user
     *
     * @param  array  $options  Additional options (parse_mode, reply_markup, etc.)
     */
    public function sendMessage(string $chatId, string $text, array $options = []): bool
    {
        try {
            $params = array_merge([
                'chat_id' => $chatId,
                'text' => $text,
            ], $options);

            $response = Http::post("{$this->apiUrl}/sendMessage", $params);

            if ($response->successful()) {
                return true;
            }

            Log::error('Telegram sendMessage failed', [
                'chat_id' => $chatId,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return false;
        } catch (\Exception $e) {
            Log::error('Telegram sendMessage exception', [
                'chat_id' => $chatId,
                'message' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Send formatted message (Markdown or HTML)
     *
     * @param  string  $parseMode  'Markdown' or 'HTML'
     */
    public function sendFormattedMessage(string $chatId, string $text, string $parseMode = 'Markdown'): bool
    {
        return $this->sendMessage($chatId, $text, ['parse_mode' => $parseMode]);
    }

    /**
     * Send error message to user
     */
    public function sendValidationError(string $chatId, array $errors): bool
    {
        $message = "❌ *Data Ditolak!*\n\n";
        $message .= "*Kesalahan Validasi:*\n";

        foreach ($errors as $index => $error) {
            $message .= ($index + 1).". {$error}\n";
        }

        $message .= "\n_Silakan perbaiki data dan kirim ulang._";

        return $this->sendFormattedMessage($chatId, $message, 'Markdown');
    }

    /**
     * Send success confirmation
     */
    public function sendSuccessConfirmation(string $chatId, string $stationName, array $data): bool
    {
        $message = "✅ *Data Berhasil Disimpan*\n\n";
        $message .= '*Stasiun:* '.ucfirst($stationName)."\n";
        $message .= '*Waktu Input:* '.now()->format('d/m/Y H:i:s')."\n\n";
        $message .= '_Data telah tersimpan dan menunggu verifikasi._';

        return $this->sendFormattedMessage($chatId, $message, 'Markdown');
    }

    /**
     * Send unauthorized access message
     */
    public function sendUnauthorizedMessage(string $chatId): bool
    {
        $message = "🚫 *Akses Ditolak*\n\n";
        $message .= "Nomor Anda belum teraktifkan atau tidak terdaftar dalam sistem.\n\n";
        $message .= 'Silakan hubungi administrator untuk aktivasi akun.';

        return $this->sendFormattedMessage($chatId, $message, 'Markdown');
    }

    /**
     * Extract user info from message
     */
    public function extractUserInfo(array $message): array
    {
        $from = $message['from'] ?? [];

        return [
            'telegram_user_id' => (string) ($from['id'] ?? ''),
            'first_name' => $from['first_name'] ?? '',
            'last_name' => $from['last_name'] ?? '',
            'username' => $from['username'] ?? null,
            'phone_number' => $from['phone_number'] ?? null,
        ];
    }

    /**
     * Extract message text and metadata
     */
    public function extractMessageData(array $message): array
    {
        return [
            'chat_id' => (string) ($message['chat']['id'] ?? ''),
            'message_id' => $message['message_id'] ?? null,
            'text' => $message['text'] ?? '',
            'date' => $message['date'] ?? null,
        ];
    }

    /**
     * Get bot information
     */
    public function getMe(): ?array
    {
        try {
            $response = Http::get("{$this->apiUrl}/getMe");

            if ($response->successful()) {
                $data = $response->json();

                return $data['result'] ?? null;
            }

            return null;
        } catch (\Exception $e) {
            Log::error('Telegram getMe exception', [
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Pasang webhook (mode produksi; alternatif dari long polling).
     */
    public function setWebhook(string $url, ?string $secretToken = null): array
    {
        $params = [
            'url' => $url,
            'allowed_updates' => ['message'],
            'drop_pending_updates' => false,
        ];

        if ($secretToken) {
            $params['secret_token'] = $secretToken;
        }

        return $this->callWebhookApi('setWebhook', $params);
    }

    /**
     * Lepas webhook (kembali ke mode polling).
     */
    public function deleteWebhook(): array
    {
        return $this->callWebhookApi('deleteWebhook', ['drop_pending_updates' => false]);
    }

    /**
     * Info webhook aktif (URL, pending updates, error terakhir).
     */
    public function getWebhookInfo(): array
    {
        return $this->callWebhookApi('getWebhookInfo', []);
    }

    /**
     * Panggil endpoint webhook API dan kembalikan ['ok' => bool, 'result'/'description' => ...].
     */
    protected function callWebhookApi(string $method, array $params): array
    {
        try {
            $response = Http::post("{$this->apiUrl}/{$method}", $params);
            $body = $response->json() ?? [];

            if (! $response->successful() || ($body['ok'] ?? false) !== true) {
                Log::error("Telegram {$method} failed", [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            }

            return $body;
        } catch (\Exception $e) {
            Log::error("Telegram {$method} exception", ['message' => $e->getMessage()]);

            return ['ok' => false, 'description' => $e->getMessage()];
        }
    }
}
