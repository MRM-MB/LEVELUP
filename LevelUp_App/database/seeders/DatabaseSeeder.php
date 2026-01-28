<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Call all seeders in order
        $this->call([
            DesksSeeder::class,             // Create 10 desks
            UserSeeder::class,              // Create test user first
            HealthCycleSeeder::class,       // Create test health cycles (needs user to exist)
            RewardsCatalogSeeder::class,    // Create reward cards
        ]);
    }
}
