<?php

namespace Tests\Feature;

use App\Livewire\UserManager;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $role, string $phone, string $password = 'secret123'): User
    {
        return User::create([
            'name' => ucfirst($role).' Test',
            'phone_number' => $phone,
            'password' => $password,
            'role' => $role,
            'department' => in_array($role, ['operator', 'asisten'], true) ? 'proses' : null,
            'plant_id' => 'PKS_01',
            'status' => 'active',
        ]);
    }

    public function test_developer_can_access_user_management_page(): void
    {
        $this->actingAs($this->makeUser('developer', '628900000001'))
            ->get('/settings/users')
            ->assertOk();
    }

    public function test_non_developer_cannot_access_user_management_page(): void
    {
        $this->actingAs($this->makeUser('manager', '628900000002'))
            ->get('/settings/users')
            ->assertForbidden();
    }

    public function test_developer_can_create_user(): void
    {
        Livewire::actingAs($this->makeUser('developer', '628900000003'))
            ->test(UserManager::class)
            ->call('openCreate')
            ->set('name', 'Budi Baru')
            ->set('phone_number', '628999000111')
            ->set('role', 'operator')
            ->set('department', 'proses')
            ->set('plant_id', 'PKS_01')
            ->set('status', 'active')
            ->set('password', 'password123')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('users', [
            'phone_number' => '628999000111',
            'role' => 'operator',
            'department' => 'proses',
        ]);
    }

    public function test_developer_can_change_role_and_department(): void
    {
        $target = $this->makeUser('operator', '628900000004');

        Livewire::actingAs($this->makeUser('developer', '628900000005'))
            ->test(UserManager::class)
            ->call('openEdit', $target->id)
            ->set('role', 'asisten')
            ->set('department', 'lab')
            ->call('save')
            ->assertHasNoErrors();

        $fresh = $target->fresh();
        $this->assertSame('asisten', $fresh->role);
        $this->assertSame('lab', $fresh->department);
    }

    public function test_department_is_cleared_for_non_operator_roles(): void
    {
        $target = $this->makeUser('operator', '628900000006');

        Livewire::actingAs($this->makeUser('developer', '628900000007'))
            ->test(UserManager::class)
            ->call('openEdit', $target->id)
            ->set('role', 'manager')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertNull($target->fresh()->department);
    }

    public function test_developer_can_reset_password(): void
    {
        $target = $this->makeUser('operator', '628900000008', 'oldpassword');

        $component = Livewire::actingAs($this->makeUser('developer', '628900000009'))
            ->test(UserManager::class)
            ->call('resetPassword', $target->id);

        $generated = $component->get('generatedPassword');
        $this->assertIsString($generated);
        $this->assertGreaterThanOrEqual(8, strlen($generated));

        $fresh = $target->fresh();
        $this->assertTrue(Hash::check($generated, $fresh->password));
        $this->assertFalse(Hash::check('oldpassword', $fresh->password));
    }

    public function test_developer_cannot_delete_own_account(): void
    {
        $dev = $this->makeUser('developer', '628900000010');

        Livewire::actingAs($dev)->test(UserManager::class)->call('delete', $dev->id);

        $this->assertDatabaseHas('users', ['id' => $dev->id]);
    }

    public function test_developer_can_delete_another_developer(): void
    {
        $dev = $this->makeUser('developer', '628900000011');
        $other = $this->makeUser('developer', '628900000012');

        Livewire::actingAs($dev)->test(UserManager::class)->call('delete', $other->id);

        $this->assertDatabaseMissing('users', ['id' => $other->id]);
        $this->assertDatabaseHas('users', ['id' => $dev->id]);
    }

    public function test_operator_cannot_access_user_management(): void
    {
        $this->actingAs($this->makeUser('operator', '628900000013', 'secret123'))
            ->get('/settings/users')
            ->assertForbidden();
    }
}
