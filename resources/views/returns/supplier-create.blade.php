@extends('layouts.app')

@section('content')
<div class="max-w-lg mx-auto">
    <h1 class="text-2xl font-bold text-[#1E2530] mb-1">Supplier return</h1>
    <p class="text-sm text-[#6B7A93] mb-6">Takes remaining stock off the batch and reverses the original purchase journals (inventory and AP).</p>

    <form method="POST" action="{{ route('returns.supplier.store') }}" class="bg-white rounded-xl border border-gray-100 shadow-sm p-6 space-y-4">
        @csrf

        <div>
            <label class="block text-sm font-medium text-[#1E2530] mb-1.5">Batch</label>
            <select name="purchase_batch_id" id="supplier-batch" class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm" required>
                <option value="">-- Select batch --</option>
                @foreach($batches as $batch)
                    <option value="{{ $batch->id }}"
                            @selected((int) old('purchase_batch_id', $selectedBatchId) === $batch->id)
                            data-base="{{ $batch->drug?->baseUnitLabel() ?? 'tablet' }}"
                            data-strip="{{ $batch->drug?->unitsPerStrip() ?? 1 }}"
                            data-pack="{{ $batch->drug?->unitsPerPack() ?? 1 }}">
                        {{ $batch->drug->name ?? 'Drug' }}
                        — {{ $batch->batch_number }}
                        ({{ $batch->quantity_remaining }} {{ $batch->drug?->baseUnitLabel() ?? 'unit' }} left
                        @if($batch->supplier) · {{ $batch->supplier->name }}@endif)
                    </option>
                @endforeach
            </select>
            @if($batches->isEmpty())
                <p class="text-sm text-red-600 mt-1.5">No batches with remaining stock.</p>
            @endif
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
        <p id="supplier-hint" class="text-xs text-[#6B7A93] -mt-2">Only unsold remaining stock can go back to the supplier.</p>

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
</div>

<script>
    (function () {
        const select = document.getElementById('supplier-batch');
        const hint = document.getElementById('supplier-hint');
        function sync() {
            const opt = select.options[select.selectedIndex];
            const base = opt && opt.dataset.base ? opt.dataset.base : 'tablet';
            const strip = opt && opt.dataset.strip ? opt.dataset.strip : '1';
            const pack = opt && opt.dataset.pack ? opt.dataset.pack : '1';
            hint.textContent = '1 strip = ' + strip + ' ' + base + ', 1 pack = ' + pack + ' ' + base + '. Remaining is in ' + base + 's.';
        }
        select.addEventListener('change', sync);
        sync();
    })();
</script>
@endsection
