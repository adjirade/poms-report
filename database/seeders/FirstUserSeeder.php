<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class FirstUserSeeder extends Seeder
{
    /**
     * Seed the first developer user for system access.
     * Idempotent: safe to re-run.
     *
     * Password diambil dari env (ADMIN_INITIAL_PASSWORD). Jika env tidak
     * di-set, password acak 24 karakter digenerate dan dicetak SEKALI ke
     * console agar operator menyimpannya — tidak pernah di-hardcode.
     */
    public function run(): void
    {
        $adminPassword = (string) config('poms.bootstrap_admin_password', '');
        $generated = false;

        if ($adminPassword === '') {
            $adminPassword = Str::random(24);
            $generated = true;
        }

        // Create Developer User
        User::updateOrCreate(
            ['phone_number' => '6281234567890'],
            [
                'name' => 'Admin Developer',
                'telegram_user_id' => null,
                'password' => Hash::make($adminPassword),
                'role' => 'developer',
                'department' => null,
                'plant_id' => 'PKS_01',
                'status' => 'active',
            ]
        );

        $this->command->info('✓ Developer user ready (phone: 6281234567890).');

        // Hanya tampilkan password yang DIGENERATE (random), bukan yang
        // berasal dari env — agar tidak membocorkan nilai konfigurasi.
        if ($generated) {
            $this->command->warn("  Password (simpan sekarang, tidak ditampilkan lagi): {$adminPassword}");
        } else {
            $this->command->info('  Password dari ADMIN_INITIAL_PASSWORD (tidak ditampilkan).');
        }

        $this->command->newLine();

        // Create Sample Operator User
        $operatorPassword = (string) config('poms.bootstrap_operator_password', '');
        $operatorGenerated = false;

        if ($operatorPassword === '') {
            $operatorPassword = Str::random(24);
            $operatorGenerated = true;
        }

        User::updateOrCreate(
            ['phone_number' => '6281234567891'],
            [
                'name' => 'Operator Proses',
                'telegram_user_id' => null, // Filled on first Telegram message
                'password' => Hash::make($operatorPassword),
                'role' => 'operator',
                'department' => 'proses',
                'plant_id' => 'PKS_01',
                'status' => 'active',
            ]
        );

        $this->command->info('✓ Sample operator user ready (phone: 6281234567891).');

        if ($operatorGenerated) {
            $this->command->warn("  Password (simpan sekarang, tidak ditampilkan lagi): {$operatorPassword}");
        } else {
            $this->command->info('  Password dari OPERATOR_INITIAL_PASSWORD (tidak ditampilkan).');
        }
    }
}
