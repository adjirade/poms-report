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

];
