<?php

use App\Models\Reward;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('admin can unarchive a reward', function () {
    $admin = adminForUsers();
    $reward = Reward::factory()->archived()->create();

    $response = $this->actingAs($admin)
        ->patch(route('admin.rewards.unarchive', $reward));

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $this->assertDatabaseHas('rewards_catalog', [
        'id' => $reward->id,
        'archived' => false,
    ]);
});

test('admin cannot unarchive already active reward', function () {
    $admin = adminForUsers();
    $reward = Reward::factory()->create(['archived' => false]);

    $response = $this->actingAs($admin)
        ->patch(route('admin.rewards.unarchive', $reward));

    $response->assertSessionHas('info', 'This reward is already active.');
});

test('unarchive validation fails with invalid reward id', function () {
    $admin = adminForUsers();

    $response = $this->actingAs($admin)
        ->patch(route('admin.rewards.unarchive', 99999));

    $response->assertNotFound();
});