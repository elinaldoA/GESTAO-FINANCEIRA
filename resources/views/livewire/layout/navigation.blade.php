<?php

use App\Livewire\Actions\Logout;
use Livewire\Volt\Component;

new class extends Component
{
    /**
     * Log the current user out of the application.
     */
    public function logout(Logout $logout): void
    {
        $logout();

        $this->redirect('/', navigate: true);
    }
}; ?>

<div x-data="{ open: false }">
    <!-- Top bar -->
    <div class="lg:pl-64">
        <div class="sticky top-0 z-30 flex items-center justify-between h-16 bg-white/90 backdrop-blur border-b border-slate-200 px-4 sm:px-6 lg:px-8">
            <!-- Mobile logo + hamburger -->
            <div class="flex items-center gap-2 lg:hidden">
                <button @click="open = true" class="text-slate-500 hover:text-slate-700 focus:outline-none">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                </button>
                <a href="{{ route('dashboard') }}" wire:navigate class="flex items-center gap-2">
                    <x-application-logo class="block h-7 w-auto fill-current text-navy-900" />
                </a>
            </div>

            <form action="{{ route('transactions.index') }}" method="GET" class="hidden lg:flex flex-1 max-w-md mx-6">
                <div class="relative w-full">
                    <svg class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z" />
                    </svg>
                    <input
                        type="text"
                        name="busca"
                        placeholder="Buscar transações..."
                        class="w-full pl-9 pr-3 py-1.5 text-sm rounded-full border-slate-200 bg-slate-50 shadow-sm focus:border-blue-500 focus:ring-blue-500 focus:bg-white"
                    >
                </div>
            </form>

            <!-- User dropdown -->
            <x-dropdown align="right" width="56">
                <x-slot name="trigger">
                    <button class="flex items-center gap-3 px-2 py-1.5 rounded-md text-sm text-slate-700 hover:bg-slate-100 transition duration-150 ease-in-out">
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-blue-600 to-navy-900 text-white text-xs font-semibold">
                            {{ Str::of(auth()->user()->name)->substr(0, 1)->upper() }}
                        </span>
                        <span class="hidden sm:flex flex-col items-start leading-tight">
                            <span class="font-medium text-slate-800" x-data="{{ json_encode(['name' => auth()->user()->name]) }}" x-text="name" x-on:profile-updated.window="name = $event.detail.name"></span>
                            <span class="text-xs text-slate-500">{{ auth()->user()->email }}</span>
                        </span>
                        <svg class="h-4 w-4 shrink-0 fill-current text-slate-400" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                        </svg>
                    </button>
                </x-slot>

                <x-slot name="content">
                    <x-dropdown-link :href="route('profile')" wire:navigate>
                        {{ __('Profile') }}
                    </x-dropdown-link>

                    <button wire:click="logout" class="w-full text-start">
                        <x-dropdown-link>
                            {{ __('Log Out') }}
                        </x-dropdown-link>
                    </button>
                </x-slot>
            </x-dropdown>
        </div>
    </div>

    <!-- Mobile overlay -->
    <div x-show="open" x-cloak @click="open = false" class="fixed inset-0 bg-black/50 z-40 lg:hidden" x-transition.opacity></div>

    @php
        $links = [
            ['route' => 'dashboard', 'label' => __('Dashboard'), 'icon' => 'M3 13h8V3H3v10Zm0 8h8v-6H3v6Zm10 0h8V11h-8v10Zm0-18v6h8V3h-8Z'],
            ['route' => 'transactions.index', 'label' => __('Transações'), 'icon' => 'M12 8c-3.5 0-6 1.5-6 3s2.5 3 6 3 6 1.5 6 3-2.5 3-6 3m0-12c2.5 0 4.5.8 5.5 2M12 8V6m0 12v2'],
            ['route' => 'accounts.index', 'label' => __('Contas'), 'icon' => 'M3 7h18M3 7v10a2 2 0 002 2h14a2 2 0 002-2V7M3 7l2-4h14l2 4'],
            ['route' => 'credit-cards.index', 'label' => __('Cartões'), 'icon' => 'M2 8h20M2 8v9a2 2 0 002 2h16a2 2 0 002-2V8M2 8V6a2 2 0 012-2h16a2 2 0 012 2v2M6 15h4'],
            ['route' => 'categories.index', 'label' => __('Categorias'), 'icon' => 'M4 6h16M4 12h10M4 18h6'],
            ['route' => 'budgets.index', 'label' => __('Orçamentos'), 'icon' => 'M12 3v18M3 12h18'],
            ['route' => 'investments.index', 'match' => 'investments.*', 'label' => __('Investimentos'), 'icon' => 'M3 17l6-6 4 4 8-8M21 7v6M21 7h-6'],
            ['route' => 'goals.index', 'label' => __('Metas'), 'icon' => 'M12 8c-2.21 0-4 1.79-4 4s1.79 4 4 4 4-1.79 4-4-1.79-4-4-4Zm0-5v3m0 13v3m9-9h-3M6 12H3'],
            ['route' => 'reports.index', 'label' => __('Relatórios'), 'icon' => 'M4 20V10m6 10V4m6 16v-7'],
            ['route' => 'trash.index', 'label' => __('Lixeira'), 'icon' => 'M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3M4 7h16'],
        ];
    @endphp

    <!-- Sidebar -->
    <aside
        :class="open ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
        class="fixed inset-y-0 left-0 z-50 w-64 bg-gradient-to-b from-navy-900 via-navy-800 to-navy-950 flex flex-col transition-transform duration-200 ease-in-out lg:translate-x-0"
    >
        <div class="h-16 flex items-center justify-between px-5 border-b border-white/10">
            <a href="{{ route('dashboard') }}" wire:navigate class="flex items-center gap-2">
                <span class="flex items-center justify-center w-8 h-8 rounded-lg bg-blue-600/20 ring-1 ring-blue-400/30 shrink-0">
                    <x-application-logo class="block h-5 w-auto fill-current text-blue-400" />
                </span>
                <span class="text-white font-semibold truncate">{{ config('app.name') }}</span>
            </a>
            <button @click="open = false" class="text-slate-400 hover:text-white lg:hidden">
                <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <nav class="flex-1 overflow-y-auto py-4 px-3 space-y-1">
            @foreach ($links as $link)
                @php $active = request()->routeIs($link['match'] ?? $link['route']); @endphp
                <a
                    href="{{ route($link['route']) }}"
                    wire:navigate
                    @click="open = false"
                    class="relative flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition duration-150 ease-in-out {{ $active ? 'bg-white/10 text-white' : 'text-slate-300 hover:bg-white/5 hover:text-white' }}"
                >
                    <span class="absolute left-0 top-1.5 bottom-1.5 w-0.5 rounded-full bg-blue-400 {{ $active ? '' : 'opacity-0' }}"></span>
                    <svg class="h-5 w-5 shrink-0 {{ $active ? 'text-blue-400' : 'text-slate-400' }}" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $link['icon'] }}" />
                    </svg>
                    {{ $link['label'] }}
                </a>
            @endforeach
        </nav>
    </aside>
</div>
