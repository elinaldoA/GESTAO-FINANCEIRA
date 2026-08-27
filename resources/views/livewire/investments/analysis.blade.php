<?php

use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    private const CONCENTRATION_ALERT_THRESHOLD = 25.0;

    public function with(): array
    {
        $user = auth()->user();
        $investments = $user->investments()->with('investmentType')->get();

        $totalCurrent = (float) $investments->sum('current_amount');

        $withIndicators = $investments->filter(fn ($i) => $i->ticker && (
            $i->price_earnings !== null || $i->price_to_book !== null || $i->dividend_yield !== null
        ))->sortBy('ticker')->values();

        $byType = $investments
            ->groupBy(fn ($i) => $i->investmentType?->name ?? 'Sem tipo')
            ->map(fn ($group, $label) => [
                'label' => $label,
                'color' => $group->first()->investmentType?->color ?? '#94a3b8',
                'value' => (float) $group->sum('current_amount'),
            ])
            ->filter(fn ($row) => $row['value'] > 0)
            ->sortByDesc('value')
            ->values();

        $byAsset = $investments
            ->filter(fn ($i) => (float) $i->current_amount > 0)
            ->sortByDesc('current_amount')
            ->take(10)
            ->map(fn ($i) => [
                'label' => $i->name,
                'color' => $i->color,
                'value' => (float) $i->current_amount,
            ])
            ->values();

        $byBroker = $investments
            ->filter(fn ($i) => (float) $i->current_amount > 0)
            ->groupBy(fn ($i) => $i->broker ?: 'Sem corretora')
            ->map(fn ($group, $label) => ['label' => $label, 'value' => (float) $group->sum('current_amount')])
            ->sortByDesc('value')
            ->values();

        $withPercent = fn ($rows) => $rows->map(fn ($row) => $row + [
            'percent' => $totalCurrent > 0 ? ($row['value'] / $totalCurrent) * 100 : 0.0,
        ]);

        return [
            'withIndicators' => $withIndicators,
            'byType' => $withPercent($byType),
            'byAsset' => $withPercent($byAsset),
            'byBroker' => $withPercent($byBroker),
            'threshold' => self::CONCENTRATION_ALERT_THRESHOLD,
            'totalCurrent' => $totalCurrent,
        ];
    }
}; ?>

    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Investimentos') }}</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @include('livewire.investments.partials.tabs')

            @php $overConcentrated = $byAsset->filter(fn ($row) => $row['percent'] > $threshold); @endphp
            @if($overConcentrated->isNotEmpty())
                <div class="flex items-start gap-2 text-sm text-amber-700 bg-amber-50 border border-amber-100 rounded-lg px-4 py-3">
                    <span class="mt-0.5">⚠️</span>
                    <div>
                        <p class="font-medium">Concentração alta</p>
                        <p class="text-xs mt-0.5">
                            {{ $overConcentrated->map(fn ($row) => $row['label'].' ('.number_format($row['percent'], 1, ',', '.').'%)')->join(', ') }}
                            {{ $overConcentrated->count() > 1 ? 'ultrapassam' : 'ultrapassa' }} {{ number_format($threshold, 0, ',', '.') }}% da carteira.
                        </p>
                    </div>
                </div>
            @endif

            <div class="bg-white shadow-sm rounded-lg overflow-hidden">
                <div class="px-6 py-3 bg-gray-50 border-b border-gray-100">
                    <h3 class="font-semibold text-gray-800">Indicadores fundamentalistas</h3>
                    <p class="text-xs text-gray-500 mt-0.5">Disponíveis para ativos com ticker acompanhado por cotação automática.</p>
                </div>
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Ativo</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">P/L</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">P/VP</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">DY</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Atualizado</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse ($withIndicators as $investment)
                            <tr>
                                <td class="px-6 py-4 text-sm text-gray-800">
                                    <a href="{{ route('investments.show', $investment) }}" wire:navigate class="hover:underline hover:text-indigo-600">{{ $investment->name }}</a>
                                    <span class="text-gray-400">{{ $investment->ticker }}</span>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-700">{{ $investment->price_earnings !== null ? number_format($investment->price_earnings, 2, ',', '.') : '—' }}</td>
                                <td class="px-6 py-4 text-sm text-gray-700">{{ $investment->price_to_book !== null ? number_format($investment->price_to_book, 2, ',', '.') : '—' }}</td>
                                <td class="px-6 py-4 text-sm text-gray-700">{{ $investment->dividend_yield !== null ? number_format($investment->dividend_yield, 2, ',', '.').'%' : '—' }}</td>
                                <td class="px-6 py-4 text-sm text-gray-400">{{ $investment->quote_updated_at?->diffForHumans() ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-6 py-6 text-center text-sm text-gray-500">Nenhum ativo com indicadores disponíveis ainda.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div class="bg-white shadow-sm rounded-lg p-6">
                    <h3 class="font-semibold text-gray-800 mb-4">Por classe de ativo</h3>
                    <div class="space-y-3">
                        @forelse ($byType as $row)
                            <div>
                                <div class="flex justify-between text-sm mb-1">
                                    <span class="flex items-center gap-2 text-gray-700">
                                        <span class="w-2.5 h-2.5 rounded-full" style="background-color: {{ $row['color'] }}"></span>
                                        {{ $row['label'] }}
                                    </span>
                                    <span class="text-gray-500">{{ number_format($row['percent'], 1, ',', '.') }}%</span>
                                </div>
                                <div class="w-full bg-gray-100 rounded-full h-2">
                                    <div class="h-2 rounded-full" style="width: {{ $row['percent'] }}%; background-color: {{ $row['color'] }}"></div>
                                </div>
                            </div>
                        @empty
                            <p class="text-sm text-gray-500">Nenhum investimento cadastrado ainda.</p>
                        @endforelse
                    </div>
                </div>

                <div class="bg-white shadow-sm rounded-lg p-6">
                    <h3 class="font-semibold text-gray-800 mb-4">Maiores posições</h3>
                    <div class="space-y-3">
                        @forelse ($byAsset as $row)
                            @php $isOver = $row['percent'] > $threshold; @endphp
                            <div>
                                <div class="flex justify-between text-sm mb-1">
                                    <span class="flex items-center gap-2 text-gray-700">
                                        <span class="w-2.5 h-2.5 rounded-full" style="background-color: {{ $row['color'] }}"></span>
                                        {{ $row['label'] }}
                                    </span>
                                    <span class="{{ $isOver ? 'text-amber-600 font-medium' : 'text-gray-500' }}">{{ number_format($row['percent'], 1, ',', '.') }}%</span>
                                </div>
                                <div class="w-full bg-gray-100 rounded-full h-2">
                                    <div class="h-2 rounded-full {{ $isOver ? 'bg-amber-500' : '' }}" style="width: {{ $row['percent'] }}%; {{ $isOver ? '' : 'background-color: '.$row['color'] }}"></div>
                                </div>
                            </div>
                        @empty
                            <p class="text-sm text-gray-500">Nenhum investimento cadastrado ainda.</p>
                        @endforelse
                    </div>
                </div>

                <div class="bg-white shadow-sm rounded-lg p-6">
                    <h3 class="font-semibold text-gray-800 mb-4">Por corretora</h3>
                    <div class="space-y-3">
                        @forelse ($byBroker as $row)
                            <div>
                                <div class="flex justify-between text-sm mb-1">
                                    <span class="text-gray-700">{{ $row['label'] }}</span>
                                    <span class="text-gray-500">{{ number_format($row['percent'], 1, ',', '.') }}%</span>
                                </div>
                                <div class="w-full bg-gray-100 rounded-full h-2">
                                    <div class="h-2 rounded-full bg-slate-400" style="width: {{ $row['percent'] }}%"></div>
                                </div>
                            </div>
                        @empty
                            <p class="text-sm text-gray-500">Nenhum investimento cadastrado ainda.</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
