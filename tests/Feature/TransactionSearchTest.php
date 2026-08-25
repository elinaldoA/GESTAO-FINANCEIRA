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
}
