@extends('layouts.app')

@section('content')
<div class="max-w-3xl mx-auto">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-[#1E2530]">Opening Balances</h1>
        <p class="text-sm text-[#6B7A93] mt-1">
            Set the figures the pharmacy starts with. Enter what you <em>should</em> have on day one —
            the system posts the difference, so re-saving the same figures changes nothing.
        </p>
    </div>

    @if(!$branchId && $branches->isNotEmpty())
        <form method="GET" action="{{ route('opening-balances.edit') }}" class="bg-white rounded-xl border border-gray-100 shadow-sm p-6 mb-6">
            <label class="block text-sm font-medium text-[#1E2530] mb-2">Branch</label>
            <div class="flex gap-3">
                <select name="branch_id" class="flex-1 border border-gray-200 rounded-lg px-3 py-2 text-sm" required>
                    <option value="">-- Select Branch --</option>
                    @foreach($branches as $branch)
                        <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                    @endforeach
                </select>
                <button type="submit" class="bg-[var(--brand-primary)] hover:bg-[var(--brand-primary-dark)] text-white font-medium text-sm py-2 px-4 rounded-lg">Continue</button>
            </div>
            <p class="text-xs text-[#6B7A93] mt-2">Opening balances belong to a branch's books — choose which one you are setting.</p>
        </form>
    @else
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">
            <div class="flex items-start gap-3 mb-5 rounded-lg bg-amber-50 border border-amber-200 px-4 py-3">
                <p class="text-xs text-amber-800 leading-5">
                    Do this once, before recording sales or purchases. Re-opening this page later and
                    entering new figures will adjust the books to match them.
                </p>
            </div>

            <form method="POST" action="{{ route('opening-balances.store') }}" class="space-y-4">
                @csrf
                @if($branchId)
                    <input type="hidden" name="branch_id" value="{{ $branchId }}">
                @endif

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-[#1E2530] mb-1">As of date</label>
                        <input type="date" name="as_of" value="{{ old('as_of', now()->toDateString()) }}"
                               class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-[#1E2530] mb-1">Note (optional)</label>
                        <input type="text" name="notes" maxlength="255" value="{{ old('notes') }}"
                               placeholder="e.g. go-live balances"
                               class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm">
                    </div>
                </div>

                <div class="border-t border-gray-100 pt-4">
                    <div class="grid grid-cols-12 gap-2 text-xs font-semibold text-[#6B7A93] uppercase tracking-wide mb-2 px-1">
                        <span class="col-span-7">Account</span>
                        <span class="col-span-3 text-right">In the books now</span>
                        <span class="col-span-2 text-right">Opening</span>
                    </div>

                    <div class="space-y-2">
                        @foreach($rows as $row)
                            <div class="grid grid-cols-12 gap-2 items-center">
                                <label for="bal-{{ $row['code'] }}" class="col-span-7 text-sm text-[#1E2530]">
                                    <span class="text-[#6B7A93] text-xs">{{ $row['code'] }}</span> {{ $row['label'] }}
                                </label>
                                <span class="col-span-3 text-right text-sm text-[#6B7A93]">
                                    ${{ number_format($row['current'], 2) }}
                                </span>
                                <input id="bal-{{ $row['code'] }}"
                                       type="number" step="0.01" min="0"
                                       name="balances[{{ $row['code'] }}]"
                                       value="{{ old('balances.' . $row['code']) }}"
                                       data-sign="{{ $row['sign'] }}"
                                       placeholder="0.00"
                                       class="bal-input col-span-2 w-full border border-gray-200 rounded-lg px-3 py-2 text-sm text-right">
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="flex items-center justify-between border-t border-gray-100 pt-4">
                    <div class="text-sm text-[#6B7A93]">
                        Owner's capital (derived)
                        <span id="equity-preview" class="ml-2 font-semibold text-[#1E2530]">$0.00</span>
                    </div>
                    <div class="flex gap-3">
                        <a href="{{ route('journal-entries.index') }}" class="text-gray-600 py-2 px-4 text-sm">View records</a>
                        <button type="submit"
                                class="bg-[var(--brand-primary)] hover:bg-[var(--brand-primary-dark)] text-white font-medium text-sm py-2.5 px-5 rounded-lg transition-colors">
                            Post opening balances
                        </button>
                    </div>
                </div>
            </form>
        </div>
    @endif
</div>

<script>
    (function () {
        const inputs = document.querySelectorAll('.bal-input');
        const preview = document.getElementById('equity-preview');

        function refresh() {
            let equity = 0;
            inputs.forEach(function (input) {
                const value = parseFloat(input.value) || 0;
                equity += value * (parseFloat(input.dataset.sign) || 1);
            });
            preview.textContent = '$' + equity.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        inputs.forEach(function (input) {
            input.addEventListener('input', refresh);
        });
        refresh();
    })();
</script>
@endsection
