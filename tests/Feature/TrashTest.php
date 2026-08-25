<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class TrashTest extends TestCase
{
    use RefreshDatabase;

    public function test_deleted_account_appears_in_trash_and_can_be_restored(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $account = $user->accounts()->create(['name' => 'Conta teste', 'type' => 'corrente', 'initial_balance' => 0]);
        $account->delete();

        $this->assertSoftDeleted('accounts', ['id' => $account->id]);

        Volt::test('trash.index')->assertSee('Conta teste');

        Volt::test('trash.index')->call('restore', 'accounts', $account->id);

        $this->assertNotSoftDeleted('accounts', ['id' => $account->id]);
    }

    public function test_deleted_account_can_be_permanently_removed(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $account = $user->accounts()->create(['name' => 'Conta teste', 'type' => 'corrente', 'initial_balance' => 0]);
        $account->delete();

        Volt::test('trash.index')->call('forceDelete', 'accounts', $account->id);

        $this->assertDatabaseMissing('accounts', ['id' => $account->id]);
    }

    public function test_user_cannot_restore_another_users_deleted_account(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();

        $account = $owner->accounts()->create(['name' => 'Conta privada', 'type' => 'corrente', 'initial_balance' => 0]);
        $account->delete();

        $this->actingAs($intruder);

        Volt::test('trash.index')->call('restore', 'accounts', $account->id)->assertForbidden();
    }

    public function test_deleted_transaction_is_excluded_from_normal_listing_and_balance(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $account = $user->accounts()->create(['name' => 'Conta', 'type' => 'corrente', 'initial_balance' => 100]);

        $transaction = $user->transactions()->create([
            'account_id' => $account->id,
            'type' => 'despesa',
            'description' => 'Compra teste',
            'amount' => 50,
            'date' => now(),
            'is_paid' => true,
        ]);

        $this->assertEquals(50, $account->fresh()->current_balance);

        $transaction->delete();

        $this->assertEquals(100, $account->fresh()->current_balance);
        $this->assertSoftDeleted('transactions', ['id' => $transaction->id]);
    }
}
