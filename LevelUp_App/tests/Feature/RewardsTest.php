<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Reward;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RewardsTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_access_rewards_page(): void
    {
        // Arrange: Create test data
        $user = User::factory()->create(['total_points' => 100]);

        $reward = Reward::create([
            'card_name' => 'Coffee Voucher',
            'points_amount' => 50,
            'card_description' => 'Free coffee',
            'card_image' => 'images/giftcards/placeholder.png',
            'archived' => false,
        ]);

        // Act: Perform the action (visit rewards page as authenticated user)
        $response = $this->actingAs($user)->get(route('rewards.index'));

        // Assert: Verify the results
        $response->assertStatus(200);
        $response->assertViewHas('rewards');
        $response->assertViewHas('savedRewardIds');
        $response->assertViewHas('redeemedRewards');
    }

    public function test_unauthenticated_user_cannot_access_rewards(): void
    {
        // Arrange: No user created (guest user)

        // Act: Try to access rewards page without authentication
        $response = $this->get(route('rewards.index'));

        // Assert: Should be redirected to login
        $response->assertRedirect(route('login'));
    }

    public function test_only_non_archived_rewards_are_displayed(): void
    {
        // Arrange: Create a user and two rewards (one active, one archived)
        $user = User::factory()->create();

        $activeReward = Reward::create([
            'card_name' => 'Active Reward',
            'points_amount' => 50,
            'card_description' => 'This is active',
            'card_image' => 'images/giftcards/placeholder.png',
            'archived' => false,
        ]);

        $archivedReward = Reward::create([
            'card_name' => 'Archived Reward',
            'points_amount' => 100,
            'card_description' => 'This is archived',
            'card_image' => 'images/giftcards/placeholder.png',
            'archived' => true,
        ]);

        // Act: Visit the rewards page
        $response = $this->actingAs($user)->get(route('rewards.index'));

        // Assert: Only the active reward should be in the view
        $response->assertViewHas('rewards', function ($rewards) use ($activeReward, $archivedReward) {
            return $rewards->contains($activeReward) && !$rewards->contains($archivedReward);
        });
    }

    public function test_user_can_save_favorite_reward(): void
    {
        // Arrange: Create a user and a reward
        $user = User::factory()->create();

        $reward = Reward::create([
            'card_name' => 'Favorite Item',
            'points_amount' => 50,
            'card_description' => 'I want this',
            'card_image' => 'images/giftcards/placeholder.png',
            'archived' => false,
        ]);

        // Act: Favorite the reward via API
        $response = $this->actingAs($user)
            ->postJson(route('rewards.toggleSave'), ['reward_id' => $reward->id]);

        // Assert: Verify response and relationship created
        $response
            ->assertOk()
            ->assertJson(['saved' => true]);

        // Verify relationship was created in database
        $this->assertTrue($user->favoriteRewards()->where('card_id', $reward->id)->exists());
        $this->assertEquals(1, $user->favoriteRewards()->count());
    }

    public function test_user_can_unsave_favorite_reward(): void
    {
        // Arrange: Create a user and a reward
        $user = User::factory()->create();

        $reward = Reward::create([
            'card_name' => 'Favorite Item',
            'points_amount' => 50,
            'card_description' => 'I want this',
            'card_image' => 'images/giftcards/placeholder.png',
            'archived' => false,
        ]);

        // User already favorited this reward
        $user->favoriteRewards()->attach($reward->id);

        // Act: Toggle to unsave (remove favorite)
        $response = $this->actingAs($user)
            ->postJson(route('rewards.toggleSave'), ['reward_id' => $reward->id]);

        // Assert: Verify response and relationship removed
        $response
            ->assertOk()
            ->assertJson(['saved' => false]);

        // Verify relationship was removed from database
        $this->assertFalse($user->favoriteRewards()->where('card_id', $reward->id)->exists());
        $this->assertEquals(0, $user->favoriteRewards()->count());
    }

    public function test_user_can_redeem_reward_with_sufficient_points(): void
    {
        // Arrange: Create user with enough points and a reward
        $user = User::factory()->create(['total_points' => 100]);

        $reward = Reward::create([
            'card_name' => 'Premium Snack',
            'points_amount' => 50,
            'card_description' => 'Delicious snack',
            'card_image' => 'images/giftcards/placeholder.png',
            'archived' => false,
        ]);

        // Act: Redeem the reward
        $response = $this->actingAs($user)
            ->postJson(route('rewards.redeem'), ['reward_id' => $reward->id]);

        // Assert: Verify JSON response
        $response
            ->assertOk()
            ->assertJson([
                'success' => true,
                'new_points' => 50,
                'reward_name' => 'Premium Snack',
            ]);

        // Verify points were deducted in database
        $this->assertEquals(50, $user->fresh()->total_points);

        // Verify redemption record was created
        $this->assertEquals(1, $user->redeemedRewards()->count());

        // Verify the redeemed reward is the correct one
        $redeemedReward = $user->redeemedRewards()->first();
        $this->assertEquals($reward->id, $redeemedReward->id);
    }

    public function test_correct_points_deducted_after_redemption(): void
    {
        // Arrange: Create user with 150 points
        $user = User::factory()->create(['total_points' => 150]);

        $reward1 = Reward::create([
            'card_name' => 'Reward 1',
            'points_amount' => 30,
            'card_description' => 'First reward',
            'card_image' => 'images/giftcards/placeholder.png',
            'archived' => false,
        ]);

        $reward2 = Reward::create([
            'card_name' => 'Reward 2',
            'points_amount' => 40,
            'card_description' => 'Second reward',
            'card_image' => 'images/giftcards/placeholder.png',
            'archived' => false,
        ]);

        // Act: Redeem first reward
        $this->actingAs($user)->postJson(route('rewards.redeem'), ['reward_id' => $reward1->id]);

        // Assert: Points should be 120 (150 - 30)
        $this->assertEquals(120, $user->fresh()->total_points);

        // Act: Redeem second reward
        $this->actingAs($user)->postJson(route('rewards.redeem'), ['reward_id' => $reward2->id]);

        // Assert: Points should be 80 (120 - 40)
        $this->assertEquals(80, $user->fresh()->total_points);
    }

    public function test_user_cannot_redeem_with_insufficient_points(): void
    {
        // Arrange: Create user with only 30 points
        $user = User::factory()->create(['total_points' => 30]);

        $reward = Reward::create([
            'card_name' => 'Expensive Item',
            'points_amount' => 50,
            'card_description' => 'Too expensive',
            'card_image' => 'images/giftcards/placeholder.png',
            'archived' => false,
        ]);

        // Act: Try to redeem (should fail)
        $response = $this->actingAs($user)
            ->postJson(route('rewards.redeem'), ['reward_id' => $reward->id]);

        // Assert: Should return 400 error with details
        $response
            ->assertStatus(400)
            ->assertJson([
                'error' => 'Insufficient points',
                'required' => 50,
                'available' => 30,
            ]);

        // Verify points were NOT deducted
        $this->assertEquals(30, $user->fresh()->total_points);

        // Verify no redemption record was created
        $this->assertEquals(0, $user->redeemedRewards()->count());
    }

    public function test_multiple_redemptions_of_same_reward_are_allowed(): void
    {
        // Arrange: Create user with 200 points
        $user = User::factory()->create(['total_points' => 200]);

        $reward = Reward::create([
            'card_name' => 'Coffee Voucher',
            'points_amount' => 50,
            'card_description' => 'Can redeem multiple times',
            'card_image' => 'images/giftcards/placeholder.png',
            'archived' => false,
        ]);

        // Act: Redeem the same reward twice
        $this->actingAs($user)->postJson(route('rewards.redeem'), ['reward_id' => $reward->id]);
        $this->actingAs($user)->postJson(route('rewards.redeem'), ['reward_id' => $reward->id]);

        // Assert: Points should be 100 (200 - 50 - 50)
        $this->assertEquals(100, $user->fresh()->total_points);

        // Verify both redemptions were recorded
        $this->assertEquals(2, $user->redeemedRewards()->count());

        // Verify both redemptions are for the same reward
        $redemptions = $user->redeemedRewards;
        $this->assertEquals($reward->id, $redemptions[0]->id);
        $this->assertEquals($reward->id, $redemptions[1]->id);
    }

    public function test_redemption_creates_snapshot_of_reward_details(): void
    {
        // Arrange: Create user and reward
        $user = User::factory()->create(['total_points' => 100]);

        $reward = Reward::create([
            'card_name' => 'Original Name',
            'points_amount' => 50,
            'card_description' => 'Original Description',
            'card_image' => 'images/giftcards/placeholder.png',
            'archived' => false,
        ]);

        // Act: Redeem the reward
        $this->actingAs($user)->postJson(route('rewards.redeem'), ['reward_id' => $reward->id]);

        // Now change the reward details (simulate admin editing the reward)
        $reward->update([
            'card_name' => 'New Name',
            'points_amount' => 75,
            'card_description' => 'New Description',
        ]);

        // Assert: The redemption should still have the original snapshot values
        $redemption = $user->redeemedRewards()->first();

        $this->assertEquals('Original Name', $redemption->pivot->card_name_snapshot);
        $this->assertEquals(50, $redemption->pivot->points_amount_snapshot);
        $this->assertEquals('Original Description', $redemption->pivot->card_description_snapshot);

        // Verify the reward itself was changed (to confirm our test is valid)
        $this->assertEquals('New Name', $reward->fresh()->card_name);
    }
}