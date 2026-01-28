<?php

use App\Models\User;

test('guests cannot access protected routes and get redirected to login', function () {
    // Test multiple protected routes
    $protectedRoutes = [
        ['method' => 'get', 'route' => 'profile'],
        ['method' => 'get', 'route' => 'statistics'],
        ['method' => 'get', 'route' => 'rewards.index'],
    ];

    foreach ($protectedRoutes as $routeInfo) {
        // Act: Try to access protected route as guest
        $response = $this->{$routeInfo['method']}(route($routeInfo['route']));

        // Assert: Should be redirected to login
        $response->assertRedirect(route('login'));
        
        // Assert: Should still be a guest
        $this->assertGuest();
    }
});

test('authenticated users can access protected routes', function () {
    // Arrange: Create and authenticate a user
    $user = User::factory()->create(['role' => 'user']);
    $this->actingAs($user);

    // Test accessing protected routes
    $protectedRoutes = [
        ['route' => 'profile', 'view' => 'profile'],
        ['route' => 'statistics', 'view' => 'statistics'],
        ['route' => 'rewards.index', 'view' => 'rewards.rewards'],
    ];

    foreach ($protectedRoutes as $routeInfo) {
        // Act: Access protected route as authenticated user
        $response = $this->get(route($routeInfo['route']));

        // Assert: Should load successfully
        $response->assertOk();
        $response->assertViewIs($routeInfo['view']);
        
        // Assert: Should still be authenticated
        $this->assertAuthenticated();
    }
});
