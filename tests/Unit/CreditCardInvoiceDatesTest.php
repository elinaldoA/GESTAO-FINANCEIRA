<?php

namespace Tests\Unit;

use App\Models\CreditCard;
use Tests\TestCase;

class CreditCardInvoiceDatesTest extends TestCase
{
    public function test_invoice_closing_date_uses_the_configured_day(): void
    {
        $card = new CreditCard(['closing_day' => 10, 'due_day' => 17]);

        $this->assertSame('2026-03-10', $card->invoiceClosingDate(2026, 3)->format('Y-m-d'));
    }

    public function test_invoice_closing_date_clamps_to_the_last_day_of_short_months(): void
    {
        $card = new CreditCard(['closing_day' => 31, 'due_day' => 7]);

        $this->assertSame('2026-02-28', $card->invoiceClosingDate(2026, 2)->format('Y-m-d'));
    }

    public function test_due_date_stays_in_the_same_month_when_due_day_is_after_closing_day(): void
    {
        $card = new CreditCard(['closing_day' => 10, 'due_day' => 17]);

        $this->assertSame('2026-03-17', $card->invoiceDueDate(2026, 3)->format('Y-m-d'));
    }

    public function test_due_date_rolls_over_to_the_next_month_when_due_day_is_before_closing_day(): void
    {
        $card = new CreditCard(['closing_day' => 28, 'due_day' => 5]);

        $this->assertSame('2026-04-05', $card->invoiceDueDate(2026, 3)->format('Y-m-d'));
    }

    public function test_invoice_period_spans_from_the_day_after_previous_closing_to_this_closing(): void
    {
        $card = new CreditCard(['closing_day' => 10, 'due_day' => 17]);

        [$start, $end] = $card->invoicePeriod(2026, 3);

        $this->assertSame('2026-02-11', $start->format('Y-m-d'));
        $this->assertSame('2026-03-10', $end->format('Y-m-d'));
    }
}
