<div
    id="page-loading-overlay"
    class="fixed inset-0 z-[9999] items-center justify-center bg-gray-900/40 backdrop-blur-sm"
>
    <div class="flex flex-col items-center gap-3 rounded-2xl bg-white px-8 py-6 shadow-xl">
        <x-spinner class="h-8 w-8 text-indigo-600" />
        <span class="text-sm font-medium text-gray-600">{{ __('Carregando...') }}</span>
    </div>
</div>
