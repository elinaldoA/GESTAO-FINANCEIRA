<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Volt\Volt;
use Tests\TestCase;

class CategoryRuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_a_category_rule(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $category = $user->categories()->create(['name' => 'Transporte', 'type' => 'despesa']);

        Volt::test('categories.index')
            ->set('ruleKeyword', 'Uber')
            ->set('ruleCategoryId', $category->id)
            ->call('addRule')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('category_rules', [
            'user_id' => $user->id,
            'keyword' => 'Uber',
            'category_id' => $category->id,
        ]);
    }

    public function test_transaction_is_auto_categorized_when_description_matches_a_rule(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $account = $user->accounts()->create(['name' => 'Conta', 'type' => 'corrente', 'initial_balance' => 0]);
        $category = $user->categories()->create(['name' => 'Transporte', 'type' => 'despesa']);
        $user->categoryRules()->create(['keyword' => 'uber', 'category_id' => $category->id]);

        Volt::test('transactions.index')
            ->set('description', 'Corrida de Uber para o aeroporto')
            ->set('amount', '35')
            ->set('date', now()->format('Y-m-d'))
            ->set('type', 'despesa')
            ->set('payment_method', 'pix')
            ->set('account_id', $account->id)
            ->call('save')
            ->assertHasNoErrors();

        $transaction = $user->transactions()->firstOrFail();
        $this->assertEquals($category->id, $transaction->category_id);
    }

    public function test_manually_chosen_category_is_not_overridden_by_a_rule(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $account = $user->accounts()->create(['name' => 'Conta', 'type' => 'corrente', 'initial_balance' => 0]);
        $autoCategory = $user->categories()->create(['name' => 'Transporte', 'type' => 'despesa']);
        $manualCategory = $user->categories()->create(['name' => 'Lazer', 'type' => 'despesa']);
        $user->categoryRules()->create(['keyword' => 'uber', 'category_id' => $autoCategory->id]);

        Volt::test('transactions.index')
            ->set('description', 'Uber eats jantar')
            ->set('amount', '35')
            ->set('date', now()->format('Y-m-d'))
            ->set('type', 'despesa')
            ->set('payment_method', 'pix')
            ->set('account_id', $account->id)
            ->set('category_id', $manualCategory->id)
            ->call('save');

        $transaction = $user->transactions()->firstOrFail();
        $this->assertEquals($manualCategory->id, $transaction->category_id);
    }

    public function test_import_applies_category_rules_per_row(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $account = $user->accounts()->create(['name' => 'Conta', 'type' => 'corrente', 'initial_balance' => 0]);
        $transporte = $user->categories()->create(['name' => 'Transporte', 'type' => 'despesa']);
        $user->categoryRules()->create(['keyword' => 'uber', 'category_id' => $transporte->id]);

        $csv = "Data;Descrição;Valor\n01/02/2026;Uber Trip;-25,00\n02/02/2026;Padaria;-15,00\n";
        $file = UploadedFile::fake()->createWithContent('extrato.csv', $csv);

        Volt::test('transactions.import')
            ->set('file', $file)
            ->set('account_id', $account->id)
            ->call('import');

        $uberTransaction = $user->transactions()->where('description', 'Uber Trip')->firstOrFail();
        $this->assertEquals($transporte->id, $uberTransaction->category_id);

        $padariaTransaction = $user->transactions()->where('description', 'Padaria')->firstOrFail();
        $this->assertNull($padariaTransaction->category_id);
    }

    public function test_user_can_delete_a_category_rule(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $category = $user->categories()->create(['name' => 'Transporte', 'type' => 'despesa']);
        $rule = $user->categoryRules()->create(['keyword' => 'uber', 'category_id' => $category->id]);

        Volt::test('categories.index')->call('deleteRule', $rule);

        $this->assertDatabaseMissing('category_rules', ['id' => $rule->id]);
    }
}
