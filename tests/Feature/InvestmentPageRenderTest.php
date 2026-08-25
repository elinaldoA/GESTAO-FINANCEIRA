<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class InvestmentPageRenderTest extends TestCase
{
    use RefreshDatabase;

    public function test_page_renders_with_allocation_and_filter(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $component = Volt::test('investments.index');
        $renda = $user->investmentTypes()->where('name', 'Renda fixa')->firstOrFail();
        $acoes = $user->investmentTypes()->where('name', 'Ações')->firstOrFail();

        $component->set('name', 'CDB Banco X')
            ->set('investment_type_id', $renda->id)
            ->set('invested_amount', '1000')
            ->set('current_amount', '1100')
            ->call('save')->assertHasNoErrors();

        Volt::test('investments.index')
            ->set('name', 'PETR4')
            ->set('investment_type_id', $acoes->id)
            ->set('invested_amount', '500')
            ->set('current_amount', '450')
            ->call('save')->assertHasNoErrors();

        $page = Volt::test('investments.index');
        $page->assertSee('Distribuição da carteira')
            ->assertSee('Patrimônio')
            ->assertSee('CDB Banco X')
            ->assertSee('PETR4')
            ->assertSee('% carteira');

        $page->call('filterByType', $renda->id)
            ->assertSee('CDB Banco X')
            ->assertDontSee('PETR4');
    }
}
