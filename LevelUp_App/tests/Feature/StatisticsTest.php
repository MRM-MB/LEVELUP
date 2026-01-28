<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\HealthCycle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StatisticsTest extends TestCase
{
    use RefreshDatabase;

    // Test that the bar chart displays correct sitting and standing minutes for today
    public function test_bar_chart_displays_correct_minutes_for_user()
    {
        // Arrange: Create a test user with username "maxmust123"
        $user = User::factory()->create([
            'username' => 'maxmust123',
        ]);

        // Create a health cycle for today with specific sitting/standing minutes
        $healthCycle = HealthCycle::factory()->create([
            'user_id' => $user->user_id,
            'sitting_minutes' => 120,      // 2 hours sitting
            'standing_minutes' => 45,      // 45 minutes standing
            'completed_at' => now(),       // Today's date
        ]);

        // Act: Authenticate as the user and visit the statistics page
        $response = $this->actingAs($user)->get('/statistics');

        // Assert: Check that the response is successful
        $response->assertStatus(200);

        // Assert: Verify the correct data is passed to the view
        $response->assertViewHas('healthCycle', function ($viewHealthCycle) use ($healthCycle) {
            return $viewHealthCycle->sitting_minutes === 120
                && $viewHealthCycle->standing_minutes === 45
                && $viewHealthCycle->id === $healthCycle->id;
        });

        // Assert: Verify the page contains the expected minutes
        $response->assertSeeText('120');  // sitting minutes
        $response->assertSeeText('45');   // standing minutes
    }

    // Test that only today's data appears in the bar chart, not historical data
    public function test_bar_chart_only_shows_todays_data()
    {
        // Arrange: Create a user
        $user = User::factory()->create([
            'username' => 'maxmust123',
        ]);

        // Create health cycles: one from yesterday, one from today
        $yesterdaysCycle = HealthCycle::factory()->create([
            'user_id' => $user->user_id,
            'sitting_minutes' => 200,
            'standing_minutes' => 100,
            'completed_at' => now()->subDay(),  // Yesterday
        ]);

        $todaysCycle = HealthCycle::factory()->create([
            'user_id' => $user->user_id,
            'sitting_minutes' => 120,
            'standing_minutes' => 45,
            'completed_at' => now(),  // Today
        ]);

        // Act: Visit the statistics page as the user
        $response = $this->actingAs($user)->get('/statistics');

        // Assert: Only today's data should be in the view
        $response->assertViewHas('healthCycle', $todaysCycle);
    }

    // Test that the pie chart displays correct all-time totals
    public function test_pie_chart_displays_correct_all_time_totals()
    {
        // Arrange: Create a user
        $user = User::factory()->create([
            'username' => 'maxmust123',
        ]);

        // Create multiple health cycles across different days
        HealthCycle::factory()->create([
            'user_id' => $user->user_id,
            'sitting_minutes' => 100,
            'standing_minutes' => 50,
            'completed_at' => now()->subDays(3),
        ]);

        HealthCycle::factory()->create([
            'user_id' => $user->user_id,
            'sitting_minutes' => 120,
            'standing_minutes' => 45,
            'completed_at' => now()->subDay(),
        ]);

        HealthCycle::factory()->create([
            'user_id' => $user->user_id,
            'sitting_minutes' => 80,
            'standing_minutes' => 60,
            'completed_at' => now(),
        ]);

        // Act: Visit the statistics page
        $response = $this->actingAs($user)->get('/statistics');

        // Assert: Verify all-time totals are correct
        // Total sitting: 100 + 120 + 80 = 300
        // Total standing: 50 + 45 + 60 = 155
        $response->assertViewHas('totalSitting', 300);
        $response->assertViewHas('totalStanding', 155);
    }

    // Test that unauthenticated users cannot access statistics
    public function test_unauthenticated_user_cannot_access_statistics()
    {
        // Act: Try to access statistics without authentication
        $response = $this->get('/statistics');

        // Assert: Should be redirected to login
        $response->assertRedirect('/login');
    }

    // Test that users only see their own data, not other users' data
    public function test_user_only_sees_own_statistics_data()
    {
        // Arrange: Create two users
        $user_1 = User::factory()->create(['username' => 'maxmust123']);
        $user_2 = User::factory()->create(['username' => 'annenie123']);

        // Create health cycles for both users
        HealthCycle::factory()->create([
            'user_id' => $user_1->user_id,
            'sitting_minutes' => 120,
            'standing_minutes' => 45,
            'completed_at' => now(),
        ]);

        HealthCycle::factory()->create([
            'user_id' => $user_2->user_id,
            'sitting_minutes' => 200,
            'standing_minutes' => 100,
            'completed_at' => now(),
        ]);

        // Act: View statistics as user_1
        $response = $this->actingAs($user_1)->get('/statistics');

        // Assert: Only user_1's totals should be shown
        $response->assertViewHas('totalSitting', 120);
        $response->assertViewHas('totalStanding', 45);
    }
}
