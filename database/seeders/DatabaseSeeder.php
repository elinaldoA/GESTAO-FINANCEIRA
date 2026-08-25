<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Category;
use App\Models\CreditCard;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::firstOrCreate(
            ['email' => 'demo@exemplo.com'],
            ['name' => 'Usuário Demo', 'password' => bcrypt('password')]
        );

        if ($user->accounts()->exists()) {
            return;
        }

        $checking = $user->accounts()->create(['name' => 'Conta Corrente', 'type' => 'corrente', 'initial_balance' => 3000, 'color' => '#3b82f6']);
        $savings = $user->accounts()->create(['name' => 'Poupança', 'type' => 'poupanca', 'initial_balance' => 8000, 'color' => '#10b981']);
        $cash = $user->accounts()->create(['name' => 'Dinheiro', 'type' => 'dinheiro', 'initial_balance' => 200, 'color' => '#f59e0b']);

        $card = $user->creditCards()->create([
            'name' => 'Cartão Nubank', 'limit_amount' => 5000, 'closing_day' => 5, 'due_day' => 12, 'color' => '#8b5cf6',
        ]);

        $incomeCategories = [
            'Salário' => '#22c55e',
            'Freelance' => '#84cc16',
            'Investimentos' => '#06b6d4',
        ];
        $expenseCategories = [
            'Moradia' => '#ef4444',
            'Alimentação' => '#f97316',
            'Transporte' => '#eab308',
            'Saúde' => '#ec4899',
            'Lazer' => '#a855f7',
            'Educação' => '#6366f1',
        ];

        $incomeCats = collect($incomeCategories)->map(fn ($color, $name) => $user->categories()->create(['name' => $name, 'type' => 'receita', 'color' => $color]));
        $expenseCats = collect($expenseCategories)->map(fn ($color, $name) => $user->categories()->create(['name' => $name, 'type' => 'despesa', 'color' => $color]));

        foreach (range(0, 2) as $i) {
            $date = now()->subMonths($i);

            $user->transactions()->create([
                'account_id' => $checking->id,
                'category_id' => $incomeCats->get('Salário')->id,
                'type' => 'receita',
                'description' => 'Salário mensal',
                'amount' => 5500,
                'date' => $date->copy()->startOfMonth()->addDays(4),
                'is_paid' => true,
            ]);

            $user->transactions()->create([
                'account_id' => $checking->id,
                'category_id' => $expenseCats->get('Moradia')->id,
                'type' => 'despesa',
                'description' => 'Aluguel',
                'amount' => 1500,
                'date' => $date->copy()->startOfMonth()->addDays(9),
                'is_paid' => true,
            ]);

            $user->transactions()->create([
                'credit_card_id' => $card->id,
                'category_id' => $expenseCats->get('Alimentação')->id,
                'type' => 'despesa',
                'description' => 'Supermercado',
                'amount' => 620.50,
                'date' => $date->copy()->startOfMonth()->addDays(14),
                'is_paid' => true,
            ]);

            $user->transactions()->create([
                'credit_card_id' => $card->id,
                'category_id' => $expenseCats->get('Lazer')->id,
                'type' => 'despesa',
                'description' => 'Streaming e cinema',
                'amount' => 120,
                'date' => $date->copy()->startOfMonth()->addDays(17),
                'is_paid' => true,
            ]);

            $user->transactions()->create([
                'account_id' => $checking->id,
                'category_id' => $expenseCats->get('Transporte')->id,
                'type' => 'despesa',
                'description' => 'Combustível',
                'amount' => 300,
                'date' => $date->copy()->startOfMonth()->addDays(20),
                'is_paid' => true,
            ]);

            $user->transactions()->create([
                'account_id' => $checking->id,
                'destination_account_id' => $savings->id,
                'type' => 'transferencia',
                'description' => 'Transferência para poupança',
                'amount' => 500,
                'date' => $date->copy()->startOfMonth()->addDays(6),
                'is_paid' => true,
            ]);
        }

        $user->budgets()->create(['category_id' => $expenseCats->get('Moradia')->id, 'month' => now()->month, 'year' => now()->year, 'amount' => 1600]);
        $user->budgets()->create(['category_id' => $expenseCats->get('Alimentação')->id, 'month' => now()->month, 'year' => now()->year, 'amount' => 800]);
        $user->budgets()->create(['category_id' => $expenseCats->get('Lazer')->id, 'month' => now()->month, 'year' => now()->year, 'amount' => 150]);
    }
}
