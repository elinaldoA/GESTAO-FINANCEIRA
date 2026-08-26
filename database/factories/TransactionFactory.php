<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Transaction>
 */
class TransactionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'account_id' => Account::factory(),
            'credit_card_id' => null,
            'destination_account_id' => null,
            'category_id' => null,
            'type' => fake()->randomElement(['receita', 'despesa']),
            'payment_method' => 'debito',
            'description' => fake()->sentence(3),
            'amount' => fake()->randomFloat(2, 1, 2000),
            'date' => fake()->dateTimeBetween('-3 months', 'now'),
            'is_paid' => true,
            'is_recurring' => false,
            'invoice_paid' => false,
        ];
    }

    public function despesa(): static
    {
        return $this->state(fn (array $attributes) => ['type' => 'despesa']);
    }

    public function receita(): static
    {
        return $this->state(fn (array $attributes) => ['type' => 'receita']);
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => ['is_paid' => false]);
    }
}
