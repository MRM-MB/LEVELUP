<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('admin can promote a user', function () {
    $admin = adminForUsers();
    $user  = User::factory()->create(['role' => 'user']);

    $response = $this->actingAs($admin)
        ->from(route('admin.dashboard'))
        ->patch(route('admin.users.promote', $user));

    $response->assertRedirect(route('admin.dashboard'));
    $response->assertSessionHas('success');

    $this->assertDatabaseHas('users', [
        'user_id' => $user->user_id,
        'role'    => 'admin',
    ]);
});

test('promoting an already admin user returns info message', function () {
    $admin = adminForUsers();
    $otherAdmin = User::factory()->create(['role' => 'admin']);

    $response = $this->actingAs($admin)->patch(route('admin.users.promote', $otherAdmin));

    $response->assertSessionHas('info', 'This user is already an admin.');
});
