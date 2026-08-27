<?php

namespace Tests\Feature;

use App\Models\Investment;
use App\Models\InvestmentType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvestmentTransactionLedgerTest extends TestCase
{
    use RefreshDatabase;

    public function test_buy_transactions_accumulate_quantity_and_invested_amount(): void
    {
        $investment = $this->tickerInvestment();

        $investment->transactions()->create($this->buy($investment, date: '2026-01-05', quantity: 10, unitPrice: 20));
        $investment->transactions()->create($this->buy($investment, date: '2026-02-05', quantity: 10, unitPrice: 30));
        $investment->recalculateFromTransactions();

        $investment->refresh();
        $this->assertEquals('20.00000000', $investment->quantity);
        $this->assertEquals('500.00', $investment->invested_amount);
    }

    public function test_sell_removes_quantity_at_average_cost_and_books_realized_gain(): void
    {
        $investment = $this->tickerInvestment();

        // Average cost after both buys: (10*20 + 10*30) / 20 = 25/share.
        $investment->transactions()->create($this->buy($investment, date: '2026-01-05', quantity: 10, unitPrice: 20));
        $investment->transactions()->create($this->buy($investment, date: '2026-02-05', quantity: 10, unitPrice: 30));
        // Sell 5 shares at 40: proceeds 200, cost removed 5*25=125, realized gain 75.
        $investment->transactions()->create($this->sell($investment, date: '2026-03-05', quantity: 5, unitPrice: 40));
        $investment->recalculateFromTransactions();

        $investment->refresh();
        $this->assertEquals('15.00000000', $investment->quantity);
        $this->assertEquals('375.00', $investment->invested_amount);
        $this->assertEquals('75.00', $investment->realized_gain);
    }

    public function test_deleting_a_transaction_and_recalculating_reverts_its_effect(): void
    {
        $investment = $this->tickerInvestment();

        $investment->transactions()->create($this->buy($investment, date: '2026-01-05', quantity: 10, unitPrice: 20));
        $second = $investment->transactions()->create($this->buy($investment, date: '2026-02-05', quantity: 10, unitPrice: 30));
        $investment->recalculateFromTransactions();

        $second->delete();
        $investment->recalculateFromTransactions();

        $investment->refresh();
        $this->assertEquals('10.00000000', $investment->quantity);
        $this->assertEquals('200.00', $investment->invested_amount);
    }

    public function test_contribution_and_withdrawal_drive_invested_amount_for_investments_without_a_ticker(): void
    {
        $investment = $this->cashInvestment();

        $investment->transactions()->create($this->contribute($investment, date: '2026-01-05', amount: 1000));
        $investment->transactions()->create($this->withdraw($investment, date: '2026-02-05', amount: 400));
        $investment->recalculateFromTransactions();

        $investment->refresh();
        $this->assertEquals('600.00', $investment->invested_amount);
        $this->assertNull($investment->quantity);
    }

    public function test_withdrawal_larger_than_the_balance_is_clamped_to_zero_instead_of_going_negative(): void
    {
        $investment = $this->cashInvestment();

        $investment->transactions()->create($this->contribute($investment, date: '2026-01-05', amount: 100));
        $investment->transactions()->create($this->withdraw($investment, date: '2026-02-05', amount: 500));
        $investment->recalculateFromTransactions();

        $investment->refresh();
        $this->assertEquals('0.00', $investment->invested_amount);
    }

    private function tickerInvestment(): Investment
    {
        $user = User::factory()->create();
        $type = InvestmentType::factory()->for($user)->create();

        return Investment::factory()->for($user)->for($type, 'investmentType')->withTicker('PETR4')->create([
            'invested_amount' => 0,
            'quantity' => 0,
        ]);
    }

    private function cashInvestment(): Investment
    {
        $user = User::factory()->create();
        $type = InvestmentType::factory()->for($user)->create();

        return Investment::factory()->for($user)->for($type, 'investmentType')->create([
            'ticker' => null,
            'quantity' => null,
            'invested_amount' => 0,
        ]);
    }

    private function buy(Investment $investment, string $date, float $quantity, float $unitPrice): array
    {
        return [
            'user_id' => $investment->user_id,
            'date' => $date,
            'type' => 'compra',
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'amount' => round($quantity * $unitPrice, 2),
        ];
    }

    private function sell(Investment $investment, string $date, float $quantity, float $unitPrice): array
    {
        return [
            'user_id' => $investment->user_id,
            'date' => $date,
            'type' => 'venda',
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'amount' => round($quantity * $unitPrice, 2),
        ];
    }

    private function contribute(Investment $investment, string $date, float $amount): array
    {
        return [
            'user_id' => $investment->user_id,
            'date' => $date,
            'type' => 'aporte',
            'amount' => $amount,
        ];
    }

    private function withdraw(Investment $investment, string $date, float $amount): array
    {
        return [
            'user_id' => $investment->user_id,
            'date' => $date,
            'type' => 'resgate',
            'amount' => $amount,
        ];
    }
}
