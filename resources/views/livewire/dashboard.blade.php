<?php

use App\Models\Account;
use App\Models\Budget;
use App\Models\CreditCard;
use App\Models\Investment;
use App\Models\Transaction;
use App\Support\FinancialAlerts;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public $accounts;

    public $creditCards;

    public $investments;

    public $totalBalance = 0;

    public $totalInvested = 0;

    public $monthIncome = 0;

    public $monthExpense = 0;

    public $recentTransactions;

    public $budgets;

    public array $budgetSpentMap = [];

    public $trendChart;

    public $categoryChart;

    public ?float $monthProjection = null;

    public array $categoryHighlights = [];

    public $alerts = [];

    public function mount(): void
    {
        $user = auth()->user();
        $start = Carbon::now()->startOfMonth();
        $end = Carbon::now()->endOfMonth();

        $this->accounts = $user->accounts()->where('is_active', true)->get();
        $this->creditCards = $user->creditCards()->where('is_active', true)->get();
        $this->investments = $user->investments()->where('is_active', true)->get();
        $this->totalBalance = $this->accounts->sum(fn ($a) => $a->current_balance);
        $this->totalInvested = $this->investments->sum(fn ($i) => (float) $i->current_amount);

        $this->monthIncome = (float) $user->transactions()
            ->where('type', 'receita')->where('is_paid', true)
            ->whereBetween('date', [$start, $end])->sum('amount');

        $this->monthExpense = (float) $user->transactions()
            ->where('type', 'despesa')->where('is_paid', true)
            ->whereBetween('date', [$start, $end])->sum('amount');

        $this->recentTransactions = $user->transactions()
            ->with(['category', 'account', 'creditCard'])
            ->orderByDesc('date')->orderByDesc('id')
            ->limit(8)->get();

        $this->budgets = $user->budgets()
            ->with('category')
            ->where('month', now()->month)->where('year', now()->year)
            ->get();
        $this->budgetSpentMap = Budget::spentMapFor($user->id, (int) now()->month, (int) now()->year);

        $this->trendChart = $this->buildTrendChart($user);
        $this->categoryChart = $this->buildCategoryChart($user, $start, $end);
        $this->categoryHighlights = $this->buildCategoryHighlights($user);
        $this->monthProjection = now()->day > 3
            ? $this->monthExpense / now()->day * now()->daysInMonth
            : null;
        $this->alerts = FinancialAlerts::forUser($user);
    }

    /**
     * Categories whose expense this month is running well above their recent average
     * (>= 1.3x the average of the previous 3 months, with a R$50 floor to avoid noise
     * on small categories). One grouped query per month (same pattern as buildTrendChart)
     * since whereYear()/whereMonth() are portable across DB drivers, unlike raw
     * YEAR()/MONTH() SQL functions.
     */
    private function buildCategoryHighlights($user): array
    {
        $current = [];
        $previous = [];
        $names = [];

        for ($i = 3; $i >= 0; $i--) {
            $month = Carbon::now()->subMonths($i);

            $rows = $user->transactions()
                ->join('categories', 'categories.id', '=', 'transactions.category_id')
                ->where('transactions.type', 'despesa')
                ->where('transactions.is_paid', true)
                ->whereYear('transactions.date', $month->year)
                ->whereMonth('transactions.date', $month->month)
                ->selectRaw('transactions.category_id, categories.name as name, SUM(transactions.amount) as total')
                ->groupBy('transactions.category_id', 'categories.name')
                ->get();

            foreach ($rows as $row) {
                $names[$row->category_id] = $row->name;

                if ($i === 0) {
                    $current[$row->category_id] = (float) $row->total;
                } else {
                    $previous[$row->category_id] = ($previous[$row->category_id] ?? 0.0) + (float) $row->total;
                }
            }
        }

        $highlights = [];

        foreach ($current as $categoryId => $currentTotal) {
            $average = ($previous[$categoryId] ?? 0.0) / 3;

            if ($average <= 0) {
                continue;
            }

            if ($currentTotal >= $average * 1.3 && ($currentTotal - $average) >= 50) {
                $highlights[] = [
                    'name' => $names[$categoryId],
                    'current' => $currentTotal,
                    'average' => $average,
                    'percent' => (($currentTotal - $average) / $average) * 100,
                ];
            }
        }

        usort($highlights, fn ($a, $b) => $b['percent'] <=> $a['percent']);

        return array_slice($highlights, 0, 3);
    }

    private function buildTrendChart($user): array
    {
        $labels = [];
        $income = [];
        $expense = [];

        for ($i = 5; $i >= 0; $i--) {
            $month = Carbon::now()->subMonths($i);
            $monthStart = $month->copy()->startOfMonth();
            $monthEnd = $month->copy()->endOfMonth();

            $labels[] = ucfirst($month->translatedFormat('M/y'));

            $income[] = (float) $user->transactions()
                ->where('type', 'receita')->where('is_paid', true)
                ->whereBetween('date', [$monthStart, $monthEnd])->sum('amount');

            $expense[] = (float) $user->transactions()
                ->where('type', 'despesa')->where('is_paid', true)
                ->whereBetween('date', [$monthStart, $monthEnd])->sum('amount');
        }

        return ['labels' => $labels, 'income' => $income, 'expense' => $expense];
    }

    private function buildCategoryChart($user, $start, $end): array
    {
        $rows = $user->transactions()
            ->join('categories', 'categories.id', '=', 'transactions.category_id')
            ->where('transactions.type', 'despesa')
            ->where('transactions.is_paid', true)
            ->whereBetween('transactions.date', [$start, $end])
            ->selectRaw('categories.name as name, categories.color as color, sum(transactions.amount) as total')
            ->groupBy('categories.name', 'categories.color')
            ->orderByDesc('total')
            ->get();

        return [
            'labels' => $rows->pluck('name')->all(),
            'colors' => $rows->pluck('color')->all(),
            'totals' => $rows->pluck('total')->map(fn ($v) => (float) $v)->all(),
        ];
    }
}; ?>

    <x-slot name="header">
        <h2 class="font-bold text-xl text-slate-900 leading-tight">{{ __('Dashboard') }}</h2>
        <p class="mt-0.5 text-sm text-slate-500">{{ __('Visão geral das suas finanças em :date', ['date' => ucfirst(now()->translatedFormat('F \d\e Y'))]) }}</p>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if(count($alerts))
                <div class="space-y-2">
                    @foreach ($alerts as $alert)
                        <a href="{{ $alert['url'] }}" wire:navigate class="flex items-center gap-3 p-3 rounded-lg text-sm border {{ $alert['severity'] === 'error' ? 'bg-red-50 border-red-100 text-red-700 hover:bg-red-100' : 'bg-amber-50 border-amber-100 text-amber-700 hover:bg-amber-100' }}">
                            <span>{{ $alert['severity'] === 'error' ? '🔴' : '🟡' }}</span>
                            <span class="flex-1">{{ $alert['message'] }}</span>
                            <span>→</span>
                        </a>
                    @endforeach
                </div>
            @endif

            @if($monthProjection !== null || count($categoryHighlights))
                <div class="bg-white shadow-sm rounded-xl border border-slate-100 p-5">
                    <h3 class="flex items-center gap-2 font-semibold text-slate-800 mb-3">
                        <svg class="w-4 h-4 text-blue-500" stroke="currentColor" fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>
                        Insights
                    </h3>
                    <div class="space-y-2 text-sm">
                        @if($monthProjection !== null)
                            <p class="text-slate-600">
                                No ritmo atual, você deve fechar o mês com cerca de
                                <span class="font-semibold text-slate-900">R$ {{ number_format($monthProjection, 2, ',', '.') }}</span>
                                em despesas.
                            </p>
                        @endif
                        @foreach ($categoryHighlights as $highlight)
                            <p class="text-amber-700">
                                <span class="font-semibold">{{ $highlight['name'] }}</span>
                                está {{ number_format($highlight['percent'], 0) }}% acima da média dos últimos meses
                                (R$ {{ number_format($highlight['current'], 2, ',', '.') }} contra R$ {{ number_format($highlight['average'], 2, ',', '.') }} de média).
                            </p>
                        @endforeach
                    </div>
                </div>
            @endif

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="bg-white shadow-sm rounded-xl border border-slate-100 p-5">
                    <div class="flex items-center gap-3">
                        <span class="flex items-center justify-center w-10 h-10 rounded-lg bg-navy-900/5 text-navy-900 shrink-0">
                            <svg class="w-5 h-5" stroke="currentColor" fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7h18M3 7v10a2 2 0 002 2h14a2 2 0 002-2V7M3 7l2-4h14l2 4" /></svg>
                        </span>
                        <p class="text-sm text-slate-500">Saldo total</p>
                    </div>
                    <p class="mt-3 text-2xl font-bold {{ $totalBalance >= 0 ? 'text-slate-900' : 'text-red-600' }}">
                        R$ {{ number_format($totalBalance, 2, ',', '.') }}
                    </p>
                </div>
                <div class="bg-white shadow-sm rounded-xl border border-slate-100 p-5">
                    <div class="flex items-center gap-3">
                        <span class="flex items-center justify-center w-10 h-10 rounded-lg bg-green-50 text-green-600 shrink-0">
                            <svg class="w-5 h-5" stroke="currentColor" fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 11l5-5 5 5M12 6v13" /></svg>
                        </span>
                        <p class="text-sm text-slate-500">Receitas do mês</p>
                    </div>
                    <p class="mt-3 text-2xl font-bold text-green-600">R$ {{ number_format($monthIncome, 2, ',', '.') }}</p>
                </div>
                <div class="bg-white shadow-sm rounded-xl border border-slate-100 p-5">
                    <div class="flex items-center gap-3">
                        <span class="flex items-center justify-center w-10 h-10 rounded-lg bg-red-50 text-red-600 shrink-0">
                            <svg class="w-5 h-5" stroke="currentColor" fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 13l5 5 5-5M12 18V5" /></svg>
                        </span>
                        <p class="text-sm text-slate-500">Despesas do mês</p>
                    </div>
                    <p class="mt-3 text-2xl font-bold text-red-600">R$ {{ number_format($monthExpense, 2, ',', '.') }}</p>
                </div>
                <div class="bg-white shadow-sm rounded-xl border border-slate-100 p-5">
                    <div class="flex items-center gap-3">
                        <span class="flex items-center justify-center w-10 h-10 rounded-lg bg-blue-50 text-blue-600 shrink-0">
                            <svg class="w-5 h-5" stroke="currentColor" fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 17l6-6 4 4 8-8M21 7v6M21 7h-6" /></svg>
                        </span>
                        <p class="text-sm text-slate-500">Investimentos</p>
                    </div>
                    <p class="mt-3 text-2xl font-bold text-slate-900">R$ {{ number_format($totalInvested, 2, ',', '.') }}</p>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div class="lg:col-span-2 bg-white shadow-sm rounded-xl border border-slate-100 p-6">
                    <h3 class="font-semibold text-slate-800 mb-4">Receitas x Despesas (6 meses)</h3>
                    <div class="h-72" wire:ignore
                        x-data="trendChart(@js($trendChart))"
                        x-init="init($el.querySelector('canvas'))"
                    >
                        <canvas></canvas>
                    </div>
                </div>

                <div class="bg-white shadow-sm rounded-xl border border-slate-100 p-6">
                    <h3 class="font-semibold text-slate-800 mb-4">Despesas por categoria</h3>
                    @if(count($categoryChart['labels']))
                        <div class="h-72" wire:ignore
                            x-data="categoryChart(@js($categoryChart))"
                            x-init="init($el.querySelector('canvas'))"
                        >
                            <canvas></canvas>
                        </div>
                    @else
                        <p class="text-sm text-slate-500">Nenhuma despesa registrada neste mês.</p>
                    @endif
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div class="lg:col-span-2 bg-white shadow-sm rounded-xl border border-slate-100 p-6">
                    <h3 class="font-semibold text-slate-800 mb-4">Últimas transações</h3>
                    @forelse ($recentTransactions as $t)
                        <div class="flex items-center justify-between py-2.5 border-b last:border-0 border-slate-100">
                            <div class="flex items-center gap-3 min-w-0">
                                <span class="flex items-center justify-center w-8 h-8 rounded-full shrink-0 {{ $t->type === 'receita' ? 'bg-green-50 text-green-600' : ($t->type === 'despesa' ? 'bg-red-50 text-red-600' : 'bg-blue-50 text-blue-600') }}">
                                    @if($t->type === 'receita')
                                        <svg class="w-4 h-4" stroke="currentColor" fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 11l5-5 5 5M12 6v13" /></svg>
                                    @elseif($t->type === 'despesa')
                                        <svg class="w-4 h-4" stroke="currentColor" fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 13l5 5 5-5M12 18V5" /></svg>
                                    @else
                                        <svg class="w-4 h-4" stroke="currentColor" fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4M16 17H4m0 0l4 4m-4-4l4-4" /></svg>
                                    @endif
                                </span>
                                <div class="min-w-0">
                                    <p class="text-sm font-medium text-slate-800 truncate">{{ $t->description }}</p>
                                    <p class="text-xs text-slate-500">
                                        {{ $t->date->format('d/m/Y') }}
                                        @if($t->category) · {{ $t->category->name }} @endif
                                        @if($t->account) · {{ $t->account->name }} @endif
                                        @if($t->creditCard) · {{ $t->creditCard->name }} @endif
                                    </p>
                                </div>
                            </div>
                            <span class="text-sm font-semibold shrink-0 ml-3 {{ $t->type === 'receita' ? 'text-green-600' : ($t->type === 'despesa' ? 'text-red-600' : 'text-blue-600') }}">
                                {{ $t->type === 'despesa' ? '-' : ($t->type === 'receita' ? '+' : '') }} R$ {{ number_format($t->amount, 2, ',', '.') }}
                            </span>
                        </div>
                    @empty
                        <p class="text-sm text-slate-500">Nenhuma transação cadastrada ainda.</p>
                    @endforelse
                    <a href="{{ route('transactions.index') }}" wire:navigate class="mt-4 inline-block text-sm font-medium text-blue-600 hover:underline">Ver todas as transações →</a>
                </div>

                <div class="bg-white shadow-sm rounded-xl border border-slate-100 p-6">
                    <h3 class="font-semibold text-slate-800 mb-4">Contas</h3>
                    @forelse ($accounts as $account)
                        <div class="flex items-center justify-between py-2 border-b last:border-0 border-slate-100">
                            <div class="flex items-center gap-2">
                                <span class="w-2.5 h-2.5 rounded-full" style="background-color: {{ $account->color }}"></span>
                                <span class="text-sm text-slate-800">{{ $account->name }}</span>
                            </div>
                            <span class="text-sm font-medium {{ $account->current_balance >= 0 ? 'text-slate-900' : 'text-red-600' }}">
                                R$ {{ number_format($account->current_balance, 2, ',', '.') }}
                            </span>
                        </div>
                    @empty
                        <p class="text-sm text-slate-500">Nenhuma conta cadastrada.</p>
                    @endforelse

                    @if($creditCards->isNotEmpty())
                        <h3 class="font-semibold text-slate-800 mt-6 mb-4">Cartões de crédito</h3>
                        @foreach ($creditCards as $card)
                            <div class="flex items-center justify-between py-2 border-b last:border-0 border-slate-100">
                                <span class="text-sm text-slate-800">{{ $card->name }}</span>
                                <span class="text-sm font-medium text-slate-900">
                                    R$ {{ number_format($card->used_limit, 2, ',', '.') }} / {{ number_format($card->limit_amount, 2, ',', '.') }}
                                </span>
                            </div>
                        @endforeach
                    @endif

                    @if($investments->isNotEmpty())
                        <h3 class="font-semibold text-slate-800 mt-6 mb-4">Investimentos</h3>
                        @foreach ($investments as $investment)
                            <div class="flex items-center justify-between py-2 border-b last:border-0 border-slate-100">
                                <div class="flex items-center gap-2">
                                    <span class="w-2.5 h-2.5 rounded-full" style="background-color: {{ $investment->color }}"></span>
                                    <span class="text-sm text-slate-800">{{ $investment->name }}</span>
                                </div>
                                <span class="text-sm font-medium {{ $investment->gain >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                    R$ {{ number_format($investment->current_amount, 2, ',', '.') }}
                                </span>
                            </div>
                        @endforeach
                        <a href="{{ route('investments.index') }}" wire:navigate class="mt-4 inline-block text-sm font-medium text-blue-600 hover:underline">Ver investimentos →</a>
                    @endif
                </div>
            </div>

            @if($budgets->isNotEmpty())
                <div class="bg-white shadow-sm rounded-xl border border-slate-100 p-6">
                    <h3 class="font-semibold text-slate-800 mb-4">Orçamento do mês</h3>
                    <div class="space-y-3">
                        @foreach ($budgets as $budget)
                            @php
                                $spent = $budgetSpentMap[$budget->category_id] ?? 0;
                                $percent = $budget->amount > 0 ? min(100, ($spent / $budget->amount) * 100) : 0;
                            @endphp
                            <div>
                                <div class="flex justify-between text-sm mb-1">
                                    <span class="text-slate-700">{{ $budget->category->name }}</span>
                                    <span class="text-slate-500">R$ {{ number_format($spent, 2, ',', '.') }} / R$ {{ number_format($budget->amount, 2, ',', '.') }}</span>
                                </div>
                                <div class="w-full bg-slate-100 rounded-full h-2">
                                    <div class="h-2 rounded-full {{ $percent >= 100 ? 'bg-red-500' : ($percent >= 80 ? 'bg-amber-500' : 'bg-green-500') }}" style="width: {{ $percent }}%"></div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </div>
