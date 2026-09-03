@extends('layouts.app')

@section('content')
<div class="mb-6">
    <h1 class="text-2xl font-bold text-[#1E2530]">Trial Balance</h1>
    <p class="text-sm text-[#6B7A93] mt-1">Sum of debits and credits per account with net balance.</p>
</div>

<div class="flex gap-2 mb-4 text-sm">
    <a href="{{ route('reports.inventory') }}" class="px-3 py-1.5 rounded-lg font-medium text-[#6B7A93] hover:bg-gray-100">Inventory</a>
    <a href="{{ route('reports.financial') }}" class="px-3 py-1.5 rounded-lg font-medium text-[#6B7A93] hover:bg-gray-100">Financial</a>
    <a href="{{ route('reports.sales') }}" class="px-3 py-1.5 rounded-lg font-medium text-[#6B7A93] hover:bg-gray-100">Sales</a>
    <a href="{{ route('reports.trial-balance') }}" class="px-3 py-1.5 rounded-lg font-medium bg-[#12A594]/10 text-[#12A594]">Trial Balance</a>
    <a href="{{ route('reports.income-statement') }}" class="px-3 py-1.5 rounded-lg font-medium text-[#6B7A93] hover:bg-gray-100">Income Statement</a>
</div>

<div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-100">
            <thead class="bg-gray-50/50">
                <tr>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-[#6B7A93] uppercase tracking-wide">Account Code</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-[#6B7A93] uppercase tracking-wide">Account Name</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-[#6B7A93] uppercase tracking-wide">Type</th>
                    <th class="px-5 py-3 text-right text-xs font-semibold text-[#6B7A93] uppercase tracking-wide">Debit</th>
                    <th class="px-5 py-3 text-right text-xs font-semibold text-[#6B7A93] uppercase tracking-wide">Credit</th>
                    <th class="px-5 py-3 text-right text-xs font-semibold text-[#6B7A93] uppercase tracking-wide">Balance</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse($trialBalance as $item)
                    <tr class="hover:bg-gray-50/50">
                        <td class="px-5 py-3.5 text-sm font-mono text-[#6B7A93]">{{ $item['code'] }}</td>
                        <td class="px-5 py-3.5 text-sm text-[#1E2530]">{{ $item['name'] }}</td>
                        <td class="px-5 py-3.5 text-sm">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium 
                                @if($item['type'] === 'asset') bg-blue-100 text-blue-800
                                @elseif($item['type'] === 'liability') bg-orange-100 text-orange-800
                                @elseif($item['type'] === 'equity') bg-purple-100 text-purple-800
                                @elseif($item['type'] === 'revenue') bg-green-100 text-green-800
                                @elseif($item['type'] === 'expense') bg-red-100 text-red-800
                                @else bg-gray-100 text-gray-800
                                @endif">
                                {{ ucfirst($item['type']) }}
                            </span>
                        </td>
                        <td class="px-5 py-3.5 text-sm text-right font-mono text-[#1E2530]">{{ number_format($item['debit'], 2) }}</td>
                        <td class="px-5 py-3.5 text-sm text-right font-mono text-[#1E2530]">{{ number_format($item['credit'], 2) }}</td>
                        <td class="px-5 py-3.5 text-sm text-right font-mono 
                            @if($item['balance'] >= 0) text-green-600
                            @else text-red-600
                            @endif">
                            {{ number_format($item['balance'], 2) }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-5 py-12 text-center text-sm text-[#6B7A93]">No accounts with transactions yet.</td>
                    </tr>
                @endforelse
            </tbody>
            <tfoot class="bg-gray-50/50 font-semibold">
                <tr>
                    <td colspan="3" class="px-5 py-3 text-sm text-[#6B7A93] text-right">Totals:</td>
                    <td class="px-5 py-3 text-right text-sm font-mono text-[#1E2530]">{{ number_format($totalDebit, 2) }}</td>
                    <td class="px-5 py-3 text-right text-sm font-mono text-[#1E2530]">{{ number_format($totalCredit, 2) }}</td>
                    <td class="px-5 py-3 text-right text-sm font-mono text-[#12A594]">{{ number_format($totalDebit - $totalCredit, 2) }}</td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<div class="mt-4 p-4 bg-blue-50 rounded-lg border border-blue-100">
    <p class="text-sm text-blue-800">
        <strong>Note:</strong> In a balanced ledger, total debits should equal total credits. 
        The balance shown is (Debit - Credit) for each account.
    </p>
</div>
@endsection
