<?php

namespace Tests\Unit;

use App\Models\LogTimbang;
use App\Models\User;
use App\Services\HQSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HQSyncServiceTest extends TestCase
{
    use RefreshDatabase;

    protected HQSyncService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(HQSyncService::class);
        config([
            'hq.enabled' => true,
            'hq.api_url' => 'https://hq.test/api/hq',
            'hq.api_token' => 'test-token',
            'hq.sync_verified_only' => true,
        ]);
    }

    protected function makeRecord(array $overrides = []): LogTimbang
    {
        $user = User::create([
            'name' => 'Operator Sync Test',
            'phone_number' => '6299' . uniqid(),
            'password' => 'secret123',
            'role' => 'operator',
            'department' => 'proses',
            'plant_id' => 'PKS_01',
            'status' => 'active',
        ]);

        return LogTimbang::create(array_merge([
            'user_id' => $user->id,
            'plant_id' => 'PKS_01',
            'no_spb' => 'SPB' . uniqid(),
            'tonase_bruto' => 25000,
            'tonase_tarra' => 9000,
            'potongan_persen' => 4.5,
            'timestamp_kirim' => now(),
            'timestamp_server' => now(),
            'is_flagged' => false,
            'is_verified' => true,
        ], $overrides));
    }

    public function test_pending_counts_only_unsynced_verified_records(): void
    {
        $this->makeRecord();                                   // pending (verified)
        $this->makeRecord(['is_verified' => false]);           // bukan verified -> skip
        $synced = $this->makeRecord();                         // sudah synced -> skip
        $synced->update(['hq_synced_at' => now()]);

        $counts = $this->service->pendingCounts('PKS_01');

        $this->assertSame(1, $counts['timbang']);
    }

    public function test_push_marks_records_as_synced_on_success(): void
    {
        $record = $this->makeRecord();

        Http::fake([
            'https://hq.test/api/hq/sync' => Http::response([
                'ok' => true,
                'received_ids' => [$record->id],
            ], 201),
        ]);

        $result = $this->service->pushStation('timbang', 'PKS_01');

        $this->assertSame(1, $result['pushed']);
        $this->assertSame(0, $result['failed']);
        $this->assertNotNull($record->fresh()->hq_synced_at);

        Http::assertSent(function ($request) use ($record) {
            $data = $request->data();

            return $request->hasHeader('X-HQ-API-TOKEN', 'test-token')
                && $data['plant_id'] === 'PKS_01'
                && $data['station'] === 'timbang'
                && $data['records'][0]['id'] === $record->id
                && isset($data['records'][0]['user_name']);
        });
    }

    public function test_push_does_not_mark_on_failure(): void
    {
        $record = $this->makeRecord();

        Http::fake([
            'https://hq.test/api/hq/sync' => Http::response(['error' => 'boom'], 500),
        ]);

        $result = $this->service->pushStation('timbang', 'PKS_01');

        $this->assertSame(0, $result['pushed']);
        $this->assertSame(1, $result['failed']);
        $this->assertNull($record->fresh()->hq_synced_at);
    }

    public function test_serialized_record_includes_operator_name_and_excludes_sync_marker(): void
    {
        $record = $this->makeRecord();

        $payload = $this->service->serializeRecord($record);

        $this->assertSame('Operator Sync Test', $payload['user_name']);
        $this->assertArrayNotHasKey('hq_synced_at', $payload);
        $this->assertSame($record->id, $payload['id']);
    }
}
