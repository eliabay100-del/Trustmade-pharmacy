@extends('layouts.app')

@section('content')
<div class="max-w-lg mx-auto">
    <h1 class="text-2xl font-bold text-[#1E2530] mb-1">Customer return</h1>
    <p class="text-sm text-[#6B7A93] mb-6">Restocks the original batch and reverses the sale journals. One line per submit.</p>

    <form method="GET" action="{{ route('returns.customer.create') }}" class="bg-white rounded-xl border border-gray-100 shadow-sm p-6 mb-4 space-y-4">
        <div>
            <label class="block text-sm font-medium text-[#1E2530] mb-1.5">Order</label>
            <select name="order_id" class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm" onchange="this.form.submit()" required>
                <option value="">-- Select order --</option>
                @foreach($orders as $row)
                    <option value="{{ $row->id }}" @selected((int) ($order->id ?? 0) === $row->id)>
                        #{{ $row->id }} · {{ $row->customer->name ?? 'Walk-in' }} · {{ $row->created_at->format('M d, Y') }} · ${{ number_format($row->total_amount, 2) }}
                    </option>
                @endforeach
            </select>
        </div>
    </form>

    @if($order)
        <form method="POST" action="{{ route('returns.customer.store') }}" class="bg-white rounded-xl border border-gray-100 shadow-sm p-6 space-y-4">
            @csrf

            <div>
                <label class="block text-sm font-medium text-[#1E2530] mb-1.5">Sale line</label>
                <select name="issued_item_id" id="return-item" class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm" required>
                    <option value="">-- Select item --</option>
                    @foreach($items as $item)
                        @php $left = $item->quantityReturnable(); @endphp
                        <option value="{{ $item->id }}"
                                @selected((int) old('issued_item_id') === $item->id)
                                data-left="{{ $left }}"
                                data-base="{{ $item->purchaseBatch->drug?->baseUnitLabel() ?? 'tablet' }}"
                                data-strip="{{ $item->purchaseBatch->drug?->unitsPerStrip() ?? 1 }}"
                                data-pack="{{ $item->purchaseBatch->drug?->unitsPerPack() ?? 1 }}"
                                @disabled($left < 1)>
                            {{ $item->purchaseBatch->drug->name ?? 'Drug' }}
                            — {{ $left }} of {{ $item->quantity_issued }} {{ $item->purchaseBatch->drug?->baseUnitLabel() ?? 'unit' }} still returnable
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-[#1E2530] mb-1.5">Quantity</label>
                    <input type="number" min="1" name="quantity" value="{{ old('quantity') }}"
                           class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-[#1E2530] mb-1.5">Unit</label>
                    <select name="quantity_unit" class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm" required>
                        <option value="tablet" @selected(old('quantity_unit', 'tablet') === 'tablet')>Base unit</option>
                        <option value="strip" @selected(old('quantity_unit') === 'strip')>Strip</option>
                        <option value="pack" @selected(old('quantity_unit') === 'pack')>Pack</option>
                    </select>
                </div>
            </div>
            <p id="return-hint" class="text-xs text-[#6B7A93] -mt-2">Refund is qty × original unit selling price. Stock goes back on the same batch.</p>

            <div>
                <label class="block text-sm font-medium text-[#1E2530] mb-1.5">Refund method</label>
                <select name="refund_method" class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm" required>
                    @foreach($paymentMethods as $value => $label)
                        <option value="{{ $value }}" @selected(old('refund_method', $defaultMethod) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-[#1E2530] mb-1.5">Notes</label>
                <input type="text" name="notes" value="{{ old('notes') }}" maxlength="255"
                       class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm" placeholder="Optional">
            </div>

            <div class="flex gap-3 pt-2">
                <button type="submit" class="bg-[#12A594] hover:bg-[#0e8a7d] text-white font-medium text-sm py-2.5 px-5 rounded-lg transition-colors">
                    Record return
                </button>
                <a href="{{ route('returns.index') }}" class="text-[#6B7A93] hover:text-[#1E2530] text-sm py-2.5 px-4">Cancel</a>
            </div>
        </form>
    @endif
</div>

@if($order)
<script>
    (function () {
        const select = document.getElementById('return-item');
        const hint = document.getElementById('return-hint');
        function sync() {
            const opt = select.options[select.selectedIndex];
            if (!opt || !opt.dataset.left) return;
            hint.textContent = opt.dataset.left + ' ' + (opt.dataset.base || 'unit') + ' still returnable. 1 strip = '
                + (opt.dataset.strip || '1') + ', 1 pack = ' + (opt.dataset.pack || '1') + '.';
        }
        select.addEventListener('change', sync);
        sync();
    })();
</script>
@endif
@endsection
