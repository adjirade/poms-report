<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Schedule — HQ Cloud Sync (PRD 2.2)
|--------------------------------------------------------------------------
|
| Sinkronisasi berjalan setiap jam sesuai PRD ("Setiap Jam / Akhir Shift").
| Jalankan `php artisan schedule:work` (atau cron: * * * * * php artisan
| schedule:run) di supervisor untuk mengaktifkannya. Job aman dijalankan
| berulang karena idempoten (record yang sudah tersinkron dilewati).
|
*/

Schedule::command('hq:sync')
    ->hourly()
    ->name('hq-cloud-sync')
    ->withoutOverlapping()
    ->onFailure(function () {
        Log::error('Scheduled HQ sync failed.');
    });
