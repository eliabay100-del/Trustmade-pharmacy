@php
    // PART D — branding from settings (never hard-coded; falls back to config default).
    $brandName = \App\Models\Setting::value('business_name', config('pmas.default_business_name'));
    $brandPrimary = \App\Models\Setting::value('brand_primary_color', '#12A594');
    $brandAccent = \App\Models\Setting::value('brand_accent_color', '#101B2D');
    $brandLogo = \App\Models\Setting::value('logo_path');
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $brandName }} - Pharmacy System</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    {{-- Part D: brand colors applied through CSS variables --}}
    <style>:root { --brand-primary: {{ $brandPrimary }}; --brand-accent: {{ $brandAccent }}; }</style>
</head>
<body class="bg-[#F5F7FA] text-[#1E2530]">

    @auth
    {{-- Mobile top bar --}}
    <div class="lg:hidden flex items-center justify-between text-white px-4 py-3" style="background-color: var(--brand-accent);">
        <a href="{{ route('dashboard') }}" class="font-bold text-lg tracking-tight">{{ $brandName }}</a>
        <button data-sidebar-toggle aria-expanded="false" class="p-2 -mr-2 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#12A594] rounded" aria-label="Toggle menu">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
            </svg>
        </button>
    </div>

    {{-- Mobile overlay --}}
    <div data-sidebar-overlay class="hidden fixed inset-0 bg-black/40 z-30 lg:hidden"></div>

    {{-- Sidebar --}}
    <aside
        data-mobile-sidebar class="fixed top-0 left-0 h-full w-64 text-[#B9C2D0] z-40 transform transition-transform duration-200 ease-in-out -translate-x-full lg:translate-x-0 flex flex-col shadow-[8px_0_24px_rgba(16,27,45,0.08)]" style="background-color: var(--brand-accent);">

        <div class="px-5 py-5 border-b border-white/10 flex items-center justify-between">
            <a href="{{ route('dashboard') }}" class="flex items-center gap-2">
                @if($brandLogo)
                    <img src="{{ Storage::url($brandLogo) }}" alt="{{ $brandName }} logo" class="h-8 w-8 object-contain rounded-lg bg-white p-0.5">
                @else
                    <span class="w-8 h-8 rounded-lg flex items-center justify-center text-white font-bold text-sm" style="background-color: var(--brand-primary);">Rx</span>
                @endif
                <span class="text-white font-bold text-lg tracking-tight">{{ $brandName }}</span>
            </a>
            <button data-sidebar-close class="lg:hidden text-white/60 hover:text-white focus:outline-none" aria-label="Close menu">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <nav class="flex-1 overflow-y-auto px-3 py-4 space-y-6 text-sm">

            {{-- Overview --}}
            <div>
                <p class="px-3 mb-1 text-xs font-semibold uppercase tracking-wider text-white/30">Overview</p>
                <x-nav-link route="dashboard" label="Dashboard">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                </x-nav-link>
            </div>

            {{-- Operations --}}
            <div>
                <p class="px-3 mb-1 text-xs font-semibold uppercase tracking-wider text-white/30">Operations</p>
                <x-nav-link route="dispense.index" label="Dispense">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </x-nav-link>
                <x-nav-link route="purchases.index" label="Purchases">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                </x-nav-link>
                <x-nav-link route="transfers.index" label="Transfers">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 7h12m0 0l-4-4m4 4l-4 4M16 17H4m0 0l4 4m-4-4l4-4" />
                </x-nav-link>
                <x-nav-link route="till-closings.index" label="Till Close">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                </x-nav-link>
                <x-nav-link route="adjustments.index" label="Adjustments">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3m0 0v3m0-3h3m-3 0H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                </x-nav-link>
                <x-nav-link route="returns.index" label="Returns">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6" />
                </x-nav-link>
            </div>

            {{-- Catalog --}}
            <div>
                <p class="px-3 mb-1 text-xs font-semibold uppercase tracking-wider text-white/30">Catalog</p>
                <x-nav-link route="drugs.index" label="Drugs">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 4H9L8 4zm0 4v10a2 2 0 002 2h4a2 2 0 002-2V8H8z" />
                </x-nav-link>
                <x-nav-link route="products.index" label="Products">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0v10l-8 4-8-4V7m16 0l-8 4-8-4m8 4v10" />
                </x-nav-link>
                <x-nav-link route="product-categories.index" label="Product Categories">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 7h16M4 12h16M4 17h16" />
                </x-nav-link>
                <x-nav-link route="categories.index" label="Categories">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-5.586 5.586a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 10V5a2 2 0 012-2z" />
                </x-nav-link>
                <x-nav-link route="suppliers.index" label="Suppliers">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 13h13V6H3v7zm0 0l-1 5h16l-2-5m-4 5a2 2 0 11-4 0m8 0a2 2 0 11-4 0M16 9h3l2 3v4h-2" />
                </x-nav-link>
                @if(auth()->user()->role === 'owner_admin')
                {{-- PART B — plain language: "Owed to suppliers", never accounting terms --}}
                <a href="{{ route('supplier-owed.index') }}"
                   class="flex items-center gap-3 px-3 py-2 rounded-lg mb-0.5 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-[#12A594]
                   {{ request()->routeIs('supplier-owed.*') || request()->routeIs('supplier-payments.*')
                        ? 'bg-[#12A594]/15 text-white border-l-2 border-[#12A594]'
                        : 'text-[#B9C2D0] hover:bg-white/5 hover:text-white border-l-2 border-transparent' }}">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 flex-shrink-0 {{ request()->routeIs('supplier-owed.*') || request()->routeIs('supplier-payments.*') ? 'text-[#12A594]' : 'text-[#6B7A93]' }}"
                         fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V6m0 10v2m0-2c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span class="text-sm {{ request()->routeIs('supplier-owed.*') || request()->routeIs('supplier-payments.*') ? 'font-medium' : '' }} flex-1">Owed to suppliers</span>
                    @if(($owedBadgeCount ?? 0) > 0)
                        <span class="inline-flex items-center justify-center min-w-[20px] h-5 px-1.5 rounded-full bg-red-500 text-white text-xs font-bold">{{ $owedBadgeCount }}</span>
                    @endif
                </a>
                {{-- PART C — Expenses (owner/admin only, same guard as Owed to suppliers) --}}
                <x-nav-link route="expenses.index" label="Expenses">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 14l6-6m-5.5.5h11m-11 5h11M4.5 5.5h15a1 1 0 011 1v11a1 1 0 01-1 1h-15a1 1 0 01-1-1v-11a1 1 0 011-1z" />
                </x-nav-link>
                <x-nav-link route="expense-categories.index" label="Expense Categories">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-5.586 5.586a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 10V5a2 2 0 012-2z" />
                </x-nav-link>
                @endif
                <x-nav-link route="customers.index" label="Customers">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m5-4a4 4 0 100-8 4 4 0 000 8zm6 4a4 4 0 00-8 0" />
                </x-nav-link>
                <x-nav-link route="branches.index" label="Branches">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 21h18M5 21V7l7-4 7 4v14M9 9h1m4 0h1M9 13h1m4 0h1M9 17h1m4 0h1" />
                </x-nav-link>
            </div>

            {{-- Finance --}}
            <div>
                <p class="px-3 mb-1 text-xs font-semibold uppercase tracking-wider text-white/30">Finance</p>
                <x-nav-link route="accounts.index" label="Chart of Accounts">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V6m0 10v2m0-2c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </x-nav-link>
                <x-nav-link route="journal-entries.index" label="Journal Entries">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </x-nav-link>
                <x-nav-link route="reports.inventory" label="Reports">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 19V6l7 7-7 7zM3 5v14" />
                </x-nav-link>
                <x-nav-link route="reports.export.form" label="Export Excel">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3" />
                </x-nav-link>
            </div>

            {{-- System --}}
            <div>
                <p class="px-3 mb-1 text-xs font-semibold uppercase tracking-wider text-white/30">System</p>
                <x-nav-link route="alerts.index" label="Alerts">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                </x-nav-link>
                <x-nav-link route="users.index" label="Users">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m5-4a4 4 0 100-8 4 4 0 000 8zm6 4a4 4 0 00-8 0" />
                </x-nav-link>
                <x-nav-link route="activity-logs.index" label="Activity Log">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                </x-nav-link>
                {{-- PART D — Business settings (owner/admin only) --}}
                @if(auth()->user()->role === 'owner_admin')
                <x-nav-link route="settings.branding" label="Business Settings">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                </x-nav-link>
                @endif
            </div>
        </nav>

        <div class="border-t border-white/10 px-4 py-4">
            <a href="{{ route('profile.show') }}" class="flex items-center gap-2 mb-3 hover:bg-white/5 -mx-2 px-2 py-1.5 rounded-lg transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-[#12A594]">
                <span class="w-8 h-8 rounded-full bg-white/10 flex items-center justify-center text-white text-xs font-semibold flex-shrink-0">
                    {{ strtoupper(substr(auth()->user()->name ?? 'U', 0, 1)) }}
                </span>
                <span class="text-white/80 text-sm truncate">{{ auth()->user()->name }}</span>
            </a>
            <form action="{{ route('logout') }}" method="POST">
                @csrf
                <button type="submit" class="w-full text-left text-sm text-white/60 hover:text-white flex items-center gap-2 px-1 py-1 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#12A594] rounded">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                    </svg>
                    Logout
                </button>
            </form>
        </div>
    </aside>
    @endauth

    {{-- Main content --}}
    <div class="{{ auth()->check() ? 'lg:pl-64' : '' }}">
        <main class="px-4 py-6 sm:px-6 lg:px-8 max-w-[1440px] mx-auto min-h-screen">
            @if(session('success'))
                <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 rounded-lg mb-4 text-sm">
                    {{ session('success') }}
                </div>
            @endif
            @if(session('error'))
                <div class="bg-amber-50 border border-amber-200 text-amber-800 px-4 py-3 rounded-lg mb-4 text-sm">
                    {{ session('error') }}
                </div>
            @endif
            @if($errors->any())
                <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg mb-4 text-sm">
                    {{ $errors->first() }}
                </div>
            @endif

            @yield('content')
        </main>
    </div>
</body>
</html>
