<?php

use App\Models\Reward;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('admin can delete an archived reward', function () {
    $admin = adminForUsers();
    $reward = Reward::factory()->archived()->create();

    $response = $this->actingAs($admin)
        ->delete(route('admin.rewards.destroy', $reward));

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $this->assertDatabaseMissing('rewards_catalog', [
        'id' => $reward->id,
    ]);
});

test('admin cannot delete active (non-archived) reward', function () {
    $admin = adminForUsers();
    $reward = Reward::factory()->create(['archived' => false]);

    $response = $this->actingAs($admin)
        ->delete(route('admin.rewards.destroy', $reward));

    $response->assertSessionHas('error', 'Only archived rewards can be deleted.');
    
    $this->assertDatabaseHas('rewards_catalog', [
        'id' => $reward->id,
        'archived' => false,
    ]);
});

test('deleting archived reward removes favorite relationships', function () {
    $admin = adminForUsers();
    $reward = Reward::factory()->archived()->create();
    $user = User::factory()->create();
    
    $user->favoriteRewards()->attach($reward->id);

    $response = $this->actingAs($admin)
        ->delete(route('admin.rewards.destroy', $reward));

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $this->assertDatabaseMissing('rewards_catalog', [
        'id' => $reward->id,
    ]);
    
    $this->assertDatabaseMissing('user_favorite_rewards', [
        'card_id' => $reward->id,
        'user_id' => $user->user_id,
    ]);
});

test('deleting archived reward removes redemption history', function () {
    $admin = adminForUsers();
    $reward = Reward::factory()->archived()->create();
    $user = User::factory()->create();
    
    $user->redeemedRewards()->attach($reward->id, [
        'redeemed_at' => now()->subDays(5),
        'card_name_snapshot' => $reward->card_name,
        'points_amount_snapshot' => $reward->points_amount,
        'card_description_snapshot' => $reward->card_description,
        'created_at' => now()->subDays(5),
        'updated_at' => now()->subDays(5),
    ]);

    $response = $this->actingAs($admin)
        ->delete(route('admin.rewards.destroy', $reward));

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $this->assertDatabaseMissing('rewards_catalog', [
        'id' => $reward->id,
    ]);
    
    $this->assertDatabaseMissing('user_rewards', [
        'card_id' => $reward->id,
        'user_id' => $user->user_id,
    ]);
});

test('delete validation fails with invalid reward id', function () {
    $admin = adminForUsers();

    $response = $this->actingAs($admin)
        ->delete(route('admin.rewards.destroy', 99999));

    $response->assertNotFound();
});