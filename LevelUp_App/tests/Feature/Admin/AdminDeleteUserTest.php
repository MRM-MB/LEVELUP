<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('admin can delete another user', function () {
    $admin = adminForUsers();
    $user = User::factory()->create();

    $response = $this->actingAs($admin)
        ->from(route('admin.dashboard'))
        ->delete(route('admin.users.destroy', $user));

    $response->assertRedirect(route('admin.dashboard'));
    $response->assertSessionHas('success');

    $this->assertDatabaseMissing('users', [
        'user_id' => $user->user_id,
    ]);
});

test('admin cannot delete themselves', function () {
    $admin = adminForUsers();

    $response = $this->actingAs($admin)->delete(route('admin.users.destroy', $admin));

    $response->assertSessionHas('error', 'You cannot delete your own account.');

    $this->assertDatabaseHas('users', [
        'user_id' => $admin->user_id,
    ]);
});
