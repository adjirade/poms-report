<?php

namespace Tests\Feature;

use App\Jobs\SendTelegramNotification;
use App\Models\MaintenanceTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class MaintenanceTicketTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $role, string $phone, ?string $department = null, ?string $telegramId = null, string $plant = 'PKS_01'): User
    {
        return User::create([
            'name' => ucfirst($role).' Uji',
            'phone_number' => $phone,
            'password' => 'secret123',
            'role' => $role,
            'department' => $department,
            'plant_id' => $plant,
            'status' => 'active',
            'telegram_user_id' => $telegramId,
        ]);
    }

    public function test_asisten_can_view_ticket_page(): void
    {
        $asisten = $this->makeUser('asisten', '628700000001', 'maintenance');

        $this->actingAs($asisten)
            ->get('/maintenance/tickets')
            ->assertOk()
            ->assertSee('Tiket Maintenance');
    }

    public function test_operator_is_blocked_from_web_ticket_page(): void
    {
        $operator = $this->makeUser('operator', '628700000002', 'proses');

        $this->actingAs($operator)->get('/maintenance/tickets')->assertForbidden();
    }

    public function test_ticket_creation_notifies_maintenance_department(): void
    {
        Queue::fake();

        $reporter = $this->makeUser('asisten', '628700000003', 'proses');
        $technician = $this->makeUser('asisten', '628700000004', 'maintenance', '777100');

        $this->actingAs($reporter)->post('/maintenance/tickets', [
            'kode_mesin' => 'PRESS-01',
            'judul' => 'Kebocoran hidrolik',
            'deskripsi' => 'Tekanan tidak stabil',
            'prioritas' => 'tinggi',
        ])->assertRedirect();

        $this->assertDatabaseHas('maintenance_tickets', [
            'kode_mesin' => 'PRESS-01',
            'status' => 'open',
            'prioritas' => 'tinggi',
            'reported_by' => $reporter->id,
        ]);

        Queue::assertPushed(SendTelegramNotification::class, function (SendTelegramNotification $job) {
            return $job->chatId === '777100' && str_contains($job->text, 'Tiket Maintenance Baru');
        });
    }

    public function test_status_change_marks_resolved_and_notifies_reporter(): void
    {
        Queue::fake();

        $reporter = $this->makeUser('asisten', '628700000005', 'proses', '777101');
        $technician = $this->makeUser('asisten', '628700000006', 'maintenance');

        $ticket = MaintenanceTicket::create([
            'plant_id' => 'PKS_01',
            'kode_mesin' => 'KERNEL-02',
            'judul' => 'Bearing panas',
            'deskripsi' => 'Suhu melebihi normal',
            'prioritas' => 'sedang',
            'status' => 'open',
            'reported_by' => $reporter->id,
        ]);

        $this->actingAs($technician)->put("/maintenance/tickets/{$ticket->id}", [
            'status' => 'selesai',
            'resolution_note' => 'Bearing diganti',
        ])->assertRedirect();

        $ticket->refresh();
        $this->assertSame('selesai', $ticket->status);
        $this->assertNotNull($ticket->resolved_at);
        $this->assertSame('Bearing diganti', $ticket->resolution_note);

        Queue::assertPushed(SendTelegramNotification::class, function (SendTelegramNotification $job) {
            return $job->chatId === '777101' && str_contains($job->text, 'Status Tiket Maintenance');
        });
    }

    public function test_ticket_from_other_plant_cannot_be_updated(): void
    {
        $manager = $this->makeUser('manager', '628700000007');

        $other = MaintenanceTicket::create([
            'plant_id' => 'PKS_02',
            'kode_mesin' => 'PRESS-99',
            'judul' => 'Masalah plant lain',
            'deskripsi' => 'Tidak boleh diubah',
            'prioritas' => 'rendah',
            'status' => 'open',
        ]);

        $this->actingAs($manager)
            ->put("/maintenance/tickets/{$other->id}", ['status' => 'selesai'])
            ->assertForbidden();
    }

    public function test_creation_requires_fields(): void
    {
        $asisten = $this->makeUser('asisten', '628700000008', 'maintenance');

        $this->actingAs($asisten)
            ->post('/maintenance/tickets', [])
            ->assertSessionHasErrors(['kode_mesin', 'judul', 'deskripsi']);
    }
}
