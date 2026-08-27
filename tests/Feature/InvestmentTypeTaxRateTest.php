<?php

namespace Tests\Feature;

use App\Models\Investment;
use App\Models\InvestmentType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class InvestmentTypeTaxRateTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_set_a_tax_rate_when_creating_a_type(): void
    {
        $user = User::factory()->create();

        Volt::actingAs($user)
            ->test('investments.positions')
            ->set('new_type_name', 'FII')
            ->set('new_type_color', '#f59e0b')
            ->set('new_type_tax_rate', '20')
            ->call('addType');

        $this->assertSame('20.00', InvestmentType::where('name', 'FII')->first()->tax_rate);
    }

    public function test_user_can_create_a_type_without_a_tax_rate(): void
    {
        $user = User::factory()->create();

        Volt::actingAs($user)
            ->test('investments.positions')
            ->set('new_type_name', 'Outro')
            ->set('new_type_color', '#64748b')
            ->call('addType');

        $this->assertNull(InvestmentType::where('name', 'Outro')->first()->tax_rate);
    }

    public function test_user_can_edit_the_tax_rate_of_an_existing_type(): void
    {
        $user = User::factory()->create();
        $type = InvestmentType::factory()->for($user)->create(['tax_rate' => 15]);

        Volt::actingAs($user)
            ->test('investments.positions')
            ->call('editTypeTaxRate', $type->id)
            ->set('editing_type_tax_rate', '20')
            ->call('saveTypeTaxRate');

        $this->assertSame('20.00', $type->fresh()->tax_rate);
    }

    public function test_dashboard_shows_estimated_net_gain_when_the_type_has_a_tax_rate(): void
    {
        $user = User::factory()->create();
        $type = InvestmentType::factory()->for($user)->create(['tax_rate' => 15]);
        Investment::factory()->for($user)->create([
            'investment_type_id' => $type->id,
            'invested_amount' => 1000,
            'current_amount' => 1200,
        ]);

        Volt::actingAs($user)
            ->test('investments.index')
            ->assertSee('líquido est.');
    }

    public function test_dashboard_does_not_show_estimated_net_gain_without_any_tax_rate_configured(): void
    {
        $user = User::factory()->create();
        $type = InvestmentType::factory()->for($user)->create(['tax_rate' => null]);
        Investment::factory()->for($user)->create([
            'investment_type_id' => $type->id,
            'invested_amount' => 1000,
            'current_amount' => 1200,
        ]);

        Volt::actingAs($user)
            ->test('investments.index')
            ->assertDontSee('líquido est.');
    }
}
