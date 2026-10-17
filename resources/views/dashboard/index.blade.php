@extends('layouts.app')

@section('content')
@php
    $hour = (int) now()->format('G');
    $greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
@endphp
<div class="space-y-6">
    <section class="relative overflow-hidden rounded-2xl bg-[#10253b] px-6 py-7 text-white shadow-sm sm:px-8">
        <div class="absolute -right-12 -top-20 h-56 w-56 rounded-full border border-[#58c6b6]/20"></div>
        <div class="absolute right-16 -bottom-24 h-48 w-48 rounded-full border border-white/10"></div>
        <div class="relative flex flex-col gap-6 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="mb-2 text-xs font-semibold uppercase tracking-[.18em] text-[#72d4c5]">Pharmacy operations</p>
                <h1 class="text-3xl font-bold tracking-tight sm:text-4xl">{{ $greeting }}, {{ explode(' ', trim(auth()->user()->name))[0] }}.</h1>
                <p class="mt-2 max-w-xl text-sm text-white/65">Here is the live picture of your pharmacy today.</p>
            </div>
            <div class="shrink-0 rounded-xl border border-white/10 bg-white/10 px-4 py-3 text-left backdrop-blur-sm sm:text-right">
                <p class="text-xs text-white/55">{{ now()->format('l, d M Y') }}</p>
                <p class="mt-1 text-sm font-semibold">{{ \App\Models\Setting::value('business_name', config('pmas.default_business_name')) }}</p>
            </div>
        </div>
    </section>

    <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5" aria-label="Pharmacy summary">
        <a href="{{ route('drugs.index') }}" class="group rounded-xl border border-[#dfe7ed] bg-white p-5 shadow-sm hover:-translate-y-0.5 hover:border-[var(--brand-primary)] hover:shadow-md">
            <span class="text-xs font-semibold uppercase tracking-wide text-[#64748b]">Drugs</span>
            <p class="mt-5 text-3xl font-bold text-[#182230] group-hover:text-[var(--brand-primary)]">{{ $totalDrugs }}</p><p class="mt-1 text-xs text-[#64748b]">View drug catalogue</p>
        </a>
        <a href="{{ route('products.index') }}" class="group rounded-xl border border-[#dfe7ed] bg-white p-5 shadow-sm hover:-translate-y-0.5 hover:border-[var(--brand-primary)] hover:shadow-md">
            <span class="text-xs font-semibold uppercase tracking-wide text-[#64748b]">Products</span>
            <p class="mt-5 text-3xl font-bold text-[#182230] group-hover:text-[var(--brand-primary)]">{{ $totalProducts }}</p><p class="mt-1 text-xs text-[#64748b]">View retail catalogue</p>
        </a>
        <div class="rounded-xl border border-[#dfe7ed] bg-white p-5 shadow-sm"><span class="text-xs font-semibold uppercase tracking-wide text-[#64748b]">Today's sales</span><p class="mt-5 text-2xl font-bold text-[#182230]">ETB {{ number_format($todaySales, 2) }}</p><p class="mt-1 text-xs text-[#64748b]">Collected today</p></div>
        <a href="{{ route('alerts.index') }}" class="group rounded-xl border border-[#dfe7ed] bg-white p-5 shadow-sm hover:-translate-y-0.5 hover:border-amber-400 hover:shadow-md"><span class="text-xs font-semibold uppercase tracking-wide text-[#64748b]">Low stock</span><p class="mt-5 text-3xl font-bold {{ $lowStockCount ? 'text-amber-600' : 'text-[#182230]' }}">{{ $lowStockCount }}</p><p class="mt-1 text-xs text-[#64748b]">Open stock alerts</p></a>
        <a href="{{ route('alerts.index') }}" class="group rounded-xl border border-[#dfe7ed] bg-white p-5 shadow-sm hover:-translate-y-0.5 hover:border-red-400 hover:shadow-md"><span class="text-xs font-semibold uppercase tracking-wide text-[#64748b]">Expiring soon</span><p class="mt-5 text-3xl font-bold {{ $expiringSoonCount ? 'text-red-600' : 'text-[#182230]' }}">{{ $expiringSoonCount }}</p><p class="mt-1 text-xs text-[#64748b]">Next {{ $expiryDays }} days</p></a>
    </section>

    <section class="grid grid-cols-1 gap-6 lg:grid-cols-[1.2fr_.8fr]">
        <div class="rounded-xl border border-[#dfe7ed] bg-white p-6 shadow-sm">
            <div class="flex items-start justify-between"><div><p class="text-xs font-semibold uppercase tracking-wide text-[#64748b]">Today's pulse</p><h2 class="mt-1 text-xl font-bold text-[#182230]">Keep the shelves moving</h2></div><a href="{{ route('reports.sales') }}" class="text-sm font-semibold text-[var(--brand-primary)] hover:underline">Sales report</a></div>
            <div class="mt-7 space-y-5">
                <div><div class="mb-2 flex justify-between text-sm"><span class="text-[#64748b]">Sales, last 7 days</span><strong class="text-[#182230]">ETB {{ number_format($recentSales, 2) }}</strong></div><div class="h-2 overflow-hidden rounded-full bg-[#e8eef1]"><div class="h-full rounded-full bg-[var(--brand-primary)]" style="width: {{ min(100, max(4, (float) $recentSales / max(1, (float) $recentSales + (float) $recentPurchases) * 100)) }}%"></div></div></div>
                <div><div class="mb-2 flex justify-between text-sm"><span class="text-[#64748b]">Purchases, last 7 days</span><strong class="text-[#182230]">ETB {{ number_format($recentPurchases, 2) }}</strong></div><div class="h-2 overflow-hidden rounded-full bg-[#e8eef1]"><div class="h-full rounded-full bg-[#e8a43a]" style="width: {{ min(100, max(4, (float) $recentPurchases / max(1, (float) $recentSales + (float) $recentPurchases) * 100)) }}%"></div></div></div>
            </div>
            <div class="mt-7 grid grid-cols-2 gap-3 border-t border-[#edf1f3] pt-5"><div><p class="text-xs text-[#64748b]">Total units on hand</p><p class="mt-1 text-lg font-bold text-[#182230]">{{ number_format($totalQuantity) }}</p></div><div><p class="text-xs text-[#64748b]">Product sales</p><p class="mt-1 text-lg font-bold text-[#182230]">ETB {{ number_format($productSales, 2) }}</p></div></div>
        </div>
        <div class="rounded-xl border border-[#dfe7ed] bg-white p-6 shadow-sm"><div class="flex items-start justify-between"><div><p class="text-xs font-semibold uppercase tracking-wide text-[#64748b]">Shortcuts</p><h2 class="mt-1 text-xl font-bold text-[#182230]">Common tasks</h2></div><span class="text-xl text-[var(--brand-primary)]">+</span></div><div class="mt-5 grid gap-3 sm:grid-cols-3 lg:grid-cols-1"><a href="{{ route('dispense.create') }}" class="rounded-lg bg-[#e7f7f4] px-4 py-3 text-sm font-semibold text-[#08796f] hover:bg-[#d7f1ed]">Start a sale <span class="float-right">→</span></a><a href="{{ route('purchases.create') }}" class="rounded-lg bg-[#fff2dc] px-4 py-3 text-sm font-semibold text-[#8a5700] hover:bg-[#ffebc8]">Record purchase <span class="float-right">→</span></a><a href="{{ route('adjustments.create') }}" class="rounded-lg bg-[#eef1f5] px-4 py-3 text-sm font-semibold text-[#405166] hover:bg-[#e5eaf0]">Adjust stock <span class="float-right">→</span></a></div></div>
    </section>

    <section class="rounded-xl border border-[#dfe7ed] bg-white p-6 shadow-sm"><div class="flex items-center justify-between"><div><p class="text-xs font-semibold uppercase tracking-wide text-[#64748b]">Attention needed</p><h2 class="mt-1 text-xl font-bold text-[#182230]">Recent alerts</h2></div><a href="{{ route('alerts.index') }}" class="text-sm font-semibold text-[var(--brand-primary)] hover:underline">View all</a></div>@if($expiringSoonCount || $lowStockCount)<div class="mt-5 grid gap-3 sm:grid-cols-2">@if($expiringSoonCount)<div class="flex items-center gap-3 rounded-lg border border-red-100 bg-red-50 px-4 py-3 text-sm text-red-800"><span class="h-2 w-2 rounded-full bg-red-500"></span>{{ $expiringSoonCount }} drug{{ $expiringSoonCount === 1 ? ' expires' : 's expire' }} within {{ $expiryDays }} days</div>@endif @if($lowStockCount)<div class="flex items-center gap-3 rounded-lg border border-amber-100 bg-amber-50 px-4 py-3 text-sm text-amber-800"><span class="h-2 w-2 rounded-full bg-amber-500"></span>{{ $lowStockCount }} item{{ $lowStockCount === 1 ? ' needs' : 's need' }} restocking</div>@endif</div>@else<div class="mt-5 rounded-lg bg-[#e7f7f4] px-4 py-3 text-sm text-[#08796f]">Everything is in good shape. No active alerts.</div>@endif</section>

    {{-- PART B — Owed to suppliers reminders (owner/admin only). Live numbers, no cron. --}}
    @if(!empty($supplierReminders) && (count($supplierReminders['overdue']) || count($supplierReminders['due_soon'])))
        <section class="rounded-xl border border-[#dfe7ed] bg-white p-6 shadow-sm">
            <div class="flex items-center justify-between"><div><p class="text-xs font-semibold uppercase tracking-wide text-[#64748b]">Owed to suppliers</p><h2 class="mt-1 text-xl font-bold text-[#182230]">Payments coming due</h2></div><a href="{{ route('supplier-owed.index') }}" class="text-sm font-semibold text-[var(--brand-primary)] hover:underline">See all</a></div>
            <div class="mt-5 grid gap-3 sm:grid-cols-2">
                @if(count($supplierReminders['overdue']))
                    <a href="{{ route('supplier-owed.index') }}" class="flex items-center gap-3 rounded-lg border border-red-100 bg-red-50 px-4 py-3 text-sm text-red-800 hover:border-red-300 min-h-[44px]"><span class="h-2 w-2 rounded-full bg-red-500"></span>{{ count($supplierReminders['overdue']) }} payment{{ count($supplierReminders['overdue']) === 1 ? '' : 's' }} overdue — ETB {{ number_format(array_sum(array_column($supplierReminders['overdue'], 'balance')), 2) }}</a>
                @endif
                @if(count($supplierReminders['due_soon']))
                    <a href="{{ route('supplier-owed.index') }}" class="flex items-center gap-3 rounded-lg border border-amber-100 bg-amber-50 px-4 py-3 text-sm text-amber-800 hover:border-amber-300 min-h-[44px]"><span class="h-2 w-2 rounded-full bg-amber-500"></span>{{ count($supplierReminders['due_soon']) }} due within {{ $supplierReminders['soon_days'] }} days — ETB {{ number_format(array_sum(array_column($supplierReminders['due_soon'], 'balance')), 2) }}</a>
                @endif
            </div>
        </section>
    @endif
</div>
@endsection
