<?php

namespace Tests\Feature;

use App\Jobs\ProcessTelegramMessage;
use App\Jobs\SendTelegramNotification;
use App\Models\LogTimbang;
use App\Models\User;
use App\Services\TelegramNotificationService;
use App\Services\TelegramService;
use App\Services\ValidationService;
use Database\Seeders\ValidationRulesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class TelegramNotificationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ValidationRulesSeeder::class);

        // Semua panggilan HTTP Telegram di-fake (sendMessage dst. sukses).
        Http::fake(['*' => Http::response(['ok' => true])]);
    }

    private function makeUser(array $overrides = []): User
    {
        return User::create(array_merge([
            'name' => 'User Uji',
            'phone_number' => '62'.random_int(81100000000, 81999999999),
            'password' => 'secret123',
            'role' => 'operator',
            'department' => 'proses',
            'plant_id' => 'PKS_01',
            'status' => 'active',
        ], $overrides));
    }

    private function handleTelegramMessage(string $text, int $messageTimestamp): void
    {
        $message = [
            'message_id' => 1,
            'from' => ['id' => '555001', 'first_name' => 'Op', 'username' => 'op_uji'],
            'chat' => ['id' => '555001'],
            'text' => $text,
            'date' => $messageTimestamp,
        ];

        (new ProcessTelegramMessage($message))
            ->handle(app(TelegramService::class), app(ValidationService::class));
    }

    public function test_flagged_record_notifies_department_asisten(): void
    {
        Queue::fake();

        $operator = $this->makeUser([
            'telegram_user_id' => '555001',
            'name' => 'Operator Proses',
        ]);

        $asisten = $this->makeUser([
            'name' => 'Asisten Proses',
            'role' => 'asisten',
            'department' => 'proses',
            'telegram_user_id' => '777001',
        ]);

        // Timestamp kirim 6 jam lalu -> selisih > 4 jam -> flagged.
        $this->handleTelegramMessage('/timbang SPB-NTF-1 25000 9000 4.5', now()->subHours(6)->timestamp);

        $this->assertDatabaseHas('log_timbang', [
            'no_spb' => 'SPB-NTF-1',
            'user_id' => $operator->id,
            'is_flagged' => true,
        ]);

        Queue::assertPushed(SendTelegramNotification::class, function (SendTelegramNotification $job) use ($asisten) {
            return $job->chatId === $asisten->telegram_user_id
                && str_contains($job->text, 'Data Flagged');
        });
    }

    public function test_asisten_opt_out_receives_no_notification(): void
    {
        Queue::fake();

        $this->makeUser([
            'telegram_user_id' => '555002',
            'name' => 'Operator Proses 2',
        ]);

        $asisten = $this->makeUser([
            'name' => 'Asisten Opt Out',
            'role' => 'asisten',
            'department' => 'proses',
            'telegram_user_id' => '777002',
            'telegram_notif_enabled' => false,
        ]);

        $this->handleTelegramMessage('/timbang SPB-NTF-2 25000 9000 4.5', now()->subHours(6)->timestamp);

        // Asisten satu-satunya dalam scope opt-out -> tidak ada notifikasi sama sekali.
        Queue::assertNothingPushed();
    }

    public function test_clean_record_does_not_trigger_flagged_notification(): void
    {
        Queue::fake();

        $this->makeUser([
            'telegram_user_id' => '555003',
            'name' => 'Operator Proses 3',
        ]);

        $this->makeUser([
            'name' => 'Asisten Proses 3',
            'role' => 'asisten',
            'department' => 'proses',
            'telegram_user_id' => '777003',
        ]);

        // Timestamp sekarang -> tidak flagged.
        $this->handleTelegramMessage('/timbang SPB-NTF-3 25000 9000 4.5', now()->timestamp);

        Queue::assertNothingPushed();
    }

    public function test_verification_notifies_submitter(): void
    {
        Queue::fake();

        $operator = $this->makeUser([
            'telegram_user_id' => '555004',
            'name' => 'Operator Diverifikasi',
        ]);

        $asisten = $this->makeUser([
            'name' => 'Asisten Verifikator',
            'role' => 'asisten',
            'department' => 'proses',
        ]);

        $record = LogTimbang::create([
            'user_id' => $operator->id,
            'plant_id' => 'PKS_01',
            'no_spb' => 'SPB-NTF-4',
            'tonase_bruto' => 25000,
            'tonase_tarra' => 9000,
            'potongan_persen' => 4.5,
            'timestamp_kirim' => now()->subHours(6),
            'timestamp_server' => now(),
            'is_flagged' => true,
            'is_verified' => false,
        ]);

        $this->actingAs($asisten)
            ->post("/stations/timbang/{$record->id}/verify")
            ->assertRedirect();

        $this->assertDatabaseHas('log_timbang', [
            'id' => $record->id,
            'is_verified' => true,
            'verified_by' => $asisten->id,
        ]);

        Queue::assertPushed(SendTelegramNotification::class, function (SendTelegramNotification $job) use ($operator) {
            return $job->chatId === $operator->telegram_user_id
                && str_contains($job->text, 'Data Diverifikasi');
        });
    }

    public function test_verifier_does_not_get_notified_for_own_submission(): void
    {
        Queue::fake();

        $asisten = $this->makeUser([
            'name' => 'Asisten Self Input',
            'role' => 'asisten',
            'department' => 'proses',
            'telegram_user_id' => '777005',
        ]);

        $record = LogTimbang::create([
            'user_id' => $asisten->id,
            'plant_id' => 'PKS_01',
            'no_spb' => 'SPB-NTF-5',
            'tonase_bruto' => 25000,
            'tonase_tarra' => 9000,
            'potongan_persen' => 4.5,
            'timestamp_kirim' => now()->subHours(6),
            'timestamp_server' => now(),
            'is_flagged' => true,
            'is_verified' => false,
        ]);

        app(TelegramNotificationService::class)->notifyVerified($record->refresh(), 'timbang', $asisten);

        Queue::assertNothingPushed();
    }

    public function test_webhook_accepts_valid_secret_and_dispatches(): void
    {
        config(['telegram.webhook.secret' => 's3cr3t-t0ken']);
        Queue::fake();

        $this->postJson('/api/telegram/webhook', [
            'message' => [
                'message_id' => 9,
                'from' => ['id' => '555009', 'first_name' => 'Op'],
                'chat' => ['id' => '555009'],
                'text' => '/ringkasan',
                'date' => now()->timestamp,
            ],
        ], ['X-Telegram-Bot-Api-Secret-Token' => 's3cr3t-t0ken'])
            ->assertOk()
            ->assertJson(['ok' => true]);

        Queue::assertPushed(ProcessTelegramMessage::class);
    }

    public function test_webhook_rejects_missing_or_wrong_secret(): void
    {
        config(['telegram.webhook.secret' => 's3cr3t-t0ken']);
        Queue::fake();

        // Tanpa header
        $this->postJson('/api/telegram/webhook', ['message' => ['text' => '/ringkasan']])
            ->assertForbidden();

        // Header salah
        $this->postJson('/api/telegram/webhook', ['message' => ['text' => '/ringkasan']],
            ['X-Telegram-Bot-Api-Secret-Token' => 'wrong'])
            ->assertForbidden();

        Queue::assertNothingPushed();
    }

    public function test_menu_commands_are_resolved(): void
    {
        $job = new ProcessTelegramMessage([]);
        $method = new \ReflectionMethod($job, 'resolveMenuAction');

        $this->assertSame('start', $method->invoke($job, '/start'));
        $this->assertSame('menu', $method->invoke($job, '/menu'));
        $this->assertSame('ringkasan', $method->invoke($job, '/ringkasan'));
        $this->assertSame('ringkasan', $method->invoke($job, '📊 Ringkasan'));
        $this->assertSame('flagged', $method->invoke($job, '🚩 Flagged'));
        $this->assertSame('status', $method->invoke($job, 'ℹ️ Status Terakhir'));
        $this->assertSame('bantuan', $method->invoke($job, '/bantuan'));
        $this->assertNull($method->invoke($job, '/timbang SPB1 25000 9000 4.5'));
    }
}
