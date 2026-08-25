<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class BulkActionsOtherModulesTest extends TestCase
{
    use RefreshDatabase;

    public function test_bulk_activate_and_deactivate_accounts(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $a1 = $user->accounts()->create(['name' => 'A', 'type' => 'corrente', 'initial_balance' => 0, 'is_active' => true]);
        $a2 = $user->accounts()->create(['name' => 'B', 'type' => 'corrente', 'initial_balance' => 0, 'is_active' => true]);

        Volt::test('accounts.index')->set('selected', [$a1->id, $a2->id])->call('bulkDeactivate');

        $this->assertFalse($a1->fresh()->is_active);
        $this->assertFalse($a2->fresh()->is_active);

        Volt::test('accounts.index')->set('selected', [$a1->id, $a2->id])->call('bulkActivate');

        $this->assertTrue($a1->fresh()->is_active);
        $this->assertTrue($a2->fresh()->is_active);
    }

    public function test_bulk_delete_accounts_soft_deletes(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $a1 = $user->accounts()->create(['name' => 'A', 'type' => 'corrente', 'initial_balance' => 0]);
        $a2 = $user->accounts()->create(['name' => 'B', 'type' => 'corrente', 'initial_balance' => 0]);

        Volt::test('accounts.index')->set('selected', [$a1->id, $a2->id])->call('bulkDelete');

        $this->assertSoftDeleted('accounts', ['id' => $a1->id]);
        $this->assertSoftDeleted('accounts', ['id' => $a2->id]);
    }

    public function test_select_all_selects_every_account(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $a1 = $user->accounts()->create(['name' => 'A', 'type' => 'corrente', 'initial_balance' => 0]);
        $a2 = $user->accounts()->create(['name' => 'B', 'type' => 'corrente', 'initial_balance' => 0]);

        $component = Volt::test('accounts.index')->set('selectAll', true);

        $this->assertEqualsCanonicalizing([(string) $a1->id, (string) $a2->id], $component->get('selected'));
    }

    public function test_bulk_delete_credit_cards_soft_deletes(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $c1 = $user->creditCards()->create(['name' => 'Cartão 1', 'limit_amount' => 1000, 'closing_day' => 5, 'due_day' => 15]);
        $c2 = $user->creditCards()->create(['name' => 'Cartão 2', 'limit_amount' => 2000, 'closing_day' => 10, 'due_day' => 20]);

        Volt::test('credit-cards.index')->set('selected', [$c1->id, $c2->id])->call('bulkDelete');

        $this->assertSoftDeleted('credit_cards', ['id' => $c1->id]);
        $this->assertSoftDeleted('credit_cards', ['id' => $c2->id]);
    }

    public function test_bulk_delete_goals_soft_deletes(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $g1 = $user->goals()->create(['name' => 'Meta 1', 'target_amount' => 1000]);
        $g2 = $user->goals()->create(['name' => 'Meta 2', 'target_amount' => 2000]);

        Volt::test('goals.index')->set('selected', [$g1->id, $g2->id])->call('bulkDelete');

        $this->assertSoftDeleted('goals', ['id' => $g1->id]);
        $this->assertSoftDeleted('goals', ['id' => $g2->id]);
    }

    public function test_bulk_action_does_not_affect_another_users_records(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();

        $account = $owner->accounts()->create(['name' => 'Conta', 'type' => 'corrente', 'initial_balance' => 0, 'is_active' => true]);

        $this->actingAs($intruder);

        Volt::test('accounts.index')->set('selected', [$account->id])->call('bulkDeactivate');

        $this->assertTrue($account->fresh()->is_active);
    }
}
