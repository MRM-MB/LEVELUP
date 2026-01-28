<?php

use App\Models\Reward;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('admin can archive a reward', function () {
    $admin = adminForUsers();
    $reward = Reward::factory()->create(['archived' => false]);

    $response = $this->actingAs($admin)
        ->from(route('admin.dashboard'))
        ->patch(route('admin.rewards.archive', $reward));

    $response->assertRedirect(route('admin.dashboard'));
    $response->assertSessionHas('success');

    $this->assertDatabaseHas('rewards_catalog', [
        'id' => $reward->id,
        'archived' => true,
    ]);
});

test('admin cannot archive already archived reward', function () {
    $admin = adminForUsers();
    $reward = Reward::factory()->archived()->create();

    $response = $this->actingAs($admin)
        ->patch(route('admin.rewards.archive', $reward));

    $response->assertSessionHas('info', 'This reward is already archived.');
});

test('archive validation fails with invalid reward id', function () {
    $admin = adminForUsers();

    $response = $this->actingAs($admin)
        ->patch(route('admin.rewards.archive', 99999));

    $response->assertNotFound();
});