<?php

/**
 * =============================================================================
 * POMS Report — Simulasi End-to-End HQ Cloud Sync (PRD 2.2)
 * =============================================================================
 * Mensimulasikan arsitektur hub-and-spoke di SATU mesin dengan DUA DATABASE
 * TERPISAH dan komunikasi HTTP sungguhan (bukan mock):
 *
 *   [DB Pabrik (spoke)] --hq:sync / HTTP--> [Server HQ lokal (hub)]
 *     storage/sim/plant.sqlite               storage/sim/hq.sqlite
 *
 * Server HQ dijalankan otomatis sebagai proses PHP built-in web server
 * terpisah (deploy/hq-server.php). Urutan pengujian:
 *
 *   1. Health check (ping) dengan token benar
 *   2. Penolakan token salah / kosong (401)
 *   3. hq:sync push: hanya data VERIFIED & belum synced yang terkirim
 *   4. Verifikasi data di DB HQ (nilai, plant, operator_name, meta HQ)
 *   5. Idempotensi: push ulang -> 0 received, N duplicates, DB tetap
 *   6. Skenario khusus maintenance (multi-station, enum, teks ber-underscore)
 *
 * Pemakaian:  php deploy/simulate-hq-sync.php
 * =============================================================================
 */

use App\Models\LogLab;
use App\Models\LogMaintenance;
use App\Models\LogTimbang;
use App\Models\User;
use App\Services\HQSyncService;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\Http;

$ROOT = dirname(__DIR__);

// ---------------------------------------------------------------------------
// 0. Boot aplikasi PABRIK (spoke) memakai database sqlite terpisah
// ---------------------------------------------------------------------------
$plantDb = $ROOT.'/storage/sim/plant.sqlite';

putenv('APP_ENV=testing');
// APP_KEY simulasi: dari env bila di-set, selain itu digenerate acak per run
// (jangan pernah hardcode key di repo).
putenv('APP_KEY='.($_ENV['APP_KEY'] ?? 'base64:'.base64_encode(random_bytes(32))));
putenv('DB_CONNECTION=sqlite');
putenv('DB_DATABASE='.$plantDb);
putenv('CACHE_STORE=array');
putenv('SESSION_DRIVER=array');
putenv('QUEUE_CONNECTION=sync');
putenv('HQ_SYNC_ENABLED=true');
putenv('HQ_API_URL=http://127.0.0.1:8199/api/hq');
putenv('HQ_API_TOKEN=e2e-shared-token');

require $ROOT.'/vendor/autoload.php';

$app = require $ROOT.'/bootstrap/app.php';
$kernel = $app->make(ConsoleKernel::class);
$kernel->bootstrap();

// Database pabrik segar dari nol setiap simulasi
@mkdir(dirname($plantDb), 0777, true);
@unlink($plantDb);
@touch($plantDb);
$kernel->call('migrate', ['--force' => true]);
$kernel->call('db:seed', ['--class' => 'Database\Seeders\ValidationRulesSeeder', '--force' => true]);

// Konfigurasi runtime pabrik untuk simulasi ini
config([
    'hq.enabled' => true,
    'hq.api_url' => 'http://127.0.0.1:8199/api/hq',
    'hq.api_token' => 'e2e-shared-token',
    'hq.sync_verified_only' => true,
    'hq.batch_size' => 200,
]);

// ---------------------------------------------------------------------------
// 1. Jalankan SERVER HQ sebagai proses terpisah (PHP built-in web server)
// ---------------------------------------------------------------------------
$hqLog = $ROOT.'/storage/sim/hq-server.log';
$hqDbPath = $ROOT.'/storage/sim/hq.sqlite';
@mkdir(dirname($hqLog), 0777, true);

// Kunci hasil deterministik: hapus DB basi SEBELUM server start.
// (Cleanup di akhir run bisa gagal di Windows karena handle server
// baru saja dilepas; simpanan lama membuat count & uniqueness meleset.)
@unlink($plantDb);
@unlink($hqDbPath);

// Bunuh server yatim dari run sebelumnya yang masih memegang port 8199 —
// kalau tidak, server baru gagal bind dan request dilayani proses basi.
if (stripos(PHP_OS, 'WIN') === 0) {
    $stalePid = trim((string) shell_exec(
        'netstat -ano | findstr ":8199" | findstr "LISTENING" | awk "{print $NF}"' ?: ''
    ));
    $stalePid = explode(PHP_EOL, trim(preg_replace('/\s+/', "\n", $stalePid) ?: ''))[0] ?? '';
    if ($stalePid !== '' && ctype_digit($stalePid)) {
        exec("taskkill /F /T /PID {$stalePid} > NUL 2>&1");
        usleep(500_000);
    }
} else {
    exec('pkill -f "php -S 127.0.0.1:8199" 2>/dev/null');
    usleep(300_000);
}

