<?php

namespace Database\Factories;

use App\Models\Dividend;
use App\Models\Investment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Dividend>
 */
class DividendFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'investment_id' => Investment::factory(),
            'date' => fake()->date(),
            'type' => fake()->randomElement(['dividendo', 'jscp', 'rendimento', 'outro']),
            'amount' => fake()->randomFloat(2, 1, 500),
            'notes' => null,
        ];
    }
}
