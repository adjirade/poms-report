<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTelegramPreferencesTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $role, string $phone, ?string $department = null): User
    {
        return User::create([
            'name' => ucfirst($role).' Uji',
            'phone_number' => $phone,
            'password' => 'secret123',
            'role' => $role,
            'department' => $department,
            'plant_id' => 'PKS_01',
            'status' => 'active',
        ]);
    }

    public function test_user_can_update_telegram_notification_preferences(): void
    {
        $operator = $this->makeUser('operator', '628800000001', 'proses');

        $this->actingAs($operator)
            ->put('/profile/telegram-notifications', [
                'telegram_notif_enabled' => '1',
                'telegram_notif_flagged' => '1',
                'telegram_notif_verified' => '0',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $operator->refresh();

        $this->assertTrue($operator->telegram_notif_enabled);
        $this->assertTrue($operator->telegram_notif_flagged);
        $this->assertFalse($operator->telegram_notif_verified);
        $this->assertTrue($operator->wantsTelegramNotification('flagged'));
        $this->assertFalse($operator->wantsTelegramNotification('verified'));
    }

    public function test_unchecked_checkboxes_disable_all_notifications(): void
    {
        $asisten = $this->makeUser('asisten', '628800000002', 'proses');

        $this->actingAs($asisten)
            ->put('/profile/telegram-notifications', []) // semua checkbox kosong
            ->assertRedirect();

        $asisten->refresh();

        $this->assertFalse($asisten->telegram_notif_enabled);
        $this->assertFalse($asisten->telegram_notif_flagged);
        $this->assertFalse($asisten->wantsTelegramNotification('flagged'));
        $this->assertFalse($asisten->wantsTelegramNotification('verified'));
    }

    public function test_profile_page_shows_notification_preferences_card(): void
    {
        $operator = $this->makeUser('operator', '628800000003', 'proses');

        $this->actingAs($operator)
            ->get('/profile')
            ->assertOk()
            ->assertSee('Notifikasi Telegram')
            ->assertSee('telegram_notif_enabled')
            ->assertSee('telegram_notif_flagged')
            ->assertSee('telegram_notif_verified');
    }

    public function test_guest_cannot_update_preferences(): void
    {
        $this->put('/profile/telegram-notifications', ['telegram_notif_enabled' => '1'])
            ->assertRedirect('/login');
    }
}
