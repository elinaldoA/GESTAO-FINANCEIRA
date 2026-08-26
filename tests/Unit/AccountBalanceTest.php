<?php

namespace Tests\Unit;

use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountBalanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_current_balance_starts_at_the_initial_balance(): void
    {
        $account = Account::factory()->create(['initial_balance' => 100]);

        $this->assertSame(100.0, $account->current_balance);
    }

    public function test_paid_income_and_expenses_adjust_the_balance(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['initial_balance' => 100]);

        Transaction::factory()->for($user)->for($account)->receita()->create(['amount' => 50]);
        Transaction::factory()->for($user)->for($account)->despesa()->create(['amount' => 30]);

        $this->assertSame(120.0, $account->current_balance);
    }

    public function test_unpaid_transactions_do_not_affect_the_balance(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['initial_balance' => 100]);

        Transaction::factory()->for($user)->for($account)->despesa()->pending()->create(['amount' => 999]);

        $this->assertSame(100.0, $account->current_balance);
    }

    public function test_transfers_move_balance_between_accounts(): void
    {
        $user = User::factory()->create();
        $origin = Account::factory()->for($user)->create(['initial_balance' => 200]);
        $destination = Account::factory()->for($user)->create(['initial_balance' => 0]);

        Transaction::factory()->for($user)->for($origin)->create([
            'type' => 'transferencia',
            'destination_account_id' => $destination->id,
            'amount' => 80,
        ]);

        $this->assertSame(120.0, $origin->current_balance);
        $this->assertSame(80.0, $destination->current_balance);
    }
}
