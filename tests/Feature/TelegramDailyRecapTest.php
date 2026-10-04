<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TelegramDailyRecapTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $role, string $phone, ?string $telegramId = null): User
    {
        return User::create([
            'name' => ucfirst($role).' Uji',
            'phone_number' => $phone,
            'password' => 'secret123',
            'role' => $role,
            'plant_id' => 'PKS_01',
            'status' => 'active',
            'telegram_user_id' => $telegramId,
            'telegram_notif_enabled' => true,
        ]);
    }

    public function test_recap_sends_message_and_pdf_to_opted_in_managers(): void
    {
        Http::fake(['*' => Http::response(['ok' => true])]);

        $this->makeUser('manager', '629550000001', '777001');
        $this->makeUser('asisten', '629550000002', '777002');
        // Operator TIDAK jadi penerima (role < asisten).
        $this->makeUser('operator', '629550000003', '777003');
        // Manager opt-out tidak menerima.
        $optOut = $this->makeUser('manager', '629550000004', '777004');
        $optOut->update(['telegram_notif_enabled' => false]);

        $this->artisan('telegram:daily-recap', ['--no-pdf' => true])
            ->assertSuccessful();

        // sendMessage: 2 penerima (manager + asisten yang opt-in).
        Http::assertSentCount(2);
    }

    public function test_recap_with_pdf_attaches_document(): void
    {
        Http::fake(['*' => Http::response(['ok' => true])]);

        $this->makeUser('manager', '629550000005', '777005');

        $this->artisan('telegram:daily-recap')
            ->assertSuccessful();

        // 1 sendMessage (teks) + 1 sendDocument (PDF).
        Http::assertSentCount(2);
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'sendDocument');
        });
    }

    public function test_recap_skips_when_no_recipients(): void
    {
        Http::fake(['*' => Http::response(['ok' => true])]);

        $this->makeUser('manager', '629550000006'); // tanpa telegram_user_id

        $this->artisan('telegram:daily-recap', ['--no-pdf' => true])
            ->assertSuccessful();

        Http::assertNothingSent();
    }
}
