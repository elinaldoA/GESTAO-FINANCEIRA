<?php

namespace Database\Factories;

use App\Models\PortfolioSnapshot;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PortfolioSnapshot>
 */
class PortfolioSnapshotFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'date' => fake()->date(),
            'total_invested' => fake()->randomFloat(2, 100, 10000),
            'total_current' => fake()->randomFloat(2, 100, 10000),
        ];
    }
}
