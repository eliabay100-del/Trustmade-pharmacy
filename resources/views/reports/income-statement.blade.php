@extends('layouts.app')

@section('content')
<div class="mb-6">
    <h1 class="text-2xl font-bold text-[#1E2530]">Income Statement</h1>
    <p class="text-sm text-[#6B7A93] mt-1">Revenue minus COGS and expenses.</p>
</div>

<div class="flex gap-2 mb-4 text-sm">
    <a href="{{ route('reports.inventory') }}" class="px-3 py-1.5 rounded-lg font-medium text-[#6B7A93] hover:bg-gray-100">Inventory</a>
    <a href="{{ route('reports.financial') }}" class="px-3 py-1.5 rounded-lg font-medium text-[#6B7A93] hover:bg-gray-100">Financial</a>
    <a href="{{ route('reports.sales') }}" class="px-3 py-1.5 rounded-lg font-medium text-[#6B7A93] hover:bg-gray-100">Sales</a>
    <a href="{{ route('reports.trial-balance') }}" class="px-3 py-1.5 rounded-lg font-medium text-[#6B7A93] hover:bg-gray-100">Trial Balance</a>
    <a href="{{ route('reports.income-statement') }}" class="px-3 py-1.5 rounded-lg font-medium bg-[#12A594]/10 text-[#12A594]">Income Statement</a>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Revenue Section -->
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 bg-green-50/50">
            <h2 class="text-lg font-semibold text-green-800">Revenue</h2>
        </div>
        <div class="p-5">
            <table class="w-full">
                <thead>
                    <tr>
                        <th class="text-left text-xs font-semibold text-[#6B7A93] uppercase">Account</th>
                        <th class="text-right text-xs font-semibold text-[#6B7A93] uppercase">Amount</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @forelse($revenue as $item)
                        <tr>
                            <td class="py-2 text-sm text-[#1E2530]">
                                <span class="font-mono text-xs text-[#6B7A93]">{{ $item['code'] }}</span>
                                {{ $item['name'] }}
                            </td>
                            <td class="py-2 text-sm text-right font-mono text-green-600">{{ number_format($item['amount'], 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="2" class="py-4 text-center text-sm text-[#6B7A93]">No revenue recorded</td>
                        </tr>
                    @endforelse
                </tbody>
                <tfoot class="border-t border-gray-100">
                    <tr>
                        <td class="pt-3 text-sm font-semibold text-[#6B7A93]">Total Revenue</td>
                        <td class="pt-3 text-sm text-right font-mono font-semibold text-green-600">{{ number_format($totalRevenue, 2) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <!-- COGS Section -->
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 bg-orange-50/50">
            <h2 class="text-lg font-semibold text-orange-800">Cost of Goods Sold</h2>
        </div>
        <div class="p-5">
            <table class="w-full">
                <thead>
                    <tr>
                        <th class="text-left text-xs font-semibold text-[#6B7A93] uppercase">Account</th>
                        <th class="text-right text-xs font-semibold text-[#6B7A93] uppercase">Amount</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @forelse($cogs as $item)
                        <tr>
                            <td class="py-2 text-sm text-[#1E2530]">
                                <span class="font-mono text-xs text-[#6B7A93]">{{ $item['code'] }}</span>
                                {{ $item['name'] }}
                            </td>
                            <td class="py-2 text-sm text-right font-mono text-orange-600">{{ number_format($item['amount'], 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="2" class="py-4 text-center text-sm text-[#6B7A93]">No COGS recorded</td>
                        </tr>
                    @endforelse
                </tbody>
                <tfoot class="border-t border-gray-100">
                    <tr>
                        <td class="pt-3 text-sm font-semibold text-[#6B7A93]">Total COGS</td>
                        <td class="pt-3 text-sm text-right font-mono font-semibold text-orange-600">{{ number_format($totalCogs, 2) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <!-- Gross Profit -->
    <div class="bg-gradient-to-br from-green-50 to-emerald-50 rounded-xl border border-green-100 shadow-sm p-5 flex flex-col justify-center">
        <h3 class="text-sm font-medium text-green-800 uppercase tracking-wide">Gross Profit</h3>
        <p class="text-3xl font-bold text-green-700 mt-2">{{ number_format($grossProfit, 2) }}</p>
        <p class="text-xs text-green-600 mt-1">Revenue - COGS</p>
    </div>
</div>

<!-- Expenses Section -->
<div class="mt-6 bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
    <div class="px-5 py-4 border-b border-gray-100 bg-red-50/50">
        <h2 class="text-lg font-semibold text-red-800">Operating Expenses</h2>
    </div>
    <div class="p-5">
        <table class="w-full">
            <thead>
                <tr>
                    <th class="text-left text-xs font-semibold text-[#6B7A93] uppercase">Account</th>
                    <th class="text-left text-xs font-semibold text-[#6B7A93] uppercase">Code</th>
                    <th class="text-right text-xs font-semibold text-[#6B7A93] uppercase">Amount</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse($expenses as $item)
                    <tr>
                        <td class="py-2 text-sm text-[#1E2530]">{{ $item['name'] }}</td>
                        <td class="py-2 text-sm font-mono text-[#6B7A93]">{{ $item['code'] }}</td>
                        <td class="py-2 text-sm text-right font-mono text-red-600">{{ number_format($item['amount'], 2) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="py-4 text-center text-sm text-[#6B7A93]">No operating expenses recorded</td>
                    </tr>
                @endforelse
            </tbody>
            <tfoot class="border-t border-gray-100">
                <tr>
                    <td colspan="2" class="pt-3 text-sm font-semibold text-[#6B7A93]">Total Operating Expenses</td>
                    <td class="pt-3 text-sm text-right font-mono font-semibold text-red-600">{{ number_format($totalExpenses, 2) }}</td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<!-- Net Income Summary -->
<div class="mt-6 bg-gradient-to-r from-[#1E2530] to-[#12A594] rounded-xl shadow-lg p-6 text-white">
    <div class="flex items-center justify-between">
        <div>
            <h3 class="text-sm font-medium text-white/80 uppercase tracking-wide">Net Income</h3>
            <p class="text-4xl font-bold mt-2">{{ number_format($netIncome, 2) }}</p>
            <p class="text-sm text-white/70 mt-1">Gross Profit - Operating Expenses</p>
        </div>
        <div class="text-right">
            <div class="text-sm text-white/70">Profit Margin</div>
            <div class="text-2xl font-bold">
                @if($totalRevenue > 0)
                    {{ number_format(($netIncome / $totalRevenue) * 100, 1) }}%
                @else
                    0%
                @endif
            </div>
        </div>
    </div>
</div>

@if($netIncome < 0)
<div class="mt-4 p-4 bg-red-50 rounded-lg border border-red-100">
    <p class="text-sm text-red-800">
        <strong>Warning:</strong> The business is operating at a loss. Review expenses and revenue streams.
    </p>
</div>
@endif
@endsection
