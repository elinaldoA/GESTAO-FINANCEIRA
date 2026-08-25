<?php

use App\Models\Goal;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public string $name = '';
    public string $target_amount = '';
    public string $current_amount = '0';
    public string $target_date = '';
    public string $color = '#22c55e';
    public ?int $editingId = null;

    public function save(): void
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'target_amount' => 'required|numeric|min:0.01',
            'current_amount' => 'required|numeric|min:0',
            'target_date' => 'nullable|date',
            'color' => 'required|string',
        ]);

        $isNew = $this->editingId === null;

        auth()->user()->goals()->updateOrCreate(
            ['id' => $this->editingId],
            [
                'name' => $this->name,
                'target_amount' => $this->target_amount,
                'current_amount' => $this->current_amount,
                'target_date' => $this->target_date !== '' ? $this->target_date : null,
                'color' => $this->color,
            ]
        );

        $this->resetForm();

        $this->dispatch('notify', type: 'success', message: $isNew ? 'Meta criada com sucesso.' : 'Meta atualizada com sucesso.');
    }

    public function edit(Goal $goal): void
    {
        $this->authorize('update', $goal);

        $this->editingId = $goal->id;
        $this->name = $goal->name;
        $this->target_amount = (string) $goal->target_amount;
        $this->current_amount = (string) $goal->current_amount;
        $this->target_date = $goal->target_date?->format('Y-m-d') ?? '';
        $this->color = $goal->color;
    }

    public function cancelEdit(): void
    {
        $this->resetForm();
    }

    private function resetForm(): void
    {
        $this->reset(['name', 'target_amount', 'target_date', 'editingId']);
        $this->current_amount = '0';
        $this->color = '#22c55e';
    }

    public function contribute(Goal $goal, $amount): void
    {
        $this->authorize('update', $goal);

        $amount = (float) str_replace(',', '.', (string) $amount);

        if ($amount <= 0) {
            $this->dispatch('notify', type: 'error', message: 'Informe um valor válido para o aporte.');

            return;
        }

        $wasAchieved = $goal->is_achieved;
        $goal->update(['current_amount' => (float) $goal->current_amount + $amount]);
        $goal->refresh();

        if (! $wasAchieved && $goal->is_achieved) {
            $this->dispatch('notify', type: 'success', message: "🎉 Meta \"{$goal->name}\" atingida!");
        } else {
            $this->dispatch('notify', type: 'success', message: 'Aporte registrado com sucesso.');
        }
    }

    public function delete(Goal $goal): void
    {
        $this->authorize('delete', $goal);
        $goal->delete();

        $this->dispatch('notify', type: 'success', message: 'Meta excluída com sucesso.');
    }

    public function with(): array
    {
        $goals = auth()->user()->goals()->orderByDesc('created_at')->get();

        return [
            'goals' => $goals,
            'totalTarget' => (float) $goals->sum('target_amount'),
            'totalSaved' => (float) $goals->sum('current_amount'),
        ];
    }
}; ?>

    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Metas') }}</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="bg-white shadow-sm rounded-lg p-5">
                    <p class="text-sm text-gray-500">Metas ativas</p>
                    <p class="mt-1 text-2xl font-bold text-gray-900">{{ $goals->count() }}</p>
                </div>
                <div class="bg-white shadow-sm rounded-lg p-5">
                    <p class="text-sm text-gray-500">Total guardado</p>
                    <p class="mt-1 text-2xl font-bold text-green-600">R$ {{ number_format($totalSaved, 2, ',', '.') }}</p>
                </div>
                <div class="bg-white shadow-sm rounded-lg p-5">
                    <p class="text-sm text-gray-500">Objetivo total</p>
                    <p class="mt-1 text-2xl font-bold text-gray-900">R$ {{ number_format($totalTarget, 2, ',', '.') }}</p>
                </div>
            </div>

            <div class="bg-white shadow-sm rounded-lg p-6">
                <h3 class="font-semibold text-gray-800 mb-4">{{ $editingId ? 'Editar meta' : 'Nova meta' }}</h3>
                <form wire:submit="save" class="grid grid-cols-1 sm:grid-cols-5 gap-4 items-end">
                    <div class="sm:col-span-2">
                        <x-input-label for="name" value="Nome" />
                        <x-text-input id="name" type="text" class="mt-1 block w-full" wire:model="name" placeholder="Ex: Viagem para a praia" />
                        <x-input-error :messages="$errors->get('name')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="target_amount" value="Valor objetivo" />
                        <x-text-input id="target_amount" type="number" step="0.01" class="mt-1 block w-full" wire:model="target_amount" />
                        <x-input-error :messages="$errors->get('target_amount')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="current_amount" value="Já guardado" />
                        <x-text-input id="current_amount" type="number" step="0.01" class="mt-1 block w-full" wire:model="current_amount" />
                        <x-input-error :messages="$errors->get('current_amount')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="target_date" value="Data alvo (opcional)" />
                        <x-text-input id="target_date" type="date" class="mt-1 block w-full" wire:model="target_date" />
                    </div>
                    <div class="sm:col-span-5 flex items-end gap-4">
                        <div>
                            <x-input-label for="color" value="Cor" />
                            <input id="color" type="color" wire:model="color" class="mt-1 block h-10 w-20 rounded-md border-gray-300 shadow-sm" />
                        </div>
                        <x-primary-button type="submit">{{ $editingId ? 'Salvar alterações' : 'Criar meta' }}</x-primary-button>
                        @if($editingId)
                            <x-secondary-button type="button" wire:click="cancelEdit">Cancelar</x-secondary-button>
                        @endif
                    </div>
                </form>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                @forelse ($goals as $goal)
                    <div class="bg-white shadow-sm rounded-lg p-6 border-t-4" style="border-color: {{ $goal->color }}">
                        <div class="flex justify-between items-start">
                            <div>
                                <p class="font-semibold text-gray-800">
                                    {{ $goal->name }}
                                    @if($goal->is_achieved)
                                        <span class="ml-1" title="Meta atingida">🎉</span>
                                    @endif
                                </p>
                                @if($goal->target_date)
                                    <p class="text-xs text-gray-500">Até {{ $goal->target_date->format('d/m/Y') }}</p>
                                @endif
                            </div>
                            <span class="space-x-2 text-sm">
                                <button wire:click="edit({{ $goal->id }})" class="text-indigo-600 hover:underline">Editar</button>
                                <button type="button" x-on:click="Swal.fire({icon:'warning',title:'Excluir meta?',showCancelButton:true,confirmButtonText:'Excluir',cancelButtonText:'Cancelar',confirmButtonColor:'#dc2626'}).then((r) => r.isConfirmed && $wire.delete({{ $goal->id }}))" class="text-red-600 hover:underline">Excluir</button>
                            </span>
                        </div>

                        <div class="mt-4">
                            <div class="flex justify-between text-sm mb-1">
                                <span class="text-gray-500">R$ {{ number_format($goal->current_amount, 2, ',', '.') }} de R$ {{ number_format($goal->target_amount, 2, ',', '.') }}</span>
                                <span class="text-gray-700 font-medium">{{ number_format($goal->progress_percent, 0) }}%</span>
                            </div>
                            <div class="w-full bg-gray-100 rounded-full h-2">
                                <div class="h-2 rounded-full" style="width: {{ $goal->progress_percent }}%; background-color: {{ $goal->color }}"></div>
                            </div>
                            @if(! $goal->is_achieved)
                                <p class="text-xs text-gray-500 mt-1">Faltam R$ {{ number_format($goal->remaining_amount, 2, ',', '.') }}</p>
                            @endif
                        </div>

                        <button
                            type="button"
                            x-on:click="Swal.fire({title:'Registrar aporte',input:'number',inputLabel:'Valor em R$',inputAttributes:{min:0,step:0.01},showCancelButton:true,confirmButtonText:'Adicionar',cancelButtonText:'Cancelar',confirmButtonColor:'#4f46e5'}).then((r) => { if (r.isConfirmed && r.value) $wire.contribute({{ $goal->id }}, r.value) })"
                            class="mt-4 w-full text-center px-3 py-2 rounded-md text-sm font-medium bg-indigo-50 text-indigo-700 hover:bg-indigo-100"
                        >+ Registrar aporte</button>
                    </div>
                @empty
                    <p class="text-sm text-gray-500">Nenhuma meta cadastrada ainda.</p>
                @endforelse
            </div>
        </div>
    </div>
