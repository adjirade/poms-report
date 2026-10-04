<?php

namespace App\Jobs;

use App\Services\HQSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncPlantDataToHQ implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [120, 300, 900];

    public function __construct(
        protected string $plantId,
    ) {
        // Sinkronisasi HQ berjalan di queue terpisah agar tidak mengganggu
        // pemrosesan pesan Telegram.
        $this->onQueue(config('hq.queue', 'default'));
    }

    public function handle(HQSyncService $service): void
    {
        if (! config('hq.enabled')) {
            Log::info('HQ sync skipped (disabled).');

            return;
        }

        $summary = $service->pushAll($this->plantId);

        Log::info('HQ sync completed', [
            'plant_id' => $this->plantId,
            'pushed' => $summary['pushed'],
            'failed' => $summary['failed'],
        ]);
    }
}
