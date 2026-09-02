@extends('layouts.app')

@section('content')
<div class="max-w-lg mx-auto">
    <h1 class="text-2xl font-bold mb-6">Add New Drug</h1>

    <form method="POST" action="{{ route('drugs.store') }}" class="bg-white rounded-lg shadow p-6 space-y-4">
        @csrf

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Name</label>
            <input type="text" name="name" value="{{ old('name') }}"
                   class="w-full border border-gray-300 rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" required>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Generic Name</label>
            <input type="text" name="generic_name" value="{{ old('generic_name') }}"
                   class="w-full border border-gray-300 rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Strength</label>
                <input type="text" name="strength" placeholder="e.g. 500mg" value="{{ old('strength') }}"
                       class="w-full border border-gray-300 rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Form</label>
                <input type="text" name="form" placeholder="e.g. Tablet" value="{{ old('form') }}"
                       class="w-full border border-gray-300 rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Category</label>
            <select name="category_id" class="w-full border border-gray-300 rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                <option value="">-- Select Category --</option>
                @foreach($categories as $category)
                    <option value="{{ $category->id }}" @selected(old('category_id') == $category->id)>{{ $category->name }}</option>
                @endforeach
            </select>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Base unit</label>
                <input type="text" name="unit_of_measure" placeholder="tablet" value="{{ old('unit_of_measure', 'tablet') }}"
                       class="w-full border border-gray-300 rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                <p class="text-xs text-gray-500 mt-1">Stock is stored in this unit (e.g. tablet).</p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Minimum Stock Level</label>
                <input type="number" step="0.01" name="minimum_stock_level" value="{{ old('minimum_stock_level', 0) }}"
                       class="w-full border border-gray-300 rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Base units per strip</label>
                <input type="number" min="1" name="units_per_strip" value="{{ old('units_per_strip', 1) }}"
                       class="w-full border border-gray-300 rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Strips per pack</label>
                <input type="number" min="1" name="strips_per_pack" value="{{ old('strips_per_pack', 1) }}"
                       class="w-full border border-gray-300 rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
        </div>
        <p class="text-xs text-gray-500 -mt-2">Leave both at 1 for loose tablets. Example: 10 tablets/strip and 10 strips/pack = 100 tablets per pack.</p>

        <div class="flex gap-3 pt-2">
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                Save Drug
            </button>
            <a href="{{ route('drugs.index') }}" class="text-gray-600 py-2 px-4">Cancel</a>
        </div>
    </form>
</div>
@endsection
