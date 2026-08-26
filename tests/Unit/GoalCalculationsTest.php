<?php

namespace Tests\Unit;

use App\Models\Goal;
use Tests\TestCase;

class GoalCalculationsTest extends TestCase
{
    public function test_progress_percent_is_computed_from_current_and_target(): void
    {
        $goal = new Goal(['target_amount' => 1000, 'current_amount' => 250]);

        $this->assertSame(25.0, $goal->progress_percent);
    }

    public function test_progress_percent_is_capped_at_100(): void
    {
        $goal = new Goal(['target_amount' => 1000, 'current_amount' => 1500]);

        $this->assertSame(100.0, $goal->progress_percent);
    }

    public function test_progress_percent_is_zero_when_target_is_zero(): void
    {
        $goal = new Goal(['target_amount' => 0, 'current_amount' => 100]);

        $this->assertSame(0.0, $goal->progress_percent);
    }

    public function test_remaining_amount_never_goes_negative(): void
    {
        $goal = new Goal(['target_amount' => 1000, 'current_amount' => 1500]);

        $this->assertSame(0.0, $goal->remaining_amount);
    }

    public function test_is_achieved_when_current_reaches_target(): void
    {
        $goal = new Goal(['target_amount' => 1000, 'current_amount' => 1000]);

        $this->assertTrue($goal->is_achieved);
    }

    public function test_is_not_achieved_when_current_is_below_target(): void
    {
        $goal = new Goal(['target_amount' => 1000, 'current_amount' => 999]);

        $this->assertFalse($goal->is_achieved);
    }
}
