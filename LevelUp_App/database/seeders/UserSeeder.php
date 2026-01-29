<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Desk;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Try to grab the first two desks (if they exist)
        $deskIds = Desk::query()
            ->orderBy('id')
            ->take(2)
            ->pluck('id')
            ->values();
        
        if ($deskIds->isEmpty()) {
            throw new \RuntimeException('You must seed desks before seeding users.');
        }
        
        $adminDeskId = $deskIds->get(0); // first desk
        $userDeskId  = $deskIds->get(1, $adminDeskId); // second desk or first if only one

        // Create a complete admin test user (all fields populated)
        User::create([
            'name'              => 'Admin',
            'surname'           => 'Admin',
            'username'          => 'admin',
            'date_of_birth'     => '1990-01-15',
            'role'              => 'admin',
            'password'          => Hash::make('LevelUp!Demo#2026'),
            'sitting_position'  => 73,
            'standing_position' => 110,
            'total_points'      => 0,
            'daily_points'      => 0,
            'last_points_date'  => now()->toDateString(),
            'last_daily_reset'  => now(),
            'desk_id'           => $adminDeskId,
        ]);

        // Create a regular user with some progress
        User::create([
            'name'              => 'Max',
            'surname'           => 'Mustermann',
            'username'          => 'maxmust123',
            'date_of_birth'     => '1995-05-20',
            'password'          => Hash::make('LevelUp!User#2026'),
            'sitting_position'  => 75,
            'standing_position' => 105,
            'total_points'      => 0,
            'daily_points'      => 0,
            'last_points_date'  => now()->toDateString(),
            'desk_id'           => $userDeskId,   // may be the same as admin if only one desk
        ]);
    }
}
