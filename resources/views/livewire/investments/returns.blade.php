<?php

use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    /**
     * Simple approximation of a period's return: total gain minus net money
     * that flowed in/out during the period, divided by the balance at the
     * start of the period. This is not a real TWR/IRR — it doesn't compound
     * sub-periods or weight contributions by when in the period they landed —
     * but it's a reasonable "how did my portfolio do" estimate without
     * needing a benchmark data source.
     */
    private function periodReturn(float $startCurrent, float $endCurrent, float $netContribution): ?float
    {
        if ($startCurrent <= 0) {
            return null;
        }

        $gain = $endCurrent - $startCurrent - $netContribution;

        return ($gain / $startCurrent) * 100;
    }

    private function netContributionsBetween($user, $from, $to): float
    {
        $transactions = $user->investmentTransactions()
            ->where('date', '>', $from)
            ->where('date', '<=', $to)
            ->get();

        $in = $transactions->whereIn('type', ['aporte', 'compra'])->sum('amount');
        $out = $transactions->whereIn('type', ['resgate', 'venda'])->sum('amount');

        return (float) $in - (float) $out;
    }

    public function with(): array
    {
        $user = auth()->user();
        $allInvestments = $user->investments()->get();

        $totalInvested = (float) $allInvestments->sum('invested_amount');
        $totalCurrent = (float) $allInvestments->sum('current_amount');
        $totalGain = $totalCurrent - $totalInvested;
        $totalGainPercent = $totalInvested > 0 ? ($totalGain / $totalInvested) * 100 : 0.0;

        $withNetGain = $allInvestments->filter(fn ($investment) => $investment->net_gain !== null);
        $totalNetGain = (float) $withNetGain->sum('net_gain');
        $totalNetGainInvested = (float) $withNetGain->sum('invested_amount');
        $hasNetGainData = $withNetGain->isNotEmpty();

        $snapshots = $user->portfolioSnapshots()->orderBy('date')->get();

        $monthly = $snapshots->groupBy(fn ($s) => $s->date->format('Y-m'))->map->last()->values();

        $monthlyReturns = collect();
        for ($i = 1; $i < $monthly->count(); $i++) {
            $previous = $monthly[$i - 1];
            $current = $monthly[$i];

            $net = $this->netContributionsBetween($user, $previous->date, $current->date);
            $return = $this->periodReturn((float) $previous->total_current, (float) $current->total_current, $net);

            $monthlyReturns->push([
                'label' => ucfirst($current->date->translatedFormat('M/Y')),
                'return' => $return,
                'current' => (float) $current->total_current,
            ]);
        }
        $monthlyReturns = $monthlyReturns->reverse()->values();

        $currentMonthReturn = $monthlyReturns->first()['return'] ?? null;

        $yearStart = $snapshots->first(fn ($s) => $s->date->isSameYear(now()));
        $yearReturn = null;
        if ($yearStart && $snapshots->last()) {
            $net = $this->netContributionsBetween($user, $yearStart->date->copy()->subDay(), $snapshots->last()->date);
            $yearReturn = $this->periodReturn((float) $yearStart->total_current, (float) $snapshots->last()->total_current, $net);
        }

        return [
            'totalGain' => $totalGain,
            'totalGainPercent' => $totalGainPercent,
            'hasNetGainData' => $hasNetGainData,
            'totalNetGain' => $totalNetGain,
            'totalNetGainPercent' => $totalNetGainInvested > 0 ? ($totalNetGain / $totalNetGainInvested) * 100 : 0.0,
            'currentMonthReturn' => $currentMonthReturn,
            'yearReturn' => $yearReturn,
            'monthlyReturns' => $monthlyReturns,
        ];
    }
}; ?>

    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Investimentos') }}</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @include('livewire.investments.partials.tabs')

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="bg-white shadow-sm rounded-lg p-5">
                    <p class="text-sm text-gray-500">Rentabilidade total (bruta)</p>
                    <p class="mt-1 text-xl font-bold {{ $totalGain >= 0 ? 'text-green-600' : 'text-red-600' }}">
                        {{ $totalGain >= 0 ? '+' : '' }}{{ number_format($totalGainPercent, 2, ',', '.') }}%
                    </p>
                    <p class="text-xs text-gray-400 mt-0.5">R$ {{ number_format($totalGain, 2, ',', '.') }}</p>
                </div>
                <div class="bg-white shadow-sm rounded-lg p-5">
                    <p class="text-sm text-gray-500">Rentabilidade total (líquida)</p>
                    @if($hasNetGainData)
                        <p class="mt-1 text-xl font-bold {{ $totalNetGain >= 0 ? 'text-green-600' : 'text-red-600' }}">
                            {{ $totalNetGain >= 0 ? '+' : '' }}{{ number_format($totalNetGainPercent, 2, ',', '.') }}%
                        </p>
                        <p class="text-xs text-gray-400 mt-0.5" title="Estimativa com base na alíquota de IR configurada em cada tipo de investimento.">R$ {{ number_format($totalNetGain, 2, ',', '.') }} est.</p>
                    @else
                        <p class="mt-1 text-xl font-bold text-gray-400">—</p>
                    @endif
                </div>
                <div class="bg-white shadow-sm rounded-lg p-5">
                    <p class="text-sm text-gray-500">No mês</p>
                    @if($currentMonthReturn !== null)
                        <p class="mt-1 text-xl font-bold {{ $currentMonthReturn >= 0 ? 'text-green-600' : 'text-red-600' }}">
                            {{ $currentMonthReturn >= 0 ? '+' : '' }}{{ number_format($currentMonthReturn, 2, ',', '.') }}%
                        </p>
                    @else
                        <p class="mt-1 text-xl font-bold text-gray-400">—</p>
                    @endif
                </div>
                <div class="bg-white shadow-sm rounded-lg p-5">
                    <p class="text-sm text-gray-500">No ano</p>
                    @if($yearReturn !== null)
                        <p class="mt-1 text-xl font-bold {{ $yearReturn >= 0 ? 'text-green-600' : 'text-red-600' }}">
                            {{ $yearReturn >= 0 ? '+' : '' }}{{ number_format($yearReturn, 2, ',', '.') }}%
                        </p>
                    @else
                        <p class="mt-1 text-xl font-bold text-gray-400">—</p>
                    @endif
                </div>
            </div>

            <p class="text-xs text-gray-400">
                As rentabilidades mensal/anual são estimativas simplificadas (ganho do período descontando aportes e resgates líquidos, dividido pelo saldo inicial) — não é uma Taxa Interna de Retorno (TIR) nem Time-Weighted Return real, e não há comparação com CDI/Ibovespa por falta de fonte de dados histórica.
            </p>

            <div class="bg-white shadow-sm rounded-lg overflow-hidden">
                <div class="px-6 py-3 bg-gray-50 border-b border-gray-100">
                    <h3 class="font-semibold text-gray-800">Rentabilidade mensal</h3>
                </div>
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Mês</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Patrimônio no fim do mês</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Rentabilidade estimada</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse ($monthlyReturns as $row)
                            <tr>
                                <td class="px-6 py-4 text-sm text-gray-800">{{ $row['label'] }}</td>
                                <td class="px-6 py-4 text-sm text-gray-900">R$ {{ number_format($row['current'], 2, ',', '.') }}</td>
                                <td class="px-6 py-4 text-sm font-medium {{ $row['return'] === null ? 'text-gray-400' : ($row['return'] >= 0 ? 'text-green-600' : 'text-red-600') }}">
                                    {{ $row['return'] !== null ? ($row['return'] >= 0 ? '+' : '').number_format($row['return'], 2, ',', '.').'%' : '—' }}
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="px-6 py-6 text-center text-sm text-gray-500">Ainda não há histórico mensal suficiente. O patrimônio é registrado automaticamente todos os dias úteis.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
