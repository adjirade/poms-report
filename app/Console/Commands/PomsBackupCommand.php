<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Backup harian POMS: pg_dump database + salin PDF rekap tersimpan.
 * Hasil di storage/backups/, retention 14 hari.
 *
 * Jadwal: routes/console.php -> dailyAt('01:00') via layanan poms-schedule.
 */
class PomsBackupCommand extends Command
{
    protected $signature = 'poms:backup {--keep=14 : Jumlah hari retensi backup}';

    protected $description = 'Backup database (pg_dump) + arsip rekap ke storage/backups dengan retensi otomatis';

    public function handle(): int
    {
        $dir = storage_path('backups');

        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $stamp = Carbon::now()->format('Ymd_His');
        $host = (string) config('database.connections.pgsql.host', '127.0.0.1');
        $port = (string) config('database.connections.pgsql.port', '5432');
        $db = (string) config('database.connections.pgsql.database');
        $user = (string) config('database.connections.pgsql.username');
        $pass = (string) config('database.connections.pgsql.password');

        if ($db === '') {
            $this->error('Koneksi pgsql tidak ditemukan (config database.connections.pgsql kosong).');

            return self::FAILURE;
        }

        $dumpFile = $dir."/db-{$db}-{$stamp}.sql";

        // PGPASSWORD via environment proses (putenv) — tidak lewat command line.
        putenv('PGPASSWORD='.$pass);

        // pg_dump harus ada di PATH; fallback ke lokasi standar Windows.
        $pgDump = 'pg_dump';
        if (stripos(PHP_OS_FAMILY, 'Windows') !== false
            && shell_exec('where pg_dump 2>nul') === null) {
            foreach (glob('C:/Program Files/PostgreSQL/*/bin/pg_dump.exe') ?: [] as $candidate) {
                $pgDump = $candidate;
                break;
            }
        }

        $cmd = escapeshellarg($pgDump)
            .' --host='.escapeshellarg($host)
            .' --port='.escapeshellarg($port)
            .' --username='.escapeshellarg($user)
            .' --no-owner --format=plain '.escapeshellarg($db)
            .' > '.escapeshellarg($dumpFile).' 2>&1';

        $exit = 1;
        $descriptors = [];
        $proc = proc_open($cmd, $descriptors, $pipes);
        if (is_resource($proc)) {
            $exit = proc_close($proc);
        }
        putenv('PGPASSWORD');

        if ($exit !== 0 || ! is_file($dumpFile) || filesize($dumpFile) === 0) {
            $this->error("pg_dump gagal (exit={$exit}). Pastikan pg_dump ada di PATH.");

            // Hapus file dump kosong/parcial.
            if (is_file($dumpFile) && filesize($dumpFile) < 100) {
                @unlink($dumpFile);
            }

            return self::FAILURE;
        }

        $sizeKb = (int) round(filesize($dumpFile) / 1024);
        $this->info("✓ DB dump: {$dumpFile} ({$sizeKb} KB)");

        // Arsip PDF rekap (storage/app/recaps) bila ada.
        $recaps = storage_path('app/recaps');
        if (is_dir($recaps)) {
            $zip = $dir."/recaps-{$stamp}.zip";
            $tmp = $dir."/recaps-{$stamp}";
            if (class_exists(\ZipArchive::class)) {
                @mkdir($tmp, 0775, true);
                foreach (glob($recaps.'/*.pdf') ?: [] as $pdf) {
                    @copy($pdf, $tmp.'/'.basename($pdf));
                }
                $za = new \ZipArchive;
                if ($za->open($zip, \ZipArchive::CREATE) === true) {
                    foreach (glob($tmp.'/*.pdf') ?: [] as $f) {
                        $za->addFile($f, basename($f));
                    }
                    $za->close();
                }
                foreach (glob($tmp.'/*.pdf') ?: [] as $f) {
                    @unlink($f);
                }
                @rmdir($tmp);
                if (is_file($zip)) {
                    $this->info('✓ Arsip rekap: '.$zip);
                }
            } else {
                $this->warn('Ekstensi zip tidak tersedia — arsip rekap dilewati.');
            }
        }

        // Retensi: hapus backup lebih tua dari --keep hari.
        $keep = max(1, (int) $this->option('keep'));
        $cutoff = Carbon::now()->subDays($keep)->getTimestamp();
        $removed = 0;
        foreach (glob($dir.'/*') ?: [] as $file) {
            if (is_file($file) && filemtime($file) < $cutoff) {
                @unlink($file);
                $removed++;
            }
        }
        $this->info("✓ Retensi {$keep} hari: {$removed} file lama dihapus");

        return self::SUCCESS;
    }
}
