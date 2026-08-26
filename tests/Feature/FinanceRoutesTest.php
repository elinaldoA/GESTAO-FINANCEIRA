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

        foreach (['dashboard', 'contas', 'categorias', 'cartoes', 'transacoes', 'orcamentos', 'investimentos', 'metas', 'relatorios', 'lixeira'] as $path) {
            $response = $this->get('/'.$path);
            $response->assertOk();
        }
    }

    public function test_user_with_unverified_email_is_redirected_away_from_finance_pages(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user);

        $response = $this->get('/dashboard');

        $response->assertRedirect(route('verification.notice'));
    }
}
