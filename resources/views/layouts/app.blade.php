<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>IK-{{ $title ?? 'Dashboard' }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased bg-slate-50">
        <div class="min-h-screen flex items-stretch" x-data="{ sidebarOpen: false }">
            @include('layouts.navigation')

            <!-- Main content area -->
            <div class="flex-1 flex flex-col min-w-0 bg-gradient-to-br from-slate-50 via-white to-indigo-50/30">
                <!-- Top bar / Header -->
                <header class="sticky top-0 z-40 flex items-center justify-between h-16 px-4 sm:px-6 lg:px-8 bg-white/80 backdrop-blur-xl border-b border-slate-200/60 shadow-sm">
                    <!-- Mobile menu button -->
                    <button @click="sidebarOpen = !sidebarOpen" type="button" class="inline-flex items-center justify-center p-2.5 rounded-xl text-slate-500 hover:text-slate-700 hover:bg-slate-100 lg:hidden focus:outline-none focus:ring-2 focus:ring-indigo-500/20 transition-all duration-200">
                        <span class="sr-only">Buka menu</span>
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>

                    <!-- Page heading -->
                    <div class="flex-1 flex items-center min-w-0 w-full">
                        @isset($header)
                            <div class="px-4 sm:px-0 w-full">
                                {{ $header }}
                            </div>
                        @endisset
                    </div>

                    <!-- User dropdown -->
                    <div class="flex items-center gap-3">
                        <x-dropdown align="right" width="48">
                            <x-slot name="trigger">
                                <button class="inline-flex items-center gap-2 px-3 py-2 rounded-xl text-sm font-medium text-slate-700 bg-slate-50 hover:bg-slate-100 border border-slate-200/60 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 transition-all duration-200">
                                    <span class="flex items-center justify-center w-8 h-8 rounded-lg bg-gradient-to-br from-indigo-500 to-indigo-600 text-white text-xs font-bold">
                                        {{ strtoupper(substr(Auth::user()->name, 0, 2)) }}
                                    </span>
                                    <span class="hidden sm:inline">{{ Auth::user()->name }}</span>
                                    <svg class="h-4 w-4 text-slate-400" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                    </svg>
                                </button>
                            </x-slot>

                            <x-slot name="content">
                                <x-dropdown-link :href="route('profile.edit')">
                                    {{ __('Profile') }}
                                </x-dropdown-link>

                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <x-dropdown-link :href="route('logout')"
                                            onclick="event.preventDefault();
                                                        this.closest('form').submit();">
                                        {{ __('Log Out') }}
                                    </x-dropdown-link>
                                </form>
                            </x-slot>
                        </x-dropdown>
                    </div>
                </header>

                <!-- Page Content -->
                <main class="flex-1">
                    <div class="py-6 px-4 sm:px-6 lg:px-8">
                        {{ $slot }}
                    </div>
                </main>
            </div>

            <!-- Mobile sidebar overlay -->
            <div x-show="sidebarOpen"
             x-transition:enter="transition-opacity ease-out duration-300"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition-opacity ease-in duration-200"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             @click="sidebarOpen = false"
             class="fixed inset-0 z-40 bg-slate-900/60 backdrop-blur-sm lg:hidden"
             style="display: none;">
            </div>
        </div>
    </body>
</html>
