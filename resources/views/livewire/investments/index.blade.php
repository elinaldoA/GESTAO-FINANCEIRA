<?php

use App\Models\Investment;
use App\Services\StockQuoteService;
use App\Support\Money;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public function mount(): void
    {
        if (auth()->user()->investmentTypes()->count() === 0) {
            foreach ([
                ['name' => 'Renda fixa', 'color' => '#3b82f6', 'tax_rate' => 15],
                ['name' => 'Ações', 'color' => '#ef4444', 'tax_rate' => 15],
                ['name' => 'Fundos imobiliários', 'color' => '#f59e0b', 'tax_rate' => 20],
                ['name' => 'Tesouro Direto', 'color' => '#22c55e', 'tax_rate' => 15],
                ['name' => 'Criptomoeda', 'color' => '#a855f7', 'tax_rate' => 15],
                ['name' => 'Previdência', 'color' => '#06b6d4', 'tax_rate' => null],
                ['name' => 'Outro', 'color' => '#64748b', 'tax_rate' => null],
            ] as $type) {
                auth()->user()->investmentTypes()->create($type);
            }
        }
    }

    public function refreshAllQuotes(StockQuoteService $quotes): void
    {
        $updated = $this->updateTrackedQuotes($quotes);

        $this->dispatch('notify', type: $updated > 0 ? 'success' : 'warning', message: $updated > 0
            ? "{$updated} cotação(ões) atualizada(s)."
            : 'Nenhum investimento com ticker cadastrado para atualizar.');
    }

    /**
     * Silently refreshes tracked quotes on a timer (wire:poll) while the page is open,
     * so prices update without the user having to click anything. No toast here —
     * a notification every 60s would just be noise.
     */
    public function pollQuotes(StockQuoteService $quotes): void
    {
        $this->updateTrackedQuotes($quotes);
    }

    private function updateTrackedQuotes(StockQuoteService $quotes): int
    {
        $investments = auth()->user()->investments()
            ->whereNotNull('ticker')->whereNotNull('quantity')->where('is_active', true)->get();

        $updated = 0;

        foreach ($investments as $investment) {
            $quote = $quotes->fetchQuote($investment->ticker);

            if ($quote === null) {
                $investment->markQuoteFailed();

                continue;
            }

            $investment->applyQuote($quote);

            $updated++;
        }

        return $updated;
    }

    public function with(): array
    {
        $user = auth()->user();
        $allInvestments = $user->investments()->with('investmentType')->get();

        $totalInvested = (float) $allInvestments->sum('invested_amount');
        $totalCurrent = (float) $allInvestments->sum('current_amount');

        $withNetGain = $allInvestments->filter(fn ($investment) => $investment->net_gain !== null);
        $totalNetGain = (float) $withNetGain->sum('net_gain');
        $totalNetGainInvested = (float) $withNetGain->sum('invested_amount');
        $hasNetGainData = $withNetGain->isNotEmpty();
        $netGainIsPartial = $hasNetGainData && $withNetGain->count() < $allInvestments->count();

        $hasFailingQuotes = $allInvestments->whereNotNull('ticker')->contains(fn ($investment) => $investment->quote_status === 'failing');

        $investmentTypes = $user->investmentTypes()->orderBy('name')->get();

        $allocation = $investmentTypes
            ->map(function ($type) use ($allInvestments) {
                $current = (float) $allInvestments->where('investment_type_id', $type->id)->sum('current_amount');

                return ['type' => $type, 'current' => $current];
            })
            ->filter(fn ($row) => $row['current'] > 0)
            ->sortByDesc('current')
            ->values();

        $topPositions = $allInvestments->sortByDesc('current_amount')->take(5)->values();

        $history = $user->portfolioSnapshots()->orderBy('date')->limit(90)->get();

        // Order here is the order they appear in the ticker banner. IBOV needs
        // a BRAPI_TOKEN (see StockQuoteService::fetchIbovespa) — the rest are
        // all free via AwesomeAPI and need no token.
        $indexDefs = [
            'ibovespa' => ['ticker' => 'IBOV', 'name' => 'Ibovespa', 'unit' => 'points', 'fetch' => 'fetchIbovespa'],
            'usd' => ['ticker' => 'USD', 'name' => 'Dólar', 'unit' => 'currency', 'fetch' => 'fetchUsdBrl'],
            'eur' => ['ticker' => 'EUR', 'name' => 'Euro', 'unit' => 'currency', 'fetch' => 'fetchEurBrl'],
            'gbp' => ['ticker' => 'GBP', 'name' => 'Libra esterlina', 'unit' => 'currency', 'fetch' => 'fetchGbpBrl'],
            'ars' => ['ticker' => 'ARS', 'name' => 'Peso argentino', 'unit' => 'currency', 'fetch' => 'fetchArsBrl'],
            'btc' => ['ticker' => 'BTC', 'name' => 'Bitcoin', 'unit' => 'currency', 'fetch' => 'fetchBtcBrl'],
            'eth' => ['ticker' => 'ETH', 'name' => 'Ethereum', 'unit' => 'currency', 'fetch' => 'fetchEthBrl'],
            'xrp' => ['ticker' => 'XRP', 'name' => 'XRP', 'unit' => 'currency', 'fetch' => 'fetchXrpBrl'],
        ];

        $marketIndices = Cache::remember('market:indices', 600, function () use ($indexDefs) {
            $quotes = app(StockQuoteService::class);

            return collect($indexDefs)->map(fn ($def) => $quotes->{$def['fetch']}())->all();
        });

        $formatPrice = fn (float $price, string $unit) => $unit === 'points'
            ? number_format($price, 0, ',', '.').' pts'
            : 'R$ '.Money::trimmed($price);

        $quotesCarousel = collect();

        foreach ($indexDefs as $key => $def) {
            if (! $marketIndices[$key]) {
                continue;
            }

            $quotesCarousel->push([
                'ticker' => $def['ticker'],
                'name' => $def['name'],
                'price' => $marketIndices[$key]['price'],
                'priceDisplay' => $formatPrice($marketIndices[$key]['price'], $def['unit']),
                'change' => $marketIndices[$key]['changePercent'],
            ]);
        }

        $quotesCarousel = $quotesCarousel->concat(
            $allInvestments
                ->whereNotNull('ticker')
                ->whereNotNull('current_price')
                ->unique('ticker')
                ->sortBy('ticker')
                ->map(fn ($investment) => [
                    'ticker' => $investment->ticker,
                    'name' => $investment->name,
                    'price' => (float) $investment->current_price,
                    'priceDisplay' => $formatPrice((float) $investment->current_price, 'currency'),
                    'change' => $investment->day_change_percent !== null ? (float) $investment->day_change_percent : null,
                    'status' => $investment->quote_status,
                ])
                ->values()
        );

        return [
            'investmentTypes' => $investmentTypes,
            'totalInvested' => $totalInvested,
            'totalCurrent' => $totalCurrent,
            'totalAssets' => $allInvestments->count(),
            'totalDividends' => (float) $user->dividends()->sum('amount'),
            'hasTrackedInvestments' => $allInvestments->whereNotNull('ticker')->isNotEmpty(),
            'hasFailingQuotes' => $hasFailingQuotes,
            'totalNetGain' => $totalNetGain,
            'totalNetGainPercent' => $totalNetGainInvested > 0 ? ($totalNetGain / $totalNetGainInvested) * 100 : 0.0,
            'hasNetGainData' => $hasNetGainData,
            'netGainIsPartial' => $netGainIsPartial,
            'marketIndices' => $marketIndices,
            'topPositions' => $topPositions,
            'allocationChart' => [
                'labels' => $allocation->map(fn ($row) => $row['type']->name)->all(),
                'colors' => $allocation->map(fn ($row) => $row['type']->color)->all(),
                'totals' => $allocation->map(fn ($row) => $row['current'])->all(),
            ],
            'quotesCarousel' => $quotesCarousel,
            'historyChart' => [
                'labels' => $history->map(fn ($row) => $row->date->format('d/m'))->all(),
                'invested' => $history->map(fn ($row) => (float) $row->total_invested)->all(),
                'current' => $history->map(fn ($row) => (float) $row->total_current)->all(),
            ],
        ];
    }
}; ?>

    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Investimentos') }}</h2>
    </x-slot>

    <div class="py-8" @if($hasTrackedInvestments) wire:poll.60s="pollQuotes" @endif>
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @include('livewire.investments.partials.tabs')

            @if($hasTrackedInvestments)
                @if($hasFailingQuotes)
                    <div class="flex items-center gap-1.5 text-xs text-amber-600 bg-amber-50 border border-amber-100 rounded-md px-3 py-2">
                        <span class="relative flex h-2 w-2 shrink-0">
                            <span class="relative inline-flex rounded-full h-2 w-2 bg-amber-500"></span>
                        </span>
                        Algumas cotações não puderam ser atualizadas agora — exibindo os últimos valores conhecidos.
                    </div>
                @else
                    <div class="flex items-center gap-1.5 text-xs text-gray-400">
                        <span class="relative flex h-2 w-2">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-2 w-2 bg-green-500"></span>
                        </span>
                        Cotações atualizando automaticamente a cada minuto
                    </div>
                @endif
            @endif

            @if($quotesCarousel->isNotEmpty())
                <div class="relative bg-navy-950 rounded-lg overflow-hidden group">
                    <div
                        class="flex w-max animate-marquee group-hover:[animation-play-state:paused] motion-reduce:animate-none"
                        style="animation-duration: {{ max(20, $quotesCarousel->count() * 4) }}s"
                    >
                        @for ($lap = 0; $lap < 2; $lap++)
                            <div class="flex items-stretch shrink-0" aria-hidden="{{ $lap === 1 ? 'true' : 'false' }}">
                                @foreach ($quotesCarousel as $quote)
                                    @php $up = $quote['change'] !== null && $quote['change'] >= 0; @endphp
                                    <div class="flex items-center gap-2 px-5 py-3 border-r border-white/10 shrink-0 whitespace-nowrap" title="{{ $quote['name'] }}">
                                        <span class="text-sm font-bold text-white">{{ $quote['ticker'] }}</span>
                                        <span class="text-sm text-slate-300">{{ $quote['priceDisplay'] }}</span>
                                        @if($quote['change'] !== null)
                                            <span class="inline-flex items-center gap-0.5 text-xs font-semibold {{ $up ? 'text-green-400' : 'text-red-400' }}">
                                                {{ $up ? '▲' : '▼' }} {{ number_format(abs($quote['change']), 2, ',', '.') }}%
                                            </span>
                                        @endif
                                        @if(($quote['status'] ?? null) === 'failing')
                                            <span class="w-1.5 h-1.5 rounded-full bg-amber-400" title="Falha ao atualizar — exibindo último valor conhecido"></span>
                                        @elseif(($quote['status'] ?? null) === 'stale')
                                            <span class="w-1.5 h-1.5 rounded-full bg-slate-500" title="Cotação desatualizada"></span>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @endfor
                    </div>
                </div>
            @endif

            @php $totalGain = $totalCurrent - $totalInvested; @endphp
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
                <div class="bg-white shadow-sm rounded-lg p-5">
                    <p class="text-sm text-gray-500">Patrimônio</p>
                    <p class="mt-1 text-2xl font-bold text-gray-900">R$ {{ number_format($totalCurrent, 2, ',', '.') }}</p>
                </div>
                <div class="bg-white shadow-sm rounded-lg p-5">
                    <p class="text-sm text-gray-500">Total investido</p>
                    <p class="mt-1 text-2xl font-bold text-gray-900">R$ {{ number_format($totalInvested, 2, ',', '.') }}</p>
                </div>
                <div class="bg-white shadow-sm rounded-lg p-5">
                    <p class="text-sm text-gray-500">Rentabilidade</p>
                    <p class="mt-1 text-2xl font-bold {{ $totalGain >= 0 ? 'text-green-600' : 'text-red-600' }}">
                        {{ $totalGain >= 0 ? '+' : '' }}R$ {{ number_format($totalGain, 2, ',', '.') }}
                        <span class="text-base font-medium">
                            ({{ $totalGain >= 0 ? '+' : '' }}{{ $totalInvested > 0 ? number_format(($totalGain / $totalInvested) * 100, 2, ',', '.') : '0,00' }}%)
                        </span>
                    </p>
                    @if($hasNetGainData)
                        <p class="text-xs text-gray-400 mt-1" title="Estimativa com base na alíquota de IR configurada em cada tipo de investimento. Não considera isenções, day trade ou custos de corretagem.">
                            líquido est.{{ $netGainIsPartial ? ' (parcial)' : '' }}: R$ {{ number_format($totalNetGain, 2, ',', '.') }} ({{ number_format($totalNetGainPercent, 2, ',', '.') }}%)
                        </p>
                    @endif
                </div>
                <div class="bg-white shadow-sm rounded-lg p-5">
                    <p class="text-sm text-gray-500">Proventos recebidos</p>
                    <p class="mt-1 text-2xl font-bold text-green-600">R$ {{ number_format($totalDividends, 2, ',', '.') }}</p>
                </div>
                <div class="bg-white shadow-sm rounded-lg p-5">
                    <p class="text-sm text-gray-500">Ativos</p>
                    <p class="mt-1 text-2xl font-bold text-gray-900">{{ $totalAssets }}</p>
                </div>
            </div>

            @if(count($historyChart['labels']) >= 2)
                <div class="bg-white shadow-sm rounded-lg p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="font-semibold text-gray-800">Evolução do patrimônio</h3>
                        <a href="{{ route('investments.wealth') }}" wire:navigate class="text-xs text-indigo-600 hover:underline">Ver histórico completo &rarr;</a>
                    </div>
                    <div class="h-56" wire:ignore
                        x-data="lineChart(@js($historyChart))"
                        x-init="init($el.querySelector('canvas'))"
                    >
                        <canvas></canvas>
                    </div>
                </div>
            @endif

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div class="bg-white shadow-sm rounded-lg p-6">
                    <h3 class="font-semibold text-gray-800 mb-4">Distribuição da carteira</h3>
                    @if(count($allocationChart['labels']))
                        <div class="h-56" wire:ignore
                            x-data="categoryChart(@js($allocationChart))"
                            x-init="init($el.querySelector('canvas'))"
                        >
                            <canvas></canvas>
                        </div>
                    @else
                        <p class="text-sm text-gray-500">Cadastre um investimento para ver a distribuição da carteira.</p>
                    @endif
                </div>

                <div class="lg:col-span-2 bg-white shadow-sm rounded-lg p-6">
                    <h3 class="font-semibold text-gray-800 mb-4">Por classe de ativo</h3>
                    <div class="space-y-3">
                        @php $typeRows = collect($allocationChart['labels'])->map(fn ($label, $i) => ['label' => $label, 'color' => $allocationChart['colors'][$i], 'value' => $allocationChart['totals'][$i]]); @endphp
                        @forelse ($typeRows as $row)
                            @php $percent = $totalCurrent > 0 ? ($row['value'] / $totalCurrent) * 100 : 0; @endphp
                            <div>
                                <div class="flex justify-between text-sm mb-1">
                                    <span class="flex items-center gap-2 text-gray-700">
                                        <span class="w-2.5 h-2.5 rounded-full" style="background-color: {{ $row['color'] }}"></span>
                                        {{ $row['label'] }}
                                    </span>
                                    <span class="text-gray-500">R$ {{ number_format($row['value'], 2, ',', '.') }} &middot; {{ number_format($percent, 1, ',', '.') }}%</span>
                                </div>
                                <div class="w-full bg-gray-100 rounded-full h-2">
                                    <div class="h-2 rounded-full" style="width: {{ $percent }}%; background-color: {{ $row['color'] }}"></div>
                                </div>
                            </div>
                        @empty
                            <p class="text-sm text-gray-500">Nenhum investimento cadastrado ainda.</p>
                        @endforelse
                    </div>
                </div>
            </div>

            <div class="bg-white shadow-sm rounded-lg overflow-hidden">
                <div class="px-6 py-3 bg-gray-50 border-b border-gray-100 flex items-center justify-between">
                    <h3 class="font-semibold text-gray-800">Principais posições</h3>
                    <a href="{{ route('investments.positions') }}" wire:navigate class="text-xs text-indigo-600 hover:underline">Ver todas as posições &rarr;</a>
                </div>
                <table class="min-w-full divide-y divide-gray-200">
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse ($topPositions as $investment)
                            <tr>
                                <td class="px-6 py-4 text-sm text-gray-800">
                                    <div class="flex items-center gap-2">
                                        <span class="w-2.5 h-2.5 rounded-full" style="background-color: {{ $investment->color }}"></span>
                                        <a href="{{ route('investments.show', $investment) }}" wire:navigate class="hover:underline hover:text-indigo-600">{{ $investment->name }}</a>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500">{{ $investment->investmentType?->name ?? 'Sem tipo' }}</td>
                                <td class="px-6 py-4 text-sm text-gray-900">R$ {{ number_format($investment->current_amount, 2, ',', '.') }}</td>
                                <td class="px-6 py-4 text-sm font-medium {{ $investment->gain >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                    {{ $investment->gain >= 0 ? '+' : '' }}{{ number_format($investment->gain_percent, 2, ',', '.') }}%
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500">
                                    {{ $totalCurrent > 0 ? number_format(((float) $investment->current_amount / $totalCurrent) * 100, 1, ',', '.') : '0,0' }}%
                                </td>
                            </tr>
                        @empty
                            <tr><td class="px-6 py-6 text-center text-sm text-gray-500">Nenhum investimento cadastrado ainda. <a href="{{ route('investments.positions') }}" wire:navigate class="text-indigo-600 hover:underline">Cadastre o primeiro</a>.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