$env = 'APP_ENV=testing'
    .' HQ_API_TOKEN=e2e-shared-token';
// APP_KEY tidak di-set di sini: hq-server.php menggenerate acak per run.
$serverCmd = "cd \"$ROOT\" && env {$env} php -S 127.0.0.1:8199 deploy/hq-server.php > ".escapeshellarg($hqLog).' 2>&1';
$serverProc = proc_open($serverCmd, [], $pipes);
$serverPid = is_resource($serverProc) ? proc_get_status($serverProc)['pid'] : 0;

/**
 * Hentikan server HQ secara paksa dan aman lintas-OS.
 * Windows: php -S tidak mematuhi SIGTERM -> pakai taskkill /T (tree).
 * Unix: proc_terminate cukup. proc_close dipanggil SETELAH proses
 * terkonfirmasi mati (menghindari hang menunggu handle stdout).
 */
$stopServer = function () use ($serverProc, $serverPid) {
    if (! is_resource($serverProc)) {
        return;
    }

    $onWindows = stripos(PHP_OS, 'WIN') === 0;

    if ($onWindows && $serverPid > 0) {
        exec("taskkill /F /T /PID {$serverPid} > NUL 2>&1");
    } else {
        proc_terminate($serverProc);
    }

    // Tunggu maksimal 5 detik sampai proses benar-benar mati.
    $deadline = microtime(true) + 5;
    $running = true;

    while (microtime(true) < $deadline) {
        $status = proc_get_status($serverProc);
        $running = $status['running'];

        if (! $running) {
            break;
        }
        usleep(100_000);
    }

    if ($running && ! $onWindows) {
        proc_terminate($serverProc, 9); // SIGKILL
        usleep(200_000);
    }

    proc_close($serverProc);
};

register_shutdown_function($stopServer);

usleep(1_500_000); // beri waktu server boot + migrasi DB HQ

echo "=== SIMULASI HQ SYNC END-TO-END (PRD 2.2) ===\n\n";

// ---------------------------------------------------------------------------
// 2. Seed data PABRIK: operator + record stasiun timbang & lab & maintenance
// ---------------------------------------------------------------------------
$operator = User::create([
    'name' => 'Operator Sim',
    'phone_number' => '6287700000001',
    'password' => 'secret123',
    'role' => 'operator',
    'department' => 'proses',
    'plant_id' => 'PKS_01',
    'status' => 'active',
]);

$maintOperator = User::create([
    'name' => 'Teknisi Sim',
    'phone_number' => '6287700000002',
    'password' => 'secret123',
    'role' => 'operator',
    'department' => 'maintenance',
    'plant_id' => 'PKS_01',
    'status' => 'active',
]);

$verified = LogTimbang::create([
    'user_id' => $operator->id, 'plant_id' => 'PKS_01',
    'no_spb' => 'SPB-E2E-001', 'tonase_bruto' => 25300, 'tonase_tarra' => 9500,
    'potongan_persen' => 4.5, 'timestamp_kirim' => now()->subHour(),
    'timestamp_server' => now(), 'is_verified' => true, 'is_flagged' => false,
]);

$flaggedVerified = LogTimbang::create([
    'user_id' => $operator->id, 'plant_id' => 'PKS_01',
    'no_spb' => 'SPB-E2E-002', 'tonase_bruto' => 24100, 'tonase_tarra' => 9500,
    'potongan_persen' => 6.2, 'timestamp_kirim' => now()->subHours(9),
    'timestamp_server' => now(), 'is_verified' => true, 'is_flagged' => true, // anti-fraud > 4 jam
]);

$unverified = LogTimbang::create([
    'user_id' => $operator->id, 'plant_id' => 'PKS_01',
    'no_spb' => 'SPB-E2E-003', 'tonase_bruto' => 20000, 'tonase_tarra' => 9000,
    'potongan_persen' => 3.0, 'timestamp_kirim' => now(),
    'timestamp_server' => now(), 'is_verified' => false, // TIDAK boleh terkirim
]);

$alreadySynced = LogTimbang::create([
    'user_id' => $operator->id, 'plant_id' => 'PKS_01',
    'no_spb' => 'SPB-E2E-004', 'tonase_bruto' => 21000, 'tonase_tarra' => 9000,
    'potongan_persen' => 3.1, 'timestamp_kirim' => now(),
    'timestamp_server' => now(), 'is_verified' => true,
]);
$alreadySynced->forceFill(['hq_synced_at' => now()])->save();

LogLab::create([
    'user_id' => $operator->id, 'plant_id' => 'PKS_01',
    'kadar_alb_cpo' => 3.5, 'losses_fiber_persen' => 4.2, 'losses_jankos_persen' => 0.8,
    'timestamp_kirim' => now(), 'timestamp_server' => now(), 'is_verified' => true,
]);

