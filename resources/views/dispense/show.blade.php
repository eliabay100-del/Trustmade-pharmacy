@extends('layouts.app')

@section('content')
<div class="max-w-lg mx-auto">
    <div class="flex justify-between items-start mb-6">
        <div>
            <h1 class="text-2xl font-bold text-[#1E2530]">Order #{{ $order->id }}</h1>
            <p class="text-sm text-[#6B7A93] mt-1">{{ $order->created_at->format('M d, Y g:i A') }}</p>
        </div>
        <div class="flex flex-col items-end gap-2">
            <a href="{{ route('dispense.index') }}" class="text-sm text-[#12A594] hover:underline">← Back to Dispense</a>
            <a href="{{ route('dispense.receipt', ['order' => $order->id, 'autoprint' => 1]) }}"
               class="inline-flex items-center bg-[#12A594] hover:bg-[#0e8a7d] text-white font-medium text-sm py-2 px-4 rounded-lg transition-colors">
                Print Receipt
            </a>
            <a href="{{ route('returns.customer.create', ['order_id' => $order->id]) }}"
               class="text-sm text-[#6B7A93] hover:text-[#1E2530] hover:underline">
                Return items
            </a>
        </div>
    </div>

    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">
        <div class="flex justify-between text-sm mb-4 pb-4 border-b border-gray-100">
            <div>
                <p class="text-[#6B7A93]">Customer</p>
                <p class="font-medium text-[#1E2530]">{{ $order->customer->name ?? 'Walk-in' }}</p>
            </div>
            <div class="text-right">
                <p class="text-[#6B7A93]">Branch</p>
                <p class="font-medium text-[#1E2530]">{{ $order->branch->name ?? '—' }}</p>
            </div>
        </div>

        <table class="w-full text-sm mb-4">
            <thead>
                <tr class="text-xs text-[#6B7A93] uppercase tracking-wide">
                    <th class="text-left pb-2">Drug</th>
                    <th class="text-right pb-2">Qty</th>
                    <th class="text-right pb-2">Unit Price</th>
                    <th class="text-right pb-2">Total</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @foreach($order->items as $item)
                    <tr>
                        <td class="py-2 text-[#1E2530]">{{ $item->purchaseBatch->drug->name ?? '—' }}</td>
                        <td class="py-2 text-right text-[#6B7A93]">{{ $item->quantity_issued }}</td>
                        <td class="py-2 text-right text-[#6B7A93]">${{ number_format($item->selling_price_per_unit, 2) }}</td>
                        <td class="py-2 text-right text-[#1E2530]">${{ number_format($item->total_amount, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="flex justify-between items-center pt-3 border-t border-gray-100">
            <span class="font-bold text-[#1E2530]">Total</span>
            <span class="font-bold text-lg text-[#12A594]">${{ number_format($order->total_amount, 2) }}</span>
        </div>

        @if($order->payments->isNotEmpty())
            <div class="mt-4 pt-4 border-t border-gray-100">
                <p class="text-xs font-semibold text-[#6B7A93] uppercase tracking-wide mb-2">Payments</p>
                <ul class="space-y-1 text-sm">
                    @foreach($order->payments as $payment)
                        <li class="flex justify-between">
                            <span class="text-[#6B7A93]">{{ $payment->methodLabel() }}</span>
                            <span class="text-[#1E2530]">${{ number_format($payment->amount, 2) }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
</div>
@endsection
