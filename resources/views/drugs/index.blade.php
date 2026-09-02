@extends('layouts.app')

@section('content')
<div class="flex justify-between items-center mb-6">
    <h1 class="text-2xl font-bold text-[#1E2530]">Drugs</h1>
    <a href="{{ route('drugs.create') }}" class="bg-[#12A594] hover:bg-[#0e8a7d] text-white font-bold py-2 px-4 rounded-lg text-sm">+ Add New Drug</a>
</div>

<div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden overflow-x-auto">
    <table class="min-w-full divide-y divide-gray-100">
        <thead class="bg-gray-50/50">
            <tr>
                <th class="px-5 py-3 text-left text-xs font-semibold text-[#6B7A93] uppercase tracking-wide">Name</th>
                <th class="px-5 py-3 text-left text-xs font-semibold text-[#6B7A93] uppercase tracking-wide">Generic Name</th>
                <th class="px-5 py-3 text-left text-xs font-semibold text-[#6B7A93] uppercase tracking-wide">Strength</th>
                <th class="px-5 py-3 text-left text-xs font-semibold text-[#6B7A93] uppercase tracking-wide">Category</th>
                <th class="px-5 py-3 text-left text-xs font-semibold text-[#6B7A93] uppercase tracking-wide">Unit</th>
                <th class="px-5 py-3 text-left text-xs font-semibold text-[#6B7A93] uppercase tracking-wide">Min. Stock</th>
                <th class="px-5 py-3"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
            @forelse($drugs as $drug)
                <tr class="hover:bg-gray-50/50">
                    <td class="px-5 py-3.5 text-sm font-medium text-[#1E2530]">{{ $drug->name }}</td>
                    <td class="px-5 py-3.5 text-sm text-[#6B7A93]">{{ $drug->generic_name ?? '—' }}</td>
                    <td class="px-5 py-3.5 text-sm text-[#6B7A93]">{{ $drug->strength ?? '—' }}</td>
                    <td class="px-5 py-3.5 text-sm">
                        <span class="bg-purple-100 text-purple-700 text-xs font-semibold px-2 py-1 rounded-full">{{ $drug->category->name ?? '—' }}</span>
                    </td>
                    <td class="px-5 py-3.5 text-sm text-[#6B7A93]">
                        {{ $drug->baseUnitLabel() }}
                        @if($drug->unitsPerStrip() > 1 || $drug->stripsPerPack() > 1)
                            <span class="block text-xs">{{ $drug->unitsPerStrip() }}/strip · {{ $drug->stripsPerPack() }} strips/pack</span>
                        @endif
                    </td>
                    <td class="px-5 py-3.5 text-sm text-[#6B7A93]">{{ $drug->minimum_stock_level }}</td>
                    <td class="px-5 py-3.5 text-sm text-right whitespace-nowrap">
                        <a href="{{ route('drugs.edit', $drug->id) }}" class="text-[#12A594] hover:underline mr-3">Edit</a>
                        <form action="{{ route('drugs.destroy', $drug->id) }}" method="POST" class="inline"
                              onsubmit="return confirm('Delete {{ $drug->name }}? This cannot be undone.');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-red-500 hover:underline">Delete</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="px-5 py-12 text-center text-sm text-[#6B7A93]">
                        No drugs yet. <a href="{{ route('drugs.create') }}" class="text-[#12A594] underline">Add your first one</a>.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">{{ $drugs->links() }}</div>
@endsection
