<?php

/**
 * =============================================================================
 * POMS Report — HQ Cloud Server (simulasi lokal, PRD 2.2)
 * =============================================================================
 * Front controller berdiri sendiri untuk menjalankan "Cloud HQ Server" di
 * mesin lokal memakai PHP built-in web server dengan DATABASE TERPISAH dari
 * aplikasi pabrik:
 *
 *     php -S 127.0.0.1:8199 deploy/hq-server.php
 *
 * Database HQ: storage/sim/hq.sqlite (otomatis dimigrasikan saat pertama kali).
 * Token API dibaca dari HQ_API_TOKEN (di-set oleh skrip simulasi, atau .env).
 * =============================================================================
 */

use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Http\Request;

// --- Environment HQ (real env MENANG atas .env, jadi ini efektif) -----------
$hqDb = __DIR__.'/../storage/sim/hq.sqlite';

putenv('APP_ENV=testing');
// APP_KEY simulasi: dari env bila di-set, selain itu digenerate acak per run
// (jangan pernah hardcode key di repo).
putenv('APP_KEY='.($_ENV['APP_KEY'] ?? 'base64:'.base64_encode(random_bytes(32))));
putenv('DB_CONNECTION=sqlite');
putenv('DB_DATABASE='.$hqDb);
putenv('DB_HOST=127.0.0.1');
putenv('DB_PORT=5432');
putenv('DB_DATABASE='.$hqDb);
putenv('CACHE_STORE=array');
putenv('CACHE_DRIVER=array');
putenv('SESSION_DRIVER=array');
putenv('QUEUE_CONNECTION=sync');
putenv('HQ_SYNC_ENABLED=false'); // sisi HQ tidak perlu push ke mana-mana

// Token: prioritas environment yang di-set skrip simulasi, fallback .env.
if (getenv('HQ_API_TOKEN') === false || getenv('HQ_API_TOKEN') === '') {
    putenv('HQ_API_TOKEN=e2e-shared-token');
}

require __DIR__.'/../vendor/autoload.php';

// --- Boot aplikasi & migrasi sekali saat database HQ belum ada --------------
$app = require __DIR__.'/../bootstrap/app.php';
$consoleKernel = $app->make(ConsoleKernel::class);
$consoleKernel->bootstrap();

$firstBoot = ! file_exists($hqDb) || filesize($hqDb) === 0;

if ($firstBoot) {
    @mkdir(dirname($hqDb), 0777, true);
    touch($hqDb);
    $consoleKernel->call('migrate', ['--force' => true]);
    error_log('[hq-sim] HQ database migrated at '.$hqDb);
}

// --- Layani request HTTP seperti public/index.php resmi Laravel 11 ----------
$app->handleRequest(Request::capture());
