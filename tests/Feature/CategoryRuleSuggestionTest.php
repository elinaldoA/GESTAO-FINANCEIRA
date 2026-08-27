<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class CategoryRuleSuggestionTest extends TestCase
{
    use RefreshDatabase;

    private function makeTransportCategory(User $user)
    {
        return $user->categories()->create(['name' => 'Transporte', 'type' => 'despesa']);
    }

    public function test_suggests_a_rule_after_repeated_manual_categorization(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $account = $user->accounts()->create(['name' => 'Conta', 'type' => 'corrente', 'initial_balance' => 0]);
        $category = $this->makeTransportCategory($user);

        $user->transactions()->create(['account_id' => $account->id, 'category_id' => $category->id, 'type' => 'despesa', 'description' => 'Uber Manha', 'amount' => 20, 'date' => now()]);
        $user->transactions()->create(['account_id' => $account->id, 'category_id' => $category->id, 'type' => 'despesa', 'description' => 'Uber Noite', 'amount' => 18, 'date' => now()]);

        $component = Volt::test('transactions.index')
            ->set('description', 'Uber Tarde')
            ->set('amount', '35')
            ->set('date', now()->format('Y-m-d'))
            ->set('type', 'despesa')
            ->set('payment_method', 'pix')
            ->set('account_id', $account->id)
            ->set('category_id', $category->id)
            ->call('save');

        $this->assertSame('uber', $component->get('ruleSuggestion')['keyword']);
        $component->assertSee('Criar regra')->assertSee('regra automática');
    }

    public function test_accepting_the_suggestion_creates_a_category_rule(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $account = $user->accounts()->create(['name' => 'Conta', 'type' => 'corrente', 'initial_balance' => 0]);
        $category = $this->makeTransportCategory($user);

        $user->transactions()->create(['account_id' => $account->id, 'category_id' => $category->id, 'type' => 'despesa', 'description' => 'Uber Manha', 'amount' => 20, 'date' => now()]);
        $user->transactions()->create(['account_id' => $account->id, 'category_id' => $category->id, 'type' => 'despesa', 'description' => 'Uber Noite', 'amount' => 18, 'date' => now()]);

        $component = Volt::test('transactions.index')
            ->set('description', 'Uber Tarde')
            ->set('amount', '35')
            ->set('date', now()->format('Y-m-d'))
            ->set('type', 'despesa')
            ->set('payment_method', 'pix')
            ->set('account_id', $account->id)
            ->set('category_id', $category->id)
            ->call('save')
            ->call('acceptRuleSuggestion');

        $this->assertDatabaseHas('category_rules', [
            'user_id' => $user->id,
            'category_id' => $category->id,
            'keyword' => 'uber',
        ]);
        $this->assertNull($component->get('ruleSuggestion'));
    }

    public function test_no_suggestion_when_category_was_auto_matched_by_an_existing_rule(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $account = $user->accounts()->create(['name' => 'Conta', 'type' => 'corrente', 'initial_balance' => 0]);
        $category = $this->makeTransportCategory($user);
        $user->categoryRules()->create(['category_id' => $category->id, 'keyword' => 'uber']);

        $component = Volt::test('transactions.index')
            ->set('description', 'Uber Manha')
            ->set('amount', '20')
            ->set('date', now()->format('Y-m-d'))
            ->set('type', 'despesa')
            ->set('payment_method', 'pix')
            ->set('account_id', $account->id)
            ->call('save');

        $this->assertNull($component->get('ruleSuggestion'));
    }
}
