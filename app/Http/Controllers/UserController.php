<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Http\Request;

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

        User::create($validated + ['active' => true]);

        return redirect()->route('users.index')->with('success', 'User account created.');
    }

    public function edit(User $user)
    {
        return view('users.edit', [
            'user' => $user,
            'branches' => Branch::orderBy('name')->get(),
            'roles' => User::ROLES,
        ]);
    }

    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $user->id,
            'password' => 'nullable|string|min:8|confirmed',
            'role' => 'required|in:' . implode(',', array_keys(User::ROLES)),
            'branch_id' => 'nullable|exists:branches,id',
            'active' => 'nullable|boolean',
        ]);
        if ($validated['role'] !== User::ROLE_OWNER_ADMIN && empty($validated['branch_id'])) {
            return back()->withErrors(['branch_id' => 'Branch is required for this role.'])->withInput();
        }
        if (empty($validated['password'])) unset($validated['password']);
        $validated['active'] = $request->boolean('active');
        $user->update($validated);
        return redirect()->route('users.index')->with('success', 'Staff account updated.');
    }
}
