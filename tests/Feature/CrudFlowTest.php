<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class CrudFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_account_category_and_transaction(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Volt::test('accounts.index')
            ->set('name', 'Conta Teste')
            ->set('type', 'corrente')
            ->set('initial_balance', '1000')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('accounts', ['name' => 'Conta Teste', 'user_id' => $user->id]);
        $account = $user->accounts()->first();

        Volt::test('categories.index')
            ->set('name', 'Mercado')
            ->set('type', 'despesa')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('categories', ['name' => 'Mercado', 'user_id' => $user->id]);
        $category = $user->categories()->first();

        Volt::test('transactions.index')
            ->set('description', 'Compra do mês')
            ->set('amount', '250.50')
            ->set('date', now()->format('Y-m-d'))
            ->set('type', 'despesa')
            ->set('account_id', $account->id)
            ->set('category_id', $category->id)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('transactions', [
            'description' => 'Compra do mês',
            'user_id' => $user->id,
            'amount' => 250.50,
        ]);

        $account->refresh();
        $this->assertEquals(749.50, $account->current_balance);
    }
}
