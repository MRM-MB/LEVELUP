<?php

namespace Tests\Unit;

use App\Models\User;
use App\Models\Reward;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RewardRelationshipTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_favorite_rewards_relationship_is_configured_correctly(): void
    {
        // Arrange: Create a user instance
        $user = new User();

        // Act: Get the relationship
        $relationship = $user->favoriteRewards();

        // Assert: Verify relationship type
        $this->assertInstanceOf(BelongsToMany::class, $relationship);

        // Assert: Verify pivot table name
        $this->assertEquals('user_favorite_rewards', $relationship->getTable());

        // Assert: Verify foreign key
        $this->assertEquals('user_id', $relationship->getForeignPivotKeyName());

        // Assert: Verify related key
        $this->assertEquals('card_id', $relationship->getRelatedPivotKeyName());
    }

    public function test_user_redeemed_rewards_relationship_is_configured_correctly(): void
    {
        // Arrange: Create a user instance
        $user = new User();

        // Act: Get the relationship
        $relationship = $user->redeemedRewards();

        // Assert: Verify relationship type
        $this->assertInstanceOf(BelongsToMany::class, $relationship);

        // Assert: Verify pivot table name
        $this->assertEquals('user_rewards', $relationship->getTable());

        // Assert: Verify foreign key
        $this->assertEquals('user_id', $relationship->getForeignPivotKeyName());

        // Assert: Verify related key
        $this->assertEquals('card_id', $relationship->getRelatedPivotKeyName());

        // Assert: Verify pivot columns are included
        $pivotColumns = $relationship->getPivotColumns();
        $this->assertContains('redeemed_at', $pivotColumns);
        $this->assertContains('card_name_snapshot', $pivotColumns);
        $this->assertContains('points_amount_snapshot', $pivotColumns);
        $this->assertContains('card_description_snapshot', $pivotColumns);
    }

    public function test_reward_favorited_by_relationship_is_configured_correctly(): void
    {
        // Arrange: Create a reward instance
        $reward = new Reward();

        // Act: Get the relationship
        $relationship = $reward->favoritedBy();

        // Assert: Verify relationship type
        $this->assertInstanceOf(BelongsToMany::class, $relationship);

        // Assert: Verify pivot table name
        $this->assertEquals('user_favorite_rewards', $relationship->getTable());

        // Assert: Verify foreign key
        $this->assertEquals('card_id', $relationship->getForeignPivotKeyName());

        // Assert: Verify related key
        $this->assertEquals('user_id', $relationship->getRelatedPivotKeyName());
    }
}
