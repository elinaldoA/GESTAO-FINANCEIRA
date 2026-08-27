<?php

use App\Models\Dividend;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] class extends Component
{
    use WithPagination;

    #[Url(as: 'ativo')]
    public ?int $filterInvestmentId = null;

    public ?string $filterType = null;

    public ?int $dividend_investment_id = null;

    public string $dividendDate = '';

    public string $dividendType = 'dividendo';

    public string $dividendAmount = '';

    public string $dividendNotes = '';

    public ?int $editingDividendId = null;

    public function mount(): void
    {
        $this->dividendDate = now()->format('Y-m-d');
        $this->dividend_investment_id = $this->filterInvestmentId;
    }

    public function saveDividend(): void
    {
        $this->validate([
            'dividend_investment_id' => ['required', Rule::exists('investments', 'id')->where('user_id', auth()->id())],
            'dividendDate' => 'required|date',
            'dividendType' => 'required|in:dividendo,jscp,rendimento,outro',
            'dividendAmount' => 'required|numeric|min:0.000001',
            'dividendNotes' => 'nullable|string|max:255',
        ]);

        $isNew = $this->editingDividendId === null;

        $data = [
            'investment_id' => $this->dividend_investment_id,
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
        $this->dividend_investment_id = $dividend->investment_id;
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

    public function filterByInvestment(?int $investmentId): void
    {
        $this->filterInvestmentId = $investmentId;
        $this->resetPage();
    }

    public function filterByType(?string $type): void
    {
        $this->filterType = $type;
        $this->resetPage();
    }

    private function resetDividendForm(): void
    {
        $this->reset(['dividend_investment_id', 'dividendAmount', 'dividendNotes', 'editingDividendId']);
        $this->dividendDate = now()->format('Y-m-d');
        $this->dividendType = 'dividendo';
    }

    public function with(): array
    {
        $user = auth()->user();
        $investments = $user->investments()->orderBy('name')->get();

        $query = $user->dividends()->with('investment')->latest('date')->latest('id');

        if ($this->filterInvestmentId) {
            $query->where('investment_id', $this->filterInvestmentId);
        }
        if ($this->filterType) {
            $query->where('type', $this->filterType);
        }

        $allDividends = $user->dividends()->get();
        $totalReceived = (float) $allDividends->sum('amount');
        $totalInvested = (float) $investments->sum('invested_amount');

        $monthlyTotals = $allDividends
            ->where('date', '>=', now()->subMonths(11)->startOfMonth())
            ->groupBy(fn ($d) => $d->date->format('Y-m'))
            ->map(fn ($group) => (float) $group->sum('amount'));

        $months = collect(range(0, 11))->map(fn ($i) => now()->subMonths(11 - $i)->startOfMonth());

        return [
            'investments' => $investments,
            'dividends' => $query->paginate(15),
            'totalReceived' => $totalReceived,
            'yieldOnCost' => $totalInvested > 0 ? ($totalReceived / $totalInvested) * 100 : 0.0,
            'monthlyChart' => [
                'labels' => $months->map(fn ($m) => ucfirst($m->translatedFormat('M/y')))->all(),
                'values' => $months->map(fn ($m) => $monthlyTotals->get($m->format('Y-m'), 0.0))->all(),
                'label' => 'Proventos',
                'colors' => '#22c55e',
            ],
        ];
    }
}; ?>

    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Investimentos') }}</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @include('livewire.investments.partials.tabs')

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="bg-white shadow-sm rounded-lg p-5">
                    <p class="text-sm text-gray-500">Total recebido em proventos</p>
                    <p class="mt-1 text-2xl font-bold text-green-600">R$ {{ number_format($totalReceived, 2, ',', '.') }}</p>
                </div>
                <div class="bg-white shadow-sm rounded-lg p-5">
                    <p class="text-sm text-gray-500">Yield on cost (carteira)</p>
                    <p class="mt-1 text-2xl font-bold text-gray-900">{{ number_format($yieldOnCost, 2, ',', '.') }}%</p>
                    <p class="text-xs text-gray-400 mt-0.5">Proventos recebidos ÷ valor investido</p>
                </div>
            </div>

            <div class="bg-white shadow-sm rounded-lg p-6">
                <h3 class="font-semibold text-gray-800 mb-4">Proventos por mês</h3>
                @if(collect($monthlyChart['values'])->sum() > 0)
                    <div class="h-56" wire:ignore
                        x-data="barChart(@js($monthlyChart))"
                        x-init="init($el.querySelector('canvas'))"
                    >
                        <canvas></canvas>
                    </div>
                @else
                    <p class="text-sm text-gray-500">Nenhum provento registrado nos últimos 12 meses.</p>
                @endif
            </div>

            <div class="bg-white shadow-sm rounded-lg p-6">
                <h3 class="font-semibold text-gray-800 mb-4">{{ $editingDividendId ? 'Editar provento' : 'Registrar provento' }}</h3>
                <form wire:submit="saveDividend" class="grid grid-cols-1 sm:grid-cols-6 gap-4 items-end">
                    <div class="sm:col-span-2">
                        <x-input-label for="dividend_investment_id" value="Ativo" />
                        <select id="dividend_investment_id" wire:model="dividend_investment_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                            <option value="">Selecione…</option>
                            @foreach ($investments as $inv)
                                <option value="{{ $inv->id }}">{{ $inv->name }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('dividend_investment_id')" class="mt-1" />
                    </div>
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
                        <x-text-input id="dividendAmount" type="number" step="any" min="0" class="mt-1 block w-full" wire:model="dividendAmount" />
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

            <div class="flex flex-wrap items-center gap-2 bg-white shadow-sm rounded-lg p-2">
                <button type="button" wire:click="filterByInvestment(null)" class="px-3 py-1.5 rounded-md text-sm font-medium {{ $filterInvestmentId === null ? 'bg-indigo-600 text-white' : 'text-gray-600 hover:bg-gray-100' }}">Todos os ativos</button>
                @foreach ($investments as $inv)
                    <button type="button" wire:click="filterByInvestment({{ $inv->id }})" class="px-3 py-1.5 rounded-md text-sm font-medium {{ $filterInvestmentId === $inv->id ? 'bg-indigo-600 text-white' : 'text-gray-600 hover:bg-gray-100' }}">{{ $inv->name }}</button>
                @endforeach

                <span class="mx-1 w-px h-5 bg-gray-200"></span>

                <button type="button" wire:click="filterByType(null)" class="px-3 py-1.5 rounded-md text-sm font-medium {{ $filterType === null ? 'bg-indigo-600 text-white' : 'text-gray-600 hover:bg-gray-100' }}">Todos os tipos</button>
                @foreach (Dividend::TYPES as $value => $label)
                    <button type="button" wire:click="filterByType('{{ $value }}')" class="px-3 py-1.5 rounded-md text-sm font-medium {{ $filterType === $value ? 'bg-indigo-600 text-white' : 'text-gray-600 hover:bg-gray-100' }}">{{ $label }}</button>
                @endforeach
            </div>

            <div class="bg-white shadow-sm rounded-lg overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Data</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Ativo</th>
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
                                <td class="px-6 py-4 text-sm text-gray-800">{{ $dividend->investment->name }}</td>
                                <td class="px-6 py-4 text-sm text-gray-500">{{ $dividend->type_label }}</td>
                                <td class="px-6 py-4 text-sm text-green-600 font-medium">R$ {{ $dividend->display_amount }}</td>
                                <td class="px-6 py-4 text-sm text-gray-500">{{ $dividend->notes ?? '—' }}</td>
                                <td class="px-6 py-4 text-sm text-right space-x-2">
                                    <button wire:click="editDividend({{ $dividend->id }})" class="text-indigo-600 hover:underline">Editar</button>
                                    <button type="button" x-on:click="Swal.fire({icon:'warning',title:'Excluir provento?',showCancelButton:true,confirmButtonText:'Excluir',cancelButtonText:'Cancelar',confirmButtonColor:'#dc2626'}).then((r) => r.isConfirmed && $wire.deleteDividend({{ $dividend->id }}))" class="text-red-600 hover:underline">Excluir</button>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-6 py-6 text-center text-sm text-gray-500">Nenhum provento registrado ainda.</td></tr>
                        @endforelse
                    </tbody>
                </table>
                <div class="p-4">{{ $dividends->links() }}</div>
            </div>
        </div>
    </div>
