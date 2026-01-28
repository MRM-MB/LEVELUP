<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('admin can demote another admin', function () {
    $admin = adminForUsers();
    $otherAdmin = User::factory()->create(['role' => 'admin']);

    $response = $this->actingAs($admin)
        ->from(route('admin.dashboard'))
        ->patch(route('admin.users.demote', $otherAdmin));

    $response->assertRedirect(route('admin.dashboard'));
    $response->assertSessionHas('success');

    $this->assertDatabaseHas('users', [
        'user_id' => $otherAdmin->user_id,
        'role'    => 'user',
    ]);
});

test('admin cannot demote themselves', function () {
    $admin = adminForUsers();

    $response = $this->actingAs($admin)->patch(route('admin.users.demote', $admin));

    $response->assertSessionHas('error', 'You cannot demote yourself.');

    $this->assertDatabaseHas('users', [
        'user_id' => $admin->user_id,
        'role' => 'admin',
    ]);
});

test('demoting a non-admin user returns appropriate info message', function () {
    $admin = adminForUsers();
    $user = User::factory()->create(['role' => 'user']);

    $response = $this->actingAs($admin)->patch(route('admin.users.demote', $user));

    $response->assertSessionHas('info', 'This user is not an admin.');
});