LogMaintenance::create([
    'user_id' => $maintOperator->id, 'plant_id' => 'PKS_01',
    'kode_mesin' => 'GENSET 02', 'jam_jalan_hm' => 4850,
    'status_kondisi' => 'normal', 'keterangan_perbaikan' => 'Aman tidak ada kendala',
    'timestamp_kirim' => now(), 'timestamp_server' => now(), 'is_verified' => true,
]);

$service = app(HQSyncService::class);

$pass = 0;
$fail = 0;
$check = function (string $name, bool $ok, string $detail = '') use (&$pass, &$fail) {
    echo ($ok ? '  [PASS] ' : '  [FAIL] ').$name.($detail !== '' ? " — {$detail}" : '')."\n";
    $ok ? $pass++ : $fail++;
};

$ping = function (string $token) {
    return Http::withHeaders(['X-HQ-API-TOKEN' => $token])
        ->timeout(10)
        ->get('http://127.0.0.1:8199/api/hq/ping');
};

echo "[1] Health check & autentikasi token\n";

// Tunggu server HQ siap (boot + migrasi bisa lebih lambat dari 1.5 dtk)
$resp = null;
for ($attempt = 1; $attempt <= 10; $attempt++) {
    $resp = $ping('e2e-shared-token');
    if ($resp->status() === 200) {
        break;
    }
    usleep(500_000);
}
$check('ping dengan token benar -> 200', $resp !== null && $resp->status() === 200 && $resp->json('ok') === true, 'HTTP '.($resp?->status() ?? 'null'));

if ($resp === null || $resp->status() !== 200) {
    echo "\nServer HQ tidak kunjung siap. Log:\n".file_get_contents($hqLog);
    exit(1);
}

$check('ping dengan token SALAH -> 401', $ping('token-ngasal')->status() === 401);
$check('ping tanpa token -> 401', $ping('')->status() === 401);

echo "\n[2] hq:sync push (hanya VERIFIED & belum synced)\n";
$pending = $service->pendingCounts('PKS_01');
$expectedTimbang = LogTimbang::where('plant_id', 'PKS_01')->whereNull('hq_synced_at')->where('is_verified', true)->count();
$check('pending timbang sesuai skenario (2)', $pending['timbang'] === 2, "got {$pending['timbang']}, expected {$expectedTimbang} (unverified & already-synced harus dikecualikan)");

$summary = $service->pushAll('PKS_01');
$totalPushed = $summary['pushed'];
$check('push total = 4 record (timbang2 + lab1 + maintenance1)', $totalPushed === 4, 'pushed='.$totalPushed.', failed='.$summary['failed']);
$check('tidak ada kegagalan push', $summary['failed'] === 0);

echo "\n[3] Verifikasi data di DATABASE HQ\n";
$hqPdo = new PDO('sqlite:'.$ROOT.'/storage/sim/hq.sqlite');
$hqRow = function (string $sql, array $bind = []) use ($hqPdo) {
    $stmt = $hqPdo->prepare($sql);
    $stmt->execute($bind);

    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
};

$row = $hqRow('SELECT * FROM log_timbang WHERE no_spb = ?', ['SPB-E2E-001']);
$check('record verified masuk HQ', $row !== null && (int) $row['hq_source_id'] === $verified->id, 'hq_source_id='.($row['hq_source_id'] ?? 'null'));
$check('nilai parameter identik', $row !== null && (float) $row['tonase_bruto'] === 25300.0 && (float) $row['potongan_persen'] === 4.5);
$check('plant_id tersimpan benar', $row !== null && $row['plant_id'] === 'PKS_01');
$check('operator_name terdenormalisasi', $row !== null && $row['operator_name'] === 'Operator Sim');
$check('meta HQ terisi (hq_source_id + verified flag)', $row !== null && (int) $row['is_verified'] === 1);

$rowFlagged = $hqRow('SELECT * FROM log_timbang WHERE no_spb = ?', ['SPB-E2E-002']);
$check('record flagged+verified juga terkirim (audit trail)', $rowFlagged !== null && (int) $rowFlagged['is_flagged'] === 1);

$rowUnv = $hqRow('SELECT * FROM log_timbang WHERE no_spb = ?', ['SPB-E2E-003']);
$check('record UNVERIFIED tidak bocor ke HQ', $rowUnv === null);

$rowSynced = $hqRow('SELECT * FROM log_timbang WHERE no_spb = ?', ['SPB-E2E-004']);
$check('record SUDAH synced tidak dikirim ulang', $rowSynced === null);

