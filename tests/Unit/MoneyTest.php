<?php

namespace Tests\Unit;

use App\Support\Money;
use Tests\TestCase;

class MoneyTest extends TestCase
{
    public function test_whole_amounts_still_show_two_decimals(): void
    {
        $this->assertSame('15,00', Money::trimmed(15));
    }

    public function test_tiny_amounts_show_their_full_precision(): void
    {
        $this->assertSame('0,004101', Money::trimmed(0.004101));
    }

    public function test_normal_amounts_trim_without_padding_past_what_was_entered(): void
    {
        $this->assertSame('12,50', Money::trimmed(12.5));
    }

    public function test_respects_a_custom_max_decimals(): void
    {
        $this->assertSame('0,00', Money::trimmed(0.004101, 2));
    }
}
