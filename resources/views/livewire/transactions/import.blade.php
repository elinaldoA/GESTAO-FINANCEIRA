<?php

use App\Models\CategoryRule;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts.app')] class extends Component
{
    use WithFileUploads;

    public $file = null;

    public array $headers = [];

    public array $previewRows = [];

    public string $dateColumn = '';

    public string $descriptionColumn = '';

    public string $amountColumn = '';

    public string $dateFormat = 'd/m/Y';

    public string $decimalSeparator = ',';

    public ?int $account_id = null;

    public ?int $category_id = null;

    public function updatedFile(): void
    {
        $this->validate(['file' => 'required|file|mimes:csv,txt|max:5120']);

        $delimiter = $this->detectDelimiter();
        $handle = fopen($this->file->getRealPath(), 'r');

        $this->headers = fgetcsv($handle, 0, $delimiter) ?: [];
        $this->previewRows = [];

        $count = 0;
        while ($count < 8 && ($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            $this->previewRows[] = $row;
            $count++;
        }
        fclose($handle);

        $this->dateColumn = '';
        $this->descriptionColumn = '';
        $this->amountColumn = '';

        foreach ($this->headers as $i => $h) {
            $h = mb_strtolower(trim($h));
            if ($this->dateColumn === '' && in_array($h, ['data', 'date'])) {
                $this->dateColumn = (string) $i;
            }
            if ($this->descriptionColumn === '' && in_array($h, ['descrição', 'descricao', 'description', 'histórico', 'historico'])) {
                $this->descriptionColumn = (string) $i;
            }
            if ($this->amountColumn === '' && in_array($h, ['valor', 'amount', 'value'])) {
                $this->amountColumn = (string) $i;
            }
        }
    }

    private function detectDelimiter(): string
    {
        $firstLine = fgets(fopen($this->file->getRealPath(), 'r'));

        return substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';
    }

    private function parseAmount(string $raw): float
    {
        $raw = trim($raw);

        if ($this->decimalSeparator === ',') {
            $raw = str_replace('.', '', $raw);
            $raw = str_replace(',', '.', $raw);
        } else {
            $raw = str_replace(',', '', $raw);
        }

        return (float) $raw;
    }

    public function import(): void
    {
        $this->validate([
            'file' => 'required|file',
            'dateColumn' => 'required',
            'descriptionColumn' => 'required',
            'amountColumn' => 'required',
            'account_id' => ['required', Rule::exists('accounts', 'id')->where('user_id', auth()->id())],
        ]);

        $delimiter = $this->detectDelimiter();
        $handle = fopen($this->file->getRealPath(), 'r');
        fgetcsv($handle, 0, $delimiter);

        $imported = 0;
        $reconciled = 0;
        $failed = 0;

        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            try {
                $dateRaw = trim($row[(int) $this->dateColumn] ?? '');
                $description = trim($row[(int) $this->descriptionColumn] ?? '');
                $amountRaw = trim($row[(int) $this->amountColumn] ?? '');

                if ($dateRaw === '' || $amountRaw === '') {
                    $failed++;

                    continue;
                }

                $date = Carbon::createFromFormat($this->dateFormat, $dateRaw);
                $amount = $this->parseAmount($amountRaw);

                if ($amount === 0.0) {
                    $failed++;

                    continue;
                }

                $type = $amount < 0 ? 'despesa' : 'receita';

                $match = auth()->user()->transactions()
                    ->where('account_id', $this->account_id)
                    ->where('type', $type)
                    ->where('amount', abs($amount))
                    ->whereNull('reconciled_at')
                    ->whereBetween('date', [$date->copy()->subDays(3), $date->copy()->addDays(3)])
                    ->get()
                    ->sortBy(fn ($t) => abs($t->date->diffInDays($date, false)))
                    ->first();

                if ($match) {
                    $match->update(['reconciled_at' => now(), 'is_paid' => true]);
                    $reconciled++;

                    continue;
                }

                $matchedCategoryId = CategoryRule::matchCategoryFor(auth()->id(), $description) ?? $this->category_id;

                auth()->user()->transactions()->create([
                    'account_id' => $this->account_id,
                    'category_id' => $matchedCategoryId,
                    'type' => $type,
                    'payment_method' => 'debito',
                    'description' => $description !== '' ? $description : 'Importado do extrato',
                    'amount' => abs($amount),
                    'date' => $date->format('Y-m-d'),
                    'is_paid' => true,
                    'reconciled_at' => now(),
                ]);

                $imported++;
            } catch (Throwable $e) {
                $failed++;
            }
        }

        fclose($handle);

        $this->reset(['file', 'headers', 'previewRows', 'dateColumn', 'descriptionColumn', 'amountColumn']);

        $message = "{$imported} ".($imported === 1 ? 'transação nova importada' : 'transações novas importadas').'.';
        if ($reconciled > 0) {
            $message .= " {$reconciled} já existentes foram conciliadas com o extrato.";
        }
        if ($failed > 0) {
            $message .= " {$failed} ".($failed === 1 ? 'linha foi ignorada' : 'linhas foram ignoradas').' por erro de formato.';
        }

        $this->dispatch('notify', type: ($imported > 0 || $reconciled > 0) ? 'success' : 'warning', message: $message);
    }

    public function with(): array
    {
        return [
            'accounts' => auth()->user()->accounts()->where('is_active', true)->get(),
            'categories' => auth()->user()->categories()->orderBy('name')->get(),
        ];
    }
}; ?>

    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Importar extrato') }}</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <div class="bg-white shadow-sm rounded-lg p-6">
                <h3 class="font-semibold text-gray-800 mb-1">1. Envie o arquivo CSV</h3>
                <p class="text-sm text-gray-500 mb-4">Exporte o extrato do seu banco em CSV e envie aqui. O arquivo deve ter uma linha de cabeçalho.</p>

                <input type="file" wire:model="file" accept=".csv,.txt" class="block w-full text-sm text-gray-600">
                <div wire:loading wire:target="file" class="text-xs text-gray-500 mt-1">Lendo arquivo...</div>
                <x-input-error :messages="$errors->get('file')" class="mt-1" />
            </div>

            @if(count($headers))
                <div class="bg-white shadow-sm rounded-lg p-6">
                    <h3 class="font-semibold text-gray-800 mb-1">2. Configure a importação</h3>
                    <p class="text-sm text-gray-500 mb-4">Indique quais colunas do arquivo correspondem a cada campo.</p>

                    <form wire:submit="import" class="space-y-4">
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <div>
                                <x-input-label value="Coluna da data" />
                                <select wire:model="dateColumn" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                                    <option value="">Selecione</option>
                                    @foreach ($headers as $i => $h)
                                        <option value="{{ $i }}">{{ $h }}</option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('dateColumn')" class="mt-1" />
                            </div>
                            <div>
                                <x-input-label value="Coluna da descrição" />
                                <select wire:model="descriptionColumn" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                                    <option value="">Selecione</option>
                                    @foreach ($headers as $i => $h)
                                        <option value="{{ $i }}">{{ $h }}</option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('descriptionColumn')" class="mt-1" />
                            </div>
                            <div>
                                <x-input-label value="Coluna do valor" />
                                <select wire:model="amountColumn" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                                    <option value="">Selecione</option>
                                    @foreach ($headers as $i => $h)
                                        <option value="{{ $i }}">{{ $h }}</option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('amountColumn')" class="mt-1" />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                            <div>
                                <x-input-label value="Formato da data" />
                                <select wire:model="dateFormat" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                                    <option value="d/m/Y">31/01/2026</option>
                                    <option value="Y-m-d">2026-01-31</option>
                                    <option value="m/d/Y">01/31/2026</option>
                                </select>
                            </div>
                            <div>
                                <x-input-label value="Separador decimal" />
                                <select wire:model="decimalSeparator" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                                    <option value=",">Vírgula (1.234,56)</option>
                                    <option value=".">Ponto (1,234.56)</option>
                                </select>
                            </div>
                            <div>
                                <x-input-label value="Lançar na conta" />
                                <select wire:model="account_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                                    <option value="">Selecione</option>
                                    @foreach ($accounts as $account)
                                        <option value="{{ $account->id }}">{{ $account->name }}</option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('account_id')" class="mt-1" />
                            </div>
                            <div>
                                <x-input-label value="Categoria (opcional)" />
                                <select wire:model="category_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                                    <option value="">Sem categoria</option>
                                    @foreach ($categories as $category)
                                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <p class="text-xs text-gray-500">Valores negativos viram despesa, valores positivos viram receita, automaticamente.</p>

                        <x-primary-button type="submit">Importar transações</x-primary-button>
                    </form>
                </div>

                <div class="bg-white shadow-sm rounded-lg overflow-hidden">
                    <div class="px-6 py-3 border-b border-gray-100">
                        <h3 class="font-semibold text-gray-800">Pré-visualização (primeiras linhas)</h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    @foreach ($headers as $h)
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ $h }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                @foreach ($previewRows as $row)
                                    <tr>
                                        @foreach ($row as $cell)
                                            <td class="px-4 py-2 text-gray-700 whitespace-nowrap">{{ $cell }}</td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            <a href="{{ route('transactions.index') }}" wire:navigate class="inline-block text-sm text-indigo-600 hover:underline">← Voltar para transações</a>
        </div>
    </div>
