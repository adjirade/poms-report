<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginAndRbacTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
        $response->assertSee('Nomor Telepon');
    }

    public function test_user_can_login_with_phone_number_and_password(): void
    {
        $user = User::create([
            'name' => 'Manager Test',
            'phone_number' => '628111111111',
            'password' => 'secret123',
            'role' => 'manager',
            'department' => null,
            'plant_id' => 'PKS_01',
            'status' => 'active',
        ]);

        $response = $this->post('/login', [
            'phone_number' => '628111111111',
            'password' => 'secret123',
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
    }

    public function test_guest_is_redirected_to_login_from_dashboard(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_operator_cannot_access_web_dashboard(): void
    {
        $operator = User::create([
            'name' => 'Operator Test',
            'phone_number' => '628222222222',
            'password' => 'secret123',
            'role' => 'operator',
            'department' => 'proses',
            'plant_id' => 'PKS_01',
            'status' => 'active',
        ]);

        $this->actingAs($operator)
            ->get('/dashboard')
            ->assertForbidden();
    }

    public function test_manager_can_access_dashboard_and_analytics(): void
    {
        $manager = User::create([
            'name' => 'Manager Test 2',
            'phone_number' => '628333333333',
            'password' => 'secret123',
            'role' => 'manager',
            'department' => null,
            'plant_id' => 'PKS_01',
            'status' => 'active',
        ]);

        $this->actingAs($manager)->get('/dashboard')->assertOk();
        $this->actingAs($manager)->get('/analytics/overview')->assertOk();
        $this->actingAs($manager)->get('/analytics/losses')->assertOk();
        $this->actingAs($manager)->get('/analytics/efficiency')->assertOk();
    }

    public function test_asisten_is_limited_to_department_stations(): void
    {
        User::create([
            'name' => 'Asisten Lab',
            'phone_number' => '628444444444',
            'password' => 'secret123',
            'role' => 'asisten',
            'department' => 'lab',
            'plant_id' => 'PKS_01',
            'status' => 'active',
        ]);

        $this->actingAs(User::where('phone_number', '628444444444')->first())
            ->get('/stations/lab')
            ->assertOk();

        $this->actingAs(User::where('phone_number', '628444444444')->first())
            ->get('/stations/timbang')
            ->assertForbidden();
    }

    public function test_hq_admin_gates(): void
    {
        User::create([
            'name' => 'HQ Admin',
            'phone_number' => '628555555555',
            'password' => 'secret123',
            'role' => 'hq_admin',
            'department' => null,
            'plant_id' => 'PKS_01',
            'status' => 'active',
        ]);

        $this->actingAs(User::where('phone_number', '628555555555')->first())
            ->get('/hq/dashboard')
            ->assertOk();

        $this->actingAs(User::where('phone_number', '628555555555')->first())
            ->get('/hq/comparison')
            ->assertOk();
    }

    public function test_debug_auth_route_is_developer_only(): void
    {
        User::create([
            'name' => 'Manager X',
            'phone_number' => '628666666666',
            'password' => 'secret123',
            'role' => 'manager',
            'department' => null,
            'plant_id' => 'PKS_01',
            'status' => 'active',
        ]);

        $this->actingAs(User::where('phone_number', '628666666666')->first())
            ->get('/debug-auth')
            ->assertForbidden();
    }
}
