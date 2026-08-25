<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Volt\Volt;
use Tests\TestCase;

class TransactionImportTest extends TestCase
{
    use RefreshDatabase;

    private function csvFile(string $content, string $name = 'extrato.csv'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, $content);
    }

    public function test_csv_is_parsed_and_columns_are_auto_detected(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $csv = "Data;Descrição;Valor\n31/01/2026;Mercado;-150,00\n02/02/2026;Salário;3000,00\n";

        $component = Volt::test('transactions.import')
            ->set('file', $this->csvFile($csv));

        $component->assertSet('dateColumn', '0')
            ->assertSet('descriptionColumn', '1')
            ->assertSet('amountColumn', '2');
    }

    public function test_import_creates_expense_and_income_transactions_from_signed_amounts(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $account = $user->accounts()->create(['name' => 'Conta', 'type' => 'corrente', 'initial_balance' => 0]);

        $csv = "Data;Descrição;Valor\n31/01/2026;Mercado;-150,00\n02/02/2026;Salário;3000,00\n";

        Volt::test('transactions.import')
            ->set('file', $this->csvFile($csv))
            ->set('account_id', $account->id)
            ->call('import')
            ->assertDispatched('notify', type: 'success');

        $this->assertDatabaseCount('transactions', 2);

        $expense = $user->transactions()->where('type', 'despesa')->firstOrFail();
        $this->assertEquals('Mercado', $expense->description);
        $this->assertEquals(150.0, (float) $expense->amount);
        $this->assertEquals('2026-01-31', $expense->date->format('Y-m-d'));

        $income = $user->transactions()->where('type', 'receita')->firstOrFail();
        $this->assertEquals('Salário', $income->description);
        $this->assertEquals(3000.0, (float) $income->amount);
    }

    public function test_import_skips_rows_with_invalid_date_or_zero_amount(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $account = $user->accounts()->create(['name' => 'Conta', 'type' => 'corrente', 'initial_balance' => 0]);

        $csv = "Data;Descrição;Valor\ndata-invalida;Item ruim;-10,00\n01/02/2026;Item zero;0,00\n01/02/2026;Item bom;-50,00\n";

        Volt::test('transactions.import')
            ->set('file', $this->csvFile($csv))
            ->set('account_id', $account->id)
            ->call('import')
            ->assertDispatched('notify', type: 'success');

        $this->assertDatabaseCount('transactions', 1);
        $this->assertDatabaseHas('transactions', ['description' => 'Item bom']);
    }

    public function test_import_requires_an_account(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $csv = "Data;Descrição;Valor\n31/01/2026;Mercado;-150,00\n";

        Volt::test('transactions.import')
            ->set('file', $this->csvFile($csv))
            ->call('import')
            ->assertHasErrors(['account_id']);

        $this->assertDatabaseCount('transactions', 0);
    }
}
