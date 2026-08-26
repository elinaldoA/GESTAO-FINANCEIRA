<?php

namespace Tests\Unit;

use App\Models\CategoryRule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryRuleMatchTest extends TestCase
{
    use RefreshDatabase;

    public function test_matches_a_transaction_description_containing_the_keyword(): void
    {
        $user = User::factory()->create();
        $rule = CategoryRule::factory()->for($user)->create(['keyword' => 'uber']);

        $categoryId = CategoryRule::matchCategoryFor($user->id, 'Uber *Trip 123');

        $this->assertSame($rule->category_id, $categoryId);
    }

    public function test_match_is_case_insensitive(): void
    {
        $user = User::factory()->create();
        $rule = CategoryRule::factory()->for($user)->create(['keyword' => 'netflix']);

        $categoryId = CategoryRule::matchCategoryFor($user->id, 'NETFLIX.COM');

        $this->assertSame($rule->category_id, $categoryId);
    }

    public function test_returns_null_when_no_rule_matches(): void
    {
        $user = User::factory()->create();
        CategoryRule::factory()->for($user)->create(['keyword' => 'uber']);

        $this->assertNull(CategoryRule::matchCategoryFor($user->id, 'Padaria do bairro'));
    }

    public function test_prefers_the_longest_matching_keyword(): void
    {
        $user = User::factory()->create();
        CategoryRule::factory()->for($user)->create(['keyword' => 'super']);
        $specific = CategoryRule::factory()->for($user)->create(['keyword' => 'supermercado extra']);

        $categoryId = CategoryRule::matchCategoryFor($user->id, 'Compra no supermercado extra');

        $this->assertSame($specific->category_id, $categoryId);
    }

    public function test_does_not_match_rules_from_another_user(): void
    {
        $otherUser = User::factory()->create();
        CategoryRule::factory()->for($otherUser)->create(['keyword' => 'uber']);

        $user = User::factory()->create();

        $this->assertNull(CategoryRule::matchCategoryFor($user->id, 'Uber *Trip 123'));
    }
}
