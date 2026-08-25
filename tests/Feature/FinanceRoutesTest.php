<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinanceRoutesTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_access_all_finance_pages(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        foreach (['dashboard', 'contas', 'categorias', 'cartoes', 'transacoes', 'orcamentos', 'investimentos', 'metas', 'relatorios'] as $path) {
            $response = $this->get('/'.$path);
            $response->assertOk();
        }
    }
}
