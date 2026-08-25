<?php

use App\Models\Account;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] class extends Component
{
    use WithPagination;

    #[Validate('required|string|max:255')]
    public string $name = '';

    #[Validate('required|in:corrente,poupanca,dinheiro,investimento,outro')]
    public string $type = 'corrente';

    #[Validate('required|numeric')]
    public string $initial_balance = '0';

    #[Validate('required|string')]
    public string $color = '#3b82f6';

    public ?int $editingId = null;

    public function save(): void
    {
        $this->validate();

        $isNew = $this->editingId === null;

        auth()->user()->accounts()->updateOrCreate(
            ['id' => $this->editingId],
            [
                'name' => $this->name,
                'type' => $this->type,
                'initial_balance' => $this->initial_balance,
                'color' => $this->color,
            ]
        );

        $this->reset(['name', 'type', 'initial_balance', 'color', 'editingId']);
        $this->type = 'corrente';
        $this->color = '#3b82f6';
        $this->initial_balance = '0';

        $this->dispatch('notify', type: 'success', message: $isNew ? 'Conta adicionada com sucesso.' : 'Conta atualizada com sucesso.');
    }

    public function edit(Account $account): void
    {
        $this->authorize('update', $account);

        $this->editingId = $account->id;
        $this->name = $account->name;
        $this->type = $account->type;
        $this->initial_balance = (string) $account->initial_balance;
        $this->color = $account->color;
    }

    public function cancelEdit(): void
    {
        $this->reset(['name', 'type', 'initial_balance', 'color', 'editingId']);
    }

    public function toggleActive(Account $account): void
    {
        $this->authorize('update', $account);
        $account->update(['is_active' => ! $account->is_active]);

        $this->dispatch('notify', type: 'success', message: $account->is_active ? 'Conta ativada.' : 'Conta inativada.');
    }

    public function delete(Account $account): void
    {
        $this->authorize('delete', $account);
        $account->delete();

        $this->dispatch('notify', type: 'success', message: 'Conta excluída com sucesso.');
    }

    public function with(): array
    {
        return [
            'accounts' => auth()->user()->accounts()->latest()->paginate(10),
        ];
    }
}; ?>

    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Contas') }}</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <div class="bg-white shadow-sm rounded-lg p-6">
                <h3 class="font-semibold text-gray-800 mb-4">{{ $editingId ? 'Editar conta' : 'Nova conta' }}</h3>
                <form wire:submit="save" class="grid grid-cols-1 sm:grid-cols-4 gap-4 items-end">
                    <div class="sm:col-span-2">
                        <x-input-label for="name" value="Nome" />
                        <x-text-input id="name" type="text" class="mt-1 block w-full" wire:model="name" />
                        <x-input-error :messages="$errors->get('name')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="type" value="Tipo" />
                        <select id="type" wire:model="type" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                            <option value="corrente">Conta corrente</option>
                            <option value="poupanca">Poupança</option>
                            <option value="dinheiro">Dinheiro</option>
                            <option value="investimento">Investimento</option>
                            <option value="outro">Outro</option>
                        </select>
                    </div>
                    <div>
                        <x-input-label for="initial_balance" value="Saldo inicial" />
                        <x-text-input id="initial_balance" type="number" step="0.01" class="mt-1 block w-full" wire:model="initial_balance" />
                        <x-input-error :messages="$errors->get('initial_balance')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="color" value="Cor" />
                        <input id="color" type="color" wire:model="color" class="mt-1 block w-full h-10 rounded-md border-gray-300 shadow-sm" />
                    </div>
                    <div class="sm:col-span-4 flex gap-2">
                        <x-primary-button type="submit">{{ $editingId ? 'Salvar alterações' : 'Adicionar conta' }}</x-primary-button>
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
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nome</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tipo</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Saldo atual</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Situação</th>
                            <th class="px-6 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse ($accounts as $account)
                            <tr>
                                <td class="px-6 py-4 text-sm text-gray-800 flex items-center gap-2">
                                    <span class="w-2.5 h-2.5 rounded-full" style="background-color: {{ $account->color }}"></span>
                                    {{ $account->name }}
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500">{{ ucfirst($account->type) }}</td>
                                <td class="px-6 py-4 text-sm font-medium {{ $account->current_balance >= 0 ? 'text-gray-900' : 'text-red-600' }}">
                                    R$ {{ number_format($account->current_balance, 2, ',', '.') }}
                                </td>
                                <td class="px-6 py-4 text-sm">
                                    <button wire:click="toggleActive({{ $account->id }})" class="px-2 py-1 rounded text-xs {{ $account->is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                                        {{ $account->is_active ? 'Ativa' : 'Inativa' }}
                                    </button>
                                </td>
                                <td class="px-6 py-4 text-sm text-right space-x-2">
                                    <button wire:click="edit({{ $account->id }})" class="text-indigo-600 hover:underline">Editar</button>
                                    <button type="button" x-on:click="Swal.fire({icon:'warning',title:'Excluir conta?',text:'A conta será movida para a lixeira e poderá ser restaurada depois.',showCancelButton:true,confirmButtonText:'Excluir',cancelButtonText:'Cancelar',confirmButtonColor:'#dc2626'}).then((r) => r.isConfirmed && $wire.delete({{ $account->id }}))" class="text-red-600 hover:underline">Excluir</button>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-6 py-6 text-center text-sm text-gray-500">Nenhuma conta cadastrada.</td></tr>
                        @endforelse
                    </tbody>
                </table>
                <div class="p-4">{{ $accounts->links() }}</div>
            </div>
        </div>
    </div>
