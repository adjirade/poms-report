<?php

namespace Tests\Feature;

use App\Jobs\ProcessTelegramMessage;
use App\Models\LogLab;
use App\Models\LogTimbang;
use App\Models\User;
use App\Services\DailyRecapService;
use App\Services\TelegramService;
use App\Services\ValidationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Rekap mingguan: agregat 7 hari (service), perintah bot
 * /rekap_mingguan + /pdf_mingguan (RBAC), dan command otomatis
 * telegram:weekly-recap.
 */
class TelegramWeeklyRecapTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake(['*' => Http::response(['ok' => true])]);
    }

    // ------------------------------------------------------------------
    // Helper
    // ------------------------------------------------------------------

    private function makeUser(array $overrides = []): User
    {
        return User::create(array_merge([
            'name' => 'Penguji UAT',
            'phone_number' => '628999000001',
            'password' => 'secret123',
            'role' => 'operator',
            'department' => 'proses',
            'plant_id' => 'PKS_01',
            'status' => 'active',
        ], $overrides));
    }

    private function makeTimbang(User $user, array $overrides = []): LogTimbang
    {
        return LogTimbang::create(array_merge([
            'user_id' => $user->id,
            'plant_id' => 'PKS_01',
            'no_spb' => 'SPB-'.uniqid(),
            'tonase_bruto' => 25000,
            'tonase_tarra' => 9000,
            'potongan_persen' => 4.5,
            'timestamp_kirim' => now(),
            'timestamp_server' => now(),
            'is_flagged' => false,
            'is_verified' => false,
        ], $overrides));
    }

    private function handle(array $update): void
    {
        (new ProcessTelegramMessage($update['message']))
            ->handle(app(TelegramService::class), app(ValidationService::class));
    }

    private function update(string $text, string $chatId = '888100'): array
    {
        return [
            'update_id' => 1,
            'message' => [
                'message_id' => 101,
                'from' => ['id' => (int) $chatId, 'first_name' => 'Penguji', 'username' => 'uat_user'],
                'chat' => ['id' => (int) $chatId, 'type' => 'private'],
                'text' => $text,
                'date' => now()->timestamp,
            ],
        ];
    }

    private function lastBotText(string $chatId = '888100'): string
    {
        $reqs = collect(Http::recorded())
            ->map(fn ($pair) => $pair[0])
            ->filter(fn ($req) => str_contains($req->url(), 'sendMessage')
                && (string) ($req['chat_id'] ?? '') === $chatId)
            ->values()
            ->all();

        return $reqs === [] ? '' : (string) (end($reqs)['text'] ?? '');
    }

    private function documentRequests(): array
    {
        return collect(Http::recorded())
            ->map(fn ($pair) => $pair[0])
            ->filter(fn ($req) => str_contains($req->url(), 'sendDocument'))
            ->values()
            ->all();
    }

    // ------------------------------------------------------------------
    // Service: weeklySummary + weeklyRecapText + PDF
    // ------------------------------------------------------------------

    public function test_weekly_summary_aggregates_only_records_within_range(): void
    {
        $user = $this->makeUser();

        // 2 record dalam 7 hari terakhir + 1 di luar rentang (20 hari lalu).
        $this->makeTimbang($user, ['tonase_bruto' => 1000000, 'timestamp_kirim' => now()->subDays(1)]);
        $this->makeTimbang($user, ['tonase_bruto' => 500000, 'is_flagged' => true, 'timestamp_kirim' => now()->subDays(3)]);
        $this->makeTimbang($user, ['tonase_bruto' => 999999, 'timestamp_kirim' => now()->subDays(20)]);

        LogLab::create([
            'user_id' => $user->id,
            'plant_id' => 'PKS_01',
            'kadar_alb_cpo' => 3.0,
            'losses_fiber_persen' => 4.0,
            'losses_jankos_persen' => 0.8,
            'timestamp_kirim' => now()->subDays(2),
            'timestamp_server' => now()->subDays(2),
            'is_flagged' => false,
            'is_verified' => false,
        ]);

        $summary = app(DailyRecapService::class)->weeklySummary('PKS_01', now()->subDays(6));

        $this->assertSame(7, $summary['days']);
        $this->assertCount(7, $summary['daily']);
        $this->assertSame(3, $summary['records']); // 2 timbang + 1 lab (stasiun ikut dihitung)
        $this->assertSame(1500.0, $summary['tonnage']); // (1jt + 500rb kg) / 1000; record 999rb di luar rentang tidak ikut
        $this->assertSame(3.0, $summary['ffa']);

        $text = app(DailyRecapService::class)->weeklyRecapText($summary);
        $this->assertStringContainsString('REKAP MINGGUAN', $text);
        $this->assertStringContainsString('Tonnage bruto', $text);
    }

    public function test_last_week_range_is_monday_to_sunday(): void
    {
        [$start, $end] = DailyRecapService::lastWeekRange(now());

        $this->assertSame('Monday', $start->format('l'));
        $this->assertSame('Sunday', $end->format('l'));
        $this->assertSame($start->copy()->addDays(6)->format('Y-m-d'), $end->format('Y-m-d'));
    }

    public function test_weekly_pdf_is_generated(): void
    {
        $user = $this->makeUser();
        $this->makeTimbang($user);

        $summary = app(DailyRecapService::class)->weeklySummary('PKS_01', now()->subDays(6));
        $file = app(DailyRecapService::class)->generateWeeklyPdf($summary);

        $this->assertFileExists(
            storage_path('app/'.$file['path']),
            'PDF mingguan harus tersimpan di storage lokal'
        );
        $this->assertStringContainsString('weekly_recap', $file['filename']);

        \Illuminate\Support\Facades\Storage::disk('local')->delete($file['path']);
    }

    // ------------------------------------------------------------------
    // Command otomatis: telegram:weekly-recap
    // ------------------------------------------------------------------

    public function test_weekly_recap_command_sends_text_and_pdf_to_opted_in_users(): void
    {
        $this->makeUser(['role' => 'manager', 'phone_number' => '628999000002', 'telegram_user_id' => '777200', 'telegram_notif_enabled' => true]);
        $this->makeUser(['role' => 'operator', 'phone_number' => '628999000003', 'telegram_user_id' => '777201', 'telegram_notif_enabled' => true]);
        // Opt-out tidak menerima.
        $optOut = $this->makeUser(['role' => 'asisten', 'phone_number' => '628999000004', 'telegram_user_id' => '777202', 'telegram_notif_enabled' => false]);
        $optOut->update(['telegram_notif_enabled' => false]);

        $this->artisan('telegram:weekly-recap')->assertSuccessful();

        // 1 sendMessage teks (manager) + 1 sendDocument PDF (manager).
        Http::assertSentCount(2);
        $this->assertCount(1, $this->documentRequests());
    }

    public function test_weekly_recap_command_skips_when_no_recipients(): void
    {
        $this->makeUser(['phone_number' => '628999000005']); // tanpa telegram_user_id

        $this->artisan('telegram:weekly-recap', ['--no-pdf' => true])->assertSuccessful();

        Http::assertNothingSent();
    }

    public function test_weekly_recap_command_respects_disable_flag(): void
    {
        $this->makeUser(['role' => 'manager', 'phone_number' => '628999000006', 'telegram_user_id' => '777203', 'telegram_notif_enabled' => true]);
        config(['telegram.recap.weekly_enabled' => false]);

        $this->artisan('telegram:weekly-recap')->assertSuccessful();

        Http::assertNothingSent();
    }

    // ------------------------------------------------------------------
    // Bot: /rekap_mingguan (RBAC asisten+) & /pdf_mingguan (askep+)
    // ------------------------------------------------------------------

    public function test_bot_weekly_recap_denied_for_operator(): void
    {
        $this->makeUser(['telegram_user_id' => '888100']);

        $this->handle($this->update('/rekap_mingguan'));

        $this->assertStringContainsString('Akses Ditolak', $this->lastBotText());
    }

    public function test_bot_weekly_recap_button_text_without_slash_works_for_asisten(): void
    {
        $this->makeUser(['role' => 'asisten', 'telegram_user_id' => '888100']);

        // Tombol keyboard "🗓️ Mingguan" (tanpa slash).
        $this->handle($this->update('🗓️ Mingguan'));

        $this->assertStringContainsString('REKAP MINGGUAN', $this->lastBotText());
    }

    public function test_bot_weekly_recap_includes_web_link_for_askep(): void
    {
        $this->makeUser(['role' => 'askep', 'telegram_user_id' => '888100']);

        $this->handle($this->update('/rekap_mingguan'));

        // Link dibungkus code span agar aman dari parser Markdown Telegram.
        $this->assertStringContainsString('Buka dashboard web', $this->lastBotText());
        $this->assertStringContainsString('`'.rtrim(config('app.url'), '/').'/analytics/weekly-recap`', $this->lastBotText());
    }

    public function test_bot_weekly_recap_hides_web_link_for_asisten(): void
    {
        // Asisten (level 2) menerima rekap, tapi tidak punya gate
        // access-full-dashboard di web — link tidak dikirim agar tidak 403.
        $this->makeUser(['role' => 'asisten', 'telegram_user_id' => '888100']);

        $this->handle($this->update('/rekap_mingguan'));

        $this->assertStringContainsString('REKAP MINGGUAN', $this->lastBotText());
        $this->assertStringNotContainsString('Buka dashboard web', $this->lastBotText());
    }

    public function test_bot_pdf_mingguan_denied_for_asisten(): void
    {
        $this->makeUser(['role' => 'asisten', 'telegram_user_id' => '888100']);

        $this->handle($this->update('/pdf_mingguan'));

        $this->assertStringContainsString('Akses Ditolak', $this->lastBotText());
        $this->assertCount(0, $this->documentRequests());
    }

    public function test_bot_pdf_mingguan_sent_for_askep(): void
    {
        $this->makeUser(['role' => 'askep', 'telegram_user_id' => '888100']);

        $this->handle($this->update('/pdf_mingguan'));

        $text = $this->lastBotText();
        $this->assertStringContainsString('PDF rekap mingguan terkirim', $text);
        $this->assertCount(1, $this->documentRequests()); // dokumen terlampir ke chat
    }

    public function test_bot_menu_contains_weekly_button_for_asisten(): void
    {
        $this->makeUser(['role' => 'asisten', 'telegram_user_id' => '888100']);

        $this->handle($this->update('/menu'));

        $markupReq = collect(Http::recorded())
            ->map(fn ($pair) => $pair[0])
            ->first(fn ($req) => str_contains($req->url(), 'sendMessage')
                && (string) ($req['chat_id'] ?? '') === '888100'
                && isset($req['reply_markup']));

        $this->assertNotNull($markupReq, 'Menu harus menyertakan reply keyboard');
        $keyboard = json_decode((string) $markupReq['reply_markup'], true)['keyboard'];
        $flat = collect($keyboard)->flatten()->all();

        $this->assertContains('🗓️ Mingguan', $flat);
        $this->assertNotContains('📄 PDF Rekap', $flat); // pdf_rekap hanya askep+
    }

    public function test_bot_menu_shows_pdf_buttons_for_askep(): void
    {
        $this->makeUser(['role' => 'askep', 'telegram_user_id' => '888101']);

        $this->handle($this->update('/menu', '888101'));

        $markupReq = collect(Http::recorded())
            ->map(fn ($pair) => $pair[0])
            ->first(fn ($req) => str_contains($req->url(), 'sendMessage')
                && (string) ($req['chat_id'] ?? '') === '888101'
                && isset($req['reply_markup']));

        $this->assertNotNull($markupReq, 'Menu harus menyertakan reply keyboard');
        $keyboard = json_decode((string) $markupReq['reply_markup'], true)['keyboard'];
        $flat = collect($keyboard)->flatten()->all();

        $this->assertContains('📄 PDF Rekap', $flat);
        $this->assertContains('📄 PDF Mingguan', $flat);
    }
}
