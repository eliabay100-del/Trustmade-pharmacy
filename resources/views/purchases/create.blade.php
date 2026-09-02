@extends('layouts.app')

@section('content')
<div class="max-w-lg mx-auto">
    <h1 class="text-2xl font-bold text-[#1E2530] mb-1">Record Purchase</h1>
    <p class="text-sm text-[#6B7A93] mb-6">Add incoming stock from a supplier.</p>

    <form method="POST" action="{{ route('purchases.store') }}" class="bg-white rounded-xl border border-gray-100 shadow-sm p-6 space-y-4">
        @csrf

        <div>
            <label class="block text-sm font-medium text-[#1E2530] mb-1.5">Drug</label>
            <select name="drug_id" id="purchase-drug" class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#12A594]/30 focus:border-[#12A594]" required>
                <option value="">-- Select Drug --</option>
                @foreach($drugs as $drug)
                    <option value="{{ $drug->id }}"
                            data-base="{{ $drug->baseUnitLabel() }}"
                            data-strip="{{ $drug->unitsPerStrip() }}"
                            data-pack="{{ $drug->unitsPerPack() }}">
                        {{ $drug->name }} @if($drug->strength) ({{ $drug->strength }}) @endif
                    </option>
                @endforeach
            </select>
            @if($drugs->isEmpty())
                <p class="text-sm text-red-600 mt-1.5">No drugs yet — <a href="{{ route('drugs.create') }}" class="underline">add one first</a>.</p>
            @endif
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-[#1E2530] mb-1.5">Supplier</label>
                <select name="supplier_id" class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#12A594]/30" required>
                    <option value="">-- Select --</option>
                    @foreach($suppliers as $supplier)
                        <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                    @endforeach
                </select>
                @if($suppliers->isEmpty())
                    <p class="text-sm text-red-600 mt-1.5">No suppliers yet — <a href="{{ route('suppliers.create') }}" class="underline">add one</a>.</p>
                @endif
            </div>
            <div>
                <label class="block text-sm font-medium text-[#1E2530] mb-1.5">Branch</label>
                <select name="branch_id" class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#12A594]/30" required>
                    <option value="">-- Select --</option>
                    @foreach($branches as $branch)
                        <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-[#1E2530] mb-1.5">Batch Number</label>
            <input type="text" name="batch_number" value="{{ old('batch_number') }}"
                   class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#12A594]/30" required>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-[#1E2530] mb-1.5">Quantity received</label>
                <input type="number" min="1" name="quantity_received" value="{{ old('quantity_received') }}"
                       class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#12A594]/30" required>
            </div>
            <div>
                <label class="block text-sm font-medium text-[#1E2530] mb-1.5">Unit</label>
                <select name="quantity_unit" id="purchase-unit" class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#12A594]/30" required>
                    <option value="tablet" @selected(old('quantity_unit', 'tablet') === 'tablet')>Base unit</option>
                    <option value="strip" @selected(old('quantity_unit') === 'strip')>Strip</option>
                    <option value="pack" @selected(old('quantity_unit') === 'pack')>Pack</option>
                </select>
            </div>
        </div>
        <p id="purchase-unit-hint" class="text-xs text-[#6B7A93] -mt-2">Stock is saved in base units. Cost and selling price are per the unit you pick.</p>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label id="cost-label" class="block text-sm font-medium text-[#1E2530] mb-1.5">Cost per unit</label>
                <input type="number" step="0.01" name="cost_per_unit" value="{{ old('cost_per_unit') }}"
                       class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#12A594]/30" required>
            </div>
            <div>
                <label id="price-label" class="block text-sm font-medium text-[#1E2530] mb-1.5">Selling price per unit</label>
                <input type="number" step="0.01" name="selling_price_per_unit" value="{{ old('selling_price_per_unit') }}"
                       class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#12A594]/30" required>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-[#1E2530] mb-1.5">Purchase Date</label>
                <input type="date" name="purchase_date" value="{{ old('purchase_date', now()->toDateString()) }}"
                       class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#12A594]/30" required>
            </div>
            <div>
                <label class="block text-sm font-medium text-[#1E2530] mb-1.5">Expiry Date</label>
                <input type="date" name="expiry_date" value="{{ old('expiry_date') }}"
                       class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#12A594]/30" required>
            </div>
        </div>

        <div class="flex gap-3 pt-2">
            <button type="submit" class="bg-[#12A594] hover:bg-[#0e8a7d] text-white font-medium text-sm py-2.5 px-5 rounded-lg transition-colors">
                Save Purchase
            </button>
            <a href="{{ route('purchases.index') }}" class="text-[#6B7A93] hover:text-[#1E2530] text-sm py-2.5 px-4">Cancel</a>
        </div>
    </form>
</div>

<script>
    (function () {
        const drugSelect = document.getElementById('purchase-drug');
        const unitSelect = document.getElementById('purchase-unit');
        const hint = document.getElementById('purchase-unit-hint');
        const costLabel = document.getElementById('cost-label');
        const priceLabel = document.getElementById('price-label');

        function packing() {
            const opt = drugSelect.options[drugSelect.selectedIndex];
            return {
                base: opt && opt.dataset.base ? opt.dataset.base : 'tablet',
                strip: parseInt(opt && opt.dataset.strip ? opt.dataset.strip : '1', 10) || 1,
                pack: parseInt(opt && opt.dataset.pack ? opt.dataset.pack : '1', 10) || 1,
            };
        }

        function sync() {
            const p = packing();
            const unit = unitSelect.value;
            const unitName = unit === 'pack' ? 'pack' : (unit === 'strip' ? 'strip' : p.base);
            costLabel.textContent = 'Cost per ' + unitName;
            priceLabel.textContent = 'Selling price per ' + unitName;
            hint.textContent = '1 strip = ' + p.strip + ' ' + p.base + ', 1 pack = ' + p.pack + ' ' + p.base + '. Stock is saved in ' + p.base + 's.';
        }

        drugSelect.addEventListener('change', sync);
        unitSelect.addEventListener('change', sync);
        sync();
    })();
</script>
@endsection
