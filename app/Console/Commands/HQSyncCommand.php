<?php

namespace App\Console\Commands;

use App\Services\HQSyncService;
use Illuminate\Console\Command;

class HQSyncCommand extends Command
{
    protected $signature = 'hq:sync
                            {--station= : Push satu stasiun saja (timbang, sortasi, ...)}
                            {--dry-run : Hanya tampilkan jumlah record pending}';

    protected $description = 'Push data pabrik yang sudah terverifikasi ke Cloud HQ (PRD 2.2)';

    public function handle(HQSyncService $service): int
    {
        $plantId = (string) config('poms.plant_id', 'PKS_01');

        if (! config('hq.enabled')) {
            $this->warn('HQ sync tidak aktif (HQ_SYNC_ENABLED=false di .env).');

            return Command::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $counts = $service->pendingCounts($plantId);
            $this->info("Record pending sync untuk {$plantId}:");
            $total = 0;

            foreach ($counts as $station => $count) {
                $this->line(str_pad("  {$station}", 16).$count);
                $total += $count;
            }

            $this->info("Total: {$total}");

            return Command::SUCCESS;
        }

        $this->info("Memulai sinkronisasi ke HQ untuk {$plantId}...");

        if ($station = $this->option('station')) {
            $result = $service->pushStation((string) $station, $plantId);
            $this->info("{$station}: pushed={$result['pushed']}, failed={$result['failed']}");
        } else {
            $summary = $service->pushAll($plantId);

            foreach ($summary['stations'] as $station => $result) {
                $this->line(str_pad("  {$station}", 16)."pushed={$result['pushed']}, failed={$result['failed']}");
            }

            $this->info("Selesai. Total pushed={$summary['pushed']}, failed={$summary['failed']}.");
        }

        return Command::SUCCESS;
    }
}
