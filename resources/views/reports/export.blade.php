@extends('layouts.app')

@section('content')
<div class="max-w-lg mx-auto">
    <h1 class="text-2xl font-bold text-[#1E2530] mb-1">Export Accounting Report</h1>
    <p class="text-sm text-[#6B7A93] mb-6">Download a monthly Excel workbook — sales, purchases, and a profit summary, ready to hand to the owner or an accountant.</p>

    <form method="GET" action="{{ route('reports.export.download') }}" class="bg-white rounded-xl border border-gray-100 shadow-sm p-6 space-y-4">

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-[#1E2530] mb-1.5">Month</label>
                <select name="month" class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[var(--brand-primary)]/30" required>
                    @foreach(range(1, 12) as $m)
                        <option value="{{ $m }}" @selected($m == now()->month)>{{ \Carbon\Carbon::create()->month($m)->format('F') }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-[#1E2530] mb-1.5">Year</label>
                <select name="year" class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[var(--brand-primary)]/30" required>
                    @foreach(range(now()->year, now()->year - 5) as $y)
                        <option value="{{ $y }}" @selected($y == now()->year)>{{ $y }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <p class="text-xs text-[#6B7A93]">The download includes three tabs: a Summary (revenue, cost of goods sold, gross profit), a full Sales list, and a full Purchases list for the period.</p>

        <button type="submit"
                class="inline-flex items-center gap-2 bg-[var(--brand-primary)] hover:bg-[var(--brand-primary-dark)] text-white font-medium text-sm py-2.5 px-5 rounded-lg transition-colors">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3" />
            </svg>
            Download Excel Report
        </button>
    </form>
</div>
@endsection
