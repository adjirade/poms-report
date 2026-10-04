<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\EnvFileEditor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EnvSettingsTest extends TestCase
{
    use RefreshDatabase;

    private string $envPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->envPath = sys_get_temp_dir().'/poms_env_feature_'.uniqid().'.env';
        file_put_contents($this->envPath, implode("\n", [
            'APP_NAME="POMS Report"',
            'TELEGRAM_BOT_TOKEN=old-token',
        ])."\n");

        // Jangan pernah menyentuh .env asli saat test: pakai file sementara.
        $this->app->instance(EnvFileEditor::class, new EnvFileEditor($this->envPath));
    }

    protected function tearDown(): void
    {
        if (is_file($this->envPath)) {
            @unlink($this->envPath);
        }

        parent::tearDown();
    }

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

    public function test_developer_can_view_environment_page(): void
    {
        Http::fake(['*' => Http::response(['ok' => true, 'result' => ['username' => 'poms_bot', 'first_name' => 'POMS']])]);

        $developer = $this->makeUser('developer', '628920000001');

        $this->actingAs($developer)
            ->get('/settings/environment')
            ->assertOk()
            ->assertSee('TELEGRAM_BOT_TOKEN')
            ->assertSee('Environment');
    }

    public function test_non_developer_cannot_view_environment_page(): void
    {
        $manager = $this->makeUser('manager', '628920000002');

        $this->actingAs($manager)->get('/settings/environment')->assertForbidden();
    }

    public function test_update_writes_whitelisted_keys_only(): void
    {
        $developer = $this->makeUser('developer', '628920000003');

        $this->actingAs($developer)
            ->put('/settings/environment', [
                'env' => [
                    'APP_NAME' => 'Pabrik Baru',
                    'TELEGRAM_BOT_USERNAME' => 'bot_baru',
                    'DB_PASSWORD' => 'should-be-ignored',
                ],
            ])
            ->assertRedirect();

        $values = (new EnvFileEditor($this->envPath))->values();

        $this->assertSame('Pabrik Baru', $values['APP_NAME']);
        $this->assertSame('bot_baru', $values['TELEGRAM_BOT_USERNAME']);
        $this->assertStringNotContainsString('should-be-ignored', (string) file_get_contents($this->envPath));
        // Secret yang tidak dikirim tetap utuh.
        $this->assertSame('old-token', $values['TELEGRAM_BOT_TOKEN']);
    }

    public function test_test_bot_reports_success_when_get_me_works(): void
    {
        Http::fake([
            '*/getMe' => Http::response(['ok' => true, 'result' => ['username' => 'poms_bot', 'first_name' => 'POMS']]),
        ]);

        $developer = $this->makeUser('developer', '628920000004');

        $this->actingAs($developer)
            ->post('/settings/environment/test-bot')
            ->assertRedirect()
            ->assertSessionHas('success');
    }
}
