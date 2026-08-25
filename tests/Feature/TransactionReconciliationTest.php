<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Volt\Volt;
use Tests\TestCase;

class TransactionReconciliationTest extends TestCase
{
    use RefreshDatabase;

    public function test_import_reconciles_an_existing_matching_transaction_instead_of_duplicating(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $account = $user->accounts()->create(['name' => 'Conta', 'type' => 'corrente', 'initial_balance' => 0]);

        $existing = $user->transactions()->create([
            'account_id' => $account->id,
            'type' => 'despesa',
            'description' => 'Supermercado',
            'amount' => 150.50,
            'date' => '2026-08-10',
            'is_paid' => false,
        ]);

        $csv = "Data;Descricao;Valor\n10/08/2026;SUPERMERCADO XPTO;-150,50\n";
        $file = UploadedFile::fake()->createWithContent('extrato.csv', $csv);

        Volt::test('transactions.import')
            ->set('file', $file)
            ->set('dateColumn', '0')
            ->set('descriptionColumn', '1')
            ->set('amountColumn', '2')
            ->set('account_id', $account->id)
            ->call('import');

        $this->assertEquals(1, $user->transactions()->count());

        $existing->refresh();
        $this->assertNotNull($existing->reconciled_at);
        $this->assertTrue($existing->is_paid);
    }

    public function test_import_creates_and_reconciles_a_new_transaction_when_no_match_exists(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $account = $user->accounts()->create(['name' => 'Conta', 'type' => 'corrente', 'initial_balance' => 0]);

        $csv = "Data;Descricao;Valor\n10/08/2026;COMPRA NOVA;-80,00\n";
        $file = UploadedFile::fake()->createWithContent('extrato.csv', $csv);

        Volt::test('transactions.import')
            ->set('file', $file)
            ->set('dateColumn', '0')
            ->set('descriptionColumn', '1')
            ->set('amountColumn', '2')
            ->set('account_id', $account->id)
            ->call('import');

        $created = $user->transactions()->firstOrFail();
        $this->assertNotNull($created->reconciled_at);
    }

    public function test_user_can_manually_toggle_reconciliation(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $account = $user->accounts()->create(['name' => 'Conta', 'type' => 'corrente', 'initial_balance' => 0]);

        $transaction = $user->transactions()->create([
            'account_id' => $account->id,
            'type' => 'despesa',
            'description' => 'Compra',
            'amount' => 30,
            'date' => now(),
            'is_paid' => true,
        ]);

        Volt::test('transactions.index')->call('toggleReconciled', $transaction);
        $this->assertNotNull($transaction->fresh()->reconciled_at);

        Volt::test('transactions.index')->call('toggleReconciled', $transaction);
        $this->assertNull($transaction->fresh()->reconciled_at);
    }

    public function test_bulk_reconcile_marks_selected_transactions(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $account = $user->accounts()->create(['name' => 'Conta', 'type' => 'corrente', 'initial_balance' => 0]);

        $t1 = $user->transactions()->create(['account_id' => $account->id, 'type' => 'despesa', 'description' => 'A', 'amount' => 10, 'date' => now(), 'is_paid' => true]);
        $t2 = $user->transactions()->create(['account_id' => $account->id, 'type' => 'despesa', 'description' => 'B', 'amount' => 20, 'date' => now(), 'is_paid' => true]);

        Volt::test('transactions.index')
            ->set('selected', [$t1->id, $t2->id])
            ->call('bulkReconcile');

        $this->assertNotNull($t1->fresh()->reconciled_at);
        $this->assertNotNull($t2->fresh()->reconciled_at);
    }

    public function test_filter_by_reconciled_status(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $account = $user->accounts()->create(['name' => 'Conta', 'type' => 'corrente', 'initial_balance' => 0]);

        $user->transactions()->create(['account_id' => $account->id, 'type' => 'despesa', 'description' => 'Conciliada', 'amount' => 10, 'date' => now(), 'is_paid' => true, 'reconciled_at' => now()]);
        $user->transactions()->create(['account_id' => $account->id, 'type' => 'despesa', 'description' => 'Pendente conciliar', 'amount' => 20, 'date' => now(), 'is_paid' => true]);

        Volt::test('transactions.index')
            ->set('filterMonth', '')
            ->set('filterReconciled', 'sim')
            ->assertSee('Conciliada')
            ->assertDontSee('Pendente conciliar');
    }
}
