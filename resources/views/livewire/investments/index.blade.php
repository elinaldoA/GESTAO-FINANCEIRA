<?php

use App\Models\Investment;
use App\Models\InvestmentType;
use App\Services\StockQuoteService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] class extends Component
{
    use WithPagination;

    public string $name = '';

    public ?int $investment_type_id = null;

    public string $broker = '';

    public string $ticker = '';

    public string $quantity = '';

    public string $invested_amount = '0';

    public string $current_amount = '0';

    public string $color = '#3b82f6';

    public ?int $editingId = null;

    public string $new_type_name = '';

    public string $new_type_color = '#64748b';

    public ?int $filterTypeId = null;

    public bool $showTypeManager = false;

    #[Url(as: 'busca', except: '')]
    public string $search = '';

    public function mount(): void
    {
        if (auth()->user()->investmentTypes()->count() === 0) {
            foreach ([
                ['name' => 'Renda fixa', 'color' => '#3b82f6'],
                ['name' => 'Ações', 'color' => '#ef4444'],
                ['name' => 'Fundos imobiliários', 'color' => '#f59e0b'],
                ['name' => 'Tesouro Direto', 'color' => '#22c55e'],
                ['name' => 'Criptomoeda', 'color' => '#a855f7'],
                ['name' => 'Previdência', 'color' => '#06b6d4'],
                ['name' => 'Outro', 'color' => '#64748b'],
            ] as $type) {
                auth()->user()->investmentTypes()->create($type);
            }
        }
    }

    public function save(): void
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'investment_type_id' => ['required', Rule::exists('investment_types', 'id')->where('user_id', auth()->id())],
            'broker' => 'nullable|string|max:255',
            'ticker' => 'nullable|string|max:20',
            'quantity' => 'nullable|required_with:ticker|numeric|min:0',
            'invested_amount' => 'required|numeric|min:0',
            'current_amount' => 'required|numeric|min:0',
            'color' => 'required|string',
        ]);

        $isNew = $this->editingId === null;

        auth()->user()->investments()->updateOrCreate(
            ['id' => $this->editingId],
            [
                'investment_type_id' => $this->investment_type_id,
                'name' => $this->name,
                'broker' => $this->broker !== '' ? $this->broker : null,
                'ticker' => $this->ticker !== '' ? mb_strtoupper($this->ticker) : null,
                'quantity' => $this->ticker !== '' ? $this->quantity : null,
                'invested_amount' => $this->invested_amount,
                'current_amount' => $this->current_amount,
                'color' => $this->color,
            ]
        );

        $this->resetForm();

        $this->dispatch('notify', type: 'success', message: $isNew ? 'Investimento adicionado com sucesso.' : 'Investimento atualizado com sucesso.');
    }

    public function edit(Investment $investment): void
    {
        $this->authorize('update', $investment);

        $this->editingId = $investment->id;
        $this->name = $investment->name;
        $this->investment_type_id = $investment->investment_type_id;
        $this->broker = (string) $investment->broker;
        $this->ticker = (string) $investment->ticker;
        $this->quantity = (string) $investment->quantity;
        $this->invested_amount = (string) $investment->invested_amount;
        $this->current_amount = (string) $investment->current_amount;
        $this->color = $investment->color;
    }

    public function cancelEdit(): void
    {
        $this->resetForm();
    }

    public function refreshQuote(Investment $investment, StockQuoteService $quotes): void
    {
        $this->authorize('update', $investment);

        if (! $investment->ticker || ! $investment->quantity) {
            return;
        }

        $quote = $quotes->fetchQuote($investment->ticker);

        if ($quote === null) {
            $this->dispatch('notify', type: 'error', message: "Não foi possível obter a cotação de {$investment->ticker} agora.");

            return;
        }

        $investment->applyQuote($quote);

        $this->dispatch('notify', type: 'success', message: "Cotação de {$investment->ticker} atualizada.");
    }

    public function refreshAllQuotes(StockQuoteService $quotes): void
    {
        $updated = $this->updateTrackedQuotes($quotes);

        $this->dispatch('notify', type: $updated > 0 ? 'success' : 'warning', message: $updated > 0
            ? "{$updated} cotação(ões) atualizada(s)."
            : 'Nenhum investimento com ticker cadastrado para atualizar.');
    }

    /**
     * Silently refreshes tracked quotes on a timer (wire:poll) while the page is open,
     * so prices update without the user having to click anything. No toast here —
     * a notification every 60s would just be noise.
     */
    public function pollQuotes(StockQuoteService $quotes): void
    {
        $this->updateTrackedQuotes($quotes);
    }

    private function updateTrackedQuotes(StockQuoteService $quotes): int
    {
        $investments = auth()->user()->investments()
            ->whereNotNull('ticker')->whereNotNull('quantity')->where('is_active', true)->get();

        $updated = 0;

        foreach ($investments as $investment) {
            $quote = $quotes->fetchQuote($investment->ticker);

            if ($quote === null) {
                continue;
            }

            $investment->applyQuote($quote);

            $updated++;
        }

        return $updated;
    }

    public function filterByType(?int $typeId): void
    {
        $this->filterTypeId = $typeId;
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    private function resetForm(): void
    {
        $this->reset(['name', 'broker', 'ticker', 'quantity', 'editingId', 'investment_type_id']);
        $this->invested_amount = '0';
        $this->current_amount = '0';
        $this->color = '#3b82f6';
    }

    public function toggleActive(Investment $investment): void
    {
        $this->authorize('update', $investment);
        $investment->update(['is_active' => ! $investment->is_active]);

        $this->dispatch('notify', type: 'success', message: $investment->is_active ? 'Investimento ativado.' : 'Investimento inativado.');
    }

    public function delete(Investment $investment): void
    {
        $this->authorize('delete', $investment);
        $investment->delete();

        $this->dispatch('notify', type: 'success', message: 'Investimento excluído com sucesso.');
    }

    public function addType(): void
    {
        $this->validate([
            'new_type_name' => ['required', 'string', 'max:255', Rule::unique('investment_types', 'name')->where('user_id', auth()->id())],
            'new_type_color' => 'required|string',
        ]);

        $type = auth()->user()->investmentTypes()->create([
            'name' => $this->new_type_name,
            'color' => $this->new_type_color,
        ]);

        $this->investment_type_id = $type->id;
        $this->reset(['new_type_name']);
        $this->new_type_color = '#64748b';

        $this->dispatch('notify', type: 'success', message: 'Tipo de investimento adicionado com sucesso.');
    }

    public function deleteType(InvestmentType $investmentType): void
    {
        $this->authorize('delete', $investmentType);
        $investmentType->delete();

        $this->dispatch('notify', type: 'success', message: 'Tipo de investimento excluído com sucesso.');
    }

    public function with(): array
    {
        $user = auth()->user();
        $allInvestments = $user->investments()->with('investmentType')->get();

        $investmentsQuery = $user->investments()->with('investmentType')->latest();
        if ($this->filterTypeId) {
            $investmentsQuery->where('investment_type_id', $this->filterTypeId);
        }
        if ($this->search !== '') {
            $investmentsQuery->where(function ($query) {
                $query->where('name', 'like', "%{$this->search}%")
                    ->orWhere('ticker', 'like', "%{$this->search}%")
                    ->orWhere('broker', 'like', "%{$this->search}%");
            });
        }

        $totalInvested = (float) $allInvestments->sum('invested_amount');
        $totalCurrent = (float) $allInvestments->sum('current_amount');

        $investmentTypes = $user->investmentTypes()->orderBy('name')->get();

        $allocation = $investmentTypes
            ->map(function ($type) use ($allInvestments) {
                $current = (float) $allInvestments->where('investment_type_id', $type->id)->sum('current_amount');

                return ['type' => $type, 'current' => $current];
            })
            ->filter(fn ($row) => $row['current'] > 0)
            ->sortByDesc('current')
            ->values();

        $history = $user->portfolioSnapshots()->orderBy('date')->limit(90)->get();

        $marketIndices = Cache::remember('market:indices', 600, function () {
            $quotes = app(StockQuoteService::class);

            return [
                'usd' => $quotes->fetchUsdBrl(),
                'ibovespa' => $quotes->fetchIbovespa(),
            ];
        });

        return [
            'investments' => $investmentsQuery->paginate(10),
            'investmentTypes' => $investmentTypes,
            'typeCounts' => $allInvestments->countBy('investment_type_id'),
            'totalInvested' => $totalInvested,
            'totalCurrent' => $totalCurrent,
            'totalAssets' => $allInvestments->count(),
            'totalDividends' => (float) $user->dividends()->sum('amount'),
            'hasTrackedInvestments' => $allInvestments->whereNotNull('ticker')->isNotEmpty(),
            'marketIndices' => $marketIndices,
            'allocationChart' => [
                'labels' => $allocation->map(fn ($row) => $row['type']->name)->all(),
                'colors' => $allocation->map(fn ($row) => $row['type']->color)->all(),
                'totals' => $allocation->map(fn ($row) => $row['current'])->all(),
            ],
            'historyChart' => [
                'labels' => $history->map(fn ($row) => $row->date->format('d/m'))->all(),
                'invested' => $history->map(fn ($row) => (float) $row->total_invested)->all(),
                'current' => $history->map(fn ($row) => (float) $row->total_current)->all(),
            ],
        ];
    }
}; ?>

    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Investimentos') }}</h2>
    </x-slot>

    <div class="py-8" @if($hasTrackedInvestments) wire:poll.60s="pollQuotes" @endif>
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if($hasTrackedInvestments)
                <div class="flex items-center gap-1.5 text-xs text-gray-400">
                    <span class="relative flex h-2 w-2">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-2 w-2 bg-green-500"></span>
                    </span>
                    Cotações atualizando automaticamente a cada minuto
                </div>
            @endif

            @if($marketIndices['usd'] || $marketIndices['ibovespa'])
                <div class="flex flex-wrap gap-4 bg-white shadow-sm rounded-lg p-4">
                    <span class="text-xs font-semibold text-gray-400 uppercase tracking-wide self-center">Mercado hoje</span>
                    @if($marketIndices['usd'])
                        <div class="flex items-center gap-2 text-sm">
                            <span class="text-gray-500">Dólar</span>
                            <span class="font-semibold text-gray-800">R$ {{ number_format($marketIndices['usd']['price'], 2, ',', '.') }}</span>
                            @if($marketIndices['usd']['changePercent'] !== null)
                                <span class="{{ $marketIndices['usd']['changePercent'] >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                    ({{ $marketIndices['usd']['changePercent'] >= 0 ? '+' : '' }}{{ number_format($marketIndices['usd']['changePercent'], 2, ',', '.') }}%)
                                </span>
                            @endif
                        </div>
                    @endif
                    @if($marketIndices['ibovespa'])
                        <div class="flex items-center gap-2 text-sm">
                            <span class="text-gray-500">Ibovespa</span>
                            <span class="font-semibold text-gray-800">{{ number_format($marketIndices['ibovespa']['price'], 0, ',', '.') }} pts</span>
                            @if($marketIndices['ibovespa']['changePercent'] !== null)
                                <span class="{{ $marketIndices['ibovespa']['changePercent'] >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                    ({{ $marketIndices['ibovespa']['changePercent'] >= 0 ? '+' : '' }}{{ number_format($marketIndices['ibovespa']['changePercent'], 2, ',', '.') }}%)
                                </span>
                            @endif
                        </div>
                    @endif
                </div>
            @endif

            @php $totalGain = $totalCurrent - $totalInvested; @endphp
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
                <div class="bg-white shadow-sm rounded-lg p-5">
                    <p class="text-sm text-gray-500">Patrimônio</p>
                    <p class="mt-1 text-2xl font-bold text-gray-900">R$ {{ number_format($totalCurrent, 2, ',', '.') }}</p>
                </div>
                <div class="bg-white shadow-sm rounded-lg p-5">
                    <p class="text-sm text-gray-500">Total investido</p>
                    <p class="mt-1 text-2xl font-bold text-gray-900">R$ {{ number_format($totalInvested, 2, ',', '.') }}</p>
                </div>
                <div class="bg-white shadow-sm rounded-lg p-5">
                    <p class="text-sm text-gray-500">Rentabilidade</p>
                    <p class="mt-1 text-2xl font-bold {{ $totalGain >= 0 ? 'text-green-600' : 'text-red-600' }}">
                        {{ $totalGain >= 0 ? '+' : '' }}R$ {{ number_format($totalGain, 2, ',', '.') }}
                        <span class="text-base font-medium">
                            ({{ $totalGain >= 0 ? '+' : '' }}{{ $totalInvested > 0 ? number_format(($totalGain / $totalInvested) * 100, 2, ',', '.') : '0,00' }}%)
                        </span>
                    </p>
                </div>
                <div class="bg-white shadow-sm rounded-lg p-5">
                    <p class="text-sm text-gray-500">Proventos recebidos</p>
                    <p class="mt-1 text-2xl font-bold text-green-600">R$ {{ number_format($totalDividends, 2, ',', '.') }}</p>
                </div>
                <div class="bg-white shadow-sm rounded-lg p-5">
                    <p class="text-sm text-gray-500">Ativos</p>
                    <p class="mt-1 text-2xl font-bold text-gray-900">{{ $totalAssets }}</p>
                </div>
            </div>

            @if(count($historyChart['labels']) >= 2)
                <div class="bg-white shadow-sm rounded-lg p-6">
                    <h3 class="font-semibold text-gray-800 mb-4">Evolução do patrimônio</h3>
                    <div class="h-56" wire:ignore
                        x-data="lineChart(@js($historyChart))"
                        x-init="init($el.querySelector('canvas'))"
                    >
                        <canvas></canvas>
                    </div>
                </div>
            @endif

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div class="bg-white shadow-sm rounded-lg p-6">
                    <h3 class="font-semibold text-gray-800 mb-4">Distribuição da carteira</h3>
                    @if(count($allocationChart['labels']))
                        <div class="h-56" wire:ignore
                            x-data="categoryChart(@js($allocationChart))"
                            x-init="init($el.querySelector('canvas'))"
                        >
                            <canvas></canvas>
                        </div>
                    @else
                        <p class="text-sm text-gray-500">Cadastre um investimento para ver a distribuição da carteira.</p>
                    @endif
                </div>

                <div class="lg:col-span-2 bg-white shadow-sm rounded-lg p-6">
                    <h3 class="font-semibold text-gray-800 mb-4">Por classe de ativo</h3>
                    <div class="space-y-3">
                        @php $typeRows = collect($allocationChart['labels'])->map(fn ($label, $i) => ['label' => $label, 'color' => $allocationChart['colors'][$i], 'value' => $allocationChart['totals'][$i]]); @endphp
                        @forelse ($typeRows as $row)
                            @php $percent = $totalCurrent > 0 ? ($row['value'] / $totalCurrent) * 100 : 0; @endphp
                            <div>
                                <div class="flex justify-between text-sm mb-1">
                                    <span class="flex items-center gap-2 text-gray-700">
                                        <span class="w-2.5 h-2.5 rounded-full" style="background-color: {{ $row['color'] }}"></span>
                                        {{ $row['label'] }}
                                    </span>
                                    <span class="text-gray-500">R$ {{ number_format($row['value'], 2, ',', '.') }} &middot; {{ number_format($percent, 1, ',', '.') }}%</span>
                                </div>
                                <div class="w-full bg-gray-100 rounded-full h-2">
                                    <div class="h-2 rounded-full" style="width: {{ $percent }}%; background-color: {{ $row['color'] }}"></div>
                                </div>
                            </div>
                        @empty
                            <p class="text-sm text-gray-500">Nenhum investimento cadastrado ainda.</p>
                        @endforelse
                    </div>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-3 bg-white shadow-sm rounded-lg p-4">
                <div class="flex-1 min-w-[220px]">
                    <input
                        type="search"
                        wire:model.live.debounce.400ms="search"
                        placeholder="Buscar por nome, ticker ou corretora..."
                        class="block w-full rounded-md border-gray-300 shadow-sm text-sm"
                    >
                </div>
                @if($hasTrackedInvestments)
                    <button
                        type="button"
                        wire:click="refreshAllQuotes"
                        wire:loading.attr="disabled"
                        wire:target="refreshAllQuotes"
                        class="px-3 py-1.5 rounded-md text-sm font-medium text-indigo-600 border border-indigo-200 hover:bg-indigo-50 disabled:opacity-50"
                    >
                        <span wire:loading.remove wire:target="refreshAllQuotes">Atualizar cotações</span>
                        <span wire:loading wire:target="refreshAllQuotes">Atualizando…</span>
                    </button>
                @endif
            </div>

            <div class="flex flex-wrap items-center gap-2 bg-white shadow-sm rounded-lg p-2">
                <button
                    type="button"
                    wire:click="filterByType(null)"
                    class="px-3 py-1.5 rounded-md text-sm font-medium {{ $filterTypeId === null ? 'bg-indigo-600 text-white' : 'text-gray-600 hover:bg-gray-100' }}"
                >
                    Todos
                    <span class="opacity-70">({{ $totalAssets }})</span>
                </button>
                @foreach ($investmentTypes as $type)
                    <button
                        type="button"
                        wire:click="filterByType({{ $type->id }})"
                        class="px-3 py-1.5 rounded-md text-sm font-medium {{ $filterTypeId === $type->id ? 'bg-indigo-600 text-white' : 'text-gray-600 hover:bg-gray-100' }}"
                    >
                        {{ $type->name }}
                        <span class="opacity-70">({{ $typeCounts->get($type->id, 0) }})</span>
                    </button>
                @endforeach

                <button type="button" wire:click="$toggle('showTypeManager')" class="ms-auto px-3 py-1.5 rounded-md text-sm font-medium text-indigo-600 hover:bg-indigo-50">
                    {{ $showTypeManager ? 'Fechar' : '+ Gerenciar tipos' }}
                </button>
            </div>

            @if($showTypeManager)
                <div class="bg-white shadow-sm rounded-lg p-6">
                    <h3 class="font-semibold text-gray-800 mb-1">Tipos de investimento</h3>
                    <p class="text-sm text-gray-500 mb-4">Cadastre quantos tipos precisar, por exemplo "FII", "Renda variável internacional" etc.</p>

                    <div class="flex flex-wrap gap-2 mb-4">
                        @forelse ($investmentTypes as $type)
                            <span class="inline-flex items-center gap-2 pl-3 pr-1.5 py-1 rounded-full text-sm bg-gray-100 text-gray-700">
                                <span class="w-2 h-2 rounded-full" style="background-color: {{ $type->color }}"></span>
                                {{ $type->name }}
                                <button
                                    type="button"
                                    x-on:click="Swal.fire({icon:'warning',title:'Excluir o tipo &quot;{{ $type->name }}&quot;?',text:'Investimentos associados ficarão sem tipo.',showCancelButton:true,confirmButtonText:'Excluir',cancelButtonText:'Cancelar',confirmButtonColor:'#dc2626'}).then((r) => r.isConfirmed && $wire.deleteType({{ $type->id }}))"
                                    class="ms-1 w-4 h-4 flex items-center justify-center rounded-full text-gray-400 hover:text-red-600 hover:bg-red-50"
                                    title="Excluir tipo"
                                >&times;</button>
                            </span>
                        @empty
                            <p class="text-sm text-gray-500">Nenhum tipo cadastrado ainda.</p>
                        @endforelse
                    </div>

                    <form wire:submit="addType" class="flex flex-wrap items-end gap-3">
                        <div class="flex-1 min-w-[200px]">
                            <x-input-label for="new_type_name" value="Novo tipo" />
                            <x-text-input id="new_type_name" type="text" class="mt-1 block w-full" wire:model="new_type_name" placeholder="Ex: FII" />
                            <x-input-error :messages="$errors->get('new_type_name')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="new_type_color" value="Cor" />
                            <input id="new_type_color" type="color" wire:model="new_type_color" class="mt-1 block w-full h-10 rounded-md border-gray-300 shadow-sm" />
                        </div>
                        <x-secondary-button type="submit">Adicionar tipo</x-secondary-button>
                    </form>
                </div>
            @endif

            <div class="bg-white shadow-sm rounded-lg p-6">
                <h3 class="font-semibold text-gray-800 mb-4">{{ $editingId ? 'Editar investimento' : 'Novo investimento' }}</h3>
                <form wire:submit="save" class="space-y-5">
                    <div class="grid grid-cols-1 sm:grid-cols-6 gap-4">
                        <div class="sm:col-span-3">
                            <x-input-label for="name" value="Nome" />
                            <x-text-input id="name" type="text" class="mt-1 block w-full" wire:model="name" placeholder="Ex: Tesouro Selic 2029" />
                            <x-input-error :messages="$errors->get('name')" class="mt-1" />
                        </div>
                        <div class="sm:col-span-2">
                            <x-input-label for="investment_type_id" value="Tipo" />
                            <select id="investment_type_id" wire:model="investment_type_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                                <option value="">Selecione…</option>
                                @foreach ($investmentTypes as $type)
                                    <option value="{{ $type->id }}">{{ $type->name }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('investment_type_id')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="color" value="Cor" />
                            <input id="color" type="color" wire:model="color" class="mt-1 block w-full h-10 rounded-md border-gray-300 shadow-sm" />
                        </div>
                    </div>

                    <div class="border-t border-gray-100 pt-5">
                        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Cotação automática (opcional)</p>
                        <div class="grid grid-cols-1 sm:grid-cols-6 gap-4">
                            <div class="sm:col-span-2">
                                <x-input-label for="broker" value="Corretora" />
                                <x-text-input id="broker" type="text" class="mt-1 block w-full" wire:model="broker" placeholder="Opcional" />
                                <x-input-error :messages="$errors->get('broker')" class="mt-1" />
                            </div>
                            <div class="sm:col-span-2">
                                <x-input-label for="ticker" value="Ticker (B3)" />
                                <x-text-input id="ticker" type="text" class="mt-1 block w-full" wire:model="ticker" placeholder="Ex: ITUB4" />
                                <x-input-error :messages="$errors->get('ticker')" class="mt-1" />
                            </div>
                            <div class="sm:col-span-2">
                                <x-input-label for="quantity" value="Quantidade" />
                                <x-text-input id="quantity" type="number" step="0.00000001" class="mt-1 block w-full" wire:model="quantity" placeholder="Nº de cotas/ações" />
                                <x-input-error :messages="$errors->get('quantity')" class="mt-1" />
                            </div>
                        </div>
                        <p class="text-xs text-gray-400 mt-2">Preenchendo ticker e quantidade, o "Valor atual" é atualizado automaticamente a partir da cotação de mercado.</p>
                    </div>

                    <div class="border-t border-gray-100 pt-5 grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="invested_amount" value="Valor investido" />
                            <x-text-input id="invested_amount" type="number" step="0.01" class="mt-1 block w-full" wire:model="invested_amount" />
                            <x-input-error :messages="$errors->get('invested_amount')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="current_amount" value="Valor atual" />
                            <x-text-input id="current_amount" type="number" step="0.01" class="mt-1 block w-full" wire:model="current_amount" />
                            <x-input-error :messages="$errors->get('current_amount')" class="mt-1" />
                        </div>
                    </div>

                    <div class="flex gap-2">
                        <x-primary-button type="submit">{{ $editingId ? 'Salvar alterações' : 'Adicionar investimento' }}</x-primary-button>
                        @if($editingId)
                            <x-secondary-button type="button" wire:click="cancelEdit">Cancelar</x-secondary-button>
                        @endif
                    </div>
                </form>
            </div>

            <div class="bg-white shadow-sm rounded-lg overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Ativo</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tipo</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Investido</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Atual</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Rentabilidade</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">% carteira</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Situação</th>
                            <th class="px-6 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse ($investments as $investment)
                            <tr>
                                <td class="px-6 py-4 text-sm text-gray-800">
                                    <div class="flex items-center gap-2">
                                        <span class="w-2.5 h-2.5 rounded-full" style="background-color: {{ $investment->color }}"></span>
                                        <a href="{{ route('investments.show', $investment) }}" wire:navigate class="hover:underline hover:text-indigo-600">{{ $investment->name }}</a>
                                    </div>
                                    @if($investment->broker)
                                        <p class="text-xs text-gray-500 mt-0.5">{{ $investment->broker }}</p>
                                    @endif
                                    @if($investment->ticker)
                                        <p class="text-xs text-gray-400 mt-0.5">
                                            {{ $investment->ticker }}
                                            @if($investment->day_change_percent !== null)
                                                <span class="{{ $investment->day_change_percent >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                                    ({{ $investment->day_change_percent >= 0 ? '+' : '' }}{{ number_format($investment->day_change_percent, 2, ',', '.') }}% hoje)
                                                </span>
                                            @endif
                                            @if($investment->quote_updated_at)
                                                &middot; cotação de {{ $investment->quote_updated_at->diffForHumans() }}
                                            @endif
                                        </p>
                                        @if($investment->week52_low !== null && $investment->week52_high !== null && $investment->current_price !== null && $investment->week52_high > $investment->week52_low)
                                            @php
                                                $rangePercent = min(100, max(0, (($investment->current_price - $investment->week52_low) / ($investment->week52_high - $investment->week52_low)) * 100));
                                            @endphp
                                            <div class="mt-1.5 w-32" title="Faixa de 52 semanas: R$ {{ number_format($investment->week52_low, 2, ',', '.') }} – R$ {{ number_format($investment->week52_high, 2, ',', '.') }}">
                                                <div class="w-full bg-gray-100 rounded-full h-1">
                                                    <div class="h-1 rounded-full bg-slate-400" style="width: {{ $rangePercent }}%"></div>
                                                </div>
                                                <p class="text-[10px] text-gray-400 mt-0.5">52 sem: R$ {{ number_format($investment->week52_low, 2, ',', '.') }} – R$ {{ number_format($investment->week52_high, 2, ',', '.') }}</p>
                                            </div>
                                        @endif
                                        @if($investment->price_earnings !== null || $investment->price_to_book !== null || $investment->dividend_yield !== null)
                                            <div class="flex flex-wrap gap-1 mt-1.5">
                                                @if($investment->price_earnings !== null)
                                                    <span class="px-1.5 py-0.5 rounded text-[10px] font-medium bg-gray-100 text-gray-600">P/L {{ number_format($investment->price_earnings, 1, ',', '.') }}</span>
                                                @endif
                                                @if($investment->price_to_book !== null)
                                                    <span class="px-1.5 py-0.5 rounded text-[10px] font-medium bg-gray-100 text-gray-600">P/VP {{ number_format($investment->price_to_book, 1, ',', '.') }}</span>
                                                @endif
                                                @if($investment->dividend_yield !== null)
                                                    <span class="px-1.5 py-0.5 rounded text-[10px] font-medium bg-gray-100 text-gray-600">DY {{ number_format($investment->dividend_yield, 1, ',', '.') }}%</span>
                                                @endif
                                            </div>
                                        @endif
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500">
                                    @if($investment->investmentType)
                                        <span class="inline-flex items-center gap-1.5">
                                            <span class="w-2 h-2 rounded-full" style="background-color: {{ $investment->investmentType->color }}"></span>
                                            {{ $investment->investmentType->name }}
                                        </span>
                                    @else
                                        <span class="text-gray-400">Sem tipo</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-900">R$ {{ number_format($investment->invested_amount, 2, ',', '.') }}</td>
                                <td class="px-6 py-4 text-sm text-gray-900">R$ {{ number_format($investment->current_amount, 2, ',', '.') }}</td>
                                <td class="px-6 py-4 text-sm font-medium {{ $investment->gain >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                    {{ $investment->gain >= 0 ? '+' : '' }}R$ {{ number_format($investment->gain, 2, ',', '.') }}
                                    <span class="block text-xs">({{ $investment->gain >= 0 ? '+' : '' }}{{ number_format($investment->gain_percent, 2, ',', '.') }}%)</span>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500">
                                    {{ $totalCurrent > 0 ? number_format(((float) $investment->current_amount / $totalCurrent) * 100, 1, ',', '.') : '0,0' }}%
                                </td>
                                <td class="px-6 py-4 text-sm">
                                    <button wire:click="toggleActive({{ $investment->id }})" class="px-2 py-1 rounded text-xs {{ $investment->is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                                        {{ $investment->is_active ? 'Ativo' : 'Inativo' }}
                                    </button>
                                </td>
                                <td class="px-6 py-4 text-sm text-right space-x-2">
                                    @if($investment->ticker)
                                        <button wire:click="refreshQuote({{ $investment->id }})" wire:loading.attr="disabled" wire:target="refreshQuote({{ $investment->id }})" class="text-gray-500 hover:underline disabled:opacity-50">Atualizar cotação</button>
                                    @endif
                                    <a href="{{ route('investments.show', $investment) }}" wire:navigate class="text-gray-500 hover:underline">Detalhes</a>
                                    <button wire:click="edit({{ $investment->id }})" class="text-indigo-600 hover:underline">Editar</button>
                                    <button type="button" x-on:click="Swal.fire({icon:'warning',title:'Excluir investimento?',showCancelButton:true,confirmButtonText:'Excluir',cancelButtonText:'Cancelar',confirmButtonColor:'#dc2626'}).then((r) => r.isConfirmed && $wire.delete({{ $investment->id }}))" class="text-red-600 hover:underline">Excluir</button>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="px-6 py-6 text-center text-sm text-gray-500">{{ $search !== '' ? 'Nenhum investimento encontrado para "'.$search.'".' : 'Nenhum investimento cadastrado.' }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
                <div class="p-4">{{ $investments->links() }}</div>
            </div>
        </div>
    </div>
