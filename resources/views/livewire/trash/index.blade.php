<?php

use App\Models\Account;
use App\Models\Budget;
use App\Models\Category;
use App\Models\CreditCard;
use App\Models\Dividend;
use App\Models\Goal;
use App\Models\Investment;
use App\Models\Transaction;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    private const TYPES = [
        'accounts' => ['model' => Account::class, 'label' => 'Contas'],
        'credit-cards' => ['model' => CreditCard::class, 'label' => 'Cartões de crédito'],
        'transactions' => ['model' => Transaction::class, 'label' => 'Transações'],
        'categories' => ['model' => Category::class, 'label' => 'Categorias'],
        'budgets' => ['model' => Budget::class, 'label' => 'Orçamentos'],
        'investments' => ['model' => Investment::class, 'label' => 'Investimentos'],
        'dividends' => ['model' => Dividend::class, 'label' => 'Proventos'],
        'goals' => ['model' => Goal::class, 'label' => 'Metas'],
    ];

    private function resolveModel(string $type)
    {
        abort_unless(array_key_exists($type, self::TYPES), 404);

        return self::TYPES[$type]['model'];
    }

    private function find(string $type, int $id)
    {
        $model = $this->resolveModel($type);

        $item = $model::onlyTrashed()->findOrFail($id);

        abort_unless($item->user_id === auth()->id(), 403);

        return $item;
    }

    public function restore(string $type, int $id): void
    {
        $item = $this->find($type, $id);
        $item->restore();

        $this->dispatch('notify', type: 'success', message: 'Item restaurado com sucesso.');
    }

    public function forceDelete(string $type, int $id): void
    {
        $item = $this->find($type, $id);

        if ($type === 'transactions' && $item->attachment_path) {
            Storage::disk('public')->delete($item->attachment_path);
        }

        $item->forceDelete();

        $this->dispatch('notify', type: 'success', message: 'Item excluído permanentemente.');
    }

    public function labelFor(string $type, $item): string
    {
        return match ($type) {
            'transactions' => $item->description,
            'dividends' => 'R$ '.number_format($item->amount, 2, ',', '.').' — '.$item->date->format('d/m/Y'),
            default => $item->name,
        };
    }

    public function with(): array
    {
        $groups = [];

        foreach (self::TYPES as $type => $meta) {
            $items = $meta['model']::onlyTrashed()
                ->where('user_id', auth()->id())
                ->orderByDesc('deleted_at')
                ->get();

            if ($items->isNotEmpty()) {
                $groups[$type] = [
                    'label' => $meta['label'],
                    'items' => $items,
                ];
            }
        }

        return ['groups' => $groups];
    }
}; ?>

    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Lixeira') }}</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <p class="text-sm text-gray-500">Itens excluídos ficam aqui até serem restaurados ou removidos definitivamente.</p>

            @forelse ($groups as $type => $group)
                <div class="bg-white shadow-sm rounded-lg overflow-hidden">
                    <h3 class="px-6 py-3 bg-gray-50 font-semibold text-gray-800 text-sm">{{ $group['label'] }}</h3>
                    <table class="min-w-full divide-y divide-gray-200">
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach ($group['items'] as $item)
                                <tr>
                                    <td class="px-6 py-4 text-sm text-gray-800">{{ $this->labelFor($type, $item) }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-500">Excluído em {{ $item->deleted_at->format('d/m/Y H:i') }}</td>
                                    <td class="px-6 py-4 text-sm text-right space-x-3">
                                        <button type="button" wire:click="restore('{{ $type }}', {{ $item->id }})" class="text-indigo-600 hover:underline">Restaurar</button>
                                        <button
                                            type="button"
                                            x-on:click="Swal.fire({icon:'warning',title:'Excluir definitivamente?',text:'Esta ação não pode ser desfeita.',showCancelButton:true,confirmButtonText:'Excluir para sempre',cancelButtonText:'Cancelar',confirmButtonColor:'#dc2626'}).then((r) => r.isConfirmed && $wire.forceDelete('{{ $type }}', {{ $item->id }}))"
                                            class="text-red-600 hover:underline"
                                        >Excluir para sempre</button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @empty
                <div class="bg-white shadow-sm rounded-lg p-6 text-center text-sm text-gray-500">A lixeira está vazia.</div>
            @endforelse
        </div>
    </div>
