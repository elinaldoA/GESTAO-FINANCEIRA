<?php

namespace Tests\Feature;

use App\Models\Investment;
use App\Models\InvestmentTransaction;
use App\Models\InvestmentType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class InvestmentTransactionsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_a_buy_transaction_for_a_ticker_investment(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $type = InvestmentType::factory()->for($user)->create();
        $investment = Investment::factory()->for($user)->for($type, 'investmentType')->withTicker('PETR4')->create([
            'invested_amount' => 0,
            'quantity' => 0,
        ]);

        Volt::test('investments.transactions')
            ->set('transaction_investment_id', $investment->id)
            ->set('transaction_type', 'compra')
            ->set('transaction_quantity', '10')
            ->set('transaction_unit_price', '20')
            ->call('save')
            ->assertHasNoErrors();

        $investment->refresh();
        $this->assertEquals('10.00000000', $investment->quantity);
        $this->assertEquals('200.00', $investment->invested_amount);
        $this->assertDatabaseHas('investment_transactions', [
            'investment_id' => $investment->id,
            'user_id' => $user->id,
            'type' => 'compra',
        ]);
    }

    public function test_user_can_register_a_contribution_for_an_investment_without_ticker(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $type = InvestmentType::factory()->for($user)->create();
        $investment = Investment::factory()->for($user)->for($type, 'investmentType')->create([
            'ticker' => null,
            'quantity' => null,
            'invested_amount' => 0,
        ]);

        Volt::test('investments.transactions')
            ->set('transaction_investment_id', $investment->id)
            ->set('transaction_type', 'aporte')
            ->set('transaction_amount', '500')
            ->call('save')
            ->assertHasNoErrors();

        $investment->refresh();
        $this->assertEquals('500.00', $investment->invested_amount);
    }

    public function test_selling_more_than_the_current_position_is_rejected(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $type = InvestmentType::factory()->for($user)->create();
        $investment = Investment::factory()->for($user)->for($type, 'investmentType')->withTicker('PETR4')->create([
            'invested_amount' => 0,
            'quantity' => 0,
        ]);

        Volt::test('investments.transactions')
            ->set('transaction_investment_id', $investment->id)
            ->set('transaction_type', 'compra')
            ->set('transaction_quantity', '10')
            ->set('transaction_unit_price', '20')
            ->call('save');

        Volt::test('investments.transactions')
            ->set('transaction_investment_id', $investment->id)
            ->set('transaction_type', 'venda')
            ->set('transaction_quantity', '15')
            ->set('transaction_unit_price', '25')
            ->call('save')
            ->assertHasErrors('transaction_quantity');

        $investment->refresh();
        $this->assertEquals('10.00000000', $investment->quantity);
    }

    public function test_editing_a_transaction_recalculates_the_investment(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $type = InvestmentType::factory()->for($user)->create();
        $investment = Investment::factory()->for($user)->for($type, 'investmentType')->withTicker('PETR4')->create([
            'invested_amount' => 0,
            'quantity' => 0,
        ]);

        $transaction = InvestmentTransaction::factory()->for($user)->for($investment)->create([
            'type' => 'compra', 'quantity' => 10, 'unit_price' => 20, 'amount' => 200,
        ]);
        $investment->recalculateFromTransactions();

        Volt::test('investments.transactions')
            ->call('edit', $transaction)
            ->set('transaction_quantity', '20')
            ->call('save')
            ->assertHasNoErrors();

        $investment->refresh();
        $this->assertEquals('20.00000000', $investment->quantity);
        $this->assertEquals('400.00', $investment->invested_amount);
    }

    public function test_deleting_a_transaction_recalculates_the_investment(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $type = InvestmentType::factory()->for($user)->create();
        $investment = Investment::factory()->for($user)->for($type, 'investmentType')->withTicker('PETR4')->create([
            'invested_amount' => 0,
            'quantity' => 0,
        ]);

        $transaction = InvestmentTransaction::factory()->for($user)->for($investment)->create([
            'type' => 'compra', 'quantity' => 10, 'unit_price' => 20, 'amount' => 200,
        ]);
        $investment->recalculateFromTransactions();

        Volt::test('investments.transactions')->call('delete', $transaction);

        $this->assertSoftDeleted('investment_transactions', ['id' => $transaction->id]);
        $investment->refresh();
        $this->assertEquals('0.00000000', $investment->quantity);
        $this->assertEquals('0.00', $investment->invested_amount);
    }

    public function test_user_cannot_edit_another_users_transaction(): void
    {
        $owner = User::factory()->create();
        $type = InvestmentType::factory()->for($owner)->create();
        $investment = Investment::factory()->for($owner)->for($type, 'investmentType')->withTicker('PETR4')->create();
        $transaction = InvestmentTransaction::factory()->for($owner)->for($investment)->create();

        $intruder = User::factory()->create();
        $this->actingAs($intruder);

        Volt::test('investments.transactions')
            ->call('edit', $transaction)
            ->assertForbidden();
    }
}
