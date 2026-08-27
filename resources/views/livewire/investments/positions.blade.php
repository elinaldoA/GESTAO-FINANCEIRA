<?php

use App\Models\Investment;
use App\Models\InvestmentType;
use App\Services\StockQuoteService;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
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

    public string $new_type_tax_rate = '';

    public ?int $editingTypeTaxRateId = null;

    public string $editing_type_tax_rate = '';

    #[Url(as: 'tipo')]
    public ?int $filterTypeId = null;

    #[Url(as: 'busca', except: '')]
    public string $search = '';

    public bool $showTypeManager = false;

    public function save(): void
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'investment_type_id' => ['required', Rule::exists('investment_types', 'id')->where('user_id', auth()->id())],
            'broker' => 'nullable|string|max:255',
            'color' => 'required|string',
            'current_amount' => 'required|numeric|min:0',
        ]);

        if ($this->editingId) {
            $investment = auth()->user()->investments()->findOrFail($this->editingId);
            $this->authorize('update', $investment);

            $investment->update([
                'investment_type_id' => $this->investment_type_id,
                'name' => $this->name,
                'broker' => $this->broker !== '' ? $this->broker : null,
                'color' => $this->color,
                'current_amount' => $this->current_amount,
            ]);

            $this->resetForm();
            $this->dispatch('notify', type: 'success', message: 'Investimento atualizado com sucesso.');

            return;
        }

        $this->validate([
            'ticker' => 'nullable|string|max:20',
            'quantity' => 'nullable|required_with:ticker|numeric|min:0',
            'invested_amount' => 'required|numeric|min:0',
        ]);

        $isTicker = $this->ticker !== '';

        $investment = auth()->user()->investments()->create([
            'investment_type_id' => $this->investment_type_id,
            'name' => $this->name,
            'broker' => $this->broker !== '' ? $this->broker : null,
            'ticker' => $isTicker ? mb_strtoupper($this->ticker) : null,
            'quantity' => 0,
            'invested_amount' => 0,
            'current_amount' => $this->current_amount,
            'color' => $this->color,
        ]);

        $investedAmount = (float) $this->invested_amount;

        if ($investedAmount > 0) {
            $quantity = (float) $this->quantity;

            $investment->transactions()->create([
                'user_id' => auth()->id(),
                'date' => now()->format('Y-m-d'),
                'type' => $isTicker ? 'compra' : 'aporte',
                'quantity' => $isTicker ? $quantity : null,
                'unit_price' => $isTicker && $quantity > 0 ? round($investedAmount / $quantity, 4) : null,
                'amount' => $investedAmount,
                'notes' => 'Saldo inicial ao cadastrar o ativo.',
            ]);

            $investment->recalculateFromTransactions();
        }

        $this->resetForm();

        $this->dispatch('notify', type: 'success', message: 'Investimento adicionado com sucesso.');
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

    public function filterByType(?int $typeId): void
    {
        $this->filterTypeId = $typeId;
    }

    public function refreshQuote(Investment $investment, StockQuoteService $quotes): void
    {
        $this->authorize('update', $investment);

        if (! $investment->ticker || ! $investment->quantity) {
            return;
        }

        $quote = $quotes->fetchQuote($investment->ticker);

        if ($quote === null) {
            $investment->markQuoteFailed();
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
                $investment->markQuoteFailed();

                continue;
            }

            $investment->applyQuote($quote);

            $updated++;
        }

        return $updated;
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
            'new_type_tax_rate' => 'nullable|numeric|min:0|max:100',
        ]);

        $type = auth()->user()->investmentTypes()->create([
            'name' => $this->new_type_name,
            'color' => $this->new_type_color,
            'tax_rate' => $this->new_type_tax_rate !== '' ? $this->new_type_tax_rate : null,
        ]);

        $this->investment_type_id = $type->id;
        $this->reset(['new_type_name', 'new_type_tax_rate']);
        $this->new_type_color = '#64748b';

        $this->dispatch('notify', type: 'success', message: 'Tipo de investimento adicionado com sucesso.');
    }

    public function editTypeTaxRate(InvestmentType $investmentType): void
    {
        $this->authorize('update', $investmentType);

        $this->editingTypeTaxRateId = $investmentType->id;
        $this->editing_type_tax_rate = (string) $investmentType->tax_rate;
    }

    public function saveTypeTaxRate(): void
    {
        $investmentType = auth()->user()->investmentTypes()->findOrFail($this->editingTypeTaxRateId);

        $this->validate([
            'editing_type_tax_rate' => 'nullable|numeric|min:0|max:100',
        ]);

        $investmentType->update([
            'tax_rate' => $this->editing_type_tax_rate !== '' ? $this->editing_type_tax_rate : null,
        ]);

        $this->reset(['editingTypeTaxRateId', 'editing_type_tax_rate']);

        $this->dispatch('notify', type: 'success', message: 'Alíquota de IR atualizada.');
    }

    public function cancelEditTypeTaxRate(): void
    {
        $this->reset(['editingTypeTaxRateId', 'editing_type_tax_rate']);
    }

    public function deleteType(InvestmentType $investmentType): void
    {
        $this->authorize('delete', $investmentType);
        $investmentType->delete();

        $this->dispatch('notify', type: 'success', message: 'Tipo de investimento excluído com sucesso.');
    }

    private function resetForm(): void
    {
        $this->reset(['name', 'broker', 'ticker', 'quantity', 'editingId', 'investment_type_id']);
        $this->invested_amount = '0';
        $this->current_amount = '0';
        $this->color = '#3b82f6';
    }

    public function with(): array
    {
        $user = auth()->user();
        $investmentTypes = $user->investmentTypes()->orderBy('name')->get();

        $investmentsQuery = $user->investments()->with('investmentType')->orderBy('name');
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

        $investments = $investmentsQuery->get();
        $totalCurrent = (float) $user->investments()->sum('current_amount');

        $groups = $investmentTypes
            ->map(fn ($type) => ['type' => $type, 'investments' => $investments->where('investment_type_id', $type->id)->values()])
            ->filter(fn ($group) => $group['investments']->isNotEmpty());

        // Anything not covered by one of the user's own types — either no type
        // at all, or (defensively) a type id that doesn't resolve to one of
        // their types — still needs to show up somewhere instead of vanishing.
        $groupedIds = $groups->flatMap(fn ($group) => $group['investments']->pluck('id'));
        $withoutType = $investments->whereNotIn('id', $groupedIds)->values();

        $allInvestments = $user->investments()->get();

        return [
            'investmentTypes' => $investmentTypes,
            'typeCounts' => $allInvestments->countBy('investment_type_id'),
            'totalAssets' => $allInvestments->count(),
            'totalCurrent' => $totalCurrent,
            'groups' => $groups,
            'withoutType' => $withoutType,
            'hasTrackedInvestments' => $allInvestments->whereNotNull('ticker')->isNotEmpty(),
            'hasFailingQuotes' => $allInvestments->whereNotNull('ticker')->contains(fn ($investment) => $investment->quote_status === 'failing'),
        ];
    }
}; ?>

    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Investimentos') }}</h2>
    </x-slot>

    <div class="py-8" @if($hasTrackedInvestments) wire:poll.60s="pollQuotes" @endif>
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @include('livewire.investments.partials.tabs')

            @if($hasFailingQuotes)
                <div class="flex items-center gap-1.5 text-xs text-amber-600 bg-amber-50 border border-amber-100 rounded-md px-3 py-2">
                    <span class="relative flex h-2 w-2 shrink-0">
                        <span class="relative inline-flex rounded-full h-2 w-2 bg-amber-500"></span>
                    </span>
                    Algumas cotações não puderam ser atualizadas agora — exibindo os últimos valores conhecidos.
                </div>
            @endif

            <div class="bg-white shadow-sm rounded-lg p-4">
                <input
                    type="search"
                    wire:model.live.debounce.400ms="search"
                    placeholder="Buscar por nome, ticker ou corretora..."
                    class="block w-full rounded-md border-gray-300 shadow-sm text-sm"
                >
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

                <button type="button" wire:click="$toggle('showTypeManager')" class="ms-auto px-3 py-1.5 rounded-md text-sm font-medium text-indigo-600 hover:bg-indigo-50">
                    {{ $showTypeManager ? 'Fechar' : '+ Gerenciar tipos' }}
                </button>
            </div>

            @if($showTypeManager)
                <div class="bg-white shadow-sm rounded-lg p-6">
                    <h3 class="font-semibold text-gray-800 mb-1">Tipos de investimento</h3>
                    <p class="text-sm text-gray-500 mb-4">Cadastre quantos tipos precisar, por exemplo "FII", "Renda variável internacional" etc.</p>

                    <div class="space-y-1.5 mb-4">
                        @forelse ($investmentTypes as $type)
                            <div class="flex items-center gap-2 pl-3 pr-1.5 py-1 rounded-full text-sm bg-gray-100 text-gray-700 w-fit">
                                <span class="w-2 h-2 rounded-full shrink-0" style="background-color: {{ $type->color }}"></span>
                                {{ $type->name }}

                                @if($editingTypeTaxRateId === $type->id)
                                    <form wire:submit="saveTypeTaxRate" class="flex items-center gap-1">
                                        <input type="number" step="0.01" min="0" max="100" wire:model="editing_type_tax_rate" class="w-16 rounded border-gray-300 text-xs py-0.5" placeholder="IR %" autofocus>
                                        <button type="submit" class="text-indigo-600 hover:underline text-xs">Salvar</button>
                                        <button type="button" wire:click="cancelEditTypeTaxRate" class="text-gray-400 hover:underline text-xs">Cancelar</button>
                                    </form>
                                @else
                                    <button type="button" wire:click="editTypeTaxRate({{ $type->id }})" class="text-xs text-gray-500 hover:text-indigo-600 hover:underline" title="Alíquota de IR estimada sobre o ganho deste tipo">
                                        {{ $type->tax_rate !== null ? 'IR '.number_format($type->tax_rate, 0, ',', '.').'%' : '+ IR %' }}
                                    </button>
                                @endif

                                <button
                                    type="button"
                                    x-on:click="Swal.fire({icon:'warning',title:'Excluir o tipo &quot;{{ $type->name }}&quot;?',text:'Investimentos associados ficarão sem tipo.',showCancelButton:true,confirmButtonText:'Excluir',cancelButtonText:'Cancelar',confirmButtonColor:'#dc2626'}).then((r) => r.isConfirmed && $wire.deleteType({{ $type->id }}))"
                                    class="ms-1 w-4 h-4 flex items-center justify-center rounded-full text-gray-400 hover:text-red-600 hover:bg-red-50"
                                    title="Excluir tipo"
                                >&times;</button>
                            </div>
                        @empty
                            <p class="text-sm text-gray-500">Nenhum tipo cadastrado ainda.</p>
                        @endforelse
                    </div>

                    <p class="text-xs text-gray-400 mb-4">A alíquota de IR é uma estimativa simples sobre o ganho bruto, para exibir uma "rentabilidade líquida" aproximada. Não considera isenções (ex.: venda de ações até R$ 20 mil/mês), day trade, prazo de aplicação ou custos de corretagem — consulte um contador para o cálculo real do imposto devido.</p>

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
                        <div class="w-24">
                            <x-input-label for="new_type_tax_rate" value="IR estimado (%)" />
                            <x-text-input id="new_type_tax_rate" type="number" step="0.01" min="0" max="100" class="mt-1 block w-full" wire:model="new_type_tax_rate" placeholder="Opcional" />
                            <x-input-error :messages="$errors->get('new_type_tax_rate')" class="mt-1" />
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

                    <div class="sm:col-span-2">
                        <x-input-label for="broker" value="Corretora" />
                        <x-text-input id="broker" type="text" class="mt-1 block w-full" wire:model="broker" placeholder="Opcional" />
                        <x-input-error :messages="$errors->get('broker')" class="mt-1" />
                    </div>

                    @if($editingId)
                        <div class="border-t border-gray-100 pt-5">
                            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Posição atual (calculada a partir dos lançamentos)</p>
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-sm text-gray-600">
                                <div>Ticker: <span class="font-medium text-gray-900">{{ $ticker !== '' ? $ticker : '—' }}</span></div>
                                <div>Quantidade: <span class="font-medium text-gray-900">{{ $ticker !== '' ? rtrim(rtrim($quantity, '0'), '.') : '—' }}</span></div>
                                <div>Valor investido: <span class="font-medium text-gray-900">R$ {{ number_format((float) $invested_amount, 2, ',', '.') }}</span></div>
                            </div>
                            <p class="text-xs text-gray-400 mt-2">Para alterar quantidade ou valor investido, registre um lançamento na aba <a href="{{ route('investments.transactions', ['ativo' => $editingId]) }}" wire:navigate class="text-indigo-600 hover:underline">Lançamentos</a>.</p>
                        </div>
                    @else
                        <div class="border-t border-gray-100 pt-5">
                            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Cotação automática (opcional)</p>
                            <div class="grid grid-cols-1 sm:grid-cols-6 gap-4">
                                <div class="sm:col-span-3">
                                    <x-input-label for="ticker" value="Ticker (B3)" />
                                    <x-text-input id="ticker" type="text" class="mt-1 block w-full" wire:model="ticker" placeholder="Ex: ITUB4" />
                                    <x-input-error :messages="$errors->get('ticker')" class="mt-1" />
                                </div>
                                <div class="sm:col-span-3">
                                    <x-input-label for="quantity" value="Quantidade" />
                                    <x-text-input id="quantity" type="number" step="0.00000001" class="mt-1 block w-full" wire:model="quantity" placeholder="Nº de cotas/ações" />
                                    <x-input-error :messages="$errors->get('quantity')" class="mt-1" />
                                </div>
                            </div>
                            <p class="text-xs text-gray-400 mt-2">Preenchendo ticker e quantidade, o "Valor atual" passa a ser atualizado automaticamente a partir da cotação de mercado.</p>
                        </div>

                        <div class="border-t border-gray-100 pt-5">
                            <x-input-label for="invested_amount" value="Valor investido inicial" />
                            <x-text-input id="invested_amount" type="number" step="0.01" class="mt-1 block w-full sm:w-64" wire:model="invested_amount" />
                            <x-input-error :messages="$errors->get('invested_amount')" class="mt-1" />
                            <p class="text-xs text-gray-400 mt-2">Cria automaticamente o primeiro lançamento (compra/aporte) deste ativo. Novos aportes depois disso são feitos na aba Lançamentos.</p>
                        </div>
                    @endif

                    <div class="border-t border-gray-100 pt-5">
                        <x-input-label for="current_amount" value="Valor atual" />
                        <x-text-input id="current_amount" type="number" step="0.01" class="mt-1 block w-full sm:w-64" wire:model="current_amount" />
                        <x-input-error :messages="$errors->get('current_amount')" class="mt-1" />
                    </div>

                    <div class="flex gap-2">
                        <x-primary-button type="submit">{{ $editingId ? 'Salvar alterações' : 'Adicionar investimento' }}</x-primary-button>
                        @if($editingId)
                            <x-secondary-button type="button" wire:click="cancelEdit">Cancelar</x-secondary-button>
                        @endif
                    </div>
                </form>
            </div>

            @foreach ($groups as $group)
                <div class="bg-white shadow-sm rounded-lg overflow-hidden">
                    <div class="px-6 py-3 bg-gray-50 border-b border-gray-100 flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full" style="background-color: {{ $group['type']->color }}"></span>
                        <h3 class="font-semibold text-gray-800">{{ $group['type']->name }}</h3>
                        <span class="text-xs text-gray-400">({{ $group['investments']->count() }})</span>
                    </div>
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Ativo</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Qtd.</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Preço médio</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Investido</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Atual</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Rentabilidade</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Lucro realizado</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">% carteira</th>
                                <th class="px-6 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach ($group['investments'] as $investment)
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
                                            <p class="text-xs text-gray-400 mt-0.5">{{ $investment->ticker }}</p>
                                        @endif
                                        @if(! $investment->is_active)
                                            <span class="inline-block mt-1 px-1.5 py-0.5 rounded text-[10px] font-medium bg-gray-100 text-gray-500">Inativo</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-500">{{ $investment->ticker ? rtrim(rtrim(number_format($investment->quantity, 8, ',', '.'), '0'), ',') : '—' }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-500">{{ $investment->average_price !== null ? 'R$ '.number_format($investment->average_price, 2, ',', '.') : '—' }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-900">R$ {{ number_format($investment->invested_amount, 2, ',', '.') }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-900">R$ {{ number_format($investment->current_amount, 2, ',', '.') }}</td>
                                    <td class="px-6 py-4 text-sm font-medium {{ $investment->gain >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                        {{ $investment->gain >= 0 ? '+' : '' }}R$ {{ number_format($investment->gain, 2, ',', '.') }}
                                        <span class="block text-xs">({{ $investment->gain >= 0 ? '+' : '' }}{{ number_format($investment->gain_percent, 2, ',', '.') }}%)</span>
                                    </td>
                                    <td class="px-6 py-4 text-sm {{ (float) $investment->realized_gain > 0 ? 'text-green-600' : ((float) $investment->realized_gain < 0 ? 'text-red-600' : 'text-gray-400') }}">
                                        {{ $investment->realized_gain !== null ? 'R$ '.number_format($investment->realized_gain, 2, ',', '.') : '—' }}
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-500">
                                        {{ $totalCurrent > 0 ? number_format(((float) $investment->current_amount / $totalCurrent) * 100, 1, ',', '.') : '0,0' }}%
                                    </td>
                                    <td class="px-6 py-4 text-sm text-right space-x-2 whitespace-nowrap">
                                        @if($investment->ticker)
                                            <button wire:click="refreshQuote({{ $investment->id }})" wire:loading.attr="disabled" wire:target="refreshQuote({{ $investment->id }})" class="text-gray-500 hover:underline disabled:opacity-50">Atualizar cotação</button>
                                        @endif
                                        <a href="{{ route('investments.transactions', ['ativo' => $investment->id]) }}" wire:navigate class="text-gray-500 hover:underline">Lançamentos</a>
                                        <button wire:click="edit({{ $investment->id }})" class="text-indigo-600 hover:underline">Editar</button>
                                        <button wire:click="toggleActive({{ $investment->id }})" class="text-gray-500 hover:underline">{{ $investment->is_active ? 'Inativar' : 'Ativar' }}</button>
                                        <button type="button" x-on:click="Swal.fire({icon:'warning',title:'Excluir investimento?',showCancelButton:true,confirmButtonText:'Excluir',cancelButtonText:'Cancelar',confirmButtonColor:'#dc2626'}).then((r) => r.isConfirmed && $wire.delete({{ $investment->id }}))" class="text-red-600 hover:underline">Excluir</button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endforeach

            @if($withoutType->isNotEmpty())
                <div class="bg-white shadow-sm rounded-lg overflow-hidden">
                    <div class="px-6 py-3 bg-gray-50 border-b border-gray-100">
                        <h3 class="font-semibold text-gray-800">Sem tipo</h3>
                    </div>
                    <table class="min-w-full divide-y divide-gray-200">
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach ($withoutType as $investment)
                                <tr>
                                    <td class="px-6 py-4 text-sm text-gray-800">{{ $investment->name }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-900">R$ {{ number_format($investment->current_amount, 2, ',', '.') }}</td>
                                    <td class="px-6 py-4 text-sm text-right space-x-2">
                                        <button wire:click="edit({{ $investment->id }})" class="text-indigo-600 hover:underline">Editar</button>
                                        <button type="button" x-on:click="Swal.fire({icon:'warning',title:'Excluir investimento?',showCancelButton:true,confirmButtonText:'Excluir',cancelButtonText:'Cancelar',confirmButtonColor:'#dc2626'}).then((r) => r.isConfirmed && $wire.delete({{ $investment->id }}))" class="text-red-600 hover:underline">Excluir</button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            @if($groups->isEmpty() && $withoutType->isEmpty())
                <div class="bg-white shadow-sm rounded-lg p-8 text-center text-sm text-gray-500">
                    Nenhum investimento cadastrado{{ $filterTypeId ? ' neste tipo' : '' }}.
                </div>
            @endif
        </div>
    </div>
