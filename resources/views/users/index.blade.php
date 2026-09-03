@extends('layouts.app')

@section('content')
<div class="flex justify-between items-center mb-6">
    <div>
        <h1 class="text-2xl font-bold text-[#1E2530]">Users</h1>
        <p class="text-sm text-[#6B7A93] mt-1">Staff accounts and their access level.</p>
    </div>
    <a href="{{ route('users.create') }}"
       class="inline-flex items-center gap-2 bg-[#12A594] hover:bg-[#0e8a7d] text-white font-medium text-sm py-2.5 px-4 rounded-lg transition-colors">
        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
        </svg>
        Add User
    </a>
</div>

@if(session('success'))
    <div class="mb-4 bg-green-50 border border-green-200 rounded-lg p-4">
        <p class="text-sm text-green-700">{{ session('success') }}</p>
    </div>
@endif

@if(session('error'))
    <div class="mb-4 bg-red-50 border border-red-200 rounded-lg p-4">
        <p class="text-sm text-red-700">{{ session('error') }}</p>
    </div>
@endif

<div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
    <table class="min-w-full divide-y divide-gray-100">
        <thead class="bg-gray-50/50">
            <tr>
                <th class="px-5 py-3 text-left text-xs font-semibold text-[#6B7A93] uppercase tracking-wide">Name</th>
                <th class="px-5 py-3 text-left text-xs font-semibold text-[#6B7A93] uppercase tracking-wide">Email</th>
                <th class="px-5 py-3 text-left text-xs font-semibold text-[#6B7A93] uppercase tracking-wide">Role</th>
                <th class="px-5 py-3 text-left text-xs font-semibold text-[#6B7A93] uppercase tracking-wide">Branch</th>
                <th class="px-5 py-3 text-left text-xs font-semibold text-[#6B7A93] uppercase tracking-wide">Status</th>
                <th class="px-5 py-3 text-right text-xs font-semibold text-[#6B7A93] uppercase tracking-wide">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
            @forelse($users as $user)
                <tr class="hover:bg-gray-50/50 {{ !$user->active ? 'bg-gray-50' : '' }}">
                    <td class="px-5 py-3.5 text-sm font-medium text-[#1E2530]">{{ $user->name }}</td>
                    <td class="px-5 py-3.5 text-sm text-[#6B7A93]">{{ $user->email }}</td>
                    <td class="px-5 py-3.5 text-sm">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium
                            {{ $user->role === 'owner_admin' ? 'bg-[#12A594]/10 text-[#12A594]' : 'bg-gray-100 text-[#6B7A93]' }}">
                            {{ $user->roleLabel() }}
                        </span>
                    </td>
                    <td class="px-5 py-3.5 text-sm text-[#6B7A93]">{{ $user->branch->name ?? 'All Branches' }}</td>
                    <td class="px-5 py-3.5 text-sm">
                        @if($user->active)
                            <span class="inline-flex items-center gap-1 text-green-600">
                                <span class="w-1.5 h-1.5 rounded-full bg-green-600"></span>
                                Active
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1 text-gray-400">
                                <span class="w-1.5 h-1.5 rounded-full bg-gray-400"></span>
                                Inactive
                            </span>
                        @endif
                    </td>
                    <td class="px-5 py-3.5 text-sm text-right">
                        <div class="flex justify-end gap-2">
                            <a href="{{ route('users.edit', $user) }}" 
                               class="text-[#12A594] hover:text-[#0e8a7d] font-medium text-xs">Edit</a>
                            
                            @if($user->active)
                                <form method="POST" action="{{ route('users.deactivate', $user) }}" class="inline" onsubmit="return confirm('Deactivate this user?')">
                                    @csrf
                                    <button type="submit" class="text-gray-500 hover:text-gray-700 font-medium text-xs">Deactivate</button>
                                </form>
                            @else
                                <form method="POST" action="{{ route('users.reactivate', $user) }}" class="inline" onsubmit="return confirm('Reactivate this user?')">
                                    @csrf
                                    <button type="submit" class="text-green-600 hover:text-green-700 font-medium text-xs">Reactivate</button>
                                </form>
                            @endif
                            
                            <button type="button" onclick="document.getElementById('reset-password-modal-{{ $user->id }}').classList.remove('hidden')"
                                    class="text-blue-600 hover:text-blue-700 font-medium text-xs">Reset Password</button>
                            
                            @if($user->id !== auth()->id())
                                <form method="POST" action="{{ route('users.destroy', $user) }}" class="inline" onsubmit="return confirm('Delete this user? This cannot be undone.')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-600 hover:text-red-700 font-medium text-xs">Delete</button>
                                </form>
                            @endif
                        </div>
                    </td>
                </tr>

                <!-- Reset Password Modal -->
                <div id="reset-password-modal-{{ $user->id }}" class="hidden fixed inset-0 bg-black/50 flex items-center justify-center z-50">
                    <div class="bg-white rounded-xl p-6 max-w-md w-full mx-4">
                        <h3 class="text-lg font-semibold text-[#1E2530] mb-4">Reset Password for {{ $user->name }}</h3>
                        <form method="POST" action="{{ route('users.reset-password', $user) }}">
                            @csrf
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-[#1E2530] mb-1.5">New Password</label>
                                    <input type="password" name="password" class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#12A594]/30" required>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-[#1E2530] mb-1.5">Confirm New Password</label>
                                    <input type="password" name="password_confirmation" class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#12A594]/30" required>
                                </div>
                            </div>
                            <div class="flex gap-3 mt-6">
                                <button type="submit" class="bg-[#12A594] hover:bg-[#0e8a7d] text-white font-medium text-sm py-2.5 px-5 rounded-lg transition-colors">
                                    Reset Password
                                </button>
                                <button type="button" onclick="document.getElementById('reset-password-modal-{{ $user->id }}').classList.add('hidden')"
                                        class="text-[#6B7A93] hover:text-[#1E2530] text-sm py-2.5 px-4">Cancel</button>
                            </div>
                        </form>
                    </div>
                </div>
            @empty
                <tr>
                    <td colspan="6" class="px-5 py-12 text-center text-sm text-[#6B7A93]">No users yet.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
