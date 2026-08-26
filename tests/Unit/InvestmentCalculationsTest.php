<?php

namespace Tests\Unit;

use App\Models\Investment;
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
}
