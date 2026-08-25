<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class TransactionBulkActionsTest extends TestCase
{
    use RefreshDatabase;

    private function makeTransaction($user, $account, array $overrides = [])
    {
        return $user->transactions()->create(array_merge([
            'account_id' => $account->id,
            'type' => 'despesa',
            'description' => 'Compra',
            'amount' => 50,
            'date' => now(),
            'is_paid' => false,
        ], $overrides));
    }

    public function test_bulk_mark_paid_updates_all_selected_transactions(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $account = $user->accounts()->create(['name' => 'Conta', 'type' => 'corrente', 'initial_balance' => 0]);

        $t1 = $this->makeTransaction($user, $account);
        $t2 = $this->makeTransaction($user, $account);

        Volt::test('transactions.index')
            ->set('selected', [$t1->id, $t2->id])
            ->call('bulkMarkPaid');

        $this->assertTrue($t1->fresh()->is_paid);
        $this->assertTrue($t2->fresh()->is_paid);
    }

    public function test_bulk_assign_category_updates_all_selected_transactions(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $account = $user->accounts()->create(['name' => 'Conta', 'type' => 'corrente', 'initial_balance' => 0]);
        $category = $user->categories()->create(['name' => 'Mercado', 'type' => 'despesa']);

        $t1 = $this->makeTransaction($user, $account);
        $t2 = $this->makeTransaction($user, $account);

        Volt::test('transactions.index')
            ->set('selected', [$t1->id, $t2->id])
            ->set('bulkCategoryId', (string) $category->id)
            ->call('bulkAssignCategory');

        $this->assertEquals($category->id, $t1->fresh()->category_id);
        $this->assertEquals($category->id, $t2->fresh()->category_id);
    }

    public function test_bulk_delete_soft_deletes_all_selected_transactions(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $account = $user->accounts()->create(['name' => 'Conta', 'type' => 'corrente', 'initial_balance' => 0]);

        $t1 = $this->makeTransaction($user, $account);
        $t2 = $this->makeTransaction($user, $account);

        Volt::test('transactions.index')
            ->set('selected', [$t1->id, $t2->id])
            ->call('bulkDelete');

        $this->assertSoftDeleted('transactions', ['id' => $t1->id]);
        $this->assertSoftDeleted('transactions', ['id' => $t2->id]);
    }

    public function test_bulk_action_only_affects_the_authenticated_users_own_transactions(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $account = $owner->accounts()->create(['name' => 'Conta', 'type' => 'corrente', 'initial_balance' => 0]);

        $transaction = $this->makeTransaction($owner, $account);

        $this->actingAs($intruder);

        Volt::test('transactions.index')
            ->set('selected', [$transaction->id])
            ->call('bulkMarkPaid');

        $this->assertFalse($transaction->fresh()->is_paid);
    }
}
