<?php

namespace Tests\Feature;

use App\Jobs\ProcessTelegramMessage;
use App\Jobs\SendTelegramNotification;
use App\Models\LogTimbang;
use App\Models\User;
use App\Services\TelegramService;
use App\Services\ValidationService;
use Database\Seeders\ValidationRulesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * UAT dry-run alur bot Telegram — mensimulasikan update Telegram ASLI
 * (payload persis seperti yang dikirim Bot API) tanpa jaringan.
 * Begitu TELEGRAM_BOT_TOKEN diisi, alur live harus identik.
 */
class TelegramBotFlowUatTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ValidationRulesSeeder::class);
        Http::fake(['*' => Http::response(['ok' => true])]);
    }

    /** Buat user terdaftar (belum terhubung Telegram). */
    private function makeUser(array $overrides = []): User
    {
        return User::create(array_merge([
            'name' => 'Operator UAT',
            'phone_number' => '6281234567890',
            'password' => 'secret123',
            'role' => 'operator',
            'department' => 'proses',
            'plant_id' => 'PKS_01',
            'status' => 'active',
        ], $overrides));
    }

    /** Update Bot API format asli (message teks). */
    private function update(string $text, string $chatId = '888001', array $extra = []): array
    {
        return array_merge([
            'update_id' => 1,
            'message' => array_merge([
                'message_id' => 101,
                'from' => [
                    'id' => (int) $chatId,
                    'first_name' => 'Penguji',
                    'username' => 'uat_user',
                ],
                'chat' => ['id' => (int) $chatId, 'type' => 'private'],
                'text' => $text,
                'date' => now()->timestamp,
            ], $extra),
        ]);
    }

    private function handle(array $update): void
    {
        (new ProcessTelegramMessage($update['message']))
            ->handle(app(TelegramService::class), app(ValidationService::class));
    }

    /** Semua request sendMessage bot ke chat (urut waktu). */
    private function botRequests(string $chatId = '888001'): array
    {
        return collect(Http::recorded())
            ->map(fn ($pair) => $pair[0])
            ->filter(fn ($req) => str_contains($req->url(), 'sendMessage')
                && (string) ($req['chat_id'] ?? '') === $chatId)
            ->values()
            ->all();
    }

    /** Teks terakhir yang dikirim bot ke chat. */
    private function lastBotText(string $chatId = '888001'): string
    {
        $reqs = $this->botRequests($chatId);

        return $reqs === [] ? '' : (string) (end($reqs)['text'] ?? '');
    }

    /** Markup (reply keyboard JSON) terakhir yang dikirim ke chat. */
    private function lastReplyMarkup(string $chatId = '888001'): ?array
    {
        $reqs = $this->botRequests($chatId);
        if ($reqs === []) {
            return null;
        }

        $raw = end($reqs)['reply_markup'] ?? null;

        return is_string($raw) ? json_decode($raw, true) : $raw;
    }

    // ------------------------------------------------------------------
    // 1. User BELUM terhubung: /start harus menawarkan tombol share contact
    // ------------------------------------------------------------------
    public function test_uat_1_start_from_unknown_chat_offers_contact_button(): void
    {
        $this->handle($this->update('/start'));

        $text = $this->lastBotText();
        $this->assertStringContainsString('belum terhubung ke akun POMS', $text);

        $markup = $this->lastReplyMarkup();
        $this->assertNotNull($markup, 'Harus menyertakan reply keyboard');
        $this->assertSame('📱 Hubungkan Nomor Saya', $markup['keyboard'][0][0]['text']);
        $this->assertTrue($markup['keyboard'][0][0]['request_contact']);
    }

    // ------------------------------------------------------------------
    // 2. Share contact → akun otomatis tertaut + menu utama muncul
    // ------------------------------------------------------------------
    public function test_uat_2_share_contact_links_account_and_shows_menu(): void
    {
        $this->makeUser(); // phone 6281234567890, belum ada telegram_user_id

        $update = $this->update('', '888001', [
            'contact' => [
                'phone_number' => '6281234567890',
                'first_name' => 'Penguji',
            ],
        ]);
        $this->handle($update);

        // Akun tertaut ke chat ini.
        $this->assertDatabaseHas('users', [
            'phone_number' => '6281234567890',
            'telegram_user_id' => '888001',
        ]);

        // Bot menyambut dengan menu utama.
        $text = $this->lastBotText();
        $this->assertStringContainsString('Selamat datang di POMS Bot', $text);
        $this->assertStringContainsString('pilih menu di keyboard bawah', $text);
        $this->assertSame('📊 Ringkasan', $this->lastReplyMarkup()['keyboard'][0][0]['text']);
    }

    // ------------------------------------------------------------------
    // 3. Menu: /ringkasan, tombol keyboard, /flagged, /status, /bantuan
    // ------------------------------------------------------------------
    public function test_uat_3_menu_and_queries_respond(): void
    {
        $this->makeUser(['telegram_user_id' => '888001']);

        // /ringkasan
        $this->handle($this->update('/ringkasan'));
        $this->assertStringContainsString('Ringkasan Hari Ini', $this->lastBotText());

        // Tombol keyboard "📊 Ringkasan" — perilaku sama dengan /ringkasan.
        $this->handle($this->update('📊 Ringkasan'));
        $this->assertStringContainsString('Ringkasan Hari Ini', $this->lastBotText());

        // /flagged — belum ada data flagged.
        $this->handle($this->update('/flagged'));
        $this->assertStringContainsString('Tidak ada record flagged', $this->lastBotText());

        // /status — belum pernah mengirim data.
        $this->handle($this->update('/status'));
        $this->assertStringContainsString('belum mengirim data', $this->lastBotText());

        // /bantuan — daftar perintah.
        $this->handle($this->update('/bantuan'));
        $this->assertStringContainsString('Bantuan POMS Bot', $this->lastBotText());
        $this->assertStringContainsString('/timbang SPB10293', $this->lastBotText());

        // /menu — ulangi menu.
        $this->handle($this->update('/menu'));
        $this->assertStringContainsString('Menu POMS', $this->lastBotText());
    }

    // ------------------------------------------------------------------
    // 4. Submit data sukses via bot → tersimpan, /status & /ringkasan ikut
    // ------------------------------------------------------------------
    public function test_uat_4_submit_data_via_bot_then_status_followup(): void
    {
        Queue::fake(); // tidak perlu notifikasi pada skenario ini

        $this->makeUser(['telegram_user_id' => '888001']);

        // Submit dengan waktu SEKARANG (tidak flagged).
        $this->handle($this->update('/timbang SPB-UAT-1 25000 9000 4.5'));

        // REGRESI: timestamp_kirim vs timestamp_server harus selisih < 1 menit
        // (bug lama: createFromTimestamp() Carbon 3 default UTC → selisih 7 jam
        // → semua record bot ter-flag sebagai anomali waktu).
        $record = LogTimbang::where('user_id', User::where('telegram_user_id', '888001')->first()->id)->first();
        $this->assertNotNull($record);
        $this->assertTrue(
            $record->timestamp_server->diffInMinutes($record->timestamp_kirim) <= 1,
            'timestamp_kirim dan timestamp_server harus dalam timezone yang sama'
        );

        $this->assertDatabaseHas('log_timbang', [
            'no_spb' => 'SPB-UAT-1',
            'user_id' => User::where('telegram_user_id', '888001')->first()->id,
            'is_flagged' => false,
        ]);
        $this->assertStringContainsString('Data Berhasil Disimpan', $this->lastBotText());

        // /status kini menampilkan record yang baru dikirim.
        $this->handle($this->update('/status'));
        $this->assertStringContainsString('#', $this->lastBotText());
        $this->assertStringContainsString('Menunggu verifikasi', $this->lastBotText());

        // /ringkasan menghitung 1 record.
        $this->handle($this->update('/ringkasan'));
        $this->assertStringContainsString('1 record', $this->lastBotText());
    }

    // ------------------------------------------------------------------
    // 5. Error handling: jumlah parameter salah & perintah tak dikenal
    // ------------------------------------------------------------------
    public function test_uat_5_error_messages_for_bad_input(): void
    {
        $this->makeUser(['telegram_user_id' => '888001']);

        // Parameter kurang.
        $this->handle($this->update('/lab 3.5 4.2'));
        $this->assertStringContainsString('membutuhkan 3 parameter', $this->lastBotText());

        // Perintah tidak dikenal.
        $this->handle($this->update('/tidakada 1 2 3'));
        $this->assertStringContainsString('tidak dikenal', $this->lastBotText());
    }

    // ------------------------------------------------------------------
    // 6. RBAC: operator proses tidak bisa mengirim data stasiun lab
    // ------------------------------------------------------------------
    public function test_uat_6_operator_blocked_from_other_department(): void
    {
        $this->makeUser(['telegram_user_id' => '888001']);

        $this->handle($this->update('/lab 3.5 4.2 0.8'));

        $this->assertStringContainsString('Akses Ditolak', $this->lastBotText());
        $this->assertDatabaseMissing('log_lab', [
            'user_id' => User::where('telegram_user_id', '888001')->first()->id,
        ]);
    }

    // ------------------------------------------------------------------
    // 7. /notif on|off — saklar tersimpan & tersinkron dengan halaman Profil
    // ------------------------------------------------------------------
    public function test_uat_7_notif_toggle_persists(): void
    {
        $user = $this->makeUser(['telegram_user_id' => '888001']);

        $this->handle($this->update('/notif off'));
        $this->assertStringContainsString('dimatikan', $this->lastBotText());
        $this->assertFalse($user->refresh()->telegram_notif_enabled);

        $this->handle($this->update('/notif on'));
        $this->assertStringContainsString('diaktifkan', $this->lastBotText());
        $this->assertTrue($user->refresh()->telegram_notif_enabled);

        // Status tanpa argumen.
        $this->handle($this->update('/notif'));
        $this->assertStringContainsString('AKTIF', $this->lastBotText());
    }

    // ------------------------------------------------------------------
    // 8. Record flagged via bot → notifikasi ke asisten departemen
    //    (selisih waktu > 4 jam), sama seperti alur live.
    // ------------------------------------------------------------------
    public function test_uat_8_flagged_submission_notifies_department_asisten(): void
    {
        Queue::fake();

        $operator = $this->makeUser(['telegram_user_id' => '888001']);
        $this->makeUser([
            'name' => 'Asisten Proses UAT',
            'phone_number' => '6289876543210',
            'role' => 'asisten',
            'department' => 'proses',
            'telegram_user_id' => '999001',
        ]);

        // Update asli Telegram dengan timestamp kirim 6 jam lalu (date field).
        $message = $this->update('/timbang SPB-UAT-2 25000 9000 4.5')['message'];
        $message['date'] = now()->subHours(6)->timestamp;
        $this->handle(['message' => $message]);

        $record = LogTimbang::where('no_spb', 'SPB-UAT-2')->first();
        $this->assertNotNull($record);
        $this->assertTrue((bool) $record->is_flagged);

        Queue::assertPushed(SendTelegramNotification::class, function ($job) {
            return $job->chatId === '999001';
        });
    }

    // ------------------------------------------------------------------
    // 9. /rekap — dibatasi asisten ke atas; asisten menerima rekap harian
    // ------------------------------------------------------------------
    public function test_uat_9_rekap_command_restricted_then_returns_recap(): void
    {
        // Operator (default) ditolak.
        $this->makeUser(['telegram_user_id' => '888001']);
        $this->handle($this->update('/rekap'));
        $this->assertStringContainsString('Akses Ditolak', $this->lastBotText());

        // Asisten menerima rekap harian.
        $this->makeUser([
            'name' => 'Asisten UAT',
            'phone_number' => '6289876500001',
            'role' => 'asisten',
            'department' => 'proses',
            'telegram_user_id' => '888002',
        ]);
        $this->handle($this->update('/rekap', '888002'));
        $this->assertStringContainsString('REKAP HARIAN POMS', $this->lastBotText('888002'));
    }
}
