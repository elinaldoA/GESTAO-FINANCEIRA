<?php

namespace Database\Factories;

use App\Models\CreditCard;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CreditCard>
 */
class CreditCardFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->company().' Card',
            'limit_amount' => fake()->randomFloat(2, 500, 20000),
            'closing_day' => fake()->numberBetween(1, 28),
            'due_day' => fake()->numberBetween(1, 28),
            'color' => fake()->hexColor(),
            'is_active' => true,
        ];
    }
}
