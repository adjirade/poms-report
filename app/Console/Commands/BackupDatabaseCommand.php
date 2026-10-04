<?php

namespace App\Console\Commands;

use App\Support\AlertNotifier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;

class BackupDatabaseCommand extends Command
{
    protected $signature = 'db:backup
                            {--keep=7 : Jumlah file backup yang dipertahankan}
                            {--to= : Direktori tujuan (default: storage/app/backups)}';

    protected $description = 'Backup database + rotasi otomatis (sqlite/pgsql/mysql)';

    public function handle(): int
    {
        $keep = max(1, (int) $this->option('keep'));
        $dir = (string) ($this->option('to') ?: storage_path('app/backups'));
        $driver = (string) config('database.default');

        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        try {
            $result = match ($driver) {
                'sqlite' => $this->backupSqlite($dir),
                'pgsql' => $this->backupPgsql($dir),
                'mysql' => $this->backupMysql($dir),
                default => null,
            };
        } catch (\Throwable $e) {
            AlertNotifier::send('Backup database GAGAL', $e->getMessage());

            return self::FAILURE;
        }

        if ($result === null) {
            $this->error("Driver database '{$driver}' belum didukung command backup.");

            return self::FAILURE;
        }

        [$path, $size] = $result;
        $this->info("✓ Backup OK: {$path} (".number_format($size / 1024, 1).' KB)');

        $removed = $this->rotate($dir, $keep);
        if ($removed > 0) {
            $this->line("  Rotasi: {$removed} backup lama dihapus (keep={$keep}).");
        }

        return self::SUCCESS;
    }

    /**
     * Backup sqlite via VACUUM INTO (snapshot konsisten, cepat).
     *
     * @return array{0: string, 1: int}
     */
    private function backupSqlite(string $dir): array
    {
        $database = (string) config('database.connections.sqlite.database');
        $target = $dir.'/poms-'.now()->format('Ymd_His').'.sqlite';

        DB::statement("VACUUM INTO '{$target}'");

        $size = is_file($target) ? (int) filesize($target) : 0;
        if ($size === 0) {
            throw new \RuntimeException('File backup sqlite kosong.');
        }

        return [$target, $size];
    }

    /**
     * Backup PostgreSQL via pg_dump (butuh binary pg_dump di server).
     *
     * @return array{0: string, 1: int}
     */
    private function backupPgsql(string $dir): array
    {
        $connection = (array) config('database.connections.pgsql');
        $target = $dir.'/poms-'.now()->format('Ymd_His').'.sql';

        $bin = (string) config('database.backup_pg_dump_path', '/usr/bin/pg_dump');

        $proc = Process::timeout(600)
            ->env(['PGPASSWORD' => (string) ($connection['password'] ?? '')])
            ->run([
                $bin,
                '-h', (string) ($connection['host'] ?? '127.0.0.1'),
                '-p', (string) ($connection['port'] ?? '5432'),
                '-U', (string) ($connection['username'] ?? ''),
                '-d', (string) ($connection['database'] ?? ''),
                '-f', $target,
            ]);

        if (! $proc->successful()) {
            throw new \RuntimeException('pg_dump gagal: '.$proc->errorOutput());
        }

        $size = is_file($target) ? (int) filesize($target) : 0;
        if ($size === 0) {
            throw new \RuntimeException('File backup pgsql kosong.');
        }

        return [$target, $size];
    }

    /**
     * Backup MySQL via mysqldump (butuh binary mysqldump di server).
     *
     * @return array{0: string, 1: int}
     */
    private function backupMysql(string $dir): array
    {
        $connection = (array) config('database.connections.mysql');
        $target = $dir.'/poms-'.now()->format('Ymd_His').'.sql';

        $bin = (string) config('database.backup_mysqldump_path', '/usr/bin/mysqldump');

        $proc = Process::timeout(600)
            ->env(['MYSQL_PWD' => (string) ($connection['password'] ?? '')])
            ->run([
                $bin,
                '-h', (string) ($connection['host'] ?? '127.0.0.1'),
                '-P', (string) ($connection['port'] ?? '3306'),
                '-u', (string) ($connection['username'] ?? ''),
                '--result-file='.$target,
                (string) ($connection['database'] ?? ''),
            ]);

        if (! $proc->successful()) {
            throw new \RuntimeException('mysqldump gagal: '.$proc->errorOutput());
        }

        $size = is_file($target) ? (int) filesize($target) : 0;
        if ($size === 0) {
            throw new \RuntimeException('File backup mysql kosong.');
        }

        return [$target, $size];
    }

    /** Hapus backup lama melewati batas $keep. */
    private function rotate(string $dir, int $keep): int
    {
        $files = array_merge(
            glob($dir.'/poms-*.sqlite') ?: [],
            glob($dir.'/poms-*.sql') ?: [],
        );

        if (count($files) <= $keep) {
            return 0;
        }

        sort($files); // nama mengandung timestamp → urut = kronologis
        $toDelete = array_slice($files, 0, count($files) - $keep);

        foreach ($toDelete as $file) {
            @unlink($file);
        }

        return count($toDelete);
    }
}
