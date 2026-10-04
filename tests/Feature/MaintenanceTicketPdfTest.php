<?php

namespace Tests\Feature;

use App\Models\MaintenanceTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MaintenanceTicketPdfTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $role, string $phone, string $plant = 'PKS_01'): User
    {
        return User::create([
            'name' => ucfirst($role).' Uji',
            'phone_number' => $phone,
            'password' => 'secret123',
            'role' => $role,
            'department' => 'maintenance',
            'plant_id' => $plant,
            'status' => 'active',
        ]);
    }

    private function seedTickets(User $reporter): void
    {
        MaintenanceTicket::create([
            'plant_id' => 'PKS_01',
            'kode_mesin' => 'PRESS-01',
            'judul' => 'Kebocoran hidrolik',
            'deskripsi' => 'Tekanan tidak stabil',
            'prioritas' => 'tinggi',
            'status' => 'open',
            'reported_by' => $reporter->id,
        ]);

        MaintenanceTicket::create([
            'plant_id' => 'PKS_01',
            'kode_mesin' => 'PRESS-01',
            'judul' => 'Seal aus',
            'deskripsi' => 'Diganti berkala',
            'prioritas' => 'sedang',
            'status' => 'selesai',
            'reported_by' => $reporter->id,
            'resolution_note' => 'Seal baru dipasang',
            'resolved_at' => now(),
        ]);

        MaintenanceTicket::create([
            'plant_id' => 'PKS_01',
            'kode_mesin' => 'KERNEL-02',
            'judul' => 'Bearing panas',
            'deskripsi' => 'Suhu tinggi',
            'prioritas' => 'sedang',
            'status' => 'open',
            'reported_by' => $reporter->id,
        ]);
    }

    public function test_manager_can_download_pdf_for_all_machines(): void
    {
        $manager = $this->makeUser('manager', '628920000001');
        $this->seedTickets($manager);

        $response = $this->actingAs($manager)->get('/export/maintenance-tickets');

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', (string) $response->getContent());
    }

    public function test_manager_can_download_pdf_filtered_by_machine(): void
    {
        $manager = $this->makeUser('manager', '628920000002');
        $this->seedTickets($manager);

        $response = $this->actingAs($manager)
            ->get('/export/maintenance-tickets?kode_mesin=PRESS-01');

        $response->assertOk();
        $this->assertStringStartsWith('%PDF', (string) $response->getContent());
    }

    public function test_asisten_cannot_download_maintenance_pdf(): void
    {
        $asisten = $this->makeUser('asisten', '628920000003');

        $this->actingAs($asisten)->get('/export/maintenance-tickets')->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/export/maintenance-tickets')->assertRedirect('/login');
    }
}
