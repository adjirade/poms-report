<?php

use App\Http\Controllers\HQSyncController;
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
