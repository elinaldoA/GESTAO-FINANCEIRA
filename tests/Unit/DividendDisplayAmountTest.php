<?php

namespace Tests\Unit;

use App\Models\Dividend;
use Tests\TestCase;

class DividendDisplayAmountTest extends TestCase
{
    public function test_whole_amounts_still_show_two_decimals(): void
    {
        $dividend = new Dividend(['amount' => 15]);

        $this->assertSame('15,00', $dividend->display_amount);
    }

    public function test_sub_cent_amounts_show_their_full_precision(): void
    {
        $dividend = new Dividend(['amount' => 0.004101]);

        $this->assertSame('0,004101', $dividend->display_amount);
    }

    public function test_amounts_with_three_decimals_trim_without_padding_past_what_was_entered(): void
    {
        $dividend = new Dividend(['amount' => 12.5]);

        $this->assertSame('12,50', $dividend->display_amount);
    }
}
