<?php

namespace Tests\Feature;

use App\Models\LogTimbang;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HQSyncApiTest extends TestCase
{
    use RefreshDatabase;

    protected function payload(array $overrides = []): array
    {
        return array_merge([
            'plant_id' => 'PKS_01',
            'station' => 'timbang',
            'synced_at' => now()->toIso8601String(),
            'records' => [
                [
                    'id' => 101,
                    'user_id' => 5,
                    'user_name' => 'Operator A',
                    'no_spb' => 'SPB90001',
                    'tonase_bruto' => 25000,
                    'tonase_tarra' => 9000,
                    'potongan_persen' => 4.5,
                    'timestamp_kirim' => '2026-10-01 08:00:00',
                    'timestamp_server' => '2026-10-01 08:00:05',
                    'is_flagged' => false,
                    'is_verified' => true,
                ],
                [
                    'id' => 102,
                    'user_id' => 5,
                    'user_name' => 'Operator A',
                    'no_spb' => 'SPB90002',
                    'tonase_bruto' => 26000,
                    'tonase_tarra' => 9000,
                    'potongan_persen' => 4.0,
                    'timestamp_kirim' => '2026-10-01 09:00:00',
                    'timestamp_server' => '2026-10-01 09:00:05',
                    'is_flagged' => true,
                    'is_verified' => true,
                ],
            ],
        ], $overrides);
    }

    public function test_sync_endpoint_requires_valid_token(): void
    {
        // Tanpa token
        $this->postJson('/api/hq/sync', $this->payload())
            ->assertStatus(401);

        // Token salah
        $this->withHeader('X-HQ-API-TOKEN', 'wrong-token')
            ->postJson('/api/hq/sync', $this->payload())
            ->assertStatus(401);
    }

    public function test_ping_endpoint_works_with_token(): void
    {
        $this->withHeader('X-HQ-API-TOKEN', 'test-token')
            ->getJson('/api/hq/ping')
            ->assertOk()
            ->assertJsonPath('ok', true);
    }

    public function test_sync_stores_records_with_hq_metadata(): void
    {
        $response = $this->withHeader('X-HQ-API-TOKEN', 'test-token')
            ->postJson('/api/hq/sync', $this->payload());

        $response->assertStatus(201)
            ->assertJsonPath('received', 2)
            ->assertJsonPath('duplicates', 0);

        $record = LogTimbang::where('hq_source_id', 101)->first();

        $this->assertNotNull($record);
        $this->assertSame('PKS_01', $record->plant_id);
        $this->assertSame('SPB90001', $record->no_spb);
        $this->assertSame('Operator A', $record->operator_name);
        $this->assertTrue((bool) $record->is_verified);
        $this->assertTrue((bool) $record->is_flagged === false || $record->is_flagged === 0 || $record->is_flagged === false);
        // FK user_id dipetakan ke akun sync sistem per-plant
        $this->assertSame('hq-sync-pks_01', $record->user->phone_number);
    }

    public function test_sync_is_idempotent(): void
    {
        $headers = ['X-HQ-API-TOKEN' => 'test-token'];

        $this->withHeaders($headers)->postJson('/api/hq/sync', $this->payload())->assertStatus(201);
        $this->withHeaders($headers)->postJson('/api/hq/sync', $this->payload())
            ->assertStatus(201)
            ->assertJsonPath('received', 0)
            ->assertJsonPath('duplicates', 2);

        $this->assertSame(2, LogTimbang::where('plant_id', 'PKS_01')->count());
    }

    public function test_sync_rejects_unknown_station(): void
    {
        $this->withHeader('X-HQ-API-TOKEN', 'test-token')
            ->postJson('/api/hq/sync', $this->payload(['station' => 'hacker-table']))
            ->assertStatus(422);
    }

    public function test_sync_validates_required_fields(): void
    {
        $this->withHeader('X-HQ-API-TOKEN', 'test-token')
            ->postJson('/api/hq/sync', ['plant_id' => 'PKS_01'])
            ->assertStatus(422);
    }
}
