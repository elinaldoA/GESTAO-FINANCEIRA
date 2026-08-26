<?php

namespace Tests\Feature;

use App\Models\Dividend;
use App\Models\Investment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class DividendCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_a_dividend_for_their_investment(): void
    {
        $user = User::factory()->create();
        $investment = Investment::factory()->for($user)->create();

        Volt::actingAs($user)
            ->test('investments.show', ['investment' => $investment])
            ->set('dividendDate', '2026-01-15')
            ->set('dividendType', 'dividendo')
            ->set('dividendAmount', '25.50')
            ->set('dividendNotes', 'Pagamento mensal')
            ->call('saveDividend')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('dividends', [
            'investment_id' => $investment->id,
            'user_id' => $user->id,
            'type' => 'dividendo',
            'amount' => 25.50,
            'notes' => 'Pagamento mensal',
        ]);
    }

    public function test_user_can_edit_a_dividend(): void
    {
        $user = User::factory()->create();
        $investment = Investment::factory()->for($user)->create();
        $dividend = Dividend::factory()->for($user)->for($investment)->create(['amount' => 10]);

        Volt::actingAs($user)
            ->test('investments.show', ['investment' => $investment])
            ->call('editDividend', $dividend->id)
            ->set('dividendAmount', '99.90')
            ->call('saveDividend');

        $this->assertSame('99.90', $dividend->fresh()->amount);
    }

    public function test_user_can_delete_a_dividend(): void
    {
        $user = User::factory()->create();
        $investment = Investment::factory()->for($user)->create();
        $dividend = Dividend::factory()->for($user)->for($investment)->create();

        Volt::actingAs($user)
            ->test('investments.show', ['investment' => $investment])
            ->call('deleteDividend', $dividend->id);

        $this->assertSoftDeleted('dividends', ['id' => $dividend->id]);
    }

    public function test_user_cannot_edit_another_users_dividend(): void
    {
        $owner = User::factory()->create();
        $investment = Investment::factory()->for($owner)->create();
        $dividend = Dividend::factory()->for($owner)->for($investment)->create();

        $intruder = User::factory()->create();
        $intruderInvestment = Investment::factory()->for($intruder)->create();

        Volt::actingAs($intruder)
            ->test('investments.show', ['investment' => $intruderInvestment])
            ->call('editDividend', $dividend->id)
            ->assertForbidden();
    }

    public function test_total_dividends_received_is_summed_on_the_investment(): void
    {
        $user = User::factory()->create();
        $investment = Investment::factory()->for($user)->create();
        Dividend::factory()->for($user)->for($investment)->create(['amount' => 10]);
        Dividend::factory()->for($user)->for($investment)->create(['amount' => 15.50]);

        $this->assertSame(25.50, $investment->fresh()->total_dividends_received);
    }

    public function test_deleted_dividend_appears_in_trash_and_can_be_restored(): void
    {
        $user = User::factory()->create();
        $investment = Investment::factory()->for($user)->create();
        $dividend = Dividend::factory()->for($user)->for($investment)->create();
        $dividend->delete();

        Volt::actingAs($user)
            ->test('trash.index')
            ->call('restore', 'dividends', $dividend->id);

        $this->assertDatabaseHas('dividends', ['id' => $dividend->id, 'deleted_at' => null]);
    }
}
