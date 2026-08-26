<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Volt\Volt;
use Tests\TestCase;

class InvestmentPageRenderTest extends TestCase
{
    use RefreshDatabase;

    public function test_page_renders_with_allocation_and_filter(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $component = Volt::test('investments.index');
        $renda = $user->investmentTypes()->where('name', 'Renda fixa')->firstOrFail();
        $acoes = $user->investmentTypes()->where('name', 'Ações')->firstOrFail();

        $component->set('name', 'CDB Banco X')
            ->set('investment_type_id', $renda->id)
            ->set('invested_amount', '1000')
            ->set('current_amount', '1100')
            ->call('save')->assertHasNoErrors();

        Volt::test('investments.index')
            ->set('name', 'PETR4')
            ->set('investment_type_id', $acoes->id)
            ->set('invested_amount', '500')
            ->set('current_amount', '450')
            ->call('save')->assertHasNoErrors();

        $page = Volt::test('investments.index');
        $page->assertSee('Distribuição da carteira')
            ->assertSee('Patrimônio')
            ->assertSee('CDB Banco X')
            ->assertSee('PETR4')
            ->assertSee('% carteira');

        $page->call('filterByType', $renda->id)
            ->assertSee('CDB Banco X')
            ->assertDontSee('PETR4');
    }

    public function test_market_panel_shows_dollar_and_hides_ibovespa_without_a_token(): void
    {
        config(['services.brapi.token' => null]);
        Http::fake([
            'economia.awesomeapi.com.br/*' => Http::response([
                'USDBRL' => ['bid' => '5.15', 'pctChange' => '0.13'],
            ]),
        ]);

        $user = User::factory()->create();
        $this->actingAs($user);

        Volt::test('investments.index')
            ->assertSee('Dólar')
            ->assertSee('5,15')
            ->assertDontSee('Ibovespa');
    }

    public function test_portfolio_history_chart_is_hidden_with_fewer_than_two_snapshots(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $user->portfolioSnapshots()->create(['date' => today(), 'total_invested' => 1000, 'total_current' => 1100]);

        Volt::test('investments.index')->assertDontSee('Evolução do patrimônio');
    }

    public function test_portfolio_history_chart_shows_with_two_or_more_snapshots(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $user->portfolioSnapshots()->create(['date' => today()->subDay(), 'total_invested' => 900, 'total_current' => 950]);
        $user->portfolioSnapshots()->create(['date' => today(), 'total_invested' => 1000, 'total_current' => 1100]);

        Volt::test('investments.index')->assertSee('Evolução do patrimônio');
    }
}
