@extends('layouts.app')

@section('content')
<h1 class="text-2xl font-bold text-[#1E2530] mb-6">Add Expense</h1>

<form method="POST" action="{{ route('expenses.store') }}" class="bg-white rounded-xl border border-gray-100 shadow-sm p-6 max-w-2xl space-y-5">
    @csrf

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-semibold text-[#1E2530] mb-1">Date</label>
            <input type="date" name="expense_date" value="{{ old('expense_date', now()->toDateString()) }}" required
                   class="w-full rounded-lg border-gray-200 focus:border-[#12A594] focus:ring-[#12A594] min-h-[44px]">
            @error('expense_date')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="block text-sm font-semibold text-[#1E2530] mb-1">Category</label>
            <select name="expense_category_id" required class="w-full rounded-lg border-gray-200 focus:border-[#12A594] focus:ring-[#12A594] min-h-[44px]">
                <option value="">Choose a category…</option>
                @foreach($categories as $cat)
                    <option value="{{ $cat->id }}" @selected(old('expense_category_id') == $cat->id)>{{ $cat->name }}</option>
                @endforeach
            </select>
            @error('expense_category_id')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
        </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-semibold text-[#1E2530] mb-1">Amount</label>
            <input type="number" step="0.01" min="0.01" name="amount" value="{{ old('amount') }}" required
                   class="w-full rounded-lg border-gray-200 focus:border-[#12A594] focus:ring-[#12A594] min-h-[44px]">
            @error('amount')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
        </div>
        @if(auth()->user()->branch_id === null)
        <div>
            <label class="block text-sm font-semibold text-[#1E2530] mb-1">Branch</label>
            <select name="branch_id" class="w-full rounded-lg border-gray-200 focus:border-[#12A594] focus:ring-[#12A594] min-h-[44px]">
                @foreach(\App\Models\Branch::orderBy('name')->get() as $branch)
                    <option value="{{ $branch->id }}" @selected(old('branch_id') == $branch->id)>{{ $branch->name }}</option>
                @endforeach
            </select>
            @error('branch_id')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
        </div>
        @endif
    </div>

    {{-- Plain language: "How did you pay?" --}}
    <div x-data="{ method: '{{ old('payment_method', 'cash') }}' }">
        <label class="block text-sm font-semibold text-[#1E2530] mb-1">How did you pay?</label>
        <div class="flex flex-wrap gap-2">
            @foreach($methods as $value => $label)
                <label class="inline-flex items-center gap-2 border rounded-lg px-3 py-2 min-h-[44px] cursor-pointer {{ in_array(old('payment_method','cash'), [$value]) ? 'border-[#12A594] bg-[#12A594]/10' : 'border-gray-200' }}">
                    <input type="radio" name="payment_method" value="{{ $value }}" x-model="method" class="accent-[#12A594]" @checked(old('payment_method', 'cash') === $value)>
                    <span class="text-sm">{{ $label }}</span>
                </label>
            @endforeach
        </div>
        @error('payment_method')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror

        <div x-show="method !== 'cash' && method !== 'check'" x-cloak class="mt-4">
            <label class="block text-sm font-semibold text-[#1E2530] mb-1">Transaction number / reference</label>
            <input type="text" name="payment_reference" value="{{ old('payment_reference') }}"
                   class="w-full rounded-lg border-gray-200 focus:border-[#12A594] focus:ring-[#12A594] min-h-[44px]">
            @error('payment_reference')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
        </div>

        <div x-show="method === 'check'" x-cloak class="mt-4 grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-semibold text-[#1E2530] mb-1">Check number</label>
                <input type="text" name="check_number" value="{{ old('check_number') }}"
                       class="w-full rounded-lg border-gray-200 focus:border-[#12A594] focus:ring-[#12A594] min-h-[44px]">
                @error('check_number')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-sm font-semibold text-[#1E2530] mb-1">Check date</label>
                <input type="date" name="check_date" value="{{ old('check_date') }}"
                       class="w-full rounded-lg border-gray-200 focus:border-[#12A594] focus:ring-[#12A594] min-h-[44px]">
                @error('check_date')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-sm font-semibold text-[#1E2530] mb-1">Bank name</label>
                <input type="text" name="check_bank" value="{{ old('check_bank') }}"
                       class="w-full rounded-lg border-gray-200 focus:border-[#12A594] focus:ring-[#12A594] min-h-[44px]">
                @error('check_bank')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
            </div>
        </div>
    </div>

    <div>
        <label class="block text-sm font-semibold text-[#1E2530] mb-1">Note (optional)</label>
        <textarea name="note" rows="2" class="w-full rounded-lg border-gray-200 focus:border-[#12A594] focus:ring-[#12A594]" placeholder="e.g. March rent, receipt #221">{{ old('note') }}</textarea>
        @error('note')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
    </div>

    <label class="flex items-center gap-3 cursor-pointer min-h-[44px]">
        <input type="checkbox" name="repeat_monthly" value="1" class="rounded accent-[#12A594] w-5 h-5" @checked(old('repeat_monthly'))>
        <span class="text-sm text-[#1E2530]">This happens every month (I'll get a reminder when the next one is due)</span>
    </label>

    <div class="flex gap-3 pt-2">
        <button type="submit" class="bg-[#12A594] hover:bg-[#0e8a7d] text-white font-bold py-2 px-6 rounded-lg min-h-[44px]">Save Expense</button>
        <a href="{{ route('expenses.index') }}" class="text-[#6B7A93] hover:text-[#1E2530] py-2 px-4 min-h-[44px] inline-flex items-center">Cancel</a>
    </div>
</form>
@endsection
