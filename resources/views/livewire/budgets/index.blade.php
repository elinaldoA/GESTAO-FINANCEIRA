<?php

use App\Models\Budget;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public ?int $category_id = null;

    #[Validate('required|numeric|min:0.01')]
    public string $amount = '';

    public string $month;

    public string $year;

    public ?int $editingId = null;

    public function mount(): void
    {
        $this->month = now()->format('n');
        $this->year = now()->format('Y');
    }

    public function save(): void
    {
        $this->validate([
            'category_id' => ['required', Rule::exists('categories', 'id')->where('user_id', auth()->id())],
            'amount' => 'required|numeric|min:0.01',
        ]);

        $isNew = $this->editingId === null;

        $data = [
            'category_id' => $this->category_id,
            'month' => $this->month,
            'year' => $this->year,
            'amount' => $this->amount,
        ];

        if ($this->editingId) {
            auth()->user()->budgets()->whereKey($this->editingId)->update($data);
        } else {
            auth()->user()->budgets()->updateOrCreate(
                ['category_id' => $this->category_id, 'month' => $this->month, 'year' => $this->year],
                ['amount' => $this->amount]
            );
        }

        $this->reset(['category_id', 'amount', 'editingId']);

        $this->dispatch('notify', type: 'success', message: $isNew ? 'Orçamento adicionado com sucesso.' : 'Orçamento atualizado com sucesso.');
    }

    public function edit(Budget $budget): void
    {
        $this->authorize('update', $budget);

        $this->editingId = $budget->id;
        $this->category_id = $budget->category_id;
        $this->amount = (string) $budget->amount;
        $this->month = (string) $budget->month;
        $this->year = (string) $budget->year;
    }

    public function cancelEdit(): void
    {
        $this->reset(['category_id', 'amount', 'editingId']);
        $this->month = now()->format('n');
        $this->year = now()->format('Y');
    }

    public function delete(Budget $budget): void
    {
        $this->authorize('delete', $budget);
        $budget->delete();

        $this->dispatch('notify', type: 'success', message: 'Orçamento excluído com sucesso.');
    }

    public function with(): array
    {
        return [
            'budgets' => auth()->user()->budgets()
                ->with('category')
                ->where('month', $this->month)->where('year', $this->year)
                ->get(),
            'categories' => auth()->user()->categories()->where('type', 'despesa')->orderBy('name')->get(),
        ];
    }
}; ?>

    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Orçamentos') }}</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <div class="bg-white shadow-sm rounded-lg p-6">
                <h3 class="font-semibold text-gray-800 mb-4">{{ $editingId ? 'Editar orçamento' : 'Novo orçamento' }}</h3>
                <form wire:submit="save" class="grid grid-cols-1 sm:grid-cols-4 gap-4 items-end">
                    <div>
                        <x-input-label value="Mês" />
                        <select wire:model="month" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                            @foreach (range(1, 12) as $m)
                                <option value="{{ $m }}">{{ \Carbon\Carbon::create()->month($m)->translatedFormat('F') }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <x-input-label value="Ano" />
                        <x-text-input type="number" class="mt-1 block w-full" wire:model="year" />
                    </div>
                    <div>
                        <x-input-label value="Categoria" />
                        <select wire:model="category_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                            <option value="">Selecione</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category->id }}">{{ $category->name }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('category_id')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label value="Valor limite" />
                        <x-text-input type="number" step="0.01" class="mt-1 block w-full" wire:model="amount" />
                        <x-input-error :messages="$errors->get('amount')" class="mt-1" />
                    </div>
                    <div class="sm:col-span-4 flex gap-2">
                        <x-primary-button type="submit">{{ $editingId ? 'Salvar alterações' : 'Adicionar orçamento' }}</x-primary-button>
                        @if($editingId)
                            <x-secondary-button type="button" wire:click="cancelEdit">Cancelar</x-secondary-button>
                        @endif
                    </div>
                </form>
            </div>

            <div class="bg-white shadow-sm rounded-lg p-6">
                <h3 class="font-semibold text-gray-800 mb-4">
                    Orçamentos de {{ \Carbon\Carbon::create()->month((int) $month)->translatedFormat('F') }}/{{ $year }}
                </h3>
                <div class="space-y-4">
                    @forelse ($budgets as $budget)
                        @php $percent = $budget->amount > 0 ? min(100, ($budget->spent / $budget->amount) * 100) : 0; @endphp
                        <div>
                            <div class="flex justify-between items-center text-sm mb-1">
                                <span class="text-gray-700 font-medium">{{ $budget->category->name }}</span>
                                <span class="space-x-2">
                                    <span class="text-gray-500">R$ {{ number_format($budget->spent, 2, ',', '.') }} / R$ {{ number_format($budget->amount, 2, ',', '.') }}</span>
                                    <button wire:click="edit({{ $budget->id }})" class="text-indigo-600 hover:underline">Editar</button>
                                    <button type="button" x-on:click="Swal.fire({icon:'warning',title:'Excluir orçamento?',showCancelButton:true,confirmButtonText:'Excluir',cancelButtonText:'Cancelar',confirmButtonColor:'#dc2626'}).then((r) => r.isConfirmed && $wire.delete({{ $budget->id }}))" class="text-red-600 hover:underline">Excluir</button>
                                </span>
                            </div>
                            <div class="w-full bg-gray-100 rounded-full h-2">
                                <div class="h-2 rounded-full {{ $percent >= 100 ? 'bg-red-500' : ($percent >= 80 ? 'bg-amber-500' : 'bg-green-500') }}" style="width: {{ $percent }}%"></div>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-gray-500">Nenhum orçamento definido para este mês.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
