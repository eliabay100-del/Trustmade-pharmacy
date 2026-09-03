@extends('layouts.app')

@section('content')
<div class="max-w-lg mx-auto">
    <h1 class="text-2xl font-bold text-[#1E2530] mb-1">Edit User</h1>
    <p class="text-sm text-[#6B7A93] mb-6">Update staff account details.</p>

    <form method="POST" action="{{ route('users.update', $user) }}" class="bg-white rounded-xl border border-gray-100 shadow-sm p-6 space-y-4">
        @csrf
        @method('PUT')

        <div>
            <label class="block text-sm font-medium text-[#1E2530] mb-1.5">Full Name</label>
            <input type="text" name="name" value="{{ old('name', $user->name) }}"
                   class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#12A594]/30" required>
        </div>

        <div>
            <label class="block text-sm font-medium text-[#1E2530] mb-1.5">Email (used to log in)</label>
            <input type="email" name="email" value="{{ old('email', $user->email) }}"
                   class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#12A594]/30" required>
        </div>

        <div>
            <label class="block text-sm font-medium text-[#1E2530] mb-1.5">Role</label>
            <select name="role" id="role-select" class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#12A594]/30" required>
                <option value="">-- Select Role --</option>
                @foreach($roles as $value => $label)
                    <option value="{{ $value }}" @selected(old('role', $user->role) == $value)>{{ $label }}</option>
                @endforeach
            </select>
            @error('role')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div id="branch-field">
            <label class="block text-sm font-medium text-[#1E2530] mb-1.5">Branch</label>
            <select name="branch_id" class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#12A594]/30">
                <option value="">-- Select Branch --</option>
                @foreach($branches as $branch)
                    <option value="{{ $branch->id }}" @selected(old('branch_id', $user->branch_id) == $branch->id)>{{ $branch->name }}</option>
                @endforeach
            </select>
            @error('branch_id')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
            <p class="text-xs text-[#6B7A93] mt-1">Not required for Owner/Admin — they get access to all branches.</p>
        </div>

        <div class="pt-2 border-t border-gray-100">
            <h3 class="text-sm font-semibold text-[#1E2530] mb-3">Change Password (optional)</h3>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-[#1E2530] mb-1.5">New Password</label>
                    <input type="password" name="password"
                           class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#12A594]/30">
                    <p class="text-xs text-[#6B7A93] mt-1">Leave blank to keep current password.</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-[#1E2530] mb-1.5">Confirm New Password</label>
                    <input type="password" name="password_confirmation"
                           class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#12A594]/30">
                </div>
            </div>
            @error('password')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div class="flex gap-3 pt-2">
            <button type="submit" class="bg-[#12A594] hover:bg-[#0e8a7d] text-white font-medium text-sm py-2.5 px-5 rounded-lg transition-colors">
                Save Changes
            </button>
            <a href="{{ route('users.index') }}" class="text-[#6B7A93] hover:text-[#1E2530] text-sm py-2.5 px-4">Cancel</a>
        </div>
    </form>

    @if($errors->any())
        <div class="mt-4 bg-red-50 border border-red-200 rounded-lg p-4">
            <ul class="list-disc list-inside text-sm text-red-700">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif
</div>

<script>
    // Purely cosmetic — dims the branch field for Owner/Admin since it's
    // optional for that role. Server-side validation is the real source
    // of truth (see UserController::update), this is just a visual hint.
    (function () {
        const roleSelect = document.getElementById('role-select');
        const branchField = document.getElementById('branch-field');

        function updateBranchField() {
            branchField.style.opacity = roleSelect.value === 'owner_admin' ? '0.5' : '1';
        }

        roleSelect.addEventListener('change', updateBranchField);
        updateBranchField();
    })();
</script>
@endsection
