<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-gray-900 antialiased">
        <x-page-loading-overlay />

        <div class="min-h-screen flex bg-white overflow-hidden">
            <!-- Left: branding panel with diagonal edge -->
            <div class="hidden lg:block relative lg:w-[44%] xl:w-[42%] shrink-0">
                <div class="absolute inset-0 bg-gradient-to-br from-[#0b1330] via-[#0f1b3d] to-[#0a1f4d] [clip-path:polygon(0_0,100%_0,82%_100%,0_100%)]"></div>

                <div class="relative z-10 flex flex-col justify-between h-full py-12 pl-12 pr-10 xl:py-16 xl:pl-16 xl:pr-14">
                    <!-- Brand -->
                    <a href="/" wire:navigate class="flex items-center gap-3">
                        <span class="flex items-center justify-center w-11 h-11 rounded-lg bg-blue-600/20 ring-1 ring-blue-400/30">
                            <x-application-logo class="w-6 h-6 fill-current text-blue-400" />
                        </span>
                        <span class="leading-tight">
                            <span class="block text-white font-bold text-base">{{ config('app.name', 'Laravel') }}</span>
                            <span class="block text-blue-200/50 text-[11px] font-medium tracking-wider uppercase">{{ __('Controle financeiro pessoal') }}</span>
                        </span>
                    </a>

                    <!-- Headline -->
                    <div class="max-w-xs xl:max-w-sm">
                        <h1 class="text-3xl xl:text-4xl font-extrabold text-white leading-tight">
                            {{ __('Organize suas finanças de forma') }}
                            <span class="text-blue-400">{{ __('inteligente') }}</span>
                        </h1>
                        <p class="mt-4 text-slate-400 text-sm leading-relaxed">
                            {{ __('Acompanhe contas, cartões e investimentos com segurança, clareza e controle total do seu dinheiro.') }}
                        </p>
                    </div>

                    <!-- Feature list -->
                    <div class="max-w-xs space-y-5">
                        <div class="flex items-start gap-3">
                            <span class="flex items-center justify-center w-9 h-9 shrink-0 rounded-lg bg-blue-500/10 ring-1 ring-blue-400/20 text-blue-400">
                                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3v18h18M7 15l4-5 3 3 5-7" />
                                </svg>
                            </span>
                            <div>
                                <p class="text-white text-sm font-semibold">{{ __('Visão em tempo real') }}</p>
                                <p class="text-slate-400 text-xs mt-0.5">{{ __('Cotações e saldos sempre atualizados') }}</p>
                            </div>
                        </div>

                        <div class="flex items-start gap-3">
                            <span class="flex items-center justify-center w-9 h-9 shrink-0 rounded-lg bg-blue-500/10 ring-1 ring-blue-400/20 text-blue-400">
                                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
                                </svg>
                            </span>
                            <div>
                                <p class="text-white text-sm font-semibold">{{ __('Dados protegidos') }}</p>
                                <p class="text-slate-400 text-xs mt-0.5">{{ __('Suas informações financeiras sempre seguras') }}</p>
                            </div>
                        </div>

                        <div class="flex items-start gap-3">
                            <span class="flex items-center justify-center w-9 h-9 shrink-0 rounded-lg bg-blue-500/10 ring-1 ring-blue-400/20 text-blue-400">
                                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7" />
                                </svg>
                            </span>
                            <div>
                                <p class="text-white text-sm font-semibold">{{ __('Metas e orçamentos') }}</p>
                                <p class="text-slate-400 text-xs mt-0.5">{{ __('Defina objetivos e acompanhe sua evolução') }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right: form panel -->
            <div class="flex flex-1 items-center justify-center bg-white px-4 py-8 sm:px-6 sm:py-12">
                <div class="w-full max-w-sm">
                    <!-- Logo shown only on small screens -->
                    <div class="flex justify-center mb-6 sm:mb-8 lg:hidden">
                        <a href="/" wire:navigate>
                            <x-application-logo class="w-14 h-14 sm:w-16 sm:h-16 fill-current text-gray-800" />
                        </a>
                    </div>

                    {{ $slot }}
                </div>
            </div>
        </div>
    </body>
</html>
