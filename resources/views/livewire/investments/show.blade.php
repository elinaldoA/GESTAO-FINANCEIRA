<?php

use App\Models\Dividend;
use App\Models\Investment;
use App\Services\StockQuoteService;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public Investment $investment;

    public string $dividendDate = '';

    public string $dividendType = 'dividendo';

    public string $dividendAmount = '';

    public string $dividendNotes = '';

    public ?int $editingDividendId = null;

    public function mount(Investment $investment): void
    {
        $this->authorize('view', $investment);

        $this->investment = $investment;
        $this->dividendDate = now()->format('Y-m-d');
    }

    public function saveDividend(): void
    {
        $this->validate([
            'dividendDate' => 'required|date',
            'dividendType' => 'required|in:dividendo,jcp,rendimento,outro',
            'dividendAmount' => 'required|numeric|min:0.01',
            'dividendNotes' => 'nullable|string|max:255',
        ]);

        $isNew = $this->editingDividendId === null;

        $data = [
            'investment_id' => $this->investment->id,
            'date' => $this->dividendDate,
            'type' => $this->dividendType,
            'amount' => $this->dividendAmount,
            'notes' => $this->dividendNotes !== '' ? $this->dividendNotes : null,
        ];

        if ($this->editingDividendId) {
            $dividend = Dividend::findOrFail($this->editingDividendId);
            $this->authorize('update', $dividend);
            $dividend->update($data);
        } else {
            auth()->user()->dividends()->create($data);
        }

        $this->resetDividendForm();

        $this->dispatch('notify', type: 'success', message: $isNew ? 'Provento registrado com sucesso.' : 'Provento atualizado com sucesso.');
    }

    public function editDividend(Dividend $dividend): void
    {
        $this->authorize('update', $dividend);

        $this->editingDividendId = $dividend->id;
        $this->dividendDate = $dividend->date->format('Y-m-d');
        $this->dividendType = $dividend->type;
        $this->dividendAmount = (string) $dividend->amount;
        $this->dividendNotes = (string) $dividend->notes;
    }

    public function cancelEditDividend(): void
    {
        $this->resetDividendForm();
    }

    public function deleteDividend(Dividend $dividend): void
    {
        $this->authorize('delete', $dividend);
        $dividend->delete();

        $this->dispatch('notify', type: 'success', message: 'Provento excluído com sucesso.');
    }

    private function resetDividendForm(): void
    {
        $this->reset(['dividendAmount', 'dividendNotes', 'editingDividendId']);
        $this->dividendDate = now()->format('Y-m-d');
        $this->dividendType = 'dividendo';
    }

    public function with(): array
    {
        $this->investment->refresh();

        $dividends = $this->investment->dividends()->orderByDesc('date')->get();

        $historyChart = null;
        if ($this->investment->ticker) {
            $points = app(StockQuoteService::class)->fetchHistory($this->investment->ticker);

            if (count($points) >= 2) {
                $historyChart = [
                    'labels' => array_column($points, 'date'),
                    'close' => array_column($points, 'close'),
                ];
            }
        }

        return [
            'dividends' => $dividends,
            'totalDividends' => (float) $dividends->sum('amount'),
            'historyChart' => $historyChart,
        ];
    }
}; ?>

    <x-slot name="header">
        <div class="flex items-center gap-3">
            <a href="{{ route('investments.index') }}" wire:navigate class="text-gray-400 hover:text-gray-600">&larr;</a>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ $investment->name }}
                @if($investment->ticker)
                    <span class="text-base font-normal text-gray-400">{{ $investment->ticker }}</span>
                @endif
            </h2>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @php
                $ownYieldOnCost = (float) $investment->invested_amount > 0
                    ? ($totalDividends / (float) $investment->invested_amount) * 100
                    : 0;
            @endphp

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="bg-white shadow-sm rounded-lg p-5">
                    <p class="text-sm text-gray-500">Quantidade</p>
                    <p class="mt-1 text-xl font-bold text-gray-900">{{ rtrim(rtrim(number_format((float) $investment->quantity, 8, ',', '.'), '0'), ',') ?: '—' }}</p>
                </div>
                <div class="bg-white shadow-sm rounded-lg p-5">
                    <p class="text-sm text-gray-500">Preço médio</p>
                    <p class="mt-1 text-xl font-bold text-gray-900">{{ $investment->average_price !== null ? 'R$ '.number_format($investment->average_price, 2, ',', '.') : '—' }}</p>
                </div>
                <div class="bg-white shadow-sm rounded-lg p-5">
                    <p class="text-sm text-gray-500">Preço atual</p>
                    <p class="mt-1 text-xl font-bold text-gray-900">{{ $investment->current_price !== null ? 'R$ '.number_format($investment->current_price, 2, ',', '.') : '—' }}</p>
                </div>
                <div class="bg-white shadow-sm rounded-lg p-5">
                    <p class="text-sm text-gray-500">Rentabilidade</p>
                    <p class="mt-1 text-xl font-bold {{ $investment->gain >= 0 ? 'text-green-600' : 'text-red-600' }}">
                        {{ $investment->gain >= 0 ? '+' : '' }}{{ number_format($investment->gain_percent, 2, ',', '.') }}%
                    </p>
                </div>
            </div>

            @if($investment->price_earnings !== null || $investment->price_to_book !== null || $investment->dividend_yield !== null)
                <div class="flex flex-wrap gap-2">
                    @if($investment->price_earnings !== null)
                        <span class="px-3 py-1.5 rounded-full text-xs font-medium bg-gray-100 text-gray-700">P/L: {{ number_format($investment->price_earnings, 2, ',', '.') }}</span>
                    @endif
                    @if($investment->price_to_book !== null)
                        <span class="px-3 py-1.5 rounded-full text-xs font-medium bg-gray-100 text-gray-700">P/VP: {{ number_format($investment->price_to_book, 2, ',', '.') }}</span>
                    @endif
                    @if($investment->dividend_yield !== null)
                        <span class="px-3 py-1.5 rounded-full text-xs font-medium bg-gray-100 text-gray-700">DY: {{ number_format($investment->dividend_yield, 2, ',', '.') }}%</span>
                    @endif
                    @if($investment->quote_updated_at)
                        <span class="px-3 py-1.5 text-xs text-gray-400 self-center">Indicadores atualizados {{ $investment->quote_updated_at->diffForHumans() }}</span>
                    @endif
                </div>
            @endif

            @if($historyChart)
                <div class="bg-white shadow-sm rounded-lg p-6">
                    <h3 class="font-semibold text-gray-800 mb-4">Histórico de cotação</h3>
                    <div class="h-56" wire:ignore
                        x-data="assetHistoryChart(@js($historyChart))"
                        x-init="init($el.querySelector('canvas'))"
                    >
                        <canvas></canvas>
                    </div>
                </div>
            @endif

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                <div class="bg-white shadow-sm rounded-lg p-5">
                    <p class="text-sm text-gray-500">Total investido</p>
                    <p class="mt-1 text-xl font-bold text-gray-900">R$ {{ number_format($investment->invested_amount, 2, ',', '.') }}</p>
                </div>
                <div class="bg-white shadow-sm rounded-lg p-5">
                    <p class="text-sm text-gray-500">Total em proventos recebidos</p>
                    <p class="mt-1 text-xl font-bold text-green-600">R$ {{ number_format($totalDividends, 2, ',', '.') }}</p>
                </div>
                <div class="bg-white shadow-sm rounded-lg p-5">
                    <p class="text-sm text-gray-500">Yield on cost</p>
                    <p class="mt-1 text-xl font-bold text-gray-900">{{ number_format($ownYieldOnCost, 2, ',', '.') }}%</p>
                    <p class="text-xs text-gray-400 mt-0.5">Proventos recebidos ÷ valor investido</p>
                </div>
            </div>

            <div class="bg-white shadow-sm rounded-lg p-6">
                <h3 class="font-semibold text-gray-800 mb-4">{{ $editingDividendId ? 'Editar provento' : 'Registrar provento' }}</h3>
                <form wire:submit="saveDividend" class="grid grid-cols-1 sm:grid-cols-5 gap-4 items-end">
                    <div>
                        <x-input-label for="dividendDate" value="Data" />
                        <x-text-input id="dividendDate" type="date" class="mt-1 block w-full" wire:model="dividendDate" />
                        <x-input-error :messages="$errors->get('dividendDate')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="dividendType" value="Tipo" />
                        <select id="dividendType" wire:model="dividendType" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                            @foreach (Dividend::TYPES as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <x-input-label for="dividendAmount" value="Valor recebido" />
                        <x-text-input id="dividendAmount" type="number" step="0.01" class="mt-1 block w-full" wire:model="dividendAmount" />
                        <x-input-error :messages="$errors->get('dividendAmount')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="dividendNotes" value="Observação" />
                        <x-text-input id="dividendNotes" type="text" class="mt-1 block w-full" wire:model="dividendNotes" placeholder="Opcional" />
                    </div>
                    <div class="flex gap-2">
                        <x-primary-button type="submit">{{ $editingDividendId ? 'Salvar' : 'Adicionar' }}</x-primary-button>
                        @if($editingDividendId)
                            <x-secondary-button type="button" wire:click="cancelEditDividend">Cancelar</x-secondary-button>
                        @endif
                    </div>
                </form>
            </div>

            <div class="bg-white shadow-sm rounded-lg overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Data</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tipo</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Valor</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Observação</th>
                            <th class="px-6 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse ($dividends as $dividend)
                            <tr>
                                <td class="px-6 py-4 text-sm text-gray-800">{{ $dividend->date->format('d/m/Y') }}</td>
                                <td class="px-6 py-4 text-sm text-gray-500">{{ $dividend->type_label }}</td>
                                <td class="px-6 py-4 text-sm text-green-600 font-medium">R$ {{ number_format($dividend->amount, 2, ',', '.') }}</td>
                                <td class="px-6 py-4 text-sm text-gray-500">{{ $dividend->notes ?? '—' }}</td>
                                <td class="px-6 py-4 text-sm text-right space-x-2">
                                    <button wire:click="editDividend({{ $dividend->id }})" class="text-indigo-600 hover:underline">Editar</button>
                                    <button type="button" x-on:click="Swal.fire({icon:'warning',title:'Excluir provento?',showCancelButton:true,confirmButtonText:'Excluir',cancelButtonText:'Cancelar',confirmButtonColor:'#dc2626'}).then((r) => r.isConfirmed && $wire.deleteDividend({{ $dividend->id }}))" class="text-red-600 hover:underline">Excluir</button>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-6 py-6 text-center text-sm text-gray-500">Nenhum provento registrado ainda.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