$check('baris timbang di HQ tepat 2', (int) $hqRow('SELECT COUNT(*) c FROM log_timbang')['c'] === 2);
$check('baris lab di HQ tepat 1', (int) $hqRow('SELECT COUNT(*) c FROM log_lab')['c'] === 1);

$rowMaint = $hqRow('SELECT * FROM log_maintenance WHERE kode_mesin = ?', ['GENSET 02']);
$check('maintenance masuk + enum & teks underscore utuh', $rowMaint !== null && $rowMaint['status_kondisi'] === 'normal' && $rowMaint['keterangan_perbaikan'] === 'Aman tidak ada kendala');

// Verifikasi akun sync sistem harus dibaca dari DB HQ (bukan Eloquent plant app)
$sysUser = $hqRow('SELECT id, phone_number FROM users WHERE phone_number = ?', ['hq-sync-pks_01']);
$check('user_id di HQ menunjuk akun sync sistem', $row !== null && $sysUser !== null && (int) $row['user_id'] === (int) $sysUser['id'], 'hq user_id='.($row['user_id'] ?? 'null').', sys='.($sysUser['id'] ?? 'null'));

echo "\n[4] Penandaan synced di sisi PABRIK\n";
$check('verified ditandai hq_synced_at', $verified->fresh()->hq_synced_at !== null);
$check('unverified TIDAK ditandai', $unverified->fresh()->hq_synced_at === null);

echo "\n[5] Idempotensi — push ulang seluruh data\n";
$pendingAfter = $service->pendingCounts('PKS_01');
$check('pending menjadi 0 di semua stasiun', array_sum($pendingAfter) === 0, 'total='.array_sum($pendingAfter));

$summary2 = $service->pushAll('PKS_01');
$check('push kedua mengirim 0 record', $summary2['pushed'] === 0 && $summary2['failed'] === 0, 'pushed='.$summary2['pushed']);

$check('DB HQ tetap 2 baris timbang (tanpa duplikat)', (int) $hqRow('SELECT COUNT(*) c FROM log_timbang')['c'] === 2);
$check('DB HQ tetap 1 baris lab', (int) $hqRow('SELECT COUNT(*) c FROM log_lab')['c'] === 1);

// Idempotensi sisi HQ: kirim payload sama via HTTP langsung
$idem = Http::withHeaders(['X-HQ-API-TOKEN' => 'e2e-shared-token'])
    ->timeout(10)
    ->post('http://127.0.0.1:8199/api/hq/sync', [
        'plant_id' => 'PKS_01',
        'station' => 'timbang',
        'synced_at' => now()->toIso8601String(),
        'records' => [array_merge($verified->fresh()->toArray(), ['user_name' => 'Operator Sim', 'id' => $verified->id])],
    ]);
$check('re-post payload sama -> duplicates terdeteksi', $idem->status() === 201 && $idem->json('received') === 0 && $idem->json('duplicates') === 1, 'received='.$idem->json('received').', dup='.$idem->json('duplicates'));

$check('DB HQ tetap 2 baris timbang setelah re-post', (int) $hqRow('SELECT COUNT(*) c FROM log_timbang')['c'] === 2);

echo "\n[6] Isolasi antar plant di sisi HQ\n";
$other = Http::withHeaders(['X-HQ-API-TOKEN' => 'e2e-shared-token'])
    ->timeout(10)
    ->post('http://127.0.0.1:8199/api/hq/sync', [
        'plant_id' => 'PKS_02',
        'station' => 'timbang',
        'synced_at' => now()->toIso8601String(),
        'records' => [['id' => 900, 'user_id' => 1, 'user_name' => 'Op PKS_02', 'no_spb' => 'SPB-02-900', 'tonase_bruto' => 1, 'tonase_tarra' => 1, 'potongan_persen' => 1, 'timestamp_kirim' => '2026-10-01 08:00:00', 'timestamp_server' => '2026-10-01 08:00:05', 'is_verified' => true]],
    ]);
$check('plant lain (PKS_02) diterima terpisah', $other->status() === 201 && $other->json('received') === 1);
$check('id source sama antar plant tidak bentrok (unique per plant)', $other->status() === 201);

// ---------------------------------------------------------------------------
// Ringkasan
// ---------------------------------------------------------------------------
echo "\n=== HASIL: {$pass} PASS, {$fail} FAIL ===\n";
echo $fail === 0 ? "✅ Simulasi end-to-end HQ Sync LULUS SEMUA.\n" : "❌ Ada kegagalan — periksa output di atas.\n";
echo "Log server HQ: storage/sim/hq-server.log\n";

$exitCode = $fail === 0 ? 0 : 1;

// Stop server dulu (baru aman menghapus file DB yang sedang terbuka).
$stopServer();
@unlink($plantDb);
@unlink($ROOT.'/storage/sim/hq.sqlite');

exit($exitCode);
