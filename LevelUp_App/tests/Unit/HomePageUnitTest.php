<?php

namespace Tests\Unit;

use App\Models\Desk;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomePageUnitTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that the user model provides the correct name for the welcome message.
     */
    public function test_user_name_is_accessible_for_welcome_message(): void
    {
        // Arrange
        $user = User::factory()->create([
            'name' => 'John',
        ]);

        // Act & Assert
        $this->assertEquals('John', $user->name);
    }

    /**
     * Test that the user model provides the total points for the navbar badge.
     */
    public function test_user_total_points_are_accessible_for_navbar(): void
    {
        // Arrange
        $user = User::factory()->create([
            'total_points' => 500,
        ]);

        // Act & Assert
        $this->assertEquals(500, $user->total_points);
    }

    /**
     * Test that the user model correctly relates to a desk for the simulator control.
     */
    public function test_user_can_have_associated_desk_for_control(): void
    {
        // Arrange
        $desk = Desk::factory()->create([
            'serial_number' => 'TEST-DESK-001',
        ]);

        $user = User::factory()->create([
            'desk_id' => $desk->id,
        ]);

        // Act & Assert
        $this->assertNotNull($user->desk);
        $this->assertEquals('TEST-DESK-001', $user->desk->serial_number);
    }

    /**
     * Test that the user has the necessary position attributes for enabling desk control.
     */
    public function test_user_has_position_attributes_for_desk_control(): void
    {
        // Arrange
        $user = User::factory()->create([
            'sitting_position' => 80,
            'standing_position' => 110,
        ]);

        // Act
        $hasPositions = $user->sitting_position && $user->standing_position;

        // Assert
        $this->assertEquals(80, $user->sitting_position);
        $this->assertEquals(110, $user->standing_position);
        $this->assertTrue($hasPositions);
    }

    /**
     * Test that a new user starts with zero total points (database default).
     */
    public function test_new_user_starts_with_zero_points(): void
    {
        // Arrange
        // Use create directly to test database defaults instead of factory random values
        $user = User::create([
            'name' => 'Test',
            'surname' => 'User',
            'username' => 'testuser_defaults',
            'password' => 'password',
        ]);

        // Act & Assert
        $this->assertEquals(0, $user->total_points);
    }

    /**
     * Test that a user might not have a desk assigned.
     */
    public function test_user_can_have_no_desk(): void
    {
        // Arrange
        $user = User::factory()->create(['desk_id' => null]);

        // Act & Assert
        $this->assertNull($user->desk);
    }

    /**
     * Test that user positions can be null (desk control disabled).
     */
    public function test_user_positions_can_be_null(): void
    {
        // Arrange
        $user = User::factory()->create([
            'sitting_position' => null,
            'standing_position' => null,
        ]);

        // Act & Assert
        $this->assertNull($user->sitting_position);
        $this->assertNull($user->standing_position);
    }

    /**
     * Test that the desk model has a serial number attribute.
     */
    public function test_desk_has_serial_number(): void
    {
        // Arrange
        $desk = Desk::factory()->create(['serial_number' => 'SN-12345']);

        // Act & Assert
        $this->assertEquals('SN-12345', $desk->serial_number);
    }

    /**
     * Test that the desk model has a model name attribute.
     */
    public function test_desk_has_model_name(): void
    {
        // Arrange
        $desk = Desk::factory()->create(['desk_model' => 'ProDesk']);

        // Act & Assert
        $this->assertEquals('ProDesk', $desk->desk_model);
    }

    /**
     * Test that daily points are reset on a new day.
     */
    public function test_user_daily_points_reset_on_new_day(): void
    {
        // Arrange
        $user = User::factory()->create([
            'daily_points' => 100,
            'last_points_date' => now()->subDay(),
        ]);

        // Act
        $user->resetDailyPointsIfNeeded();

        // Assert
        $this->assertEquals(0, $user->daily_points);
        $this->assertEquals(now()->toDateString(), $user->last_points_date->toDateString());
    }

    /**
     * Test that adding points updates the total points displayed on the homepage.
     */
    public function test_user_add_points_updates_total(): void
    {
        // Arrange
        $user = User::factory()->create([
            'total_points' => 100,
            'daily_points' => 0,
        ]);

        // Act
        $user->addPoints(50);

        // Assert
        $this->assertEquals(150, $user->total_points);
    }

    /**
     * Test that adding points respects the daily limit.
     */
    public function test_user_cannot_earn_points_over_daily_limit(): void
    {
        // Arrange
        $user = User::factory()->create([
            'total_points' => 0,
            'daily_points' => 150,
            'last_points_date' => now()->toDateString(), // Ensure it's today so it doesn't reset
        ]);

        // Act
        // Try to add 20 points, but limit is 160 (so only 10 should be added)
        $added = $user->addPoints(20);

        // Assert
        $this->assertEquals(10, $added);
        $this->assertEquals(160, $user->daily_points);
        $this->assertEquals(10, $user->total_points);
    }

    /**
     * Test that the user role defaults to 'user'.
     */
    public function test_user_role_defaults_to_user(): void
    {
        // Arrange
        $user = User::factory()->create();

        // Act & Assert
        $this->assertEquals('user', $user->role);
    }

    /**
     * Test that the user date of birth is cast to a Carbon instance.
     */
    public function test_user_date_of_birth_is_carbon_instance(): void
    {
        // Arrange
        $user = User::factory()->create([
            'date_of_birth' => '1990-01-01',
        ]);

        // Act & Assert
        $this->assertInstanceOf(\Carbon\Carbon::class, $user->date_of_birth);
        $this->assertEquals('1990-01-01', $user->date_of_birth->format('Y-m-d'));
    }
}
