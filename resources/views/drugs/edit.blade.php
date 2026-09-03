@extends('layouts.app')

@section('content')
<div class="max-w-lg mx-auto">
    <h1 class="text-2xl font-bold text-[#1E2530] mb-6">Edit Drug</h1>

    <form method="POST" action="{{ route('drugs.update', $drug->id) }}" class="bg-white rounded-xl border border-gray-100 shadow-sm p-6 space-y-4">
        @csrf
        @method('PUT')

        <div>
            <label class="block text-sm font-medium text-[#1E2530] mb-1.5">Name</label>
            <input type="text" name="name" value="{{ old('name', $drug->name) }}"
                   class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#12A594]/30" required>
        </div>

        <div>
            <label class="block text-sm font-medium text-[#1E2530] mb-1.5">Generic Name</label>
            <input type="text" name="generic_name" value="{{ old('generic_name', $drug->generic_name) }}"
                   class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#12A594]/30">
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-[#1E2530] mb-1.5">Strength</label>
                <input type="text" name="strength" value="{{ old('strength', $drug->strength) }}"
                       class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#12A594]/30">
            </div>
            <div>
                <label class="block text-sm font-medium text-[#1E2530] mb-1.5">Form</label>
                <input type="text" name="form" value="{{ old('form', $drug->form) }}"
                       class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#12A594]/30">
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-[#1E2530] mb-1.5">Category</label>
            <select name="category_id" class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#12A594]/30" required>
                @foreach($categories as $category)
                    <option value="{{ $category->id }}" @selected(old('category_id', $drug->category_id) == $category->id)>{{ $category->name }}</option>
                @endforeach
            </select>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-[#1E2530] mb-1.5">Base unit</label>
                <input type="text" name="unit_of_measure" value="{{ old('unit_of_measure', $drug->unit_of_measure) }}"
                       class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#12A594]/30">
                <p class="text-xs text-[#6B7A93] mt-1">Existing stock is already in this unit and is not converted.</p>
            </div>
            <div>
                  <label class="block text-sm font-medium text-[#1E2530] mb-1.5">Minimum stock level</label>
                <input type="number" step="0.01" name="minimum_stock_level" value="{{ old('minimum_stock_level', $drug->minimum_stock_level) }}"
                       class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#12A594]/30">
                  <p class="text-xs text-[#6B7A93] mt-1">Show a low-stock alert when available stock reaches this amount.</p>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-[#1E2530] mb-1.5">Base units per strip</label>
                <input type="number" min="1" name="units_per_strip" value="{{ old('units_per_strip', $drug->units_per_strip ?? 1) }}"
                       class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#12A594]/30">
            </div>
            <div>
                <label class="block text-sm font-medium text-[#1E2530] mb-1.5">Strips per pack</label>
                <input type="number" min="1" name="strips_per_pack" value="{{ old('strips_per_pack', $drug->strips_per_pack ?? 1) }}"
                       class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#12A594]/30">
            </div>
        </div>
        <p class="text-xs text-[#6B7A93] -mt-2">Example: 10 per strip and 10 strips per pack = 100 base units per pack.</p>

        <div class="flex gap-3 pt-2">
            <button type="submit" class="bg-[#12A594] hover:bg-[#0e8a7d] text-white font-medium text-sm py-2.5 px-5 rounded-lg transition-colors">
                Save Changes
            </button>
            <a href="{{ route('drugs.index') }}" class="text-[#6B7A93] hover:text-[#1E2530] text-sm py-2.5 px-4">Cancel</a>
        </div>
    </form>
</div>
@endsection
