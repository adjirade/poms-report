<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Telegram Bot Token
    |--------------------------------------------------------------------------
    |
    | Your Telegram Bot API token obtained from @BotFather
    |
    */

    'bot_token' => env('TELEGRAM_BOT_TOKEN', ''),

    /*
    |--------------------------------------------------------------------------
    | Bot Username
    |--------------------------------------------------------------------------
    |
    | Your Telegram Bot username (without @)
    |
    */

    'bot_username' => env('TELEGRAM_BOT_USERNAME', ''),

    /*
    |--------------------------------------------------------------------------
    | Long Polling Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for the telegram:poll command
    |
    */

    'polling' => [
        'timeout' => env('TELEGRAM_POLLING_TIMEOUT', 30), // Long polling timeout in seconds
        'sleep_between_polls' => env('TELEGRAM_POLLING_SLEEP', 3), // Sleep duration between polls
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue Configuration
    |--------------------------------------------------------------------------
    |
    | Queue connection and name for processing messages
    |
    */

    'queue' => [
        'connection' => env('TELEGRAM_QUEUE_CONNECTION', 'redis'),
        'name' => env('TELEGRAM_QUEUE_NAME', 'telegram'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Allowed Update Types
    |--------------------------------------------------------------------------
    |
    | Only fetch specific types of updates from Telegram
    |
    */

    'allowed_updates' => [
        'message',
    ],

    /*
    |--------------------------------------------------------------------------
    | Webhook (mode produksi — alternatif dari telegram:poll)
    |--------------------------------------------------------------------------
    |
    | TELEGRAM_WEBHOOK_URL  : URL publik HTTPS, mis. https://poms.example.com/api/telegram/webhook.
    | TELEGRAM_WEBHOOK_SECRET: secret acak (>= 1-32 char) yang dikirim Telegram pada
    |                          header X-Telegram-Bot-Api-Secret-Token; divalidasi route.
    |
    | Kelola via: php artisan telegram:webhook --set | --remove
    |
    */

    'webhook' => [
        'url' => env('TELEGRAM_WEBHOOK_URL'),
        'secret' => env('TELEGRAM_WEBHOOK_SECRET'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Notifications (event -> Telegram)
    |--------------------------------------------------------------------------
    |
    | Saklar global untuk notifikasi event (flagged/verifikasi). Per-user
    | opt-out tersimpan di kolom users.telegram_notif_*.
    |
    */

    'notifications' => [
        'enabled' => env('TELEGRAM_NOTIF_ENABLED', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Rekap Otomatis (telegram:daily-recap)
    |--------------------------------------------------------------------------
    |
    | Saklar global + daftar chat_id tambahan (dipisah koma) untuk grup/arsip.
    | Penerima personal: user dengan role >= asisten, terhubung bot, dan
    | telegram_notif_enabled = true.
    |
    */

    'recap' => [
        'enabled' => env('TELEGRAM_RECAP_ENABLED', true),
        'chat_ids' => env('TELEGRAM_RECAP_CHAT_IDS', ''),

        // Saklar khusus rekap mingguan (telegram:weekly-recap).
        'weekly_enabled' => env('TELEGRAM_WEEKLY_RECAP_ENABLED', true),
    ],

];
