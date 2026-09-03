@extends('layouts.app')

@section('content')
<div class="max-w-5xl mx-auto">
    <h1 class="text-2xl font-bold text-[#1E2530] mb-1">Record Purchase</h1>
    <p class="text-sm text-[#6B7A93] mb-6">Add one or more Drugs or Products from the same supplier.</p>

    <form method="POST" action="{{ route('purchases.store') }}" class="space-y-5">
        @csrf
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6 space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div><label class="block text-sm font-medium mb-1.5">Supplier</label><select name="supplier_id" class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm" required><option value="">-- Select supplier --</option>@foreach($suppliers as $supplier)<option value="{{ $supplier->id }}">{{ $supplier->name }}</option>@endforeach</select></div>
                <div><label class="block text-sm font-medium mb-1.5">Branch</label><select name="branch_id" class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm" required><option value="">-- Select branch --</option>@foreach($branches as $branch)<option value="{{ $branch->id }}">{{ $branch->name }}</option>@endforeach</select></div>
            </div>
            <div><label class="block text-sm font-medium mb-1.5">Purchase date</label><input type="date" name="purchase_date" value="{{ old('purchase_date', now()->toDateString()) }}" class="w-full sm:w-1/2 border border-gray-200 rounded-lg px-3 py-2.5 text-sm" required></div>
        </div>

        <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">
            <div class="flex justify-between items-center mb-3"><h2 class="font-semibold">Purchase items</h2><button type="button" id="add-purchase-line" class="text-[#12A594] text-sm font-semibold">+ Add item</button></div>
            <div id="purchase-lines" class="space-y-4">
                <div class="purchase-line border border-gray-100 rounded-lg p-4 space-y-3" data-index="0">
                    <div class="flex items-start gap-3">
                    <div class="grid flex-1 grid-cols-1 sm:grid-cols-4 gap-3">
                        <select name="items[0][item_type]" class="item-type border border-gray-200 rounded-lg px-3 py-2.5 text-sm" required><option value="drug">Drug</option><option value="product">Product</option></select>
                        <select name="items[0][item_id]" class="drug-choice border border-gray-200 rounded-lg px-3 py-2.5 text-sm" required><option value="">-- Select drug --</option>@foreach($drugs as $drug)<option value="{{ $drug->id }}">{{ $drug->name }} @if($drug->strength) ({{ $drug->strength }}) @endif</option>@endforeach</select>
                        <select name="items[0][item_id]" class="product-choice hidden border border-gray-200 rounded-lg px-3 py-2.5 text-sm"><option value="">-- Select product --</option>@foreach($products as $product)<option value="{{ $product->id }}">{{ $product->name }} ({{ $product->sku }})</option>@endforeach</select>
                        <input name="items[0][batch_number]" placeholder="Batch / reference" class="border border-gray-200 rounded-lg px-3 py-2.5 text-sm" required>
                    </div>
                    <button type="button" class="remove-purchase-line hidden mt-2 rounded-lg px-2 py-1 text-xl leading-none text-red-500 hover:bg-red-50 hover:text-red-700" title="Remove purchase item" aria-label="Remove purchase item">&times;</button>
                    </div>
                    <div class="grid grid-cols-2 sm:grid-cols-5 gap-3">
                        <input type="number" min="1" name="items[0][quantity_received]" placeholder="Quantity" class="border border-gray-200 rounded-lg px-3 py-2.5 text-sm" required>
                        <select name="items[0][quantity_unit]" class="border border-gray-200 rounded-lg px-3 py-2.5 text-sm"><option value="tablet">Base unit</option><option value="strip">Strip</option><option value="pack">Pack</option></select>
                        <input type="number" min="0" step="0.01" name="items[0][cost_per_unit]" placeholder="Cost / unit" class="border border-gray-200 rounded-lg px-3 py-2.5 text-sm" required>
                        <input type="number" min="0" step="0.01" name="items[0][selling_price_per_unit]" placeholder="Selling / unit" class="border border-gray-200 rounded-lg px-3 py-2.5 text-sm" required>
                        <label class="text-xs font-medium text-[#6B7A93]">Expiry date (optional)<input type="date" name="items[0][expiry_date]" class="mt-1 w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm"></label>
                    </div>
                </div>
            </div>
            <p class="text-xs text-[#6B7A93] mt-3">Drug quantities support base units, strips, and packs. Product quantities are individual units. Product expiry is optional.</p>
        </div>

        <div class="flex gap-3"><button class="bg-[#12A594] text-white font-medium text-sm py-2.5 px-5 rounded-lg">Save Purchase</button><a href="{{ route('purchases.index') }}" class="text-[#6B7A93] py-2.5 px-4">Cancel</a></div>
    </form>
</div>

<script>
(() => {
    const lines = document.getElementById('purchase-lines');
    let index = 1;
    const bind = (line) => {
        const type = line.querySelector('.item-type');
        const drug = line.querySelector('.drug-choice');
        const product = line.querySelector('.product-choice');
        const sync = () => {
            const isProduct = type.value === 'product';
            drug.classList.toggle('hidden', isProduct);
            product.classList.toggle('hidden', !isProduct);
            drug.required = !isProduct;
            product.required = isProduct;
            drug.name = isProduct ? '' : `items[${line.dataset.index}][item_id]`;
            product.name = isProduct ? `items[${line.dataset.index}][item_id]` : '';
        };
        type.addEventListener('change', sync);
        sync();
    };
    bind(lines.firstElementChild);
    document.getElementById('add-purchase-line').addEventListener('click', () => {
        const line = lines.firstElementChild.cloneNode(true);
        line.dataset.index = index;
        line.querySelectorAll('[name]').forEach(el => el.name = el.name.replace(/items\[0\]/g, `items[${index}]`));
        line.querySelectorAll('input').forEach(el => el.value = '');
        line.querySelectorAll('select').forEach(el => el.selectedIndex = 0);
        line.querySelector('.remove-purchase-line').classList.remove('hidden');
        lines.appendChild(line);
        bind(line);
        index++;
    });
    lines.addEventListener('click', (event) => {
        const removeButton = event.target.closest('.remove-purchase-line');
        if (removeButton) removeButton.closest('.purchase-line').remove();
    });
})();
</script>
@endsection
