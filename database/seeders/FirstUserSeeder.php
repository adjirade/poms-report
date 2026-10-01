<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class FirstUserSeeder extends Seeder
{
    /**
     * Seed the first developer user for system access.
     * Idempotent: safe to re-run.
     */
    public function run(): void
    {
        // Create Developer User
        $developer = User::updateOrCreate(
            ['phone_number' => '6281234567890'],
            [
                'name' => 'Admin Developer',
                'telegram_user_id' => null,
                'password' => Hash::make('password123'),
                'role' => 'developer',
                'department' => null,
                'plant_id' => 'PKS_01',
                'status' => 'active',
            ]
        );

        $this->command->info('✓ Developer user ready (phone: 6281234567890).');

        // Create Sample Operator User
        User::updateOrCreate(
            ['phone_number' => '6281234567891'],
            [
                'name' => 'Operator Proses',
                'telegram_user_id' => null, // Filled on first Telegram message
                'password' => Hash::make('password'),
                'role' => 'operator',
                'department' => 'proses',
                'plant_id' => 'PKS_01',
                'status' => 'active',
            ]
        );

        $this->command->info('✓ Sample operator user ready (phone: 6281234567891).');
    }
}
