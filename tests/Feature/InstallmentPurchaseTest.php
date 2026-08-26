<?php

namespace Tests\Feature;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class InstallmentPurchaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_credit_purchase_splits_into_equal_monthly_installments(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $card = $user->creditCards()->create(['name' => 'Nubank', 'limit_amount' => 5000, 'closing_day' => 5, 'due_day' => 12]);

        Volt::test('transactions.index')
            ->set('description', 'Notebook')
            ->set('amount', '1200')
            ->set('date', '2026-01-10')
            ->set('type', 'despesa')
            ->set('payment_method', 'credito')
            ->set('credit_card_id', $card->id)
            ->set('installments', 12)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseCount('transactions', 12);

        $installments = $user->transactions()->orderBy('installment_number')->get();
        $this->assertEquals(12, $installments->count());
        $this->assertEquals('Notebook (1/12)', $installments->first()->description);
        $this->assertEquals('Notebook (12/12)', $installments->last()->description);
        $this->assertEquals('2026-01-10', $installments->first()->date->format('Y-m-d'));
        $this->assertEquals('2026-12-10', $installments->last()->date->format('Y-m-d'));

        // Sum of all installments must equal the original purchase amount (rounding absorbed on the last one).
        $this->assertEquals(1200.0, (float) $installments->sum('amount'));

        // All installments must be linked to the first one.
        $parent = $installments->first();
        $this->assertNull($parent->parent_transaction_id);
        foreach ($installments->skip(1) as $installment) {
            $this->assertEquals($parent->id, $installment->parent_transaction_id);
        }

        // The full purchase must count against the card's used limit immediately.
        $card->refresh();
        $this->assertEquals(1200.0, $card->used_limit);
    }

    public function test_single_installment_credit_purchase_is_not_split(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $card = $user->creditCards()->create(['name' => 'Nubank', 'limit_amount' => 5000, 'closing_day' => 5, 'due_day' => 12]);

        Volt::test('transactions.index')
            ->set('description', 'Mercado')
            ->set('amount', '150')
            ->set('date', now()->format('Y-m-d'))
            ->set('type', 'despesa')
            ->set('payment_method', 'credito')
            ->set('credit_card_id', $card->id)
            ->set('installments', 1)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseCount('transactions', 1);
        $transaction = $user->transactions()->firstOrFail();
        $this->assertFalse($transaction->is_installment);
        $this->assertEquals('Mercado', $transaction->description);
    }

    public function test_deleting_installment_series_removes_all_installments(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $card = $user->creditCards()->create(['name' => 'Nubank', 'limit_amount' => 5000, 'closing_day' => 5, 'due_day' => 12]);

        Volt::test('transactions.index')
            ->set('description', 'Geladeira')
            ->set('amount', '900')
            ->set('date', now()->format('Y-m-d'))
            ->set('type', 'despesa')
            ->set('payment_method', 'credito')
            ->set('credit_card_id', $card->id)
            ->set('installments', 3)
            ->call('save');

        $parent = $user->transactions()->whereNull('parent_transaction_id')->firstOrFail();

        Volt::test('transactions.index')->call('deleteSeries', $parent);

        $this->assertEquals(0, Transaction::count());
    }
}
