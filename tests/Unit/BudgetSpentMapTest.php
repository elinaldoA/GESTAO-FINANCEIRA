<?php

namespace Tests\Unit;

use App\Models\Budget;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BudgetSpentMapTest extends TestCase
{
    use RefreshDatabase;

    public function test_spent_map_matches_the_per_budget_accessor(): void
    {
        $user = User::factory()->create();
        $account = $user->accounts()->create(['name' => 'Conta', 'type' => 'corrente', 'initial_balance' => 0]);
        $moradia = Category::factory()->for($user)->create(['name' => 'Moradia', 'type' => 'despesa']);
        $lazer = Category::factory()->for($user)->create(['name' => 'Lazer', 'type' => 'despesa']);

        $budgetMoradia = $user->budgets()->create(['category_id' => $moradia->id, 'month' => now()->month, 'year' => now()->year, 'amount' => 1000]);
        $budgetLazer = $user->budgets()->create(['category_id' => $lazer->id, 'month' => now()->month, 'year' => now()->year, 'amount' => 200]);

        Transaction::factory()->for($user)->for($account)->create(['category_id' => $moradia->id, 'type' => 'despesa', 'amount' => 600, 'date' => now()]);
        Transaction::factory()->for($user)->for($account)->create(['category_id' => $moradia->id, 'type' => 'despesa', 'amount' => 150, 'date' => now()]);
        Transaction::factory()->for($user)->for($account)->create(['category_id' => $lazer->id, 'type' => 'despesa', 'amount' => 80, 'date' => now()]);

        $map = Budget::spentMapFor($user->id, (int) now()->month, (int) now()->year);

        $this->assertSame(750.0, $map[$moradia->id]);
        $this->assertSame(80.0, $map[$lazer->id]);
        $this->assertSame($budgetMoradia->spent, $map[$moradia->id]);
        $this->assertSame($budgetLazer->spent, $map[$lazer->id]);
    }

    public function test_spent_map_is_computed_with_a_single_query(): void
    {
        $user = User::factory()->create();
        $account = $user->accounts()->create(['name' => 'Conta', 'type' => 'corrente', 'initial_balance' => 0]);
        $category = Category::factory()->for($user)->create(['type' => 'despesa']);
        $user->budgets()->create(['category_id' => $category->id, 'month' => now()->month, 'year' => now()->year, 'amount' => 500]);
        Transaction::factory()->for($user)->for($account)->create(['category_id' => $category->id, 'type' => 'despesa', 'amount' => 100, 'date' => now()]);

        DB::enableQueryLog();
        Budget::spentMapFor($user->id, (int) now()->month, (int) now()->year);
        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame(1, $queryCount);
    }
}
