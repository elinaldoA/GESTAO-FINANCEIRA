<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class PaymentMethodTest extends TestCase
{
    use RefreshDatabase;

    public function test_despesa_paid_by_credit_card_requires_credit_card_and_no_account(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $card = $user->creditCards()->create(['name' => 'Nubank', 'limit_amount' => 1000, 'closing_day' => 5, 'due_day' => 12]);

        Volt::test('transactions.index')
            ->set('description', 'Compra no crédito')
            ->set('amount', '80')
            ->set('date', now()->format('Y-m-d'))
            ->set('type', 'despesa')
            ->set('payment_method', 'credito')
            ->set('credit_card_id', $card->id)
            ->call('save')
            ->assertHasNoErrors();

        $transaction = $user->transactions()->firstOrFail();
        $this->assertEquals('credito', $transaction->payment_method);
        $this->assertEquals($card->id, $transaction->credit_card_id);
        $this->assertNull($transaction->account_id);
    }

    public function test_despesa_paid_by_credit_card_without_card_fails_validation(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Volt::test('transactions.index')
            ->set('description', 'Compra no crédito')
            ->set('amount', '80')
            ->set('date', now()->format('Y-m-d'))
            ->set('type', 'despesa')
            ->set('payment_method', 'credito')
            ->call('save')
            ->assertHasErrors(['credit_card_id']);
    }

    public function test_pix_expense_requires_account_not_credit_card(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $account = $user->accounts()->create(['name' => 'Conta', 'type' => 'corrente', 'initial_balance' => 0]);

        Volt::test('transactions.index')
            ->set('description', 'Pagamento via Pix')
            ->set('amount', '30')
            ->set('date', now()->format('Y-m-d'))
            ->set('type', 'despesa')
            ->set('payment_method', 'pix')
            ->set('account_id', $account->id)
            ->call('save')
            ->assertHasNoErrors();

        $transaction = $user->transactions()->firstOrFail();
        $this->assertEquals('pix', $transaction->payment_method);
        $this->assertNull($transaction->credit_card_id);
        $this->assertEquals($account->id, $transaction->account_id);
    }

    public function test_receita_cannot_use_credito_as_payment_method(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $account = $user->accounts()->create(['name' => 'Conta', 'type' => 'corrente', 'initial_balance' => 0]);

        Volt::test('transactions.index')
            ->set('description', 'Salário')
            ->set('amount', '3000')
            ->set('date', now()->format('Y-m-d'))
            ->set('type', 'receita')
            ->set('payment_method', 'credito')
            ->set('account_id', $account->id)
            ->call('save')
            ->assertHasErrors(['payment_method']);
    }

    public function test_switching_type_resets_payment_method(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Volt::test('transactions.index')
            ->set('payment_method', 'credito')
            ->set('type', 'receita')
            ->assertSet('payment_method', 'pix');
    }
}
