<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Account>
 */
class AccountFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->words(2, true),
            'type' => fake()->randomElement(['corrente', 'poupanca', 'dinheiro', 'investimento', 'outro']),
            'initial_balance' => fake()->randomFloat(2, 0, 5000),
            'color' => fake()->hexColor(),
            'is_active' => true,
        ];
    }
}
