<?php

namespace Database\Factories;

use App\Models\Goal;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Goal>
 */
class GoalFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->words(3, true),
            'target_amount' => fake()->randomFloat(2, 1000, 50000),
            'current_amount' => 0,
            'target_date' => fake()->dateTimeBetween('+1 month', '+2 years'),
            'color' => fake()->hexColor(),
            'is_active' => true,
        ];
    }
}
