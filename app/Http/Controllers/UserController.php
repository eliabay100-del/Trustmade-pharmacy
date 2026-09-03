<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function index()
    {
        $users = User::with('branch')->orderBy('name')->get();
        return view('users.index', compact('users'));
    }

    public function create()
    {
        $branches = Branch::orderBy('name')->get();
        return view('users.create', ['branches' => $branches, 'roles' => User::ROLES]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'role' => 'required|in:' . implode(',', array_keys(User::ROLES)),
            'branch_id' => 'nullable|exists:branches,id',
        ]);

        // Owner/Admin isn't tied to a single branch — null means all-branch
        // access. For any other role, a branch is required so the app's
        // existing auth()->user()->branch_id filtering works correctly.
        if ($validated['role'] !== User::ROLE_OWNER_ADMIN && empty($validated['branch_id'])) {
            return back()->withErrors(['branch_id' => 'Branch is required for this role.'])->withInput();
        }

        User::create($validated);

        return redirect()->route('users.index')->with('success', 'User account created.');
    }

    public function edit(User $user)
    {
        $branches = Branch::orderBy('name')->get();
        return view('users.edit', compact('user', 'branches'));
    }

    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $user->id,
            'role' => 'required|in:' . implode(',', array_keys(User::ROLES)),
            'branch_id' => 'nullable|exists:branches,id',
            'password' => 'nullable|string|min:8|confirmed',
        ]);

        // Prevent changing the only owner_admin
        if ($user->role === User::ROLE_OWNER_ADMIN && $validated['role'] !== User::ROLE_OWNER_ADMIN) {
            if ($this->countOwnerAdmins() <= 1) {
                return back()->withErrors(['role' => 'Cannot change role: This is the only Owner/Admin account.'])->withInput();
            }
        }

        // Branch required for non-owner roles
        if ($validated['role'] !== User::ROLE_OWNER_ADMIN && empty($validated['branch_id'])) {
            return back()->withErrors(['branch_id' => 'Branch is required for this role.'])->withInput();
        }

        // Update password only if provided
        if (!empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        $user->update($validated);

        return redirect()->route('users.index')->with('success', 'User account updated.');
    }

    public function deactivate(Request $request, User $user)
    {
        // Prevent deactivating the only owner_admin
        if ($user->role === User::ROLE_OWNER_ADMIN) {
            if ($this->countOwnerAdmins() <= 1) {
                return back()->withErrors(['error' => 'Cannot deactivate: This is the only Owner/Admin account.']);
            }
        }

        $user->update(['active' => false]);

        return back()->with('success', 'User account deactivated.');
    }

    public function reactivate(Request $request, User $user)
    {
        $user->update(['active' => true]);

        return back()->with('success', 'User account reactivated.');
    }

    public function destroy(User $user)
    {
        // Prevent deleting the only owner_admin
        if ($user->role === User::ROLE_OWNER_ADMIN) {
            if ($this->countOwnerAdmins() <= 1) {
                return back()->withErrors(['error' => 'Cannot delete: This is the only Owner/Admin account.']);
            }
        }

        // Prevent deleting yourself
        if ($user->id === auth()->id()) {
            return back()->withErrors(['error' => 'Cannot delete your own account.']);
        }

        $user->delete();

        return redirect()->route('users.index')->with('success', 'User account deleted.');
    }

    public function resetPassword(Request $request, User $user)
    {
        $request->validate([
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user->update(['password' => Hash::make($request->password)]);

        return back()->with('success', 'Password reset successfully.');
    }

    protected function countOwnerAdmins(): int
    {
        return User::where('role', User::ROLE_OWNER_ADMIN)
                   ->where('active', true)
                   ->count();
    }
}
