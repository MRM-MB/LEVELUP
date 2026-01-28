<?php

namespace Database\Factories;

use App\Models\Reward;
use Illuminate\Database\Eloquent\Factories\Factory;

class RewardFactory extends Factory
{
    protected $model = Reward::class;

    public function definition(): array
    {
        return [
            'card_name' => $this->faker->words(3, true),
            'card_description' => $this->faker->sentence(),
            'points_amount' => $this->faker->numberBetween(50, 500),
            'card_image' => 'default-reward.jpg',
            'archived' => false,
        ];
    }

    public function archived(): static
    {
        return $this->state(fn (array $attributes) => [
            'archived' => true,
        ]);
    }
}