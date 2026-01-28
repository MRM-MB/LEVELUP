<?php

namespace Tests\Unit;

use App\Models\Desk;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfilePageUnitTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that user profile data is accessible for display.
     */
    public function test_user_profile_data_is_accessible(): void
    {
        // Arrange
        $user = User::factory()->create([
            'name' => 'Mats',
            'surname' => 'Haertel',
            'username' => 'mhaertel',
            'date_of_birth' => '2004-12-08',
        ]);

        // Act & Assert
        $this->assertEquals('Mats', $user->name);
        $this->assertEquals('Haertel', $user->surname);
        $this->assertEquals('mhaertel', $user->username);
        $this->assertNotNull($user->date_of_birth);
        $this->assertEquals('2004-12-08', $user->date_of_birth->format('Y-m-d'));
    }

    /**
     * Test that user desk positions are accessible and updatable.
     */
    public function test_user_desk_positions_are_accessible(): void
    {
        // Arrange
        $user = User::factory()->create([
            'sitting_position' => 75,
            'standing_position' => 120,
        ]);

        // Act & Assert
        $this->assertEquals(75, $user->sitting_position);
        $this->assertEquals(120, $user->standing_position);

        // Test update
        $user->update([
            'sitting_position' => 80,
            'standing_position' => 115,
        ]);

        $this->assertEquals(80, $user->sitting_position);
        $this->assertEquals(115, $user->standing_position);
    }

    /**
     * Test that user desk relationship is accessible for profile page.
     */
    public function test_user_desk_relationship_is_accessible(): void
    {
        // Arrange
        $desk = Desk::factory()->create([
            'desk_model' => 'Linak Desk',
            'serial_number' => 'cd:fb:1a:53:fb:e6',
        ]);

        $user = User::factory()->create([
            'desk_id' => $desk->id,
        ]);

        // Act
        $userDesk = $user->desk;

        // Assert
        $this->assertNotNull($userDesk);
        $this->assertEquals('Linak Desk', $userDesk->desk_model);
        $this->assertEquals('cd:fb:1a:53:fb:e6', $userDesk->serial_number);
        $this->assertEquals($desk->id, $user->desk_id);
    }
}
