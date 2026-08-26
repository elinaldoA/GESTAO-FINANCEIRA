<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'parent_id' => null,
            'name' => fake()->word(),
            'type' => fake()->randomElement(['receita', 'despesa']),
            'color' => fake()->hexColor(),
            'icon' => 'tag',
        ];
    }
}
