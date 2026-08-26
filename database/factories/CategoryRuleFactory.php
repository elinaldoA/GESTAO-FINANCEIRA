<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\CategoryRule;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CategoryRule>
 */
class CategoryRuleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'category_id' => Category::factory(),
            'keyword' => fake()->unique()->word(),
        ];
    }
}
