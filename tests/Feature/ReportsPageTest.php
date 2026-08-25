<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class ReportsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_reports_page_renders_with_charts_and_custom_range(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $account = $user->accounts()->create(['name' => 'Conta', 'type' => 'corrente', 'initial_balance' => 0]);
        $category = $user->categories()->create(['name' => 'Mercado', 'type' => 'despesa', 'color' => '#ef4444']);
        $user->transactions()->create([
            'account_id' => $account->id,
            'category_id' => $category->id,
            'type' => 'despesa',
            'description' => 'Compra do mês',
            'amount' => 200,
            'date' => now(),
        ]);

        $page = Volt::test('reports.index');
        $page->assertSee('Despesas por categoria')
            ->assertSee('Receitas x Despesas')
            ->assertSee('Mercado');

        $page->set('customRange', true)
            ->set('startDate', now()->startOfMonth()->format('Y-m-d'))
            ->set('endDate', now()->endOfMonth()->format('Y-m-d'))
            ->assertSee('Mercado');

        $page->call('exportCsv')->assertStatus(200);
    }
}
