<?php

use App\Models\User;

test('user can log in with valid credentials', function () {
    // Arrange: Create a user with known credentials
    $user = User::factory()->create([
        'username' => 'testuser',
        'password' => bcrypt('password123'),
        'role' => 'user'
    ]);

    // Act: Attempt to login
    $response = $this->post(route('login.perform'), [
        'username' => 'testuser',
        'password' => 'password123'
    ]);

    // Assert: Should be redirected to home (intended route)
    $response->assertRedirect(route('home'));
    
    // Assert: User should be authenticated
    $this->assertAuthenticated();
    
    // Assert: Session should be regenerated (security measure)
    $this->assertTrue(auth()->check());
    $this->assertEquals($user->user_id, auth()->id());
});

test('login fails with incorrect password', function () {
    // Arrange: Create a user with known credentials
    $user = User::factory()->create([
        'username' => 'testuser',
        'password' => bcrypt('correctpassword')
    ]);

    // Act: Attempt to login with wrong password
    $response = $this->post(route('login.perform'), [
        'username' => 'testuser',
        'password' => 'wrongpassword'
    ]);

    // Assert: Should return validation errors
    $response->assertSessionHasErrors(['username']);
    
    // Assert: User should not be authenticated
    $this->assertGuest();
});

test('login fails with non-existent account', function () {
    // Act: Attempt to login with non-existent username
    $response = $this->post(route('login.perform'), [
        'username' => 'nonexistent',
        'password' => 'somepassword'
    ]);

    // Assert: Should return validation errors
    $response->assertSessionHasErrors(['username']);
    
    // Assert: User should not be authenticated
    $this->assertGuest();
});

test('authenticated users are redirected away from login page', function () {
    // Arrange: Create and authenticate a user
    $user = User::factory()->create();
    $this->actingAs($user);

    // Act: Try to access login page while authenticated
    $response = $this->get(route('login'));

    // Assert: Should be redirected away (guest middleware should prevent access)
    // Laravel's guest middleware typically redirects to home
    $response->assertRedirect(route('home'));
});

test('successful login redirects to intended page', function () {
    // Arrange: Create a user
    $user = User::factory()->create([
        'username' => 'testuser',
        'password' => bcrypt('password123')
    ]);
    
    // First, try to access a protected page as guest (should redirect to login)
    $response = $this->get(route('statistics'));
    $response->assertRedirect(route('login'));

    // Act: Login after attempting to access protected page
    $response = $this->post(route('login.perform'), [
        'username' => 'testuser',
        'password' => 'password123'
    ]);

    // Assert: Should be redirected to the originally intended page (statistics)
    $response->assertRedirect(route('statistics'));
});