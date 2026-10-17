{{--
    Shared month / year filter for the reports screens.

    Included with the route it posts back to:
    @include('partials.period-filter', ['action' => route('reports.sales')])
--}}
<form method="GET" action="{{ $action }}" class="bg-white rounded-xl border border-gray-100 shadow-sm p-4 mb-4">
    <div class="flex flex-wrap items-end gap-3">
        <div class="w-32">
            <label for="month" class="block text-xs font-semibold text-[#64748b] uppercase tracking-wide mb-1">Month</label>
            <select name="month" id="month" class="w-full rounded-lg border-gray-200 focus:border-[var(--brand-primary)] focus:ring-[var(--brand-primary)] min-h-[44px]">
                <option value="">All months</option>
                @foreach(\App\Support\ReportPeriod::MONTH_NAMES as $number => $name)
                    <option value="{{ $number }}" @selected($period->month === $number)>{{ $name }}</option>
                @endforeach
            </select>
        </div>
        <div class="w-32">
            <label for="year" class="block text-xs font-semibold text-[#64748b] uppercase tracking-wide mb-1">Year</label>
            <select name="year" id="year" class="w-full rounded-lg border-gray-200 focus:border-[var(--brand-primary)] focus:ring-[var(--brand-primary)] min-h-[44px]">
                <option value="">All years</option>
                @foreach($period->years() as $year)
                    <option value="{{ $year }}" @selected($period->year === $year)>{{ $year }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="px-4 min-h-[44px] rounded-lg bg-[var(--brand-primary)] text-white text-sm font-semibold hover:opacity-90">Filter</button>
        @if($period->isFiltered())
            <a href="{{ $action }}" class="px-3 min-h-[44px] inline-flex items-center text-sm font-medium text-[#64748b] hover:text-[#1E2530]">Clear</a>
        @endif
    </div>
    <p class="text-xs text-[#64748b] mt-2">
        {{ $hint ?? 'Picking a month without a year uses ' . now()->year . '. Leaving both on "All" covers everything on record.' }}
    </p>
</form>
