<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class FirstUserSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_uses_config_password_without_printing_it(): void
    {
        config(['poms.bootstrap_admin_password' => 'rahasia-dari-env']);
        config(['poms.bootstrap_operator_password' => 'rahasia-operator']);

        $this->artisan('db:seed', ['--class' => 'Database\Seeders\FirstUserSeeder', '--force' => true])
            ->expectsOutputToContain('tidak ditampilkan')
            ->assertSuccessful();

        $admin = User::where('phone_number', '6281234567890')->first();

        $this->assertNotNull($admin);
        $this->assertTrue(Hash::check('rahasia-dari-env', $admin->password));
    }

    public function test_generates_random_password_when_config_empty(): void
    {
        config(['poms.bootstrap_admin_password' => '']);

        $this->artisan('db:seed', ['--class' => 'Database\Seeders\FirstUserSeeder', '--force' => true])
            ->expectsOutputToContain('simpan sekarang')
            ->assertSuccessful();

        $admin = User::where('phone_number', '6281234567890')->first();

        $this->assertNotNull($admin);
        $this->assertFalse(Hash::check('password123', $admin->password));
        $this->assertFalse(Hash::check('password', $admin->password));
    }

    public function test_seeder_is_idempotent(): void
    {
        config(['poms.bootstrap_admin_password' => 'pw-1']);
        config(['poms.bootstrap_operator_password' => 'pw-2']);

        $this->artisan('db:seed', ['--class' => 'Database\Seeders\FirstUserSeeder', '--force' => true])->assertSuccessful();
        $this->artisan('db:seed', ['--class' => 'Database\Seeders\FirstUserSeeder', '--force' => true])->assertSuccessful();

        $this->assertSame(1, User::where('phone_number', '6281234567890')->count());
        $this->assertSame(1, User::where('phone_number', '6281234567891')->count());
        $this->assertTrue(Hash::check('pw-1', User::where('phone_number', '6281234567890')->first()->password));
    }
}
