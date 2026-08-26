<?php

namespace Database\Factories;

use App\Models\Investment;
use App\Models\InvestmentType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Investment>
 */
class InvestmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'investment_type_id' => InvestmentType::factory(),
            'name' => fake()->words(2, true),
            'broker' => fake()->company(),
            'ticker' => null,
            'quantity' => null,
            'invested_amount' => fake()->randomFloat(2, 100, 10000),
            'current_amount' => fake()->randomFloat(2, 100, 10000),
            'color' => fake()->hexColor(),
            'is_active' => true,
        ];
    }

    public function withTicker(string $ticker = 'PETR4'): static
    {
        return $this->state(fn (array $attributes) => [
            'ticker' => $ticker,
            'quantity' => fake()->randomFloat(2, 1, 100),
        ]);
    }
}
