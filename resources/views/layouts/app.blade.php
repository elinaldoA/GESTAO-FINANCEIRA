<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased">
        <x-page-loading-overlay />

        <div class="min-h-screen bg-slate-50 flex flex-col">
            <livewire:layout.navigation />

            <div class="lg:pl-64 flex flex-col flex-1">
                <!-- Page Heading -->
                @if (isset($header))
                    <header class="bg-white border-b border-slate-200">
                        <div class="max-w-7xl mx-auto py-5 px-4 sm:px-6 lg:px-8">
                            {{ $header }}
                        </div>
                    </header>
                @endif

                <!-- Page Content -->
                <main class="flex-1">
                    {{ $slot }}
                </main>

                <!-- Footer -->
                <footer class="border-t border-slate-200 py-4">
                    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col sm:flex-row items-center justify-between gap-2 text-sm text-slate-400">
                        <span>&copy; {{ now()->year }} {{ config('app.name') }}. Todos os direitos reservados.</span>
                        <span>Feito com Laravel &amp; Livewire</span>
                    </div>
                </footer>
            </div>
        </div>
    </body>
</html>
