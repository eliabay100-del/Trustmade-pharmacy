@extends('layouts.app')

@section('content')
<div class="mb-6">
    <h1 class="text-2xl font-bold text-[#1E2530]">Sales Report</h1>
    <p class="text-sm text-[#64748b] mt-1">Every dispensing transaction, filterable by month or year.</p>
</div>

<div class="flex gap-2 mb-4 text-sm">
    <a href="{{ route('reports.inventory') }}" class="px-3 py-1.5 rounded-lg font-medium text-[#64748b] hover:bg-gray-100">Inventory</a>
    <a href="{{ route('reports.financial') }}" class="px-3 py-1.5 rounded-lg font-medium text-[#64748b] hover:bg-gray-100">Financial</a>
    <a href="{{ route('reports.sales') }}" class="px-3 py-1.5 rounded-lg font-medium bg-[var(--brand-primary)]/10 text-[var(--brand-primary)]">Sales</a>
</div>

<form method="GET" action="{{ route('reports.sales') }}" class="bg-white rounded-xl border border-gray-100 shadow-sm p-4 mb-4">
    <div class="flex flex-wrap items-end gap-3">
        <div class="w-32">
            <label for="month" class="block text-xs font-semibold text-[#64748b] uppercase tracking-wide mb-1">Month</label>
            <select name="month" id="month" class="w-full rounded-lg border-gray-200 focus:border-[var(--brand-primary)] focus:ring-[var(--brand-primary)] min-h-[44px]">
                <option value="">All months</option>
                @foreach(['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'] as $index => $name)
                    <option value="{{ $index + 1 }}" @selected((int) $month === $index + 1)>{{ $name }}</option>
                @endforeach
            </select>
        </div>
        <div class="w-32">
            <label for="year" class="block text-xs font-semibold text-[#64748b] uppercase tracking-wide mb-1">Year</label>
            <select name="year" id="year" class="w-full rounded-lg border-gray-200 focus:border-[var(--brand-primary)] focus:ring-[var(--brand-primary)] min-h-[44px]">
                <option value="">All years</option>
                @foreach($years as $option)
                    <option value="{{ $option }}" @selected((int) $year === $option)>{{ $option }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="px-4 min-h-[44px] rounded-lg bg-[var(--brand-primary)] text-white text-sm font-semibold hover:opacity-90">Filter</button>
        @if($month || $year)
            <a href="{{ route('reports.sales') }}" class="px-3 min-h-[44px] inline-flex items-center text-sm font-medium text-[#64748b] hover:text-[#1E2530]">Clear</a>
        @endif
    </div>
    <p class="text-xs text-[#64748b] mt-2">
        Picking a month without a year uses {{ now()->year }}. Leaving both on &ldquo;All&rdquo; shows every sale on record.
    </p>
</form>

@php
    $monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
    if ($month && $year) {
        $periodLabel = $monthNames[$month - 1] . ' ' . $year;
    } elseif ($month) {
        $periodLabel = $monthNames[$month - 1] . ' (all years)';
    } elseif ($year) {
        $periodLabel = 'All of ' . $year;
    } else {
        $periodLabel = 'All time';
    }
@endphp

<div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5 mb-4 inline-block">
    <p class="text-xs font-medium text-[#64748b] uppercase tracking-wide mb-1">Total Sales &middot; {{ $periodLabel }}</p>
    <p class="text-2xl font-bold text-[var(--brand-primary)]">ETB {{ number_format($total, 2) }}</p>
    <p class="text-xs text-[#64748b] mt-1">{{ number_format($sales->total()) }} transaction{{ $sales->total() === 1 ? '' : 's' }} in this period</p>
</div>

<div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden overflow-x-auto">
    <table class="min-w-full divide-y divide-gray-100">
        <thead class="bg-gray-50/50">
            <tr>
                <th class="px-5 py-3 text-left text-xs font-semibold text-[#64748b] uppercase tracking-wide">Date</th>
                <th class="px-5 py-3 text-left text-xs font-semibold text-[#64748b] uppercase tracking-wide">Item</th>
                <th class="px-5 py-3 text-left text-xs font-semibold text-[#64748b] uppercase tracking-wide">Customer</th>
                <th class="px-5 py-3 text-right text-xs font-semibold text-[#64748b] uppercase tracking-wide">Qty</th>
                <th class="px-5 py-3 text-right text-xs font-semibold text-[#64748b] uppercase tracking-wide">Total</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
            @forelse($sales as $sale)
                <tr class="hover:bg-gray-50/50">
                    <td class="px-5 py-3.5 text-sm text-[#64748b]">{{ \Carbon\Carbon::parse($sale->issue_date)->format('M d, Y') }}</td>
                    <td class="px-5 py-3.5 text-sm font-medium text-[#1E2530]">{{ $sale->purchaseBatch->drug->name ?? $sale->purchaseBatch->product->name ?? '—' }}</td>
                    <td class="px-5 py-3.5 text-sm text-[#64748b]">{{ $sale->customer->name ?? 'Walk-in' }}</td>
                    <td class="px-5 py-3.5 text-sm text-right text-[#1E2530]">{{ $sale->quantity_issued }}</td>
                    <td class="px-5 py-3.5 text-sm text-right font-semibold text-[#1E2530]">ETB {{ number_format($sale->total_amount, 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="px-5 py-12 text-center text-sm text-[#64748b]">No sales recorded for this period.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">
    {{ $sales->links() }}
</div>
@endsection
