<?php

namespace Tests\Unit;

use App\Models\Investment;
use App\Models\InvestmentType;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class InvestmentCalculationsTest extends TestCase
{
    public function test_gain_is_the_difference_between_current_and_invested_amount(): void
    {
        $investment = new Investment(['invested_amount' => 1000, 'current_amount' => 1200]);

        $this->assertSame(200.0, $investment->gain);
    }

    public function test_gain_is_negative_when_current_amount_is_lower(): void
    {
        $investment = new Investment(['invested_amount' => 1000, 'current_amount' => 800]);

        $this->assertSame(-200.0, $investment->gain);
    }

    public function test_gain_percent_is_computed_relative_to_invested_amount(): void
    {
        $investment = new Investment(['invested_amount' => 1000, 'current_amount' => 1250]);

        $this->assertSame(25.0, $investment->gain_percent);
    }

    public function test_gain_percent_is_zero_when_invested_amount_is_zero(): void
    {
        $investment = new Investment(['invested_amount' => 0, 'current_amount' => 500]);

        $this->assertSame(0.0, $investment->gain_percent);
    }

    public function test_quote_status_is_null_without_a_ticker(): void
    {
        $investment = new Investment(['ticker' => null]);

        $this->assertNull($investment->quote_status);
    }

    public function test_quote_status_is_never_when_the_ticker_was_never_fetched(): void
    {
        $investment = new Investment(['ticker' => 'PETR4', 'quote_updated_at' => null]);

        $this->assertSame('never', $investment->quote_status);
    }

    public function test_quote_status_is_failing_when_the_last_attempt_failed_after_the_last_success(): void
    {
        $investment = new Investment([
            'ticker' => 'PETR4',
            'quote_updated_at' => Carbon::now()->subMinutes(5),
            'quote_failed_at' => Carbon::now()->subMinute(),
        ]);

        $this->assertSame('failing', $investment->quote_status);
    }

    public function test_quote_status_is_stale_after_20_minutes_without_a_successful_update(): void
    {
        $investment = new Investment([
            'ticker' => 'PETR4',
            'quote_updated_at' => Carbon::now()->subMinutes(21),
        ]);

        $this->assertSame('stale', $investment->quote_status);
    }

    public function test_quote_status_is_fresh_when_recently_updated_successfully(): void
    {
        $investment = new Investment([
            'ticker' => 'PETR4',
            'quote_updated_at' => Carbon::now()->subMinutes(2),
        ]);

        $this->assertSame('fresh', $investment->quote_status);
    }

    public function test_net_gain_is_null_without_an_investment_type(): void
    {
        $investment = new Investment(['invested_amount' => 1000, 'current_amount' => 1200]);

        $this->assertNull($investment->net_gain);
        $this->assertNull($investment->net_gain_percent);
    }

    public function test_net_gain_is_null_when_the_type_has_no_tax_rate_configured(): void
    {
        $investment = new Investment(['invested_amount' => 1000, 'current_amount' => 1200]);
        $investment->setRelation('investmentType', new InvestmentType(['tax_rate' => null]));

        $this->assertNull($investment->net_gain);
    }

    public function test_net_gain_discounts_the_tax_rate_on_a_positive_gain(): void
    {
        $investment = new Investment(['invested_amount' => 1000, 'current_amount' => 1200]);
        $investment->setRelation('investmentType', new InvestmentType(['tax_rate' => 15]));

        $this->assertSame(170.0, $investment->net_gain);
        $this->assertSame(17.0, $investment->net_gain_percent);
    }

    public function test_net_gain_is_not_discounted_on_a_loss(): void
    {
        $investment = new Investment(['invested_amount' => 1000, 'current_amount' => 800]);
        $investment->setRelation('investmentType', new InvestmentType(['tax_rate' => 15]));

        $this->assertSame(-200.0, $investment->net_gain);
    }
}
