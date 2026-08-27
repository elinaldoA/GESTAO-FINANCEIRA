<?php

use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    #[Url(as: 'periodo')]
    public string $range = '6m';

    public function setRange(string $range): void
    {
        $this->range = $range;
    }

    public function with(): array
    {
        $user = auth()->user();

        $since = match ($this->range) {
            '3m' => now()->subMonths(3),
            '6m' => now()->subMonths(6),
            '1y' => now()->subYear(),
            default => null,
        };

        $snapshots = $user->portfolioSnapshots()
            ->when($since, fn ($query) => $query->where('date', '>=', $since))
            ->orderBy('date')
            ->get();

        $first = $snapshots->first();
        $last = $snapshots->last();

        $periodChange = $first && $last
            ? (float) $last->total_current - (float) $first->total_current
            : null;

        $contributionsSince = now()->subMonths(11)->startOfMonth();
        $monthlyContributions = $user->investmentTransactions()
            ->whereIn('type', ['aporte', 'compra'])
            ->where('date', '>=', $contributionsSince)
            ->get()
            ->groupBy(fn ($transaction) => $transaction->date->format('Y-m'))
            ->map(fn ($group) => (float) $group->sum('amount'));

        $months = collect(range(0, 11))->map(fn ($i) => now()->subMonths(11 - $i)->startOfMonth());

        return [
            'snapshots' => $snapshots->reverse()->values(),
            'periodChange' => $periodChange,
            'currentTotal' => $last ? (float) $last->total_current : 0.0,
            'investedTotal' => $last ? (float) $last->total_invested : 0.0,
            'historyChart' => [
                'labels' => $snapshots->map(fn ($row) => $row->date->format('d/m/y'))->all(),
                'invested' => $snapshots->map(fn ($row) => (float) $row->total_invested)->all(),
                'current' => $snapshots->map(fn ($row) => (float) $row->total_current)->all(),
            ],
            'contributionsChart' => [
                'labels' => $months->map(fn (Carbon $month) => ucfirst($month->translatedFormat('M/y')))->all(),
                'values' => $months->map(fn (Carbon $month) => $monthlyContributions->get($month->format('Y-m'), 0.0))->all(),
                'label' => 'Aportes',
                'colors' => '#22c55e',
            ],
        ];
    }
}; ?>

    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Investimentos') }}</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @include('livewire.investments.partials.tabs')

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="bg-white shadow-sm rounded-lg p-5">
                    <p class="text-sm text-gray-500">Patrimônio atual</p>
                    <p class="mt-1 text-2xl font-bold text-gray-900">R$ {{ number_format($currentTotal, 2, ',', '.') }}</p>
                </div>
                <div class="bg-white shadow-sm rounded-lg p-5">
                    <p class="text-sm text-gray-500">Total investido</p>
                    <p class="mt-1 text-2xl font-bold text-gray-900">R$ {{ number_format($investedTotal, 2, ',', '.') }}</p>
                </div>
                <div class="bg-white shadow-sm rounded-lg p-5">
                    <p class="text-sm text-gray-500">Variação no período</p>
                    @if($periodChange !== null)
                        <p class="mt-1 text-2xl font-bold {{ $periodChange >= 0 ? 'text-green-600' : 'text-red-600' }}">
                            {{ $periodChange >= 0 ? '+' : '' }}R$ {{ number_format($periodChange, 2, ',', '.') }}
                        </p>
                    @else
                        <p class="mt-1 text-2xl font-bold text-gray-400">—</p>
                    @endif
                </div>
            </div>

            <div class="bg-white shadow-sm rounded-lg p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="font-semibold text-gray-800">Evolução do patrimônio</h3>
                    <div class="flex gap-1">
                        @foreach (['3m' => '3M', '6m' => '6M', '1y' => '1A', 'all' => 'Tudo'] as $value => $label)
                            <button type="button" wire:click="setRange('{{ $value }}')" class="px-2.5 py-1 rounded text-xs font-medium {{ $range === $value ? 'bg-indigo-600 text-white' : 'text-gray-500 hover:bg-gray-100' }}">{{ $label }}</button>
                        @endforeach
                    </div>
                </div>
                @if(count($historyChart['labels']) >= 2)
                    <div class="h-64" wire:ignore
                        x-data="lineChart(@js($historyChart))"
                        x-init="init($el.querySelector('canvas'))"
                    >
                        <canvas></canvas>
                    </div>
                @else
                    <p class="text-sm text-gray-500">Ainda não há histórico suficiente neste período. O patrimônio é registrado automaticamente todos os dias úteis.</p>
                @endif
            </div>

            <div class="bg-white shadow-sm rounded-lg p-6">
                <h3 class="font-semibold text-gray-800 mb-4">Aportes por mês</h3>
                @if(collect($contributionsChart['values'])->sum() > 0)
                    <div class="h-56" wire:ignore
                        x-data="barChart(@js($contributionsChart))"
                        x-init="init($el.querySelector('canvas'))"
                    >
                        <canvas></canvas>
                    </div>
                @else
                    <p class="text-sm text-gray-500">Nenhum aporte registrado nos últimos 12 meses.</p>
                @endif
            </div>

            <div class="bg-white shadow-sm rounded-lg overflow-hidden">
                <div class="px-6 py-3 bg-gray-50 border-b border-gray-100">
                    <h3 class="font-semibold text-gray-800">Histórico diário</h3>
                </div>
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Data</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Investido</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Patrimônio</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Rentabilidade</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse ($snapshots->take(30) as $snapshot)
                            @php $gain = (float) $snapshot->total_current - (float) $snapshot->total_invested; @endphp
                            <tr>
                                <td class="px-6 py-4 text-sm text-gray-800">{{ $snapshot->date->format('d/m/Y') }}</td>
                                <td class="px-6 py-4 text-sm text-gray-500">R$ {{ number_format($snapshot->total_invested, 2, ',', '.') }}</td>
                                <td class="px-6 py-4 text-sm text-gray-900">R$ {{ number_format($snapshot->total_current, 2, ',', '.') }}</td>
                                <td class="px-6 py-4 text-sm font-medium {{ $gain >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                    {{ $gain >= 0 ? '+' : '' }}R$ {{ number_format($gain, 2, ',', '.') }}
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-6 py-6 text-center text-sm text-gray-500">Nenhum registro de patrimônio neste período ainda.</td></tr>
                        @endforelse
                    </tbody>
                </table>
                @if($snapshots->count() > 30)
                    <p class="px-6 py-3 text-xs text-gray-400">Exibindo os 30 registros mais recentes deste período.</p>
                @endif
            </div>
        </div>
    </div>
