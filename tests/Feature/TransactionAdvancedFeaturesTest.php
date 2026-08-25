<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;
use Tests\TestCase;

class TransactionAdvancedFeaturesTest extends TestCase
{
    use RefreshDatabase;

    public function test_recurring_transaction_generates_future_occurrences(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $account = $user->accounts()->create(['name' => 'Conta', 'type' => 'corrente', 'initial_balance' => 0]);

        Volt::test('transactions.index')
            ->set('description', 'Assinatura Streaming')
            ->set('amount', '39.90')
            ->set('date', now()->format('Y-m-d'))
            ->set('type', 'despesa')
            ->set('account_id', $account->id)
            ->set('is_recurring', true)
            ->set('recurrence_interval', 'mensal')
            ->set('recurrence_count', 3)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseCount('transactions', 4);

        $parent = $user->transactions()->whereNull('parent_transaction_id')->firstOrFail();
        $this->assertTrue($parent->is_recurring);
        $this->assertEquals(3, $user->transactions()->where('parent_transaction_id', $parent->id)->count());
        $this->assertFalse($user->transactions()->where('parent_transaction_id', $parent->id)->first()->is_paid);
    }

    public function test_delete_series_removes_future_occurrences(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $account = $user->accounts()->create(['name' => 'Conta', 'type' => 'corrente', 'initial_balance' => 0]);

        Volt::test('transactions.index')
            ->set('description', 'Academia')
            ->set('amount', '100')
            ->set('date', now()->format('Y-m-d'))
            ->set('type', 'despesa')
            ->set('account_id', $account->id)
            ->set('is_recurring', true)
            ->set('recurrence_interval', 'mensal')
            ->set('recurrence_count', 5)
            ->call('save');

        $parent = $user->transactions()->whereNull('parent_transaction_id')->firstOrFail();

        Volt::test('transactions.index')->call('deleteSeries', $parent);

        $this->assertEquals(0, \App\Models\Transaction::count());
    }

    public function test_transaction_can_have_attachment_uploaded_and_removed(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $this->actingAs($user);
        $account = $user->accounts()->create(['name' => 'Conta', 'type' => 'corrente', 'initial_balance' => 0]);
        $file = UploadedFile::fake()->create('nota.pdf', 200, 'application/pdf');

        Volt::test('transactions.index')
            ->set('description', 'Compra com nota')
            ->set('amount', '50')
            ->set('date', now()->format('Y-m-d'))
            ->set('type', 'despesa')
            ->set('account_id', $account->id)
            ->set('attachment', $file)
            ->call('save')
            ->assertHasNoErrors();

        $transaction = $user->transactions()->firstOrFail();
        $this->assertNotNull($transaction->attachment_path);
        Storage::disk('public')->assertExists($transaction->attachment_path);

        Volt::test('transactions.index')
            ->call('edit', $transaction)
            ->call('removeAttachment');

        $transaction->refresh();
        $this->assertNull($transaction->attachment_path);
        Storage::disk('public')->assertMissing($transaction->attachment_path ?? 'nonexistent');
    }

    public function test_budget_alert_is_dispatched_when_threshold_exceeded(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $account = $user->accounts()->create(['name' => 'Conta', 'type' => 'corrente', 'initial_balance' => 0]);
        $category = $user->categories()->create(['name' => 'Mercado', 'type' => 'despesa']);
        $user->budgets()->create([
            'category_id' => $category->id,
            'month' => now()->month,
            'year' => now()->year,
            'amount' => 100,
        ]);

        Volt::test('transactions.index')
            ->set('description', 'Compra grande')
            ->set('amount', '150')
            ->set('date', now()->format('Y-m-d'))
            ->set('type', 'despesa')
            ->set('account_id', $account->id)
            ->set('category_id', $category->id)
            ->call('save')
            ->assertDispatched('notify', type: 'error');
    }

    public function test_csv_export_streams_a_response(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $account = $user->accounts()->create(['name' => 'Conta', 'type' => 'corrente', 'initial_balance' => 0]);
        $user->transactions()->create([
            'account_id' => $account->id,
            'type' => 'despesa',
            'description' => 'Teste export',
            'amount' => 10,
            'date' => now(),
        ]);

        $response = Volt::test('transactions.index')->call('exportCsv');

        $response->assertStatus(200);
    }
}
