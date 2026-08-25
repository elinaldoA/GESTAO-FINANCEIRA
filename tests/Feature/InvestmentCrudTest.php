<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class InvestmentCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_edit_and_delete_an_investment(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // Mounting seeds the user's default investment types automatically.
        $component = Volt::test('investments.index');
        $type = $user->investmentTypes()->where('name', 'Tesouro Direto')->firstOrFail();

        $component
            ->set('name', 'Tesouro Selic 2029')
            ->set('investment_type_id', $type->id)
            ->set('broker', 'XP Investimentos')
            ->set('invested_amount', '1000')
            ->set('current_amount', '1080')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('investments', [
            'name' => 'Tesouro Selic 2029',
            'user_id' => $user->id,
            'investment_type_id' => $type->id,
        ]);

        $investment = $user->investments()->first();
        $this->assertEquals(80.0, $investment->gain);
        $this->assertEquals(8.0, $investment->gain_percent);

        Volt::test('investments.index')
            ->call('edit', $investment)
            ->set('current_amount', '900')
            ->call('save')
            ->assertHasNoErrors();

        $investment->refresh();
        $this->assertEquals('900.00', $investment->current_amount);
        $this->assertTrue($investment->gain < 0);

        Volt::test('investments.index')->call('delete', $investment);
        $this->assertSoftDeleted('investments', ['id' => $investment->id]);
    }

    public function test_user_can_create_a_custom_investment_type(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Volt::test('investments.index')
            ->set('new_type_name', 'FII')
            ->set('new_type_color', '#f59e0b')
            ->call('addType')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('investment_types', [
            'name' => 'FII',
            'user_id' => $user->id,
        ]);
    }
}
