<?php

namespace Tests\Feature;

use App\Models\Dividend;
use App\Models\Investment;
use App\Models\InvestmentTransaction;
use App\Models\InvestmentType;
use App\Models\PortfolioSnapshot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class InvestmentTabsRenderTest extends TestCase
{
    use RefreshDatabase;

    private function seedPortfolio(User $user): Investment
    {
        $type = InvestmentType::factory()->for($user)->create(['tax_rate' => 15]);
        $investment = Investment::factory()->for($user)->for($type, 'investmentType')->withTicker('PETR4')->create([
            'invested_amount' => 0,
            'quantity' => 0,
            'current_amount' => 0,
            'price_earnings' => 8.5,
            'price_to_book' => 1.2,
            'dividend_yield' => 6.3,
        ]);

        InvestmentTransaction::factory()->for($user)->for($investment)->create([
            'type' => 'compra', 'date' => now()->subMonths(2), 'quantity' => 10, 'unit_price' => 20, 'amount' => 200,
        ]);
        $investment->recalculateFromTransactions();
        $investment->update(['current_amount' => 250]);

        Dividend::factory()->for($user)->for($investment)->create(['amount' => 15, 'date' => now()->subDays(10)]);

        PortfolioSnapshot::factory()->for($user)->create(['date' => now()->subDays(20), 'total_invested' => 200, 'total_current' => 220]);
        PortfolioSnapshot::factory()->for($user)->create(['date' => now(), 'total_invested' => 200, 'total_current' => 250]);

        return $investment;
    }

    public function test_all_tabs_render_for_a_populated_portfolio(): void
    {
        $user = User::factory()->create();
        $this->seedPortfolio($user);

        Volt::actingAs($user)->test('investments.index')->assertOk()->assertSee('Resumo');
        Volt::actingAs($user)->test('investments.positions')->assertOk()->assertSee('Posições');
        Volt::actingAs($user)->test('investments.dividends')->assertOk()->assertSee('Proventos');
        Volt::actingAs($user)->test('investments.wealth')->assertOk()->assertSee('Patrimônio');
        Volt::actingAs($user)->test('investments.returns')->assertOk()->assertSee('Rentabilidade');
        Volt::actingAs($user)->test('investments.analysis')->assertOk()->assertSee('Análise');
        Volt::actingAs($user)->test('investments.transactions')->assertOk()->assertSee('Lançamentos');
    }

    public function test_all_tabs_render_for_an_empty_portfolio(): void
    {
        $user = User::factory()->create();

        Volt::actingAs($user)->test('investments.index')->assertOk();
        Volt::actingAs($user)->test('investments.positions')->assertOk();
        Volt::actingAs($user)->test('investments.dividends')->assertOk();
        Volt::actingAs($user)->test('investments.wealth')->assertOk();
        Volt::actingAs($user)->test('investments.returns')->assertOk();
        Volt::actingAs($user)->test('investments.analysis')->assertOk();
        Volt::actingAs($user)->test('investments.transactions')->assertOk();
    }

    public function test_analysis_tab_shows_indicators_and_flags_concentration(): void
    {
        $user = User::factory()->create();
        $investment = $this->seedPortfolio($user);

        Volt::actingAs($user)->test('investments.analysis')
            ->assertSee($investment->name)
            ->assertSee('8,50')
            ->assertSee('Concentração alta');
    }

    public function test_routes_resolve_to_the_expected_tabs_in_the_correct_order(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->get(route('investments.positions'))->assertOk()->assertSee('Posições');
        $this->get(route('investments.dividends'))->assertOk()->assertSee('Proventos');
        $this->get(route('investments.wealth'))->assertOk()->assertSee('Patrimônio');
        $this->get(route('investments.returns'))->assertOk();
        $this->get(route('investments.analysis'))->assertOk()->assertSee('Análise');
        $this->get(route('investments.transactions'))->assertOk()->assertSee('Lançamentos');
    }
}
