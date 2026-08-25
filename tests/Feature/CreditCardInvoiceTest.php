<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class CreditCardInvoiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_invoice_period_and_due_date_are_computed_correctly(): void
    {
        $user = User::factory()->create();
        $card = $user->creditCards()->create(['name' => 'Nubank', 'limit_amount' => 2000, 'closing_day' => 5, 'due_day' => 12]);

        // due_day (12) >= closing_day (5): due date falls in the same month as closing.
        $closing = $card->invoiceClosingDate(2026, 3);
        $due = $card->invoiceDueDate(2026, 3);
        [$start, $end] = $card->invoicePeriod(2026, 3);

        $this->assertEquals('2026-03-05', $closing->format('Y-m-d'));
        $this->assertEquals('2026-03-12', $due->format('Y-m-d'));
        $this->assertEquals('2026-02-06', $start->format('Y-m-d'));
        $this->assertEquals('2026-03-05', $end->format('Y-m-d'));
    }

    public function test_invoice_due_date_rolls_to_next_month_when_due_day_precedes_closing_day(): void
    {
        $user = User::factory()->create();
        $card = $user->creditCards()->create(['name' => 'Itaú', 'limit_amount' => 2000, 'closing_day' => 25, 'due_day' => 5]);

        $due = $card->invoiceDueDate(2026, 3);

        $this->assertEquals('2026-04-05', $due->format('Y-m-d'));
    }

    public function test_paying_invoice_creates_debit_transaction_and_frees_used_limit(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $card = $user->creditCards()->create(['name' => 'Nubank', 'limit_amount' => 2000, 'closing_day' => 5, 'due_day' => 12]);
        $account = $user->accounts()->create(['name' => 'Conta', 'type' => 'corrente', 'initial_balance' => 5000]);

        $user->transactions()->create([
            'credit_card_id' => $card->id,
            'type' => 'despesa',
            'payment_method' => 'credito',
            'description' => 'Compra fatura fechada',
            'amount' => 300,
            'date' => '2026-02-20',
        ]);

        $card->refresh();
        $this->assertEquals(300.0, $card->used_limit);

        $component = Volt::test('credit-cards.invoice', ['creditCard' => $card]);
        $component->set('year', 2026)->set('month', 3)
            ->assertSee('Fechada');

        $component->set('payFromAccountId', $account->id)
            ->call('payInvoice')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('transactions', [
            'account_id' => $account->id,
            'type' => 'despesa',
            'amount' => 300.00,
        ]);

        $card->refresh();
        $this->assertEquals(0.0, $card->used_limit);
    }

    public function test_cannot_view_another_users_credit_card_invoice(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $card = $owner->creditCards()->create(['name' => 'Nubank', 'limit_amount' => 2000, 'closing_day' => 5, 'due_day' => 12]);

        $this->actingAs($intruder);

        $this->get(route('credit-cards.invoice', $card))->assertForbidden();
    }
}
