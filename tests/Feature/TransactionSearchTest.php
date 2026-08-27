<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class TransactionSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_finds_transactions_by_description_regardless_of_month(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $account = $user->accounts()->create(['name' => 'Conta', 'type' => 'corrente', 'initial_balance' => 0]);

        $user->transactions()->create([
            'account_id' => $account->id,
            'type' => 'despesa',
            'description' => 'Assinatura Netflix',
            'amount' => 39.90,
            'date' => now()->subMonths(4),
        ]);

        $user->transactions()->create([
            'account_id' => $account->id,
            'type' => 'despesa',
            'description' => 'Mercado',
            'amount' => 200,
            'date' => now(),
        ]);

        Volt::test('transactions.index')
            ->set('filterSearch', 'netflix')
            ->assertSee('Assinatura Netflix')
            ->assertDontSee('Mercado');
    }

    public function test_search_query_string_is_reflected_in_the_url(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get('/transacoes?busca=netflix');

        $response->assertOk();
    }

    public function test_transactions_from_all_months_are_shown_by_default(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $account = $user->accounts()->create(['name' => 'Conta', 'type' => 'corrente', 'initial_balance' => 0]);

        $user->transactions()->create([
            'account_id' => $account->id,
            'type' => 'despesa',
            'description' => 'Compra antiga',
            'amount' => 10,
            'date' => now()->subMonths(6),
        ]);

        $user->transactions()->create([
            'account_id' => $account->id,
            'type' => 'despesa',
            'description' => 'Compra deste mês',
            'amount' => 20,
            'date' => now(),
        ]);

        Volt::test('transactions.index')
            ->assertSee('Compra antiga')
            ->assertSee('Compra deste mês');
    }

    public function test_search_words_match_regardless_of_order(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $account = $user->accounts()->create(['name' => 'Conta', 'type' => 'corrente', 'initial_balance' => 0]);

        $user->transactions()->create([
            'account_id' => $account->id, 'type' => 'despesa',
            'description' => 'Extra Mercado Ltda', 'amount' => 90, 'date' => now(),
        ]);

        $user->transactions()->create([
            'account_id' => $account->id, 'type' => 'despesa',
            'description' => 'Farmacia', 'amount' => 30, 'date' => now(),
        ]);

        Volt::test('transactions.index')
            ->set('filterSearch', 'mercado extra')
            ->assertSee('Extra Mercado Ltda')
            ->assertDontSee('Farmacia');
    }

    public function test_search_tolerates_a_typo_via_approximate_match(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $account = $user->accounts()->create(['name' => 'Conta', 'type' => 'corrente', 'initial_balance' => 0]);

        $user->transactions()->create([
            'account_id' => $account->id, 'type' => 'despesa',
            'description' => 'Pagamento Aluguel', 'amount' => 1500, 'date' => now(),
        ]);

        Volt::test('transactions.index')
            ->set('filterSearch', 'alugel')
            ->assertSee('Pagamento Aluguel')
            ->assertSet('searchFallbackUsed', true);
    }

    public function test_search_without_matches_or_close_words_shows_nothing(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $account = $user->accounts()->create(['name' => 'Conta', 'type' => 'corrente', 'initial_balance' => 0]);

        $user->transactions()->create([
            'account_id' => $account->id, 'type' => 'despesa',
            'description' => 'Supermercado', 'amount' => 90, 'date' => now(),
        ]);

        Volt::test('transactions.index')
            ->set('filterSearch', 'xyzabc123')
            ->assertDontSee('Supermercado');
    }
}
