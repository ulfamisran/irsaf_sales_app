<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\View;

class UserManagementController extends Controller
{
    public function index(Request $request): View
    {
        $query = User::query()->with(['roles', 'branch']);

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%');
            });
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $users = $query->orderBy('name')->paginate(15)->withQueryString();

        return view('users.index', compact('users'));
    }

    public function create(): View
    {
        $roles = Role::query()->orderBy('display_name')->get();
        $branches = Branch::query()->orderBy('name')->get();

        return view('users.create', compact('roles', 'branches'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'role_id' => ['required', 'exists:roles,id'],
            'branch_id' => ['nullable', 'exists:branches,id'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $role = Role::findOrFail($validated['role_id']);
        $needsBranch = in_array($role->name, [Role::ADMIN_CABANG, Role::KASIR], true);
        if ($needsBranch && empty($validated['branch_id'])) {
            return back()
                ->withErrors(['branch_id' => __('Cabang wajib diisi untuk peran ini.')])
                ->withInput();
        }

        if (! $needsBranch) {
            $validated['branch_id'] = null;
        }

        $email = Str::lower($validated['email']);
        $user = User::create([
            'name' => $validated['name'],
            'email' => $email,
            'branch_id' => $validated['branch_id'],
            'password' => Hash::make($email),
            'is_active' => (bool) $request->input('is_active', false),
        ]);

        $user->roles()->sync([$role->id]);

        return redirect()
            ->route('users.index')
            ->with('success', __('User berhasil dibuat. Password awal sama dengan email.'));
    }

    public function resetPassword(User $user): RedirectResponse
    {
        $user->forceFill([
            'password' => Hash::make($user->email),
        ])->save();

        return back()->with('success', __('Password berhasil direset ke email user.'));
    }

    public function toggleActive(User $user): RedirectResponse
    {
        if (auth()->id() === $user->id) {
            return back()->with('error', __('Tidak dapat menonaktifkan akun sendiri.'));
        }

        $user->is_active = ! $user->is_active;
        $user->save();

        return back()->with('success', $user->is_active
            ? __('User berhasil diaktifkan.')
            : __('User berhasil dinonaktifkan.')
        );
    }
}
