<?php

namespace Database\Factories;

use App\Models\Investment;
use App\Models\InvestmentTransaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InvestmentTransaction>
 */
class InvestmentTransactionFactory extends Factory
{
    public function definition(): array
    {
        $quantity = fake()->randomFloat(8, 1, 100);
        $unitPrice = fake()->randomFloat(4, 1, 100);

        return [
            'user_id' => User::factory(),
            'investment_id' => Investment::factory(),
            'date' => fake()->date(),
            'type' => 'compra',
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'fees' => null,
            'amount' => round($quantity * $unitPrice, 2),
            'notes' => null,
        ];
    }

    public function venda(): static
    {
        return $this->state(fn (array $attributes) => ['type' => 'venda']);
    }

    public function aporte(float $amount = 100.0): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'aporte',
            'quantity' => null,
            'unit_price' => null,
            'amount' => $amount,
        ]);
    }

    public function resgate(float $amount = 100.0): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'resgate',
            'quantity' => null,
            'unit_price' => null,
            'amount' => $amount,
        ]);
    }
}
