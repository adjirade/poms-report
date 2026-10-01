<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Seed validation rules first
        $this->call([
            ValidationRulesSeeder::class,
            FirstUserSeeder::class,
        ]);
    }
}
