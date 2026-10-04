<?php

namespace Tests\Feature;

use App\Jobs\ProcessTelegramMessage;
use App\Models\MaintenanceTicket;
use App\Models\User;
use App\Services\TelegramService;
use App\Services\ValidationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * UAT bot Telegram untuk tiket maintenance (B3): operator melaporkan kerusakan,
 * asisten ke atas mengubah status.
 */
class TelegramMaintenanceBotTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake(['*' => Http::response(['ok' => true])]);
    }

    private function makeUser(array $overrides = []): User
    {
        return User::create(array_merge([
            'name' => 'Pengguna Bot',
            'phone_number' => '6281'.random_int(100000000, 999999999),
            'password' => 'secret123',
            'role' => 'operator',
            'department' => 'proses',
            'plant_id' => 'PKS_01',
            'status' => 'active',
            'telegram_user_id' => '999001',
        ], $overrides));
    }

    private function handle(string $text, string $chatId = '999001'): void
    {
        $message = [
            'message_id' => 1,
            'from' => ['id' => (int) $chatId, 'first_name' => 'Uji'],
            'chat' => ['id' => (int) $chatId, 'type' => 'private'],
            'text' => $text,
            'date' => now()->timestamp,
        ];

        (new ProcessTelegramMessage($message))
            ->handle(app(TelegramService::class), app(ValidationService::class));
    }

    private function lastBotText(string $chatId = '999001'): string
    {
        $requests = collect(Http::recorded())
            ->map(fn ($pair) => $pair[0])
            ->filter(fn ($req) => str_contains($req->url(), 'sendMessage')
                && (string) ($req['chat_id'] ?? '') === $chatId)
            ->values();

        return $requests->isEmpty() ? '' : (string) ($requests->last()['text'] ?? '');
    }

    public function test_operator_can_report_ticket_via_bot(): void
    {
        Queue::fake();

        $operator = $this->makeUser();

        $this->handle('/tiket PRESS-01 tinggi Kebocoran hidrolik | Oli rembes di silinder');

        $this->assertDatabaseHas('maintenance_tickets', [
            'plant_id' => 'PKS_01',
            'kode_mesin' => 'PRESS-01',
            'judul' => 'Kebocoran hidrolik',
            'deskripsi' => 'Oli rembes di silinder',
            'prioritas' => 'tinggi',
            'status' => 'open',
            'reported_by' => $operator->id,
        ]);

        $text = $this->lastBotText();
        $this->assertStringContainsString('Tiket #', $text);
        $this->assertStringContainsString('dibuat', $text);
    }

    public function test_invalid_priority_is_rejected(): void
    {
        $this->makeUser();

        $this->handle('/tiket PRESS-01 sangat-tinggi Mesin bunyi');

        $this->assertDatabaseCount('maintenance_tickets', 0);
        $this->assertStringContainsString('tidak dikenal', $this->lastBotText());
    }

    public function test_list_command_and_keyboard_button_show_active_tickets(): void
    {
        $operator = $this->makeUser();

        MaintenanceTicket::create([
            'plant_id' => 'PKS_01',
            'kode_mesin' => 'KERNEL-02',
            'judul' => 'Bearing panas',
            'deskripsi' => 'Suhu tinggi',
            'prioritas' => 'sedang',
            'status' => 'open',
            'reported_by' => $operator->id,
        ]);

        $this->handle('/tiket');
        $this->assertStringContainsString('Tiket Maintenance Aktif', $this->lastBotText());
        $this->assertStringContainsString('KERNEL-02', $this->lastBotText());

        // Tombol reply keyboard.
        $this->handle('🛠️ Tiket Maintenance');
        $this->assertStringContainsString('KERNEL-02', $this->lastBotText());
    }

    public function test_operator_cannot_update_ticket_status(): void
    {
        $operator = $this->makeUser();

        $ticket = MaintenanceTicket::create([
            'plant_id' => 'PKS_01',
            'kode_mesin' => 'PRESS-02',
            'judul' => 'Tekanan turun',
            'deskripsi' => 'Perlu cek',
            'prioritas' => 'sedang',
            'status' => 'open',
            'reported_by' => $operator->id,
        ]);

        $this->handle("/tiket_update {$ticket->id} selesai");

        $this->assertSame('open', $ticket->refresh()->status);
        $this->assertStringContainsString('Akses Ditolak', $this->lastBotText());
    }

    public function test_asisten_can_update_ticket_status_via_bot(): void
    {
        Queue::fake();

        $reporter = $this->makeUser(['telegram_user_id' => '999001']);
        $asisten = $this->makeUser([
            'role' => 'asisten',
            'department' => 'maintenance',
            'telegram_user_id' => '999002',
        ]);

        $ticket = MaintenanceTicket::create([
            'plant_id' => 'PKS_01',
            'kode_mesin' => 'STERILIZER-01',
            'judul' => 'Valve bocor',
            'deskripsi' => 'Steam bocor',
            'prioritas' => 'tinggi',
            'status' => 'open',
            'reported_by' => $reporter->id,
        ]);

        $this->handle("/tiket_update {$ticket->id} selesai Seal diganti", '999002');

        $ticket->refresh();
        $this->assertSame('selesai', $ticket->status);
        $this->assertNotNull($ticket->resolved_at);
        $this->assertSame('Seal diganti', $ticket->resolution_note);
        $this->assertStringContainsString('diperbarui', $this->lastBotText('999002'));
    }
}
