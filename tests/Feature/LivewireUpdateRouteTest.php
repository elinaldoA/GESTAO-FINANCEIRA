<?php

namespace Tests\Feature;

use App\Models\CreditCard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class LivewireUpdateRouteTest extends TestCase
{
    use RefreshDatabase;

    public function test_livewire_update_endpoint_keeps_the_web_middleware_group(): void
    {
        $route = collect(app('router')->getRoutes())
            ->first(fn ($route) => $route->uri() === 'livewire/update');

        $this->assertNotNull($route);
        $this->assertContains('web', $route->gatherMiddleware());
    }

    public function test_editing_a_credit_card_over_real_http_persists_the_change(): void
    {
        $user = User::factory()->create();
        $card = CreditCard::factory()->for($user)->create(['name' => 'Old Name']);

        $this->actingAs($user)->get('/cartoes')->assertOk();

        Volt::actingAs($user)
            ->test('credit-cards.index')
            ->call('edit', $card->id)
            ->set('name', 'New Name')
            ->call('save');

        $this->assertSame('New Name', $card->fresh()->name);
    }
}
