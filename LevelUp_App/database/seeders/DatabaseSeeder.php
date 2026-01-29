<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        Schema::disableForeignKeyConstraints();

        DB::table('health_cycles')->delete();
        DB::table('user_rewards')->delete();
        DB::table('user_favorite_rewards')->delete();
        DB::table('rewards_catalog')->delete();
        DB::table('users')->delete();
        DB::table('desks')->delete();
        DB::table('sessions')->delete();

        if (DB::getDriverName() === 'sqlite') {
            DB::statement('DELETE FROM sqlite_sequence');
        }

        Schema::enableForeignKeyConstraints();

        // Call all seeders in order
        $this->call([
            DesksSeeder::class,             // Create 10 desks
            UserSeeder::class,              // Create test user first
            HealthCycleSeeder::class,       // Create test health cycles (needs user to exist)
            RewardsCatalogSeeder::class,    // Create reward cards
        ]);
    }
}
