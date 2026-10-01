<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\HQSyncService;
use App\Services\ValidationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Endpoint penerimaan data dari server pabrik (spoke) ke Cloud HQ (hub).
 * Idempoten: record yang sama (plant_id + hq_source_id) tidak akan duplikat.
 */
class HQSyncController extends Controller
{
    public function __construct(
        protected ValidationService $validation,
        protected HQSyncService $syncService,
    ) {}

    public function ping(): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'service' => 'poms-hq-sync',
            'time' => now()->toIso8601String(),
        ]);
    }

    public function receive(Request $request): JsonResponse
    {
        $data = $request->validate([
            'plant_id' => ['required', 'string', 'max:10'],
            'station' => ['required', 'string', 'max:50'],
            'synced_at' => ['required', 'date'],
            'records' => ['required', 'array', 'max:1000'],
            'records.*' => ['required', 'array'],
        ]);

        $plantId = $data['plant_id'];
        $station = $data['station'];

        // Whitelist stasiun (mencegah penulisan ke tabel sembarangan)
        try {
            $modelClass = $this->validation->getStationModel($station);
        } catch (\Throwable) {
            return response()->json([
                'error' => "Unknown station '{$station}'.",
            ], 422);
        }

        // Akun sistem per-plant untuk memenuhi FK user_id di server HQ.
        $systemUser = $this->getOrCreatePlantSyncUser($plantId);

        $receivedIds = [];
        $duplicates = 0;
        $errors = [];

        DB::transaction(function () use ($data, $modelClass, $plantId, $systemUser, &$receivedIds, &$duplicates, &$errors) {
            foreach ($data['records'] as $index => $record) {
                $sourceId = isset($record['id']) ? (int) $record['id'] : null;

                if ($sourceId === null) {
                    $errors[$index] = 'Missing record id.';
                    continue;
                }

                // Idempotensi: (plant_id, hq_source_id) unik.
                $exists = $modelClass::where('plant_id', $plantId)
                    ->where('hq_source_id', $sourceId)
                    ->exists();

                if ($exists) {
                    $duplicates++;
                    continue;
                }

                try {
                    $attributes = $this->mapIncomingRecord($modelClass, $record, [
                        'plant_id' => $plantId,
                        'user_id' => $systemUser->id,
                        'operator_name' => $record['user_name'] ?? ($record['operator_name'] ?? null),
                        'hq_source_id' => $sourceId,
                        'verified_by' => null, // FK users tidak berlaku lintas server
                    ]);

                    $modelClass::forceCreate($attributes);
                    $receivedIds[] = $sourceId;
                } catch (\Throwable $e) {
                    $errors[$index] = $e->getMessage();
                }
            }
        });

        Log::info('HQ sync received', [
            'plant_id' => $plantId,
            'station' => $station,
            'received' => count($receivedIds),
            'duplicates' => $duplicates,
            'errors' => count($errors),
        ]);

        return response()->json([
            'ok' => true,
            'plant_id' => $plantId,
            'station' => $station,
            'received_ids' => $receivedIds,
            'received' => count($receivedIds),
            'duplicates' => $duplicates,
            'errors' => $errors,
        ], 201);
    }

    /**
     * Mapping payload pabrik -> kolom model stasiun di sisi HQ.
     * Hanya kolom fillable + kolom HQ yang boleh masuk.
     */
    protected function mapIncomingRecord(string $modelClass, array $record, array $overrides): array
    {
        $fillable = $modelClass::make()->getFillable();
        $attributes = [];

        foreach ($fillable as $field) {
            if (array_key_exists($field, $record) && !array_key_exists($field, $overrides)) {
                $attributes[$field] = $record[$field];
            }
        }

        // Kolom operator dari stasiun maintenance boleh kosong
        if (isset($record['keterangan_perbaikan']) && array_key_exists('keterangan_perbaikan', $attributes) === false) {
            $attributes['keterangan_perbaikan'] = $record['keterangan_perbaikan'];
        }

        return array_merge($attributes, $overrides);
    }

    protected function getOrCreatePlantSyncUser(string $plantId): User
    {
        return User::firstOrCreate(
            ['phone_number' => 'hq-sync-' . strtolower($plantId)],
            [
                'name' => 'HQ Sync System (' . $plantId . ')',
                'telegram_user_id' => null,
                'password' => \Illuminate\Support\Str::random(32),
                'role' => 'developer',
                'department' => null,
                'plant_id' => $plantId,
                'status' => 'active',
            ]
        );
    }
}
