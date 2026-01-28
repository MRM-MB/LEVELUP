<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\HealthCycle;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class HealthCycleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get the testuser by username
        $user = User::where('username', 'maxmust123')->first();

        if ($user) {
            // Create test health cycle for the user
            HealthCycle::create([
                'user_id' => $user->user_id,
                'sitting_minutes' => 50,
                'standing_minutes' => 35,
                'cycle_number' => 1,
                'health_score' => 80,
                'points_earned' => 100,
                'completed_at' => now()
            ]);
        }
    }
}
