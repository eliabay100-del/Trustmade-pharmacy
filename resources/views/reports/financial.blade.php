@extends('layouts.app')

@section('content')
<div class="mb-6">
    <h1 class="text-2xl font-bold text-[#1E2530]">Financial Report</h1>
    <p class="text-sm text-[#6B7A93] mt-1">Journal entries recorded for your branch.</p>
</div>

<div class="flex gap-2 mb-4 text-sm">
    <a href="{{ route('reports.inventory') }}" class="px-3 py-1.5 rounded-lg font-medium text-[#6B7A93] hover:bg-gray-100">Inventory</a>
    <a href="{{ route('reports.financial') }}" class="px-3 py-1.5 rounded-lg font-medium bg-[var(--brand-primary)]/10 text-[var(--brand-primary)]">Financial</a>
    <a href="{{ route('reports.sales') }}" class="px-3 py-1.5 rounded-lg font-medium text-[#6B7A93] hover:bg-gray-100">Sales</a>
</div>

<div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden overflow-x-auto">
    <table class="min-w-full divide-y divide-gray-100">
        <thead class="bg-gray-50/50">
            <tr>
                <th class="px-5 py-3 text-left text-xs font-semibold text-[#6B7A93] uppercase tracking-wide">Voucher #</th>
                <th class="px-5 py-3 text-left text-xs font-semibold text-[#6B7A93] uppercase tracking-wide">Date</th>
                <th class="px-5 py-3 text-left text-xs font-semibold text-[#6B7A93] uppercase tracking-wide">Description</th>
                <th class="px-5 py-3 text-left text-xs font-semibold text-[#6B7A93] uppercase tracking-wide">Source</th>
                <th class="px-5 py-3 text-right text-xs font-semibold text-[#6B7A93] uppercase tracking-wide">Debit</th>
                <th class="px-5 py-3 text-right text-xs font-semibold text-[#6B7A93] uppercase tracking-wide">Credit</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
            @forelse($entries as $entry)
                <tr class="hover:bg-gray-50/50">
                    <td class="px-5 py-3.5 text-sm font-mono text-[#6B7A93]">{{ $entry->voucher_number }}</td>
                    <td class="px-5 py-3.5 text-sm text-[#6B7A93]">{{ \Carbon\Carbon::parse($entry->date)->format('M d, Y') }}</td>
                    <td class="px-5 py-3.5 text-sm text-[#1E2530]">{{ $entry->description }}</td>
                    <td class="px-5 py-3.5 text-sm">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-[#6B7A93]">
                            {{ ucfirst($entry->reference_type ?? 'manual') }}
                        </span>
                    </td>
                    <td class="px-5 py-3.5 text-sm text-right font-mono text-[#1E2530]">{{ number_format($entry->total_debit, 2) }}</td>
                    <td class="px-5 py-3.5 text-sm text-right font-mono text-[#1E2530]">{{ number_format($entry->total_credit, 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="px-5 py-12 text-center text-sm text-[#6B7A93]">No journal entries yet.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">
    {{ $entries->links() }}
</div>
@endsection
