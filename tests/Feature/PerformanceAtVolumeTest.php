<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Volt\Volt;
use Tests\TestCase;

class PerformanceAtVolumeTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);

        $account = $this->user->accounts()->create(['name' => 'Conta principal', 'type' => 'corrente', 'initial_balance' => 1000]);
        $categories = collect(range(1, 6))->map(
            fn ($i) => $this->user->categories()->create(['name' => "Categoria {$i}", 'type' => $i % 2 === 0 ? 'receita' : 'despesa'])
        );

        $rows = [];
        $start = now()->subYears(2);

        for ($i = 0; $i < 2000; $i++) {
            $category = $categories[$i % $categories->count()];
            $rows[] = [
                'user_id' => $this->user->id,
                'account_id' => $account->id,
                'category_id' => $category->id,
                'type' => $category->type,
                'payment_method' => 'debito',
                'description' => "Transação de teste {$i}",
                'amount' => random_int(500, 50000) / 100,
                'date' => $start->copy()->addDays($i % 730)->format('Y-m-d'),
                'is_paid' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        DB::table('transactions')->insert($rows);
    }

    public function test_dashboard_query_count_does_not_scale_with_transaction_volume(): void
    {
        DB::enableQueryLog();
        $start = microtime(true);

        Volt::test('dashboard')->assertOk();

        $elapsed = microtime(true) - $start;
        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThan(40, $queryCount, "Dashboard executou {$queryCount} queries com 2000 transações — possível N+1.");
        $this->assertLessThan(3.0, $elapsed, "Dashboard levou {$elapsed}s para carregar com 2000 transações.");
    }

    public function test_transactions_index_paginates_efficiently_at_volume(): void
    {
        DB::enableQueryLog();
        $start = microtime(true);

        Volt::test('transactions.index')->assertOk()->assertSee('Transação de teste');

        $elapsed = microtime(true) - $start;
        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThan(20, $queryCount, "Listagem de transações executou {$queryCount} queries — possível N+1.");
        $this->assertLessThan(3.0, $elapsed, "Listagem de transações levou {$elapsed}s para carregar com 2000 registros.");
    }

    public function test_reports_page_loads_efficiently_at_volume(): void
    {
        DB::enableQueryLog();
        $start = microtime(true);

        Volt::test('reports.index')->assertOk();

        $elapsed = microtime(true) - $start;
        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThan(25, $queryCount, "Relatórios executaram {$queryCount} queries — possível N+1.");
        $this->assertLessThan(3.0, $elapsed, "Relatórios levaram {$elapsed}s para carregar com 2000 transações.");
    }
}
