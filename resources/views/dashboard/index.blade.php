@extends('layouts.app')

@section('content')

<div class="mb-6">
    <h1 class="text-2xl font-bold text-[#1E2530]">Dashboard</h1>
    <p class="text-sm text-[#6B7A93] mt-1">Today's snapshot across your pharmacy.</p>
</div>

{{-- Stat cards — restrained palette matching the sidebar theme --}}
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 mb-8">

    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
        <div class="flex items-center gap-3 mb-2">
            <span class="w-8 h-8 rounded-lg bg-[#12A594]/10 flex items-center justify-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-[#12A594]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 4h8l-1 4H9L8 4zm0 4v10a2 2 0 002 2h4a2 2 0 002-2V8H8z" />
                </svg>
            </span>
            <p class="text-[#6B7A93] text-xs font-medium uppercase tracking-wide">Total Drugs</p>
        </div>
        <p class="text-2xl font-bold text-[#1E2530]">{{ $totalDrugs }}</p>
    </div>

    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
        <div class="flex items-center gap-3 mb-2">
            <span class="w-8 h-8 rounded-lg bg-[#12A594]/10 flex items-center justify-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-[#12A594]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V6m0 10v2m0-2c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </span>
            <p class="text-[#6B7A93] text-xs font-medium uppercase tracking-wide">Stock Value</p>
        </div>
        <p class="text-2xl font-bold text-[#1E2530]">${{ number_format($totalValue, 2) }}</p>
    </div>

    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
        <div class="flex items-center gap-3 mb-2">
            <span class="w-8 h-8 rounded-lg bg-[#101B2D]/5 flex items-center justify-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-[#101B2D]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 19V6l7 7-7 7zM3 5v14" />
                </svg>
            </span>
            <p class="text-[#6B7A93] text-xs font-medium uppercase tracking-wide">Today's Sales</p>
        </div>
        <p class="text-2xl font-bold text-[#1E2530]">${{ number_format($todaySales, 2) }}</p>
    </div>

    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
        <div class="flex items-center gap-3 mb-2">
            <span class="w-8 h-8 rounded-lg {{ $lowStockCount > 0 ? 'bg-amber-50' : 'bg-gray-50' }} flex items-center justify-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 {{ $lowStockCount > 0 ? 'text-amber-600' : 'text-gray-400' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m0 3.75h.007v.008H12v-.008zM21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </span>
            <p class="text-[#6B7A93] text-xs font-medium uppercase tracking-wide">Low Stock</p>
        </div>
        <p class="text-2xl font-bold {{ $lowStockCount > 0 ? 'text-amber-600' : 'text-[#1E2530]' }}">{{ $lowStockCount }}</p>
    </div>

    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
        <div class="flex items-center gap-3 mb-2">
            <span class="w-8 h-8 rounded-lg {{ $expiringSoonCount > 0 ? 'bg-red-50' : 'bg-gray-50' }} flex items-center justify-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 {{ $expiringSoonCount > 0 ? 'text-red-600' : 'text-gray-400' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </span>
            <p class="text-[#6B7A93] text-xs font-medium uppercase tracking-wide">Expiring Soon</p>
        </div>
        <p class="text-2xl font-bold {{ $expiringSoonCount > 0 ? 'text-red-600' : 'text-[#1E2530]' }}">{{ $expiringSoonCount }}</p>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

    {{-- Quick Actions — clean icon buttons instead of solid color blocks --}}
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">
        <h2 class="text-base font-bold text-[#1E2530] mb-4">Quick Actions</h2>
        <div class="space-y-2">
            <a href="{{ route('purchases.create') }}"
               class="flex items-center gap-3 px-4 py-3 rounded-lg border border-gray-200 hover:border-[#12A594] hover:bg-[#12A594]/5 transition-colors group">
                <span class="w-8 h-8 rounded-lg bg-[#12A594]/10 flex items-center justify-center flex-shrink-0">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-[#12A594]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                    </svg>
                </span>
                <span class="font-medium text-[#1E2530] group-hover:text-[#12A594]">Record New Purchase</span>
            </a>
            <a href="{{ route('dispense.create') }}"
               class="flex items-center gap-3 px-4 py-3 rounded-lg border border-gray-200 hover:border-[#12A594] hover:bg-[#12A594]/5 transition-colors group">
                <span class="w-8 h-8 rounded-lg bg-[#12A594]/10 flex items-center justify-center flex-shrink-0">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-[#12A594]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                </span>
                <span class="font-medium text-[#1E2530] group-hover:text-[#12A594]">Dispense Drug</span>
            </a>
            <a href="{{ route('transfers.create') }}"
               class="flex items-center gap-3 px-4 py-3 rounded-lg border border-gray-200 hover:border-[#12A594] hover:bg-[#12A594]/5 transition-colors group">
                <span class="w-8 h-8 rounded-lg bg-[#12A594]/10 flex items-center justify-center flex-shrink-0">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-[#12A594]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 7h12m0 0l-4-4m4 4l-4 4M16 17H4m0 0l4 4m-4-4l4-4" />
                    </svg>
                </span>
                <span class="font-medium text-[#1E2530] group-hover:text-[#12A594]">Transfer Stock</span>
            </a>
        </div>
    </div>

    {{-- Recent Alerts --}}
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">
        <h2 class="text-base font-bold text-[#1E2530] mb-4">Recent Alerts</h2>

        @if($expiringSoonCount > 0 || $lowStockCount > 0)
            <ul class="space-y-3">
                @if($expiringSoonCount > 0)
                    <li class="flex items-start gap-3">
                        <span class="w-6 h-6 rounded-full bg-red-50 flex items-center justify-center flex-shrink-0 mt-0.5">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </span>
                        <span class="text-sm text-[#1E2530]">
                            <span class="font-semibold text-red-600">{{ $expiringSoonCount }}</span> drug{{ $expiringSoonCount === 1 ? '' : 's' }} expiring within 30 days
                        </span>
                    </li>
                @endif
                @if($lowStockCount > 0)
                    <li class="flex items-start gap-3">
                        <span class="w-6 h-6 rounded-full bg-amber-50 flex items-center justify-center flex-shrink-0 mt-0.5">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m0 3.75h.007v.008H12v-.008zM21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </span>
                        <span class="text-sm text-[#1E2530]">
                            <span class="font-semibold text-amber-600">{{ $lowStockCount }}</span> drug{{ $lowStockCount === 1 ? '' : 's' }} below minimum stock level
                        </span>
                    </li>
                @endif
            </ul>
            <a href="{{ route('alerts.index') }}" class="inline-block mt-4 text-sm font-medium text-[#12A594] hover:underline">
                View all alerts →
            </a>
        @else
            <div class="text-center py-6">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-10 h-10 text-gray-300 mx-auto mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <p class="text-sm text-[#6B7A93]">No active alerts. All systems normal.</p>
            </div>
        @endif
    </div>
</div>

@endsection
