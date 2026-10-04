<?php

use App\Http\Controllers\HQSyncController;
use App\Jobs\ProcessTelegramMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — HQ Cloud Sync (PRD 2.2)
|--------------------------------------------------------------------------
|
| Endpoint penerimaan data dari server pabrik (spoke) ke Cloud HQ (hub).
| Semua endpoint dilindungi X-HQ-API-TOKEN (middleware hq.token).
|
*/

Route::middleware(['throttle:120,1', 'hq.token'])->prefix('hq')->name('hq.api.')->group(function () {
    Route::get('/ping', [HQSyncController::class, 'ping'])->name('ping');
    Route::post('/sync', [HQSyncController::class, 'receive'])->name('sync');
});

/*
|--------------------------------------------------------------------------
| Telegram Webhook (mode produksi — alternatif `telegram:poll`)
|--------------------------------------------------------------------------
|
| Telegram mengirim update ke sini (pasang via `php artisan telegram:webhook
| --set`). Autentikasi: header X-Telegram-Bot-Api-Secret-Token harus sama
| dengan TELEGRAM_WEBHOOK_SECRET. Telegram butuh ulang kirim cepat (2s),
| jadi hanya dispatch ke queue di sini — proses di job telegram.
|
*/

Route::post('/telegram/webhook', function (Request $request) {
    $secret = (string) config('telegram.webhook.secret');

    if ($secret === '' || ! hash_equals($secret, (string) $request->header('X-Telegram-Bot-Api-Secret-Token'))) {
        abort(403, 'Invalid webhook secret');
    }

    $message = $request->input('message');

    if (is_array($message) && isset($message['text'])) {
        ProcessTelegramMessage::dispatch($message);
    }

    // Selalu 200 agar Telegram tidak retry tanpa henti untuk payload aneh.
    return response()->json(['ok' => true]);
})->middleware('throttle:60,1')->name('telegram.webhook');
