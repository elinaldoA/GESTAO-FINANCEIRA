<?php

use App\Models\Category;
use App\Models\CategoryRule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    #[Validate('required|string|max:255')]
    public string $name = '';

    #[Validate('required|in:receita,despesa')]
    public string $type = 'despesa';

    #[Validate('required|string')]
    public string $color = '#64748b';

    public ?int $editingId = null;

    public string $ruleKeyword = '';
    public ?int $ruleCategoryId = null;
    public bool $showRuleManager = false;

    public function save(): void
    {
        $this->validate();

        $isNew = $this->editingId === null;

        auth()->user()->categories()->updateOrCreate(
            ['id' => $this->editingId],
            [
                'name' => $this->name,
                'type' => $this->type,
                'color' => $this->color,
            ]
        );

        $this->reset(['name', 'editingId']);
        $this->type = 'despesa';
        $this->color = '#64748b';

        $this->dispatch('notify', type: 'success', message: $isNew ? 'Categoria adicionada com sucesso.' : 'Categoria atualizada com sucesso.');
    }

    public function edit(Category $category): void
    {
        $this->authorize('update', $category);

        $this->editingId = $category->id;
        $this->name = $category->name;
        $this->type = $category->type;
        $this->color = $category->color;
    }

    public function cancelEdit(): void
    {
        $this->reset(['name', 'editingId']);
    }

    public function delete(Category $category): void
    {
        $this->authorize('delete', $category);
        $category->delete();

        $this->dispatch('notify', type: 'success', message: 'Categoria excluída com sucesso.');
    }

    public function addRule(): void
    {
        $this->validate([
            'ruleKeyword' => ['required', 'string', 'max:255', \Illuminate\Validation\Rule::unique('category_rules', 'keyword')->where('user_id', auth()->id())],
            'ruleCategoryId' => 'required|exists:categories,id',
        ]);

        auth()->user()->categoryRules()->create([
            'keyword' => $this->ruleKeyword,
            'category_id' => $this->ruleCategoryId,
        ]);

        $this->reset(['ruleKeyword', 'ruleCategoryId']);

        $this->dispatch('notify', type: 'success', message: 'Regra criada com sucesso.');
    }

    public function deleteRule(CategoryRule $categoryRule): void
    {
        $this->authorize('delete', $categoryRule);
        $categoryRule->delete();

        $this->dispatch('notify', type: 'success', message: 'Regra removida com sucesso.');
    }

    public function with(): array
    {
        return [
            'categories' => auth()->user()->categories()->orderBy('type')->orderBy('name')->get(),
            'categoryRules' => auth()->user()->categoryRules()->with('category')->orderBy('keyword')->get(),
        ];
    }
}; ?>

    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Categorias') }}</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <div class="bg-white shadow-sm rounded-lg p-6">
                <h3 class="font-semibold text-gray-800 mb-4">{{ $editingId ? 'Editar categoria' : 'Nova categoria' }}</h3>
                <form wire:submit="save" class="grid grid-cols-1 sm:grid-cols-4 gap-4 items-end">
                    <div class="sm:col-span-2">
                        <x-input-label for="name" value="Nome" />
                        <x-text-input id="name" type="text" class="mt-1 block w-full" wire:model="name" />
                        <x-input-error :messages="$errors->get('name')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="type" value="Tipo" />
                        <select id="type" wire:model="type" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                            <option value="despesa">Despesa</option>
                            <option value="receita">Receita</option>
                        </select>
                    </div>
                    <div>
                        <x-input-label for="color" value="Cor" />
                        <input id="color" type="color" wire:model="color" class="mt-1 block w-full h-10 rounded-md border-gray-300 shadow-sm" />
                    </div>
                    <div class="sm:col-span-4 flex gap-2">
                        <x-primary-button type="submit">{{ $editingId ? 'Salvar alterações' : 'Adicionar categoria' }}</x-primary-button>
                        @if($editingId)
                            <x-secondary-button type="button" wire:click="cancelEdit">Cancelar</x-secondary-button>
                        @endif
                    </div>
                </form>
            </div>

            <div class="bg-white shadow-sm rounded-lg p-6">
                <button type="button" wire:click="$toggle('showRuleManager')" class="flex items-center justify-between w-full text-left">
                    <div>
                        <h3 class="font-semibold text-gray-800">🤖 Regras automáticas de categorização</h3>
                        <p class="text-sm text-gray-500 mt-1">Categorize transações automaticamente quando a descrição contiver uma palavra-chave.</p>
                    </div>
                    <span class="text-sm text-indigo-600">{{ $showRuleManager ? 'Fechar' : 'Gerenciar' }}</span>
                </button>

                @if($showRuleManager)
                    <div class="mt-4 border-t border-gray-100 pt-4">
                        <div class="space-y-2 mb-4">
                            @forelse ($categoryRules as $rule)
                                <div class="flex items-center justify-between text-sm py-1">
                                    <span class="text-gray-700">
                                        Se a descrição contém <span class="font-medium">"{{ $rule->keyword }}"</span>
                                        → <span class="inline-flex items-center gap-1.5">
                                            <span class="w-2 h-2 rounded-full" style="background-color: {{ $rule->category?->color }}"></span>
                                            {{ $rule->category?->name }}
                                        </span>
                                    </span>
                                    <button type="button" x-on:click="Swal.fire({icon:'warning',title:'Excluir esta regra?',showCancelButton:true,confirmButtonText:'Excluir',cancelButtonText:'Cancelar',confirmButtonColor:'#dc2626'}).then((r) => r.isConfirmed && $wire.deleteRule({{ $rule->id }}))" class="text-red-600 hover:underline">Excluir</button>
                                </div>
                            @empty
                                <p class="text-sm text-gray-500">Nenhuma regra cadastrada ainda.</p>
                            @endforelse
                        </div>

                        <form wire:submit="addRule" class="flex flex-wrap items-end gap-3">
                            <div class="flex-1 min-w-[180px]">
                                <x-input-label for="ruleKeyword" value="Palavra-chave" />
                                <x-text-input id="ruleKeyword" type="text" class="mt-1 block w-full" wire:model="ruleKeyword" placeholder="Ex: Uber" />
                                <x-input-error :messages="$errors->get('ruleKeyword')" class="mt-1" />
                            </div>
                            <div class="flex-1 min-w-[180px]">
                                <x-input-label for="ruleCategoryId" value="Categoria" />
                                <select id="ruleCategoryId" wire:model="ruleCategoryId" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                                    <option value="">Selecione</option>
                                    @foreach ($categories as $category)
                                        <option value="{{ $category->id }}">{{ $category->name }} ({{ $category->type }})</option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('ruleCategoryId')" class="mt-1" />
                            </div>
                            <x-secondary-button type="submit">Adicionar regra</x-secondary-button>
                        </form>
                    </div>
                @endif
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                <div class="bg-white shadow-sm rounded-lg p-6">
                    <h3 class="font-semibold text-gray-800 mb-4">Despesas</h3>
                    <div class="space-y-2">
                        @foreach ($categories->where('type', 'despesa') as $category)
                            <div class="flex items-center justify-between py-1">
                                <span class="flex items-center gap-2 text-sm text-gray-800">
                                    <span class="w-2.5 h-2.5 rounded-full" style="background-color: {{ $category->color }}"></span>
                                    {{ $category->name }}
                                </span>
                                <span class="space-x-2 text-sm">
                                    <button wire:click="edit({{ $category->id }})" class="text-indigo-600 hover:underline">Editar</button>
                                    <button type="button" x-on:click="Swal.fire({icon:'warning',title:'Excluir categoria?',showCancelButton:true,confirmButtonText:'Excluir',cancelButtonText:'Cancelar',confirmButtonColor:'#dc2626'}).then((r) => r.isConfirmed && $wire.delete({{ $category->id }}))" class="text-red-600 hover:underline">Excluir</button>
                                </span>
                            </div>
                        @endforeach
                    </div>
                </div>
                <div class="bg-white shadow-sm rounded-lg p-6">
                    <h3 class="font-semibold text-gray-800 mb-4">Receitas</h3>
                    <div class="space-y-2">
                        @foreach ($categories->where('type', 'receita') as $category)
                            <div class="flex items-center justify-between py-1">
                                <span class="flex items-center gap-2 text-sm text-gray-800">
                                    <span class="w-2.5 h-2.5 rounded-full" style="background-color: {{ $category->color }}"></span>
                                    {{ $category->name }}
                                </span>
                                <span class="space-x-2 text-sm">
                                    <button wire:click="edit({{ $category->id }})" class="text-indigo-600 hover:underline">Editar</button>
                                    <button type="button" x-on:click="Swal.fire({icon:'warning',title:'Excluir categoria?',showCancelButton:true,confirmButtonText:'Excluir',cancelButtonText:'Cancelar',confirmButtonColor:'#dc2626'}).then((r) => r.isConfirmed && $wire.delete({{ $category->id }}))" class="text-red-600 hover:underline">Excluir</button>
                                </span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
