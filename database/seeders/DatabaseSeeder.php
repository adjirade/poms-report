<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Data demo dashboard/chart (DemoDataSeeder) hanya dijalankan ketika
     * env POMS_SEED_DEMO=true. Contoh:
     *
     *   POMS_SEED_DEMO=true php artisan migrate:fresh --seed
     *
     * Suasana testing (phpunit) tidak pernah menjalankan demo seeder.
     */
    public function run(): void
    {
        // Seed validation rules first
        $this->call([
            ValidationRulesSeeder::class,
            FirstUserSeeder::class,
        ]);

        // Demo data opsional — lihat config('poms.seed_demo').
        if ((bool) config('poms.seed_demo', false)) {
            $this->call(DemoDataSeeder::class);
        }
    }
}
