<?php

namespace Tests\Feature;

use App\Console\Commands\SnapshotPortfolio;
use App\Models\Investment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SnapshotPortfolioTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_records_a_snapshot_of_todays_portfolio_totals(): void
    {
        $user = User::factory()->create();
        Investment::factory()->for($user)->create(['invested_amount' => 1000, 'current_amount' => 1200]);
        Investment::factory()->for($user)->create(['invested_amount' => 500, 'current_amount' => 450]);

        $this->artisan(SnapshotPortfolio::class)->assertSuccessful();

        $snapshot = $user->portfolioSnapshots()->whereDate('date', today())->first();

        $this->assertNotNull($snapshot);
        $this->assertSame('1500.00', $snapshot->total_invested);
        $this->assertSame('1650.00', $snapshot->total_current);
    }

    public function test_command_skips_users_without_any_active_investment(): void
    {
        $user = User::factory()->create();

        $this->artisan(SnapshotPortfolio::class)->assertSuccessful();

        $this->assertSame(0, $user->portfolioSnapshots()->count());
    }

    public function test_running_the_command_twice_in_the_same_day_updates_instead_of_duplicating(): void
    {
        $user = User::factory()->create();
        Investment::factory()->for($user)->create(['invested_amount' => 1000, 'current_amount' => 1000]);

        $this->artisan(SnapshotPortfolio::class)->assertSuccessful();

        Investment::factory()->for($user)->create(['invested_amount' => 1000, 'current_amount' => 1000]);

        $this->artisan(SnapshotPortfolio::class)->assertSuccessful();

        $this->assertSame(1, $user->portfolioSnapshots()->whereDate('date', today())->count());
        $this->assertSame('2000.00', $user->portfolioSnapshots()->whereDate('date', today())->first()->total_current);
    }
}
