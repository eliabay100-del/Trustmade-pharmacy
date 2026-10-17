@extends('layouts.app')

@section('content')
<div class="max-w-lg mx-auto">
    <h1 class="text-2xl font-bold text-[#1E2530] mb-1">Write off stock</h1>
    <p class="text-sm text-[#64748b] mb-6">Removes units from a specific batch, posts shrinkage at cost, and writes the activity log.</p>

    <form method="POST" action="{{ route('adjustments.store') }}" class="bg-white rounded-xl border border-gray-100 shadow-sm p-6 space-y-4">
        @csrf

        <div>
            <label class="block text-sm font-medium text-[#1E2530] mb-1.5">Batch</label>
            <select name="purchase_batch_id" class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm" required>
                <option value="">-- Select batch --</option>
                @foreach($batches as $batch)
                    <option value="{{ $batch->id }}"
                            @selected((int) old('purchase_batch_id', $selectedBatchId) === $batch->id)
                            data-base="{{ ($batch->drug ?? $batch->product)?->baseUnitLabel() ?? 'unit' }}"
                            data-strip="{{ ($batch->drug ?? $batch->product)?->unitsPerStrip() ?? 1 }}"
                            data-pack="{{ ($batch->drug ?? $batch->product)?->unitsPerPack() ?? 1 }}">
                        {{ ($batch->drug ?? $batch->product)->name ?? 'Item' }}
                        — {{ $batch->batch_number }}
                        ({{ $batch->quantity_remaining }} {{ ($batch->drug ?? $batch->product)?->baseUnitLabel() ?? 'unit' }} left
                        @if($batch->expiry_date) · exp {{ $batch->expiry_date->format('M d, Y') }}@endif)
                    </option>
                @endforeach
            </select>
            @if($batches->isEmpty())
                <p class="text-sm text-red-600 mt-1.5">No batches with remaining stock.</p>
            @endif
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-[#1E2530] mb-1.5">Quantity to write off</label>
                <input type="number" min="1" name="quantity" value="{{ old('quantity') }}"
                       class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm" required>
            </div>
            <div>
                <label class="block text-sm font-medium text-[#1E2530] mb-1.5">Unit</label>
                <select name="quantity_unit" id="adjust-unit" class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm" required>
                    <option value="tablet" @selected(old('quantity_unit', 'tablet') === 'tablet')>Base unit</option>
                    <option value="strip" @selected(old('quantity_unit') === 'strip')>Strip</option>
                    <option value="pack" @selected(old('quantity_unit') === 'pack')>Pack</option>
                </select>
            </div>
        </div>
        <p id="adjust-unit-hint" class="text-xs text-[#64748b] -mt-2">Remaining stock on the batch is shown in base units.</p>

        <div>
            <label class="block text-sm font-medium text-[#1E2530] mb-1.5">Reason</label>
            <select name="reason" id="reason-select" class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm" required>
                <option value="">-- Select reason --</option>
                @foreach($reasons as $value => $label)
                    <option value="{{ $value }}" @selected(old('reason') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="block text-sm font-medium text-[#1E2530] mb-1.5">Notes <span id="notes-required" class="hidden text-red-600"> (required)</span></label>
            <input type="text" name="notes" id="notes-input" value="{{ old('notes') }}" maxlength="255"
                   class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm"
                   placeholder="Optional unless reason is Other">
        </div>

        <div class="flex gap-3 pt-2">
            <button type="submit" class="bg-[var(--brand-primary)] hover:bg-[var(--brand-primary-dark)] text-white font-medium text-sm py-2.5 px-5 rounded-lg transition-colors">
                Confirm write-off
            </button>
            <a href="{{ route('adjustments.index') }}" class="text-[#64748b] hover:text-[#1E2530] text-sm py-2.5 px-4">Cancel</a>
        </div>
    </form>
</div>

<script>
    (function () {
        const batchSelect = document.querySelector('select[name="purchase_batch_id"]');
        const unitSelect = document.getElementById('adjust-unit');
        const hint = document.getElementById('adjust-unit-hint');
        function syncPacking() {
            const opt = batchSelect.options[batchSelect.selectedIndex];
            const base = opt && opt.dataset.base ? opt.dataset.base : 'tablet';
            const strip = opt && opt.dataset.strip ? opt.dataset.strip : '1';
            const pack = opt && opt.dataset.pack ? opt.dataset.pack : '1';
            hint.textContent = '1 strip = ' + strip + ' ' + base + ', 1 pack = ' + pack + ' ' + base + '. Remaining is in ' + base + 's.';
        }
        batchSelect.addEventListener('change', syncPacking);
        syncPacking();
        const reasonSelect = document.getElementById('reason-select');
        const requiredMark = document.getElementById('notes-required');
        const notes = document.getElementById('notes-input');
        function sync() {
            const other = reasonSelect.value === 'other';
            requiredMark.classList.toggle('hidden', !other);
            notes.required = other;
        }
        reasonSelect.addEventListener('change', sync);
        sync();
    })();
</script>
@endsection
