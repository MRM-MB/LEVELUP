<?php

use App\Models\User;

test('authenticated user can access their profile page', function () {
    // Arrange: Create and authenticate a user
    $user = User::factory()->create([
        'name' => 'Gabriele',
        'surname' => 'Solazzo',
        'username' => 'Gabbo',
        'date_of_birth' => '2005-08-24',
        'sitting_position' => 70,
        'standing_position' => 120,
    ]);
    
    $this->actingAs($user);

    // Act: Access profile page
    $response = $this->get(route('profile'));

    // Assert: Should load successfully
    $response->assertOk();
    $response->assertViewIs('profile');
    
    // Assert: Should have user data in view
    $response->assertViewHas('user');
    
    // Assert: Page should display user information
    $response->assertSee('Gabriele');
    $response->assertSee('Solazzo');
    $response->assertSee('Gabbo');
});

test('profile page loads correct user data', function () {
    // Arrange: Create users with different data
    $user1 = User::factory()->create([
        'name' => 'Mats',
        'surname' => 'Haertel',
        'username' => 'mqts',
        'total_points' => 150,
    ]);
    
    $user2 = User::factory()->create([
        'name' => 'Max',
        'surname' => 'Mustermann', 
        'username' => 'maxmust123',
        'total_points' => 200,
    ]);

    // Test user1's profile
    $this->actingAs($user1);
    $response = $this->get(route('profile'));
    
    $response->assertOk();
    $response->assertSee('Mats');
    $response->assertSee('Haertel');
    $response->assertSee('mqts');
    $response->assertDontSee('Max');
    $response->assertDontSee('Mustermann');

    // Test user2's profile
    $this->actingAs($user2);
    $response = $this->get(route('profile'));
    
    $response->assertOk();
    $response->assertSee('Max');
    $response->assertSee('Mustermann');
    $response->assertSee('maxmust123');
    $response->assertDontSee('Mats');
    $response->assertDontSee('Haertel');
});

test('guests cannot access profile page', function () {
    // Act: Try to access profile page as guest
    $response = $this->get(route('profile'));

    // Assert: Should be redirected to login
    $response->assertRedirect(route('login'));
    $this->assertGuest();
});
