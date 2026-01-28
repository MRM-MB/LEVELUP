<?php

namespace Database\Factories;

use App\Models\HealthCycle;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\HealthCycle>
 */
class HealthCycleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'sitting_minutes' => fake()->numberBetween(1, 240),
            'standing_minutes' => fake()->numberBetween(1, 120),
            'cycle_number' => fake()->numberBetween(1, 10),
            'health_score' => fake()->numberBetween(1, 100),
            'points_earned' => fake()->numberBetween(1, 10),
            'completed_at' => now(),
        ];
    }
}
