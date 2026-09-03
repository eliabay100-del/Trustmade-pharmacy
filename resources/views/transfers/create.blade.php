@extends('layouts.app')

@section('content')
<div class="max-w-lg mx-auto">
    <h1 class="text-2xl font-bold text-[#1E2530] mb-1">Transfer Stock</h1>
    <p class="text-sm text-[#6B7A93] mb-6">Move drug stock from one branch to another.</p>

    <form method="POST" action="{{ route('transfers.store') }}" class="bg-white rounded-xl border border-gray-100 shadow-sm p-6 space-y-4">
        @csrf

        <div>
            <label class="block text-sm font-medium text-[#1E2530] mb-1.5">Item</label>
            <select name="item_type" id="transfer-type" class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm"><option value="drug">Drug</option><option value="product">Product</option></select>
            <select name="item_id" id="transfer-drug" class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#12A594]/30" required>
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
            <select id="transfer-product" class="hidden w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm mt-2"><option value="">-- Select Product --</option>@foreach($products as $product)<option value="{{ $product->id }}">{{ $product->name }} ({{ $product->sku }})</option>@endforeach</select>
        </div>

        <div class="grid grid-cols-2 gap-4 items-end">
            <div>
                <label class="block text-sm font-medium text-[#1E2530] mb-1.5">From Branch</label>
                <select name="from_branch_id" class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#12A594]/30" required>
                    <option value="">-- Select --</option>
                    @foreach($branches as $branch)
                        <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-[#1E2530] mb-1.5">To Branch</label>
                <select name="to_branch_id" class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#12A594]/30" required>
                    <option value="">-- Select --</option>
                    @foreach($branches as $branch)
                        <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        @if($branches->count() < 2)
            <p class="text-sm text-amber-600">You need at least two branches to transfer stock — <a href="{{ route('branches.create') }}" class="underline">add another branch</a>.</p>
        @endif

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-[#1E2530] mb-1.5">Quantity</label>
                <input type="number" min="1" name="quantity" value="{{ old('quantity') }}"
                       class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#12A594]/30" required>
            </div>
            <div>
                <label class="block text-sm font-medium text-[#1E2530] mb-1.5">Unit</label>
                <select name="quantity_unit" id="transfer-unit" class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#12A594]/30" required>
                    <option value="tablet" @selected(old('quantity_unit', 'tablet') === 'tablet')>Base unit</option>
                    <option value="strip" @selected(old('quantity_unit') === 'strip')>Strip</option>
                    <option value="pack" @selected(old('quantity_unit') === 'pack')>Pack</option>
                </select>
            </div>
        </div>
        <p id="transfer-unit-hint" class="text-xs text-[#6B7A93] -mt-2">Quantity is converted to base units before the existing FEFO transfer runs.</p>

        <div class="flex gap-3 pt-2">
            <button type="submit" class="bg-[#12A594] hover:bg-[#0e8a7d] text-white font-medium text-sm py-2.5 px-5 rounded-lg transition-colors">
                Transfer Stock
            </button>
            <a href="{{ route('transfers.index') }}" class="text-[#6B7A93] hover:text-[#1E2530] text-sm py-2.5 px-4">Cancel</a>
        </div>
    </form>
</div>

<script>
    (function () {
        const drugSelect = document.getElementById('transfer-drug');
        const productSelect = document.getElementById('transfer-product');
        const typeSelect = document.getElementById('transfer-type');
        const unitSelect = document.getElementById('transfer-unit');
        const hint = document.getElementById('transfer-unit-hint');
        function sync() {
            const isProduct = typeSelect.value === 'product';
            drugSelect.classList.toggle('hidden', isProduct);
            productSelect.classList.toggle('hidden', !isProduct);
            drugSelect.required = !isProduct;
            productSelect.required = isProduct;
            drugSelect.name = isProduct ? '' : 'item_id';
            productSelect.name = isProduct ? 'item_id' : '';
            const opt = drugSelect.options[drugSelect.selectedIndex];
            const base = opt && opt.dataset.base ? opt.dataset.base : 'tablet';
            const strip = opt && opt.dataset.strip ? opt.dataset.strip : '1';
            const pack = opt && opt.dataset.pack ? opt.dataset.pack : '1';
            hint.textContent = '1 strip = ' + strip + ' ' + base + ', 1 pack = ' + pack + ' ' + base + '.';
        }
        drugSelect.addEventListener('change', sync);
        typeSelect.addEventListener('change', sync);
        unitSelect.addEventListener('change', sync);
        sync();
    })();
</script>
@endsection
