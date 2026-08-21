<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-gray-50 dark:bg-gray-900">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Inventori-Q') }}</title>

    <!-- Fonts are imported in CSS via Google Fonts -->
    
    <!-- Scripts & Styles -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="h-full text-gray-900 dark:text-gray-100 antialiased overflow-hidden flex flex-col">
    <!-- Main Content Area (Scrollable) -->
    <main class="flex-1 overflow-y-auto pb-16">
        {{ $slot }}
    </main>

    <!-- Bottom Navigation (Mobile First) -->
    <nav class="fixed bottom-0 w-full bg-white dark:bg-gray-800 border-t border-gray-200 dark:border-gray-700 flex justify-around items-center h-16 z-50">
        <a href="/app" class="flex flex-col items-center justify-center w-full h-full text-gray-500 hover:text-primary-600 dark:hover:text-primary-500 transition">
            <!-- Icon placeholder -->
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
            <span class="text-xs mt-1 font-medium">Dashboard</span>
        </a>
        <a href="{{ route('pos') }}" class="flex flex-col items-center justify-center w-full h-full text-primary-600 dark:text-primary-400 font-bold transition">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
            <span class="text-xs mt-1">Kasir</span>
        </a>
        <a href="/app/items" class="flex flex-col items-center justify-center w-full h-full text-gray-500 hover:text-primary-600 dark:hover:text-primary-500 transition">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg>
            <span class="text-xs mt-1 font-medium">Barang</span>
        </a>
        @if (auth()->user()?->role === \App\Enums\UserRole::Owner)
            <a href="/app/stock-movements" class="flex flex-col items-center justify-center w-full h-full text-gray-500 hover:text-primary-600 dark:hover:text-primary-500 transition">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg>
                <span class="text-xs mt-1 font-medium">Stok</span>
            </a>
        @else
            <a href="/app/pos-transactions" class="flex flex-col items-center justify-center w-full h-full text-gray-500 hover:text-primary-600 dark:hover:text-primary-500 transition">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h7l5 5v11a2 2 0 01-2 2z"></path></svg>
                <span class="text-xs mt-1 font-medium">Transaksi</span>
            </a>
        @endif
        <a href="/app/suppliers" class="flex flex-col items-center justify-center w-full h-full text-gray-500 hover:text-primary-600 dark:hover:text-primary-500 transition">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path></svg>
            <span class="text-xs mt-1 font-medium">Supplier</span>
        </a>
    </nav>

    @livewireScripts
</body>
</html>
