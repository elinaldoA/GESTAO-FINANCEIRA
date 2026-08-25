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
    public $trendChart;
    public $categoryChart;
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

        $this->trendChart = $this->buildTrendChart($user);
        $this->categoryChart = $this->buildCategoryChart($user, $start, $end);
        $this->alerts = FinancialAlerts::forUser($user);
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
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Dashboard') }}</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if(count($alerts))
                <div class="space-y-2">
                    @foreach ($alerts as $alert)
                        <a href="{{ $alert['url'] }}" wire:navigate class="flex items-center gap-3 p-3 rounded-lg text-sm {{ $alert['severity'] === 'error' ? 'bg-red-50 text-red-700 hover:bg-red-100' : 'bg-amber-50 text-amber-700 hover:bg-amber-100' }}">
                            <span>{{ $alert['severity'] === 'error' ? '🔴' : '🟡' }}</span>
                            <span class="flex-1">{{ $alert['message'] }}</span>
                            <span>→</span>
                        </a>
                    @endforeach
                </div>
            @endif

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="bg-white shadow-sm rounded-lg p-5">
                    <p class="text-sm text-gray-500">Saldo total</p>
                    <p class="mt-1 text-2xl font-bold {{ $totalBalance >= 0 ? 'text-gray-900' : 'text-red-600' }}">
                        R$ {{ number_format($totalBalance, 2, ',', '.') }}
                    </p>
                </div>
                <div class="bg-white shadow-sm rounded-lg p-5">
                    <p class="text-sm text-gray-500">Receitas do mês</p>
                    <p class="mt-1 text-2xl font-bold text-green-600">R$ {{ number_format($monthIncome, 2, ',', '.') }}</p>
                </div>
                <div class="bg-white shadow-sm rounded-lg p-5">
                    <p class="text-sm text-gray-500">Despesas do mês</p>
                    <p class="mt-1 text-2xl font-bold text-red-600">R$ {{ number_format($monthExpense, 2, ',', '.') }}</p>
                </div>
                <div class="bg-white shadow-sm rounded-lg p-5">
                    <p class="text-sm text-gray-500">Investimentos</p>
                    <p class="mt-1 text-2xl font-bold text-gray-900">R$ {{ number_format($totalInvested, 2, ',', '.') }}</p>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div class="lg:col-span-2 bg-white shadow-sm rounded-lg p-6">
                    <h3 class="font-semibold text-gray-800 mb-4">Receitas x Despesas (6 meses)</h3>
                    <div class="h-72" wire:ignore
                        x-data="trendChart(@js($trendChart))"
                        x-init="init($el.querySelector('canvas'))"
                    >
                        <canvas></canvas>
                    </div>
                </div>

                <div class="bg-white shadow-sm rounded-lg p-6">
                    <h3 class="font-semibold text-gray-800 mb-4">Despesas por categoria</h3>
                    @if(count($categoryChart['labels']))
                        <div class="h-72" wire:ignore
                            x-data="categoryChart(@js($categoryChart))"
                            x-init="init($el.querySelector('canvas'))"
                        >
                            <canvas></canvas>
                        </div>
                    @else
                        <p class="text-sm text-gray-500">Nenhuma despesa registrada neste mês.</p>
                    @endif
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div class="lg:col-span-2 bg-white shadow-sm rounded-lg p-6">
                    <h3 class="font-semibold text-gray-800 mb-4">Últimas transações</h3>
                    @forelse ($recentTransactions as $t)
                        <div class="flex items-center justify-between py-2 border-b last:border-0 border-gray-100">
                            <div>
                                <p class="text-sm font-medium text-gray-800">{{ $t->description }}</p>
                                <p class="text-xs text-gray-500">
                                    {{ $t->date->format('d/m/Y') }}
                                    @if($t->category) · {{ $t->category->name }} @endif
                                    @if($t->account) · {{ $t->account->name }} @endif
                                    @if($t->creditCard) · {{ $t->creditCard->name }} @endif
                                </p>
                            </div>
                            <span class="text-sm font-semibold {{ $t->type === 'receita' ? 'text-green-600' : ($t->type === 'despesa' ? 'text-red-600' : 'text-blue-600') }}">
                                {{ $t->type === 'despesa' ? '-' : ($t->type === 'receita' ? '+' : '') }} R$ {{ number_format($t->amount, 2, ',', '.') }}
                            </span>
                        </div>
                    @empty
                        <p class="text-sm text-gray-500">Nenhuma transação cadastrada ainda.</p>
                    @endforelse
                    <a href="{{ route('transactions.index') }}" wire:navigate class="mt-4 inline-block text-sm text-indigo-600 hover:underline">Ver todas as transações →</a>
                </div>

                <div class="bg-white shadow-sm rounded-lg p-6">
                    <h3 class="font-semibold text-gray-800 mb-4">Contas</h3>
                    @forelse ($accounts as $account)
                        <div class="flex items-center justify-between py-2 border-b last:border-0 border-gray-100">
                            <div class="flex items-center gap-2">
                                <span class="w-2.5 h-2.5 rounded-full" style="background-color: {{ $account->color }}"></span>
                                <span class="text-sm text-gray-800">{{ $account->name }}</span>
                            </div>
                            <span class="text-sm font-medium {{ $account->current_balance >= 0 ? 'text-gray-900' : 'text-red-600' }}">
                                R$ {{ number_format($account->current_balance, 2, ',', '.') }}
                            </span>
                        </div>
                    @empty
                        <p class="text-sm text-gray-500">Nenhuma conta cadastrada.</p>
                    @endforelse

                    @if($creditCards->isNotEmpty())
                        <h3 class="font-semibold text-gray-800 mt-6 mb-4">Cartões de crédito</h3>
                        @foreach ($creditCards as $card)
                            <div class="flex items-center justify-between py-2 border-b last:border-0 border-gray-100">
                                <span class="text-sm text-gray-800">{{ $card->name }}</span>
                                <span class="text-sm font-medium text-gray-900">
                                    R$ {{ number_format($card->used_limit, 2, ',', '.') }} / {{ number_format($card->limit_amount, 2, ',', '.') }}
                                </span>
                            </div>
                        @endforeach
                    @endif

                    @if($investments->isNotEmpty())
                        <h3 class="font-semibold text-gray-800 mt-6 mb-4">Investimentos</h3>
                        @foreach ($investments as $investment)
                            <div class="flex items-center justify-between py-2 border-b last:border-0 border-gray-100">
                                <div class="flex items-center gap-2">
                                    <span class="w-2.5 h-2.5 rounded-full" style="background-color: {{ $investment->color }}"></span>
                                    <span class="text-sm text-gray-800">{{ $investment->name }}</span>
                                </div>
                                <span class="text-sm font-medium {{ $investment->gain >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                    R$ {{ number_format($investment->current_amount, 2, ',', '.') }}
                                </span>
                            </div>
                        @endforeach
                        <a href="{{ route('investments.index') }}" wire:navigate class="mt-4 inline-block text-sm text-indigo-600 hover:underline">Ver investimentos →</a>
                    @endif
                </div>
            </div>

            @if($budgets->isNotEmpty())
                <div class="bg-white shadow-sm rounded-lg p-6">
                    <h3 class="font-semibold text-gray-800 mb-4">Orçamento do mês</h3>
                    <div class="space-y-3">
                        @foreach ($budgets as $budget)
                            @php
                                $percent = $budget->amount > 0 ? min(100, ($budget->spent / $budget->amount) * 100) : 0;
                            @endphp
                            <div>
                                <div class="flex justify-between text-sm mb-1">
                                    <span class="text-gray-700">{{ $budget->category->name }}</span>
                                    <span class="text-gray-500">R$ {{ number_format($budget->spent, 2, ',', '.') }} / R$ {{ number_format($budget->amount, 2, ',', '.') }}</span>
                                </div>
                                <div class="w-full bg-gray-100 rounded-full h-2">
                                    <div class="h-2 rounded-full {{ $percent >= 100 ? 'bg-red-500' : ($percent >= 80 ? 'bg-amber-500' : 'bg-green-500') }}" style="width: {{ $percent }}%"></div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </div>
