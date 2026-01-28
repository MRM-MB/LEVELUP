<?php

use App\Models\User;

test('user can log out successfully', function () {
    // Arrange: Create and authenticate a user
    $user = User::factory()->create();
    $this->actingAs($user);
    
    // Verify user is authenticated before logout
    $this->assertAuthenticated();

    // Act: Logout
    $response = $this->post(route('logout'));

    // Assert: Should be redirected to login page
    $response->assertRedirect(route('login'));
    
    // Assert: User should no longer be authenticated
    $this->assertGuest();
    
    // Assert: Auth::check() should return false
    $this->assertFalse(auth()->check());
});

test('logout clears user session', function () {
    // Arrange: Create and authenticate a user
    $user = User::factory()->create();
    $this->actingAs($user);
    
    // Set some session data to verify it gets cleared
    session(['test_key' => 'test_value']);
    $this->assertEquals('test_value', session('test_key'));

    // Act: Logout
    $response = $this->post(route('logout'));

    // Assert: Session should be invalidated and regenerated
    $this->assertNull(session('test_key'));
    $this->assertGuest();
});

test('logout requires authentication', function () {
    // Act: Try to logout without being authenticated
    $response = $this->post(route('logout'));

    // Assert: Should be redirected to login (auth middleware should prevent access)
    $response->assertRedirect(route('login'));
});
