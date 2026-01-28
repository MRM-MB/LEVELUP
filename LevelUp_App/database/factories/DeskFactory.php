<?php

namespace Database\Factories;

use App\Models\Desk;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<\App\Models\Desk>
 */
class DeskFactory extends Factory
{
    protected $model = Desk::class;

    public function definition(): array
    {
        return [
            'desk_model' => fake()->randomElement(['FocusLift', 'HealthDesk', 'LevelUpFlex']),
            'serial_number' => strtoupper('DL-' . Str::random(10)),
        ];
    }
}
