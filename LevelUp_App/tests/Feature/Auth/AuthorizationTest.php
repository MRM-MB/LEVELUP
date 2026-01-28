<?php

use App\Models\User;

test('admin users can access admin-only routes', function () {
    // Arrange: Create and authenticate an admin user
    $admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($admin);

    // Test admin routes
    $adminRoutes = [
        'admin.dashboard' => ['tab' => 'desks'],
    ];

    foreach ($adminRoutes as $route => $params) {
        // Act: Access admin route as admin
        $response = $this->get(route($route, $params));

        // Assert: Should load successfully
        $response->assertOk();
        
        // Assert: Should still be authenticated
        $this->assertAuthenticated();
    }
});

test('normal users cannot access admin-only routes', function () {
    // Arrange: Create and authenticate a normal user
    $user = User::factory()->create(['role' => 'user']);
    $this->actingAs($user);

    // Act: Try to access admin dashboard
    $response = $this->get(route('admin.dashboard', ['tab' => 'desks']));

    // Assert: Should be redirected to home with error message
    $response->assertRedirect(route('home'));
    $response->assertSessionHas('error', 'Accesso non autorizzato.');
    
    // Assert: Should still be authenticated (just denied access)
    $this->assertAuthenticated();
});

test('guests cannot access admin routes and get redirected to login first', function () {
    // Act: Try to access admin route as guest
    $response = $this->get(route('admin.dashboard', ['tab' => 'desks']));

    // Assert: Should be redirected to login (auth middleware comes before IsAdmin)
    $response->assertRedirect(route('login'));
    $this->assertGuest();
});

test('admin gate works correctly', function () {
    // Arrange: Create admin and regular user
    $admin = User::factory()->create(['role' => 'admin']);
    $user = User::factory()->create(['role' => 'user']);

    // Test admin user
    $this->actingAs($admin);
    $this->assertTrue(auth()->user()->can('admin'));

    // Test regular user
    $this->actingAs($user);
    $this->assertFalse(auth()->user()->can('admin'));
});

test('role-based navigation visibility', function () {
    // Test admin user sees admin navigation
    $admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($admin);
    
    $response = $this->get(route('home'));
    $response->assertSee('Control Dashboard'); // Admin-only nav item

    // Test normal user doesn't see admin navigation
    $user = User::factory()->create(['role' => 'user']);
    $this->actingAs($user);
    
    $response = $this->get(route('home'));
    $response->assertDontSee('Control Dashboard');
});
