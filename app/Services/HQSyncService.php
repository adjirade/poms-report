<?php

namespace App\Services;

use App\Models\LogKernel;
use App\Models\LogKlarifikasi;
use App\Models\LogLab;
use App\Models\LogMaintenance;
use App\Models\LogPress;
use App\Models\LogSortasi;
use App\Models\LogSterilizer;
use App\Models\LogTimbang;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class HQSyncService
{
    /**
     * Station model map (whitelist).
     */
    public function stationModels(): array
    {
        return [
            'timbang' => LogTimbang::class,
            'sortasi' => LogSortasi::class,
            'sterilizer' => LogSterilizer::class,
            'press' => LogPress::class,
            'klarifikasi' => LogKlarifikasi::class,
            'kernel' => LogKernel::class,
            'lab' => LogLab::class,
            'maintenance' => LogMaintenance::class,
        ];
    }

    /**
     * Jumlah record pending sync per stasiun.
     */
    public function pendingCounts(string $plantId): array
    {
        $counts = [];

        foreach ($this->stationModels() as $station => $modelClass) {
            $counts[$station] = $this->pendingQuery($modelClass, $plantId)->count();
        }

        return $counts;
    }

    /**
     * Push seluruh data pending untuk plant tertentu.
     *
     * @return array summary hasil push
     */
    public function pushAll(string $plantId): array
    {
        $summary = [
            'pushed' => 0,
            'failed' => 0,
            'skipped_by_config' => 0,
            'stations' => [],
        ];

        if (! $this->isConfigured()) {
            $summary['skipped_by_config'] = 1;

            return $summary;
        }

        foreach (array_keys($this->stationModels()) as $station) {
            $result = $this->pushStation($station, $plantId);

            $summary['stations'][$station] = $result;
            $summary['pushed'] += $result['pushed'];
            $summary['failed'] += $result['failed'];
        }

        return $summary;
    }

    /**
     * Push data pending satu stasiun ke HQ dalam batch.
     *
     * @return array{pushed: int, failed: int}
     */
    public function pushStation(string $station, string $plantId): array
    {
        $modelClass = $this->stationModels()[$station] ?? null;

        if (! $modelClass) {
            return ['pushed' => 0, 'failed' => 0, 'error' => "Unknown station: {$station}"];
        }

        $batchSize = max(1, (int) config('hq.batch_size', 200));
        $totalPushed = 0;
        $totalFailed = 0;

        while (true) {
            $records = $this->pendingQuery($modelClass, $plantId)
                ->limit($batchSize)
                ->get();

            if ($records->isEmpty()) {
                break;
            }

            $result = $this->pushBatch($station, $plantId, $records);

            $totalPushed += $result['pushed'];
            $totalFailed += $result['failed'];

            if ($result['pushed'] === 0) {
                // Semua record batch ini gagal -> hentikan untuk menghindari loop.
                break;
            }

            if ($records->count() < $batchSize) {
                break;
            }
        }

        return ['pushed' => $totalPushed, 'failed' => $totalFailed];
    }

    /**
     * Kirim satu batch record ke HQ dan tandai yang sukses.
     *
     * @return array{pushed: int, failed: int}
     */
    public function pushBatch(string $station, string $plantId, Collection $records): array
    {
        $payload = [
            'plant_id' => $plantId,
            'station' => $station,
            'synced_at' => now()->toIso8601String(),
            'records' => $records->map(fn ($record) => $this->serializeRecord($record))->all(),
        ];

        $logId = $this->startLog($plantId, $station, $records->count());

        try {
            $response = Http::withHeaders([
                'X-HQ-API-TOKEN' => (string) config('hq.api_token'),
                'Accept' => 'application/json',
            ])
                ->timeout((int) config('hq.timeout', 30))
                ->post(rtrim((string) config('hq.api_url'), '/').'/sync', $payload);

            if (! $response->successful()) {
                $this->failLog($logId, "HTTP {$response->status()}: ".mb_substr($response->body(), 0, 500));

                Log::error('HQ sync push failed', [
                    'station' => $station,
                    'status' => $response->status(),
                    'body' => mb_substr($response->body(), 0, 500),
                ]);

                return ['pushed' => 0, 'failed' => $records->count()];
            }

            $body = $response->json();
            $receivedIds = collect($body['received_ids'] ?? [])->map(fn ($id) => (int) $id)->all();

            // Tandai record yang dikonfirmasi HQ sebagai tersinkron.
            $modelClass = $this->stationModels()[$station];
            $marked = 0;

            if (! empty($receivedIds)) {
                $marked = $modelClass::whereIn('id', $receivedIds)
                    ->whereNull('hq_synced_at')
                    ->update(['hq_synced_at' => now()]);
            }

            $this->finishLog($logId, 'success', $marked, $records->count() - $marked);

            return ['pushed' => $marked, 'failed' => $records->count() - $marked];
        } catch (ConnectionException $e) {
            $this->failLog($logId, 'Connection error: '.$e->getMessage());

            return ['pushed' => 0, 'failed' => $records->count()];
        } catch (\Throwable $e) {
            $this->failLog($logId, get_class($e).': '.$e->getMessage());

            return ['pushed' => 0, 'failed' => $records->count()];
        }
    }

    /**
     * Serialize satu record untuk payload HQ.
     */
    public function serializeRecord($record): array
    {
        $attributes = $record->getAttributes();
        unset($attributes['hq_synced_at']);

        $attributes['user_name'] = $record->user->name ?? $record->operator_name;

        return $attributes;
    }

    /**
     * Query record pending sync (belum terkirim, opsional hanya yang verified).
     */
    protected function pendingQuery(string $modelClass, string $plantId)
    {
        $query = $modelClass::query()
            ->where('plant_id', $plantId)
            ->whereNull('hq_synced_at');

        if (config('hq.sync_verified_only', true)) {
            $query->where('is_verified', true);
        }

        return $query->orderBy('id');
    }

    protected function isConfigured(): bool
    {
        return (bool) config('hq.enabled')
            && config('hq.api_url') !== ''
            && config('hq.api_token') !== '';
    }

    protected function startLog(string $plantId, string $station, int $count): int
    {
        return (int) DB::table('sync_logs')->insertGetId([
            'plant_id' => $plantId,
            'status' => 'running',
            'records_pushed' => 0,
            'records_failed' => $count,
            'message' => "Push {$station} ({$count} records)",
            'started_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function finishLog(int $logId, string $status, int $pushed, int $failed): void
    {
        DB::table('sync_logs')->where('id', $logId)->update([
            'status' => $status,
            'records_pushed' => $pushed,
            'records_failed' => $failed,
            'finished_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function failLog(int $logId, string $message): void
    {
        DB::table('sync_logs')->where('id', $logId)->update([
            'status' => 'failed',
            'message' => mb_substr($message, 0, 1000),
            'finished_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
