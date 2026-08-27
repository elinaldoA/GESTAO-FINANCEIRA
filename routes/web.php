<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::get('/', function () {
    return redirect()->route(auth()->check() ? 'dashboard' : 'login');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Volt::route('dashboard', 'dashboard')->name('dashboard');

    Volt::route('contas', 'accounts.index')->name('accounts.index');
    Volt::route('categorias', 'categories.index')->name('categories.index');
    Volt::route('cartoes', 'credit-cards.index')->name('credit-cards.index');
    Volt::route('cartoes/{creditCard}/fatura', 'credit-cards.invoice')->name('credit-cards.invoice');
    Volt::route('transacoes', 'transactions.index')->name('transactions.index');
    Volt::route('transacoes/importar', 'transactions.import')->name('transactions.import');
    Volt::route('orcamentos', 'budgets.index')->name('budgets.index');
    Volt::route('investimentos', 'investments.index')->name('investments.index');
    Volt::route('investimentos/posicoes', 'investments.positions')->name('investments.positions');
    Volt::route('investimentos/proventos', 'investments.dividends')->name('investments.dividends');
    Volt::route('investimentos/patrimonio', 'investments.wealth')->name('investments.wealth');
    Volt::route('investimentos/rentabilidade', 'investments.returns')->name('investments.returns');
    Volt::route('investimentos/analise', 'investments.analysis')->name('investments.analysis');
    Volt::route('investimentos/lancamentos', 'investments.transactions')->name('investments.transactions');
    Volt::route('investimentos/{investment}', 'investments.show')->name('investments.show');
    Volt::route('metas', 'goals.index')->name('goals.index');
    Volt::route('relatorios', 'reports.index')->name('reports.index');
    Volt::route('lixeira', 'trash.index')->name('trash.index');
});

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

require __DIR__.'/auth.php';
