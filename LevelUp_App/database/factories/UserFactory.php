<?php

namespace Database\Factories;

use App\Models\Desk;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->firstName(),
            'surname' => fake()->lastName(),
            'username' => fake()->unique()->userName(),
            'date_of_birth' => fake()->date('Y-m-d'),
            'role' => 'user',
            'password' => static::$password ??= Hash::make('password'),
            'desk_id' => Desk::factory(),
            'total_points' => fake()->numberBetween(0, 1000),
            'daily_points' => fake()->numberBetween(0, 100),
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => $attributes);
    }
}
