<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class HQSyncMonitorTest extends TestCase
{
    use RefreshDatabase;

    protected function makeUser(string $role, string $phone): User
    {
        return User::create([
            'name' => "User {$role}",
            'phone_number' => $phone,
            'password' => 'secret123',
            'role' => $role,
            'department' => null,
            'plant_id' => 'PKS_01',
            'status' => 'active',
        ]);
    }

    public function test_developer_can_open_monitor_page(): void
    {
        $dev = $this->makeUser('developer', '6288100000001');

        $this->actingAs($dev)
            ->get('/settings/hq-sync')
            ->assertOk()
            ->assertSee('Monitoring HQ Cloud Sync')
            ->assertSee('Riwayat Sinkronisasi');
    }

    public function test_manager_cannot_open_monitor_page(): void
    {
        $manager = $this->makeUser('manager', '6288100000002');

        $this->actingAs($manager)
            ->get('/settings/hq-sync')
            ->assertForbidden();
    }

    public function test_guest_is_redirected(): void
    {
        $this->get('/settings/hq-sync')->assertRedirect('/login');
    }

    public function test_run_is_blocked_when_sync_disabled(): void
    {
        config(['hq.enabled' => false]);

        $dev = $this->makeUser('developer', '6288100000003');

        $this->actingAs($dev)
            ->post('/settings/hq-sync/run')
            ->assertRedirect()
            ->assertSessionHas('warning');
    }

    public function test_run_dispatches_job_when_configured(): void
    {
        config([
            'hq.enabled' => true,
            'hq.api_url' => 'https://hq.test/api/hq',
            'hq.api_token' => 'token',
            'poms.plant_id' => 'PKS_01',
        ]);

        Queue::fake();

        $dev = $this->makeUser('developer', '6288100000004');

        $this->actingAs($dev)
            ->post('/settings/hq-sync/run')
            ->assertRedirect()
            ->assertSessionHas('success');

        Queue::assertPushed(\App\Jobs\SyncPlantDataToHQ::class);
    }
}
