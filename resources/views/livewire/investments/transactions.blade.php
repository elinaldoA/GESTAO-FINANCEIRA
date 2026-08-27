<?php

use App\Models\Investment;
use App\Models\InvestmentTransaction;
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

    public ?int $transaction_investment_id = null;

    public string $transaction_date = '';

    public string $transaction_type = '';

    public string $transaction_quantity = '';

    public string $transaction_unit_price = '';

    public string $transaction_fees = '';

    public string $transaction_amount = '';

    public string $transaction_notes = '';

    public ?int $editingId = null;

    public function mount(): void
    {
        $this->transaction_date = now()->format('Y-m-d');
        $this->transaction_investment_id = $this->filterInvestmentId;
        $this->syncDefaultType();
    }

    public function updatedTransactionInvestmentId(): void
    {
        $this->reset(['transaction_quantity', 'transaction_unit_price', 'transaction_fees', 'transaction_amount']);
        $this->syncDefaultType();
    }

    private function syncDefaultType(): void
    {
        $investment = $this->selectedFormInvestment();

        if ($investment === null) {
            $this->transaction_type = '';

            return;
        }

        $this->transaction_type = $investment->ticker ? 'compra' : 'aporte';
    }

    private function selectedFormInvestment(): ?Investment
    {
        if (! $this->transaction_investment_id) {
            return null;
        }

        return auth()->user()->investments()->find($this->transaction_investment_id);
    }

    public function save(): void
    {
        $investment = auth()->user()->investments()->find($this->transaction_investment_id);

        $this->validate([
            'transaction_investment_id' => ['required', Rule::exists('investments', 'id')->where('user_id', auth()->id())],
            'transaction_date' => 'required|date',
        ]);

        $isTicker = (bool) $investment->ticker;

        if ($isTicker) {
            $this->validate([
                'transaction_type' => 'required|in:compra,venda',
                'transaction_quantity' => 'required|numeric|min:0.00000001',
                'transaction_unit_price' => 'required|numeric|min:0.0001',
                'transaction_fees' => 'nullable|numeric|min:0',
            ]);

            $quantity = (float) $this->transaction_quantity;
            $fees = $this->transaction_fees !== '' ? (float) $this->transaction_fees : 0.0;
            $gross = $quantity * (float) $this->transaction_unit_price;
            $amount = $this->transaction_type === 'compra' ? round($gross + $fees, 2) : round($gross - $fees, 2);

            if ($this->transaction_type === 'venda') {
                $available = $investment->previewBalance($this->editingId)['quantity'];

                if ($quantity > $available) {
                    $this->addError('transaction_quantity', 'Quantidade maior que a posição atual (' . rtrim(rtrim(number_format($available, 8, ',', '.'), '0'), ',') . ').');

                    return;
                }
            }

            $data = [
                'type' => $this->transaction_type,
                'quantity' => $quantity,
                'unit_price' => $this->transaction_unit_price,
                'fees' => $this->transaction_fees !== '' ? $this->transaction_fees : null,
                'amount' => $amount,
            ];
        } else {
            $this->validate([
                'transaction_type' => 'required|in:aporte,resgate',
                'transaction_amount' => 'required|numeric|min:0.01',
            ]);

            $amount = (float) $this->transaction_amount;

            if ($this->transaction_type === 'resgate') {
                $available = $investment->previewBalance($this->editingId)['invested_amount'];

                if ($amount > $available) {
                    $this->addError('transaction_amount', 'Valor maior que o saldo investido atual (R$ ' . number_format($available, 2, ',', '.') . ').');

                    return;
                }
            }

            $data = [
                'type' => $this->transaction_type,
                'quantity' => null,
                'unit_price' => null,
                'fees' => null,
                'amount' => $amount,
            ];
        }

        $this->validate(['transaction_notes' => 'nullable|string|max:255']);

        $data['investment_id'] = $investment->id;
        $data['date'] = $this->transaction_date;
        $data['notes'] = $this->transaction_notes !== '' ? $this->transaction_notes : null;

        $isNew = $this->editingId === null;

        if ($this->editingId) {
            $transaction = InvestmentTransaction::findOrFail($this->editingId);
            $this->authorize('update', $transaction);
            $transaction->update($data);
        } else {
            $data['user_id'] = auth()->id();
            InvestmentTransaction::create($data);
        }

        $investment->recalculateFromTransactions();

        $this->resetForm();

        $this->dispatch('notify', type: 'success', message: $isNew ? 'Lançamento registrado com sucesso.' : 'Lançamento atualizado com sucesso.');
    }

    public function edit(InvestmentTransaction $transaction): void
    {
        $this->authorize('update', $transaction);

        $this->editingId = $transaction->id;
        $this->transaction_investment_id = $transaction->investment_id;
        $this->transaction_date = $transaction->date->format('Y-m-d');
        $this->transaction_type = $transaction->type;
        $this->transaction_quantity = (string) $transaction->quantity;
        $this->transaction_unit_price = (string) $transaction->unit_price;
        $this->transaction_fees = (string) $transaction->fees;
        $this->transaction_amount = (string) $transaction->amount;
        $this->transaction_notes = (string) $transaction->notes;
    }

    public function cancelEdit(): void
    {
        $this->resetForm();
    }

    public function delete(InvestmentTransaction $transaction): void
    {
        $this->authorize('delete', $transaction);

        $investment = $transaction->investment;
        $transaction->delete();
        $investment->recalculateFromTransactions();

        $this->dispatch('notify', type: 'success', message: 'Lançamento excluído com sucesso.');
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

    private function resetForm(): void
    {
        $this->reset(['transaction_investment_id', 'transaction_quantity', 'transaction_unit_price', 'transaction_fees', 'transaction_amount', 'transaction_notes', 'editingId', 'transaction_type']);
        $this->transaction_date = now()->format('Y-m-d');
    }

    public function with(): array
    {
        $investments = auth()->user()->investments()->orderBy('name')->get();

        $query = auth()->user()->investmentTransactions()->with('investment')->latest('date')->latest('id');

        if ($this->filterInvestmentId) {
            $query->where('investment_id', $this->filterInvestmentId);
        }

        if ($this->filterType) {
            $query->where('type', $this->filterType);
        }

        return [
            'investments' => $investments,
            'transactions' => $query->paginate(15),
            'formInvestment' => $this->selectedFormInvestment(),
        ];
    }
}; ?>

    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Investimentos') }}</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @include('livewire.investments.partials.tabs')

            <div class="bg-white shadow-sm rounded-lg p-6">
                <h3 class="font-semibold text-gray-800 mb-4">{{ $editingId ? 'Editar lançamento' : 'Novo lançamento' }}</h3>
                <form wire:submit="save" class="space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                        <div class="sm:col-span-2">
                            <x-input-label for="transaction_investment_id" value="Ativo" />
                            <select id="transaction_investment_id" wire:model.live="transaction_investment_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                                <option value="">Selecione…</option>
                                @foreach ($investments as $inv)
                                    <option value="{{ $inv->id }}">{{ $inv->name }}{{ $inv->ticker ? " ({$inv->ticker})" : '' }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('transaction_investment_id')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="transaction_date" value="Data" />
                            <x-text-input id="transaction_date" type="date" class="mt-1 block w-full" wire:model="transaction_date" />
                            <x-input-error :messages="$errors->get('transaction_date')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="transaction_type" value="Tipo" />
                            <select id="transaction_type" wire:model="transaction_type" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" @disabled(! $formInvestment)>
                                @if ($formInvestment && $formInvestment->ticker)
                                    <option value="compra">Compra</option>
                                    <option value="venda">Venda</option>
                                @elseif ($formInvestment)
                                    <option value="aporte">Aporte</option>
                                    <option value="resgate">Resgate</option>
                                @else
                                    <option value="">Selecione um ativo</option>
                                @endif
                            </select>
                        </div>
                    </div>

                    @if ($formInvestment && $formInvestment->ticker)
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <div>
                                <x-input-label for="transaction_quantity" value="Quantidade" />
                                <x-text-input id="transaction_quantity" type="number" step="0.00000001" class="mt-1 block w-full" wire:model="transaction_quantity" />
                                <x-input-error :messages="$errors->get('transaction_quantity')" class="mt-1" />
                            </div>
                            <div>
                                <x-input-label for="transaction_unit_price" value="Preço unitário" />
                                <x-text-input id="transaction_unit_price" type="number" step="0.0001" class="mt-1 block w-full" wire:model="transaction_unit_price" />
                                <x-input-error :messages="$errors->get('transaction_unit_price')" class="mt-1" />
                            </div>
                            <div>
                                <x-input-label for="transaction_fees" value="Taxas/corretagem" />
                                <x-text-input id="transaction_fees" type="number" step="0.01" min="0" class="mt-1 block w-full" wire:model="transaction_fees" placeholder="Opcional" />
                            </div>
                        </div>
                    @elseif ($formInvestment)
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <div>
                                <x-input-label for="transaction_amount" value="Valor" />
                                <x-text-input id="transaction_amount" type="number" step="0.01" class="mt-1 block w-full" wire:model="transaction_amount" />
                                <x-input-error :messages="$errors->get('transaction_amount')" class="mt-1" />
                            </div>
                        </div>
                    @endif

                    <div>
                        <x-input-label for="transaction_notes" value="Observação" />
                        <x-text-input id="transaction_notes" type="text" class="mt-1 block w-full" wire:model="transaction_notes" placeholder="Opcional" />
                    </div>

                    <div class="flex gap-2">
                        <x-primary-button type="submit">{{ $editingId ? 'Salvar' : 'Adicionar' }}</x-primary-button>
                        @if($editingId)
                            <x-secondary-button type="button" wire:click="cancelEdit">Cancelar</x-secondary-button>
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
                @foreach (\App\Models\InvestmentTransaction::TYPES as $value => $label)
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
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Qtd. / Preço</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Valor</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Observação</th>
                            <th class="px-6 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse ($transactions as $transaction)
                            <tr>
                                <td class="px-6 py-4 text-sm text-gray-800">{{ $transaction->date->format('d/m/Y') }}</td>
                                <td class="px-6 py-4 text-sm text-gray-800">{{ $transaction->investment->name }}</td>
                                <td class="px-6 py-4 text-sm">
                                    <span class="px-2 py-0.5 rounded text-xs font-medium {{ in_array($transaction->type, ['compra', 'aporte']) ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                                        {{ $transaction->type_label }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500">
                                    @if($transaction->quantity !== null)
                                        {{ rtrim(rtrim(number_format($transaction->quantity, 8, ',', '.'), '0'), ',') }} &times; R$ {{ number_format($transaction->unit_price, 2, ',', '.') }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-sm font-medium text-gray-900">R$ {{ number_format($transaction->amount, 2, ',', '.') }}</td>
                                <td class="px-6 py-4 text-sm text-gray-500">{{ $transaction->notes ?? '—' }}</td>
                                <td class="px-6 py-4 text-sm text-right space-x-2">
                                    <button wire:click="edit({{ $transaction->id }})" class="text-indigo-600 hover:underline">Editar</button>
                                    <button type="button" x-on:click="Swal.fire({icon:'warning',title:'Excluir lançamento?',showCancelButton:true,confirmButtonText:'Excluir',cancelButtonText:'Cancelar',confirmButtonColor:'#dc2626'}).then((r) => r.isConfirmed && $wire.delete({{ $transaction->id }}))" class="text-red-600 hover:underline">Excluir</button>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-6 py-6 text-center text-sm text-gray-500">Nenhum lançamento registrado ainda.</td></tr>
                        @endforelse
                    </tbody>
                </table>
                <div class="p-4">{{ $transactions->links() }}</div>
            </div>
        </div>
    </div>
