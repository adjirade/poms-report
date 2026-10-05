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

/*
|--------------------------------------------------------------------------
| Schedule — Rekap Harian Telegram (AGENDA/roadmap.md A2+B4)
|--------------------------------------------------------------------------
|
| Rekap kemarin (teks + PDF) dikirim pukul 17:30 WIB ke pengelola pabrik.
| Nonaktifkan lewat TELEGRAM_RECAP_ENABLED=false.
|
*/

Schedule::command('telegram:daily-recap')
    ->dailyAt('17:30')
    ->name('telegram-daily-recap')
    ->timezone('Asia/Jakarta')
    ->withoutOverlapping()
    ->onFailure(function () {
        Log::error('Scheduled Telegram daily recap failed.');
    });

/*
|--------------------------------------------------------------------------
| Schedule — Rekap Mingguan Telegram
|--------------------------------------------------------------------------
|
| Agregat Senin–Minggu minggu sebelumnya dikirim setiap Senin pukul 07:00
| WIB. Nonaktifkan lewat TELEGRAM_WEEKLY_RECAP_ENABLED=false (atau master
| saklar TELEGRAM_RECAP_ENABLED=false).
|
*/

Schedule::command('telegram:weekly-recap')
    ->weeklyOn(1, '07:00')
    ->name('telegram-weekly-recap')
    ->timezone('Asia/Jakarta')
    ->withoutOverlapping()
    ->onFailure(function () {
        Log::error('Scheduled Telegram weekly recap failed.');
    });

/*
|--------------------------------------------------------------------------
| Schedule — Backup Database Harian (poms:backup)
|--------------------------------------------------------------------------
|
| pg_dump database + arsip PDF rekap ke storage/backups, retensi 14 hari.
| Butuh pg_dump di PATH (PostgreSQL\18\bin).
|
*/

Schedule::command('poms:backup')
    ->dailyAt('01:00')
    ->name('poms-daily-backup')
    ->timezone('Asia/Jakarta')
    ->withoutOverlapping()
    ->onFailure(function () {
        Log::error('Scheduled POMS backup failed.');
    });
