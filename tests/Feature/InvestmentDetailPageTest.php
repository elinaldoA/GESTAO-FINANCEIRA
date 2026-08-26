<?php

namespace Tests\Feature;

use App\Models\Investment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Volt\Volt;
use Tests\TestCase;

class InvestmentDetailPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_page_shows_average_price_current_price_and_gain(): void
    {
        $user = User::factory()->create();
        $investment = Investment::factory()->for($user)->create([
            'ticker' => null,
            'quantity' => 10,
            'invested_amount' => 1000,
            'current_amount' => 1200,
        ]);

        Volt::actingAs($user)
            ->test('investments.show', ['investment' => $investment])
            ->assertSee('Preço médio')
            ->assertSee('100,00')
            ->assertSee('120,00');
    }

    public function test_fundamental_indicator_badges_are_shown_when_available(): void
    {
        $user = User::factory()->create();
        $investment = Investment::factory()->for($user)->create([
            'ticker' => 'PETR4',
            'quantity' => 10,
            'price_earnings' => 4.44,
            'price_to_book' => 1.11,
            'dividend_yield' => 9.0,
        ]);

        Volt::actingAs($user)
            ->test('investments.show', ['investment' => $investment])
            ->assertSee('P/L: 4,44')
            ->assertSee('P/VP: 1,11')
            ->assertSee('DY: 9,00%');
    }

    public function test_user_cannot_view_another_users_investment_detail(): void
    {
        $owner = User::factory()->create();
        $investment = Investment::factory()->for($owner)->create();

        $intruder = User::factory()->create();

        Volt::actingAs($intruder)
            ->test('investments.show', ['investment' => $investment])
            ->assertForbidden();
    }

    public function test_price_history_chart_is_shown_when_ticker_has_history(): void
    {
        Http::fake([
            'brapi.dev/*' => Http::response([
                'results' => [[
                    'regularMarketPrice' => 41.0,
                    'historicalDataPrice' => [
                        ['date' => now()->subDays(2)->timestamp, 'close' => 39.5],
                        ['date' => now()->subDay()->timestamp, 'close' => 40.2],
                        ['date' => now()->timestamp, 'close' => 41.0],
                    ],
                ]],
            ]),
        ]);

        $user = User::factory()->create();
        $investment = Investment::factory()->for($user)->create(['ticker' => 'PETR4', 'quantity' => 10]);

        Volt::actingAs($user)
            ->test('investments.show', ['investment' => $investment])
            ->assertSee('Histórico de cotação');
    }
}
