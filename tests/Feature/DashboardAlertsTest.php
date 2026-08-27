<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class DashboardAlertsTest extends TestCase
{
    use RefreshDatabase;

    public function test_overdue_pending_transaction_generates_an_alert(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $account = $user->accounts()->create(['name' => 'Conta', 'type' => 'corrente', 'initial_balance' => 0]);

        $user->transactions()->create([
            'account_id' => $account->id,
            'type' => 'despesa',
            'description' => 'Conta de luz',
            'amount' => 120,
            'date' => now()->subDays(3),
            'is_paid' => false,
        ]);

        Volt::test('dashboard')->assertSee('transação pendente vencida');
    }

    public function test_goal_with_near_deadline_generates_an_alert(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $user->goals()->create([
            'name' => 'Viagem',
            'target_amount' => 1000,
            'current_amount' => 200,
            'target_date' => now()->addDays(10)->format('Y-m-d'),
        ]);

        Volt::test('dashboard')->assertSee('tem prazo em');
    }

    public function test_achieved_goal_does_not_generate_an_alert(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $user->goals()->create([
            'name' => 'Viagem',
            'target_amount' => 1000,
            'current_amount' => 1000,
            'target_date' => now()->addDays(10)->format('Y-m-d'),
        ]);

        Volt::test('dashboard')->assertDontSee('tem prazo em');
    }

    public function test_dashboard_with_no_issues_shows_no_alerts(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Volt::test('dashboard')
            ->assertDontSee('transação pendente vencida')
            ->assertDontSee('tem prazo em')
            ->assertDontSee('Fatura do cartão');
    }

    public function test_investment_with_big_daily_swing_generates_an_alert(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $user->investments()->create([
            'name' => 'Ações XPTO',
            'ticker' => 'XPTO4',
            'quantity' => 10,
            'invested_amount' => 1000,
            'current_amount' => 900,
            'day_change_percent' => -7.5,
        ]);

        Volt::test('dashboard')->assertSee('caiu 7,50% hoje');
    }

    public function test_investment_with_small_daily_swing_does_not_generate_an_alert(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $user->investments()->create([
            'name' => 'Ações XPTO',
            'ticker' => 'XPTO4',
            'quantity' => 10,
            'invested_amount' => 1000,
            'current_amount' => 1010,
            'day_change_percent' => 1.0,
        ]);

        Volt::test('dashboard')->assertDontSee('hoje.');
    }

    public function test_budget_on_track_to_overrun_generates_a_predictive_alert(): void
    {
        $this->travelTo(now()->startOfMonth()->addDays(9));

        $user = User::factory()->create();
        $this->actingAs($user);
        $account = $user->accounts()->create(['name' => 'Conta', 'type' => 'corrente', 'initial_balance' => 0]);
        $category = $user->categories()->create(['name' => 'Lazer', 'type' => 'despesa']);
        $user->budgets()->create(['category_id' => $category->id, 'month' => now()->month, 'year' => now()->year, 'amount' => 300]);

        // R$150 spent by day 9 of a 30-day month projects to R$500, well above the R$300 budget.
        $user->transactions()->create([
            'account_id' => $account->id, 'category_id' => $category->id,
            'type' => 'despesa', 'description' => 'Cinema', 'amount' => 150, 'date' => now(), 'is_paid' => true,
        ]);

        Volt::test('dashboard')->assertSee('deve estourar antes do fim do mês');
    }

    public function test_budget_within_projected_pace_does_not_generate_a_predictive_alert(): void
    {
        $this->travelTo(now()->startOfMonth()->addDays(9));

        $user = User::factory()->create();
        $this->actingAs($user);
        $account = $user->accounts()->create(['name' => 'Conta', 'type' => 'corrente', 'initial_balance' => 0]);
        $category = $user->categories()->create(['name' => 'Lazer', 'type' => 'despesa']);
        $user->budgets()->create(['category_id' => $category->id, 'month' => now()->month, 'year' => now()->year, 'amount' => 1000]);

        $user->transactions()->create([
            'account_id' => $account->id, 'category_id' => $category->id,
            'type' => 'despesa', 'description' => 'Cinema', 'amount' => 50, 'date' => now(), 'is_paid' => true,
        ]);

        Volt::test('dashboard')->assertDontSee('deve estourar antes do fim do mês');
    }

    public function test_month_projection_insight_is_shown_after_the_first_few_days(): void
    {
        $this->travelTo(now()->startOfMonth()->addDays(9));

        $user = User::factory()->create();
        $this->actingAs($user);
        $account = $user->accounts()->create(['name' => 'Conta', 'type' => 'corrente', 'initial_balance' => 0]);

        $user->transactions()->create([
            'account_id' => $account->id, 'type' => 'despesa', 'description' => 'Compra', 'amount' => 90, 'date' => now(), 'is_paid' => true,
        ]);

        Volt::test('dashboard')->assertSee('deve fechar o mês com cerca de');
    }

    public function test_category_spending_far_above_recent_average_is_highlighted(): void
    {
        $this->travelTo(now()->startOfMonth()->addDays(9));

        $user = User::factory()->create();
        $this->actingAs($user);
        $account = $user->accounts()->create(['name' => 'Conta', 'type' => 'corrente', 'initial_balance' => 0]);
        $category = $user->categories()->create(['name' => 'Lazer', 'type' => 'despesa']);

        foreach ([1, 2, 3] as $monthsAgo) {
            $user->transactions()->create([
                'account_id' => $account->id, 'category_id' => $category->id, 'type' => 'despesa',
                'description' => 'Gasto normal', 'amount' => 50, 'date' => now()->subMonths($monthsAgo), 'is_paid' => true,
            ]);
        }

        $user->transactions()->create([
            'account_id' => $account->id, 'category_id' => $category->id, 'type' => 'despesa',
            'description' => 'Gasto alto', 'amount' => 400, 'date' => now(), 'is_paid' => true,
        ]);

        Volt::test('dashboard')->assertSee('acima da média dos últimos meses');
    }
}
