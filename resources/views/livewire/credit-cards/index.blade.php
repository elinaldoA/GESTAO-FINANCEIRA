<?php

use App\Models\CreditCard;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    #[Validate('required|string|max:255')]
    public string $name = '';

    #[Validate('required|numeric|min:0')]
    public string $limit_amount = '0';

    #[Validate('required|integer|min:1|max:31')]
    public string $closing_day = '1';

    #[Validate('required|integer|min:1|max:31')]
    public string $due_day = '10';

    #[Validate('required|string')]
    public string $color = '#8b5cf6';

    public ?int $editingId = null;

    public function save(): void
    {
        $this->validate();

        $isNew = $this->editingId === null;

        auth()->user()->creditCards()->updateOrCreate(
            ['id' => $this->editingId],
            [
                'name' => $this->name,
                'limit_amount' => $this->limit_amount,
                'closing_day' => $this->closing_day,
                'due_day' => $this->due_day,
                'color' => $this->color,
            ]
        );

        $this->reset(['name', 'limit_amount', 'closing_day', 'due_day', 'color', 'editingId']);
        $this->closing_day = '1';
        $this->due_day = '10';
        $this->color = '#8b5cf6';

        $this->dispatch('notify', type: 'success', message: $isNew ? 'Cartão adicionado com sucesso.' : 'Cartão atualizado com sucesso.');
    }

    public function edit(CreditCard $creditCard): void
    {
        $this->authorize('update', $creditCard);

        $this->editingId = $creditCard->id;
        $this->name = $creditCard->name;
        $this->limit_amount = (string) $creditCard->limit_amount;
        $this->closing_day = (string) $creditCard->closing_day;
        $this->due_day = (string) $creditCard->due_day;
        $this->color = $creditCard->color;
    }

    public function cancelEdit(): void
    {
        $this->reset(['name', 'limit_amount', 'closing_day', 'due_day', 'color', 'editingId']);
    }

    public function delete(CreditCard $creditCard): void
    {
        $this->authorize('delete', $creditCard);
        $creditCard->delete();

        $this->dispatch('notify', type: 'success', message: 'Cartão excluído com sucesso.');
    }

    public function with(): array
    {
        return [
            'creditCards' => auth()->user()->creditCards()->latest()->get(),
        ];
    }
}; ?>

    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Cartões de crédito') }}</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <div class="bg-white shadow-sm rounded-lg p-6">
                <h3 class="font-semibold text-gray-800 mb-4">{{ $editingId ? 'Editar cartão' : 'Novo cartão' }}</h3>
                <form wire:submit="save" class="grid grid-cols-1 sm:grid-cols-5 gap-4 items-end">
                    <div class="sm:col-span-2">
                        <x-input-label for="name" value="Nome" />
                        <x-text-input id="name" type="text" class="mt-1 block w-full" wire:model="name" />
                        <x-input-error :messages="$errors->get('name')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="limit_amount" value="Limite" />
                        <x-text-input id="limit_amount" type="number" step="0.01" class="mt-1 block w-full" wire:model="limit_amount" />
                    </div>
                    <div>
                        <x-input-label for="closing_day" value="Dia de fechamento" />
                        <x-text-input id="closing_day" type="number" min="1" max="31" class="mt-1 block w-full" wire:model="closing_day" />
                    </div>
                    <div>
                        <x-input-label for="due_day" value="Dia de vencimento" />
                        <x-text-input id="due_day" type="number" min="1" max="31" class="mt-1 block w-full" wire:model="due_day" />
                    </div>
                    <div class="sm:col-span-5 flex items-end gap-4">
                        <div>
                            <x-input-label for="color" value="Cor" />
                            <input id="color" type="color" wire:model="color" class="mt-1 block h-10 w-20 rounded-md border-gray-300 shadow-sm" />
                        </div>
                        <x-primary-button type="submit">{{ $editingId ? 'Salvar alterações' : 'Adicionar cartão' }}</x-primary-button>
                        @if($editingId)
                            <x-secondary-button type="button" wire:click="cancelEdit">Cancelar</x-secondary-button>
                        @endif
                    </div>
                </form>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                @forelse ($creditCards as $card)
                    <div class="bg-white shadow-sm rounded-lg p-6 border-t-4" style="border-color: {{ $card->color }}">
                        <div class="flex justify-between items-start">
                            <div>
                                <p class="font-semibold text-gray-800">{{ $card->name }}</p>
                                <p class="text-xs text-gray-500">Fecha dia {{ $card->closing_day }} · Vence dia {{ $card->due_day }}</p>
                            </div>
                            <span class="space-x-2 text-sm">
                                <button wire:click="edit({{ $card->id }})" class="text-indigo-600 hover:underline">Editar</button>
                                <button type="button" x-on:click="Swal.fire({icon:'warning',title:'Excluir cartão?',showCancelButton:true,confirmButtonText:'Excluir',cancelButtonText:'Cancelar',confirmButtonColor:'#dc2626'}).then((r) => r.isConfirmed && $wire.delete({{ $card->id }}))" class="text-red-600 hover:underline">Excluir</button>
                            </span>
                        </div>
                        <div class="mt-4">
                            <div class="flex justify-between text-sm mb-1">
                                <span class="text-gray-500">Usado</span>
                                <span class="text-gray-700">R$ {{ number_format($card->used_limit, 2, ',', '.') }} / R$ {{ number_format($card->limit_amount, 2, ',', '.') }}</span>
                            </div>
                            @php $percent = $card->limit_amount > 0 ? min(100, ($card->used_limit / $card->limit_amount) * 100) : 0; @endphp
                            <div class="w-full bg-gray-100 rounded-full h-2">
                                <div class="h-2 rounded-full {{ $percent >= 100 ? 'bg-red-500' : ($percent >= 80 ? 'bg-amber-500' : 'bg-indigo-500') }}" style="width: {{ $percent }}%"></div>
                            </div>
                        </div>
                        <a href="{{ route('credit-cards.invoice', $card) }}" wire:navigate class="mt-4 inline-block text-sm text-indigo-600 hover:underline">Ver fatura →</a>
                    </div>
                @empty
                    <p class="text-sm text-gray-500">Nenhum cartão cadastrado.</p>
                @endforelse
            </div>
        </div>
    </div>
