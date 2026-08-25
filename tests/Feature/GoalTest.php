<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class GoalTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_a_goal(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Volt::test('goals.index')
            ->set('name', 'Viagem para a praia')
            ->set('target_amount', '5000')
            ->set('current_amount', '1000')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('goals', [
            'name' => 'Viagem para a praia',
            'user_id' => $user->id,
        ]);

        $goal = $user->goals()->firstOrFail();
        $this->assertEquals(20.0, $goal->progress_percent);
        $this->assertFalse($goal->is_achieved);
    }

    public function test_contributing_increases_current_amount_and_can_complete_the_goal(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $goal = $user->goals()->create(['name' => 'Reserva', 'target_amount' => 1000, 'current_amount' => 800]);

        Volt::test('goals.index')
            ->call('contribute', $goal, '200')
            ->assertDispatched('notify', type: 'success');

        $goal->refresh();
        $this->assertEquals(1000.0, (float) $goal->current_amount);
        $this->assertTrue($goal->is_achieved);
        $this->assertEquals(100.0, $goal->progress_percent);
        $this->assertEquals(0.0, $goal->remaining_amount);
    }

    public function test_contribution_rejects_non_positive_amount(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $goal = $user->goals()->create(['name' => 'Reserva', 'target_amount' => 1000, 'current_amount' => 800]);

        Volt::test('goals.index')
            ->call('contribute', $goal, '0')
            ->assertDispatched('notify', type: 'error');

        $goal->refresh();
        $this->assertEquals(800.0, (float) $goal->current_amount);
    }

    public function test_user_can_delete_a_goal(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $goal = $user->goals()->create(['name' => 'Reserva', 'target_amount' => 1000]);

        Volt::test('goals.index')->call('delete', $goal);

        $this->assertSoftDeleted('goals', ['id' => $goal->id]);
    }
}
