@extends('layouts.app')

@section('content')
<div class="mb-6">
    <h1 class="text-2xl font-bold text-[#1E2530]">Activity Log</h1>
    <p class="text-sm text-[#6B7A93] mt-1">Who changed what, and when.</p>
</div>

<div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
    <table class="min-w-full divide-y divide-gray-100">
        <thead class="bg-gray-50/50">
            <tr>
                <th class="px-5 py-3 text-left text-xs font-semibold text-[#6B7A93] uppercase tracking-wide">When</th>
                <th class="px-5 py-3 text-left text-xs font-semibold text-[#6B7A93] uppercase tracking-wide">User</th>
                <th class="px-5 py-3 text-left text-xs font-semibold text-[#6B7A93] uppercase tracking-wide">Action</th>
                <th class="px-5 py-3 text-left text-xs font-semibold text-[#6B7A93] uppercase tracking-wide">Record</th>
                <th class="px-5 py-3 text-left text-xs font-semibold text-[#6B7A93] uppercase tracking-wide">Details</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
            @forelse($logs as $log)
                <tr class="hover:bg-gray-50/50">
                    <td class="px-5 py-3.5 text-sm text-[#6B7A93] whitespace-nowrap">{{ $log->created_at->format('M d, Y g:i A') }}</td>
                    <td class="px-5 py-3.5 text-sm text-[#1E2530]">{{ $log->user->name ?? 'System' }}</td>
                    <td class="px-5 py-3.5 text-sm">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium
                            {{ $log->action === 'created' ? 'bg-emerald-50 text-emerald-700' : ($log->action === 'deleted' ? 'bg-red-50 text-red-600' : 'bg-amber-50 text-amber-600') }}">
                            {{ ucfirst($log->action) }}
                        </span>
                    </td>
                    <td class="px-5 py-3.5 text-sm text-[#1E2530]">{{ $log->model_type }} #{{ $log->model_id }}</td>
                    <td class="px-5 py-3.5 text-xs text-[#6B7A93] font-mono max-w-md truncate">
                        {{ collect($log->changes)->map(fn($v, $k) => "$k: " . (is_array($v) ? json_encode($v) : $v))->implode(', ') }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="px-5 py-12 text-center text-sm text-[#6B7A93]">No activity recorded yet.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">
    {{ $logs->links() }}
</div>
@endsection
