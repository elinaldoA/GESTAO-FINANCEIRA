<?php

use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public string $month;
    public string $year;

    public bool $customRange = false;
    public string $startDate = '';
    public string $endDate = '';

    public function mount(): void
    {
        $this->month = now()->format('n');
        $this->year = now()->format('Y');
        $this->startDate = now()->startOfMonth()->format('Y-m-d');
        $this->endDate = now()->endOfMonth()->format('Y-m-d');
    }

    private function periodRange(): array
    {
        if ($this->customRange && $this->startDate && $this->endDate) {
            return [Carbon::parse($this->startDate)->startOfDay(), Carbon::parse($this->endDate)->endOfDay()];
        }

        $start = Carbon::create((int) $this->year, (int) $this->month, 1)->startOfMonth();

        return [$start->copy()->startOfDay(), $start->copy()->endOfMonth()->endOfDay()];
    }

    public function exportCsv()
    {
        $user = auth()->user();
        [$start, $end] = $this->periodRange();

        $transactions = $user->transactions()
            ->with(['category', 'account', 'creditCard'])
            ->whereBetween('date', [$start, $end])
            ->orderBy('date')
            ->get();

        return response()->streamDownload(function () use ($transactions) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, ['Data', 'Descrição', 'Tipo', 'Categoria', 'Conta', 'Cartão', 'Valor', 'Pago'], ';');

            foreach ($transactions as $t) {
                fputcsv($handle, [
                    $t->date->format('d/m/Y'),
                    $t->description,
                    ucfirst($t->type),
                    $t->category?->name,
                    $t->account?->name,
                    $t->creditCard?->name,
                    number_format($t->amount, 2, ',', '.'),
                    $t->is_paid ? 'Sim' : 'Não',
                ], ';');
            }

            fclose($handle);
        }, 'relatorio_'.now()->format('Y-m-d_His').'.csv');
    }

    public function with(): array
    {
        $user = auth()->user();
        [$start, $end] = $this->periodRange();

        $expensesByCategory = $user->transactions()
            ->selectRaw('category_id, SUM(amount) as total')
            ->where('type', 'despesa')
            ->whereBetween('date', [$start, $end])
            ->groupBy('category_id')
            ->with('category')
            ->orderByDesc('total')
            ->get();

        $totalExpense = (float) $expensesByCategory->sum('total');

        $periodIncome = (float) $user->transactions()->where('type', 'receita')
            ->whereBetween('date', [$start, $end])->sum('amount');

        $periodExpense = (float) $user->transactions()->where('type', 'despesa')
            ->whereBetween('date', [$start, $end])->sum('amount');

        $monthlyTrend = collect(range(5, 0))->map(function ($i) use ($user) {
            $date = now()->subMonths($i);
            $income = (float) $user->transactions()->where('type', 'receita')
                ->whereYear('date', $date->year)->whereMonth('date', $date->month)->sum('amount');
            $expense = (float) $user->transactions()->where('type', 'despesa')
                ->whereYear('date', $date->year)->whereMonth('date', $date->month)->sum('amount');

            return ['label' => ucfirst($date->translatedFormat('M/y')), 'income' => $income, 'expense' => $expense];
        });

        return [
            'expensesByCategory' => $expensesByCategory,
            'totalExpense' => $totalExpense,
            'periodIncome' => $periodIncome,
            'periodExpense' => $periodExpense,
            'categoryChart' => [
                'labels' => $expensesByCategory->map(fn ($row) => $row->category?->name ?? 'Sem categoria')->all(),
                'colors' => $expensesByCategory->map(fn ($row) => $row->category?->color ?? '#94a3b8')->all(),
                'totals' => $expensesByCategory->pluck('total')->map(fn ($v) => (float) $v)->all(),
            ],
            'trendChart' => [
                'labels' => $monthlyTrend->pluck('label')->all(),
                'income' => $monthlyTrend->pluck('income')->all(),
                'expense' => $monthlyTrend->pluck('expense')->all(),
            ],
        ];
    }
}; ?>

    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Relatórios') }}</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <div class="bg-white shadow-sm rounded-lg p-4 space-y-3">
                <div class="flex flex-wrap items-center gap-2">
                    <button type="button" wire:click="$set('customRange', false)" class="px-3 py-1.5 rounded-md text-sm font-medium {{ ! $customRange ? 'bg-indigo-600 text-white' : 'text-gray-600 hover:bg-gray-100' }}">Mês/Ano</button>
                    <button type="button" wire:click="$set('customRange', true)" class="px-3 py-1.5 rounded-md text-sm font-medium {{ $customRange ? 'bg-indigo-600 text-white' : 'text-gray-600 hover:bg-gray-100' }}">Período personalizado</button>
                    <div class="ms-auto">
                        <x-secondary-button type="button" wire:click="exportCsv">⬇ Exportar CSV</x-secondary-button>
                    </div>
                </div>

                @if(! $customRange)
                    <div class="flex flex-wrap gap-4 items-end">
                        <div>
                            <x-input-label value="Mês" />
                            <select wire:model.live="month" class="mt-1 rounded-md border-gray-300 shadow-sm">
                                @foreach (range(1, 12) as $m)
                                    <option value="{{ $m }}">{{ \Carbon\Carbon::create()->month($m)->translatedFormat('F') }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <x-input-label value="Ano" />
                            <x-text-input type="number" class="mt-1" wire:model.live="year" />
                        </div>
                    </div>
                @else
                    <div class="flex flex-wrap gap-4 items-end">
                        <div>
                            <x-input-label value="De" />
                            <x-text-input type="date" class="mt-1" wire:model.live="startDate" />
                        </div>
                        <div>
                            <x-input-label value="Até" />
                            <x-text-input type="date" class="mt-1" wire:model.live="endDate" />
                        </div>
                    </div>
                @endif
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="bg-white shadow-sm rounded-lg p-5">
                    <p class="text-sm text-gray-500">Receitas do período</p>
                    <p class="mt-1 text-2xl font-bold text-green-600">R$ {{ number_format($periodIncome, 2, ',', '.') }}</p>
                </div>
                <div class="bg-white shadow-sm rounded-lg p-5">
                    <p class="text-sm text-gray-500">Despesas do período</p>
                    <p class="mt-1 text-2xl font-bold text-red-600">R$ {{ number_format($periodExpense, 2, ',', '.') }}</p>
                </div>
                <div class="bg-white shadow-sm rounded-lg p-5">
                    <p class="text-sm text-gray-500">Saldo do período</p>
                    @php $periodBalance = $periodIncome - $periodExpense; @endphp
                    <p class="mt-1 text-2xl font-bold {{ $periodBalance >= 0 ? 'text-gray-900' : 'text-red-600' }}">
                        {{ $periodBalance >= 0 ? '' : '-' }}R$ {{ number_format(abs($periodBalance), 2, ',', '.') }}
                    </p>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div class="bg-white shadow-sm rounded-lg p-6">
                    <h3 class="font-semibold text-gray-800 mb-4">Despesas por categoria</h3>
                    @if($expensesByCategory->isEmpty())
                        <p class="text-sm text-gray-500">Nenhuma despesa neste período.</p>
                    @else
                        <div class="h-64" wire:ignore
                            x-data="categoryChart(@js($categoryChart))"
                            x-init="init($el.querySelector('canvas'))"
                        >
                            <canvas></canvas>
                        </div>
                    @endif
                </div>

                <div class="lg:col-span-2 bg-white shadow-sm rounded-lg p-6">
                    <h3 class="font-semibold text-gray-800 mb-4">Detalhamento por categoria</h3>
                    @if($expensesByCategory->isEmpty())
                        <p class="text-sm text-gray-500">Nenhuma despesa neste período.</p>
                    @else
                        <div class="space-y-3">
                            @foreach ($expensesByCategory as $row)
                                @php $percent = $totalExpense > 0 ? ($row->total / $totalExpense) * 100 : 0; @endphp
                                <div>
                                    <div class="flex justify-between text-sm mb-1">
                                        <span class="text-gray-700 flex items-center gap-2">
                                            <span class="w-2.5 h-2.5 rounded-full" style="background-color: {{ $row->category?->color ?? '#94a3b8' }}"></span>
                                            {{ $row->category?->name ?? 'Sem categoria' }}
                                        </span>
                                        <span class="text-gray-500">R$ {{ number_format($row->total, 2, ',', '.') }} ({{ number_format($percent, 1, ',', '.') }}%)</span>
                                    </div>
                                    <div class="w-full bg-gray-100 rounded-full h-2">
                                        <div class="h-2 rounded-full" style="width: {{ $percent }}%; background-color: {{ $row->category?->color ?? '#94a3b8' }}"></div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            <div class="bg-white shadow-sm rounded-lg p-6">
                <h3 class="font-semibold text-gray-800 mb-4">Receitas x Despesas (últimos 6 meses)</h3>
                <div class="h-72" wire:ignore
                    x-data="trendChart(@js($trendChart))"
                    x-init="init($el.querySelector('canvas'))"
                >
                    <canvas></canvas>
                </div>
            </div>
        </div>
    </div>
