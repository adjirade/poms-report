<?php

namespace Tests\Feature;

use App\Models\LogLab;
use App\Models\LogTimbang;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommandCenterPdfTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $role, string $phone): User
    {
        return User::create([
            'name' => ucfirst($role).' Uji',
            'phone_number' => $phone,
            'password' => 'secret123',
            'role' => $role,
            'plant_id' => 'PKS_01',
            'status' => 'active',
        ]);
    }

    public function test_manager_can_download_command_center_pdf(): void
    {
        $manager = $this->makeUser('manager', '628910000001');
        $operator = $this->makeUser('operator', '628910000002');

        LogTimbang::create([
            'user_id' => $operator->id,
            'plant_id' => 'PKS_01',
            'no_spb' => 'SPB-1',
            'tonase_bruto' => 25000,
            'tonase_tarra' => 9000,
            'potongan_persen' => 4.5,
            'timestamp_kirim' => now(),
            'timestamp_server' => now(),
            'is_flagged' => false,
            'is_verified' => false,
        ]);

        LogLab::create([
            'user_id' => $operator->id,
            'plant_id' => 'PKS_01',
            'kadar_alb_cpo' => 3.2,
            'losses_fiber_persen' => 4.1,
            'losses_jankos_persen' => 0.9,
            'timestamp_kirim' => now(),
            'timestamp_server' => now(),
            'is_flagged' => false,
            'is_verified' => false,
        ]);

        $response = $this->actingAs($manager)->get('/export/command-center');

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', (string) $response->getContent());
    }

    public function test_asisten_cannot_download_command_center_pdf(): void
    {
        $asisten = $this->makeUser('asisten', '628910000003');
        $asisten->update(['department' => 'proses']);

        $this->actingAs($asisten)->get('/export/command-center')->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/export/command-center')->assertRedirect('/login');
    }
}
