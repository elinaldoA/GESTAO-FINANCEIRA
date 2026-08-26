<?php

namespace Tests\Feature;

use App\Console\Commands\UpdateInvestmentQuotes;
use App\Models\Investment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Volt\Volt;
use Tests\TestCase;

class InvestmentQuoteUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_updates_current_amount_for_investments_with_ticker(): void
    {
        Http::fake([
            'brapi.dev/api/quote/PETR4*' => Http::response([
                'results' => [['regularMarketPrice' => 40.0, 'regularMarketChangePercent' => 1.5]],
            ]),
        ]);

        $user = User::factory()->create();
        $investment = Investment::factory()->for($user)->create([
            'ticker' => 'PETR4',
            'quantity' => 10,
            'current_amount' => 100,
        ]);

        $this->artisan(UpdateInvestmentQuotes::class)->assertSuccessful();

        $this->assertSame('400.00', $investment->fresh()->current_amount);
        $this->assertSame('1.50', $investment->fresh()->day_change_percent);
        $this->assertNotNull($investment->fresh()->quote_updated_at);
    }

    public function test_command_skips_investments_without_ticker_or_quantity(): void
    {
        Http::fake();

        $user = User::factory()->create();
        $investment = Investment::factory()->for($user)->create([
            'ticker' => null,
            'quantity' => null,
            'current_amount' => 100,
        ]);

        $this->artisan(UpdateInvestmentQuotes::class)->assertSuccessful();

        Http::assertNothingSent();
        $this->assertSame('100.00', $investment->fresh()->current_amount);
    }

    public function test_command_leaves_current_amount_unchanged_when_the_api_fails(): void
    {
        Http::fake([
            'brapi.dev/*' => Http::response(['error' => true], 500),
        ]);

        $user = User::factory()->create();
        $investment = Investment::factory()->for($user)->create([
            'ticker' => 'PETR4',
            'quantity' => 10,
            'current_amount' => 100,
        ]);

        $this->artisan(UpdateInvestmentQuotes::class)->assertSuccessful();

        $this->assertSame('100.00', $investment->fresh()->current_amount);
        $this->assertNull($investment->fresh()->quote_updated_at);
    }

    public function test_user_can_manually_refresh_a_single_investment_quote(): void
    {
        Http::fake([
            'brapi.dev/api/quote/ITUB4*' => Http::response([
                'results' => [['regularMarketPrice' => 30.0]],
            ]),
        ]);

        $user = User::factory()->create();
        $investment = Investment::factory()->for($user)->create([
            'ticker' => 'ITUB4',
            'quantity' => 5,
            'current_amount' => 50,
        ]);

        Volt::actingAs($user)
            ->test('investments.index')
            ->call('refreshQuote', $investment->id);

        $this->assertSame('150.00', $investment->fresh()->current_amount);
    }

    public function test_user_cannot_refresh_another_users_investment_quote(): void
    {
        $owner = User::factory()->create();
        $investment = Investment::factory()->for($owner)->create(['ticker' => 'ITUB4', 'quantity' => 5]);

        $intruder = User::factory()->create();

        Volt::actingAs($intruder)
            ->test('investments.index')
            ->call('refreshQuote', $investment->id)
            ->assertForbidden();
    }

    public function test_user_can_refresh_all_tracked_investments_at_once(): void
    {
        Http::fake([
            'brapi.dev/api/quote/ITUB4*' => Http::response(['results' => [['regularMarketPrice' => 30.0]]]),
            'brapi.dev/api/quote/VALE3*' => Http::response(['results' => [['regularMarketPrice' => 70.0]]]),
        ]);

        $user = User::factory()->create();
        $itub = Investment::factory()->for($user)->create(['ticker' => 'ITUB4', 'quantity' => 5, 'current_amount' => 0]);
        $vale = Investment::factory()->for($user)->create(['ticker' => 'VALE3', 'quantity' => 2, 'current_amount' => 0]);
        $manual = Investment::factory()->for($user)->create(['ticker' => null, 'quantity' => null, 'current_amount' => 999]);

        Volt::actingAs($user)
            ->test('investments.index')
            ->call('refreshAllQuotes');

        $this->assertSame('150.00', $itub->fresh()->current_amount);
        $this->assertSame('140.00', $vale->fresh()->current_amount);
        $this->assertSame('999.00', $manual->fresh()->current_amount);
    }

    public function test_search_filters_investments_by_name_ticker_or_broker(): void
    {
        $user = User::factory()->create();
        Investment::factory()->for($user)->create(['name' => 'Reserva de emergência', 'ticker' => null, 'broker' => 'Nubank']);
        Investment::factory()->for($user)->create(['name' => 'Ações banco', 'ticker' => 'ITUB4', 'broker' => 'XP']);

        Volt::actingAs($user)
            ->test('investments.index')
            ->set('search', 'ITUB4')
            ->assertSee('Ações banco')
            ->assertDontSee('Reserva de emergência');
    }

    public function test_poll_quotes_updates_prices_silently_without_a_toast(): void
    {
        Http::fake([
            'brapi.dev/api/quote/ITUB4*' => Http::response(['results' => [['regularMarketPrice' => 30.0]]]),
        ]);

        $user = User::factory()->create();
        $investment = Investment::factory()->for($user)->create(['ticker' => 'ITUB4', 'quantity' => 5, 'current_amount' => 0]);

        Volt::actingAs($user)
            ->test('investments.index')
            ->call('pollQuotes')
            ->assertNotDispatched('notify');

        $this->assertSame('150.00', $investment->fresh()->current_amount);
    }

    public function test_page_polls_automatically_only_when_there_are_tracked_investments(): void
    {
        $user = User::factory()->create();
        Investment::factory()->for($user)->create(['ticker' => 'ITUB4', 'quantity' => 5]);

        Volt::actingAs($user)->test('investments.index')->assertSee('wire:poll.60s="pollQuotes"', false);
    }

    public function test_page_does_not_poll_when_no_investment_has_a_ticker(): void
    {
        $user = User::factory()->create();
        Investment::factory()->for($user)->create(['ticker' => null, 'quantity' => null]);

        Volt::actingAs($user)->test('investments.index')->assertDontSee('wire:poll', false);
    }
}
