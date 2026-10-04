<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $phone = '628900000001'): User
    {
        return User::create([
            'name' => 'Reset Test',
            'phone_number' => $phone,
            'password' => 'oldpassword',
            'role' => 'operator',
            'department' => 'proses',
            'plant_id' => 'PKS_01',
            'status' => 'active',
        ]);
    }

    public function test_forgot_password_page_renders(): void
    {
        $this->get('/forgot-password')->assertOk()->assertSee('Lupa Password');
    }

    public function test_reset_link_is_sent_to_linked_telegram(): void
    {
        Http::fake();

        $user = $this->makeUser();
        $user->update(['telegram_user_id' => '12345']);

        $this->post('/forgot-password', ['phone_number' => $user->phone_number])
            ->assertRedirect()
            ->assertSessionHas('status');

        Http::assertSent(fn ($request) => str_contains($request->url(), '/sendMessage')
            && $request['chat_id'] === '12345');
    }

    public function test_unknown_phone_does_not_reveal_and_sends_nothing(): void
    {
        Http::fake();

        $this->post('/forgot-password', ['phone_number' => '628000000000'])
            ->assertRedirect()
            ->assertSessionHas('status');

        Http::assertNothingSent();
    }

    public function test_signed_link_can_reset_password(): void
    {
        $user = $this->makeUser();

        $formUrl = URL::temporarySignedRoute('password.reset.form', now()->addMinutes(30), ['user' => $user->id]);
        $this->get($formUrl)->assertOk()->assertSee('Buat Password Baru');

        $postUrl = URL::temporarySignedRoute('password.reset.update', now()->addMinutes(30), ['user' => $user->id]);
        $this->post($postUrl, [
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ])->assertRedirect(route('login'));

        $this->assertTrue(Hash::check('newpassword123', $user->fresh()->password));
    }

    public function test_unsigned_link_is_rejected(): void
    {
        $user = $this->makeUser();

        $this->get("/reset-password/{$user->id}")->assertForbidden();
    }

    public function test_reset_requires_matching_confirmation(): void
    {
        $user = $this->makeUser();

        $postUrl = URL::temporarySignedRoute('password.reset.update', now()->addMinutes(30), ['user' => $user->id]);
        $this->post($postUrl, [
            'password' => 'newpassword123',
            'password_confirmation' => 'different',
        ])->assertSessionHasErrors('password');

        $this->assertTrue(Hash::check('oldpassword', $user->fresh()->password));
    }
}
