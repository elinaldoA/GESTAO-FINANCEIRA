@php
    $tabs = [
        ['route' => 'investments.index', 'label' => 'Resumo'],
        ['route' => 'investments.positions', 'label' => 'Posições'],
        ['route' => 'investments.dividends', 'label' => 'Proventos'],
        ['route' => 'investments.wealth', 'label' => 'Patrimônio'],
        ['route' => 'investments.returns', 'label' => 'Rentabilidade'],
        ['route' => 'investments.analysis', 'label' => 'Análise'],
        ['route' => 'investments.transactions', 'label' => 'Lançamentos'],
    ];
@endphp

<div class="flex flex-wrap items-center gap-1 bg-white shadow-sm rounded-lg p-2 overflow-x-auto">
    @foreach ($tabs as $tab)
        <a
            href="{{ route($tab['route']) }}"
            wire:navigate
            class="px-3 py-1.5 rounded-md text-sm font-medium whitespace-nowrap {{ request()->routeIs($tab['route']) ? 'bg-indigo-600 text-white' : 'text-gray-600 hover:bg-gray-100' }}"
        >
            {{ $tab['label'] }}
        </a>
    @endforeach
</div>
