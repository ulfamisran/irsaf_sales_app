<?php

namespace App\Http\Controllers;

use App\Models\AppModule;
use App\Models\Role;
use App\Models\RoleModulePermission;
use App\Services\ModuleAccessService;
use App\Support\ModuleAccessCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RoleAccessController extends Controller
{
    public function index(ModuleAccessService $access): View
    {
        $this->authorizeManager();

        $ready = Schema::hasTable('role_module_permissions');
        if ($ready) {
            ModuleAccessCatalog::install();
            $access->flush();
        }

        $roles = Role::query()->orderBy('id')->get();
        $counts = $ready
            ? RoleModulePermission::query()
                ->selectRaw('role_id, COUNT(*) as total')
                ->groupBy('role_id')
                ->pluck('total', 'role_id')
            : collect();

        return view('role-access.index', [
            'roles' => $roles,
            'counts' => $counts,
            'ready' => $ready,
            'actions' => ModuleAccessCatalog::ACTIONS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeManager();

        $displayName = trim((string) $request->input('display_name'));
        $rawName = trim((string) $request->input('name'));
        $name = $this->roleCode($rawName !== '' ? $rawName : $displayName);
        $request->merge([
            'display_name' => $displayName,
            'name' => $name,
        ]);

        $validated = $request->validate([
            'display_name' => ['required', 'string', 'max:100'],
            'name' => ['required', 'string', 'max:50', 'regex:/^[a-z][a-z0-9_]*$/', Rule::unique('roles', 'name')],
            'description' => ['nullable', 'string', 'max:255'],
        ], [
            'name.regex' => __('Kode role harus diawali huruf dan hanya berisi huruf kecil, angka, atau garis bawah.'),
            'name.unique' => __('Kode role ini sudah dipakai.'),
        ]);

        if ($validated['name'] === Role::SUPER_ADMIN) {
            return back()
                ->withErrors(['name' => __('Role Super Admin tidak dapat dibuat ulang.')])
                ->withInput();
        }

        $role = Role::query()->create([
            'name' => $validated['name'],
            'display_name' => $validated['display_name'],
            'description' => trim((string) ($validated['description'] ?? '')) ?: null,
        ]);

        return redirect()
            ->route('role-access.edit', $role)
            ->with('success', __('Role berhasil ditambahkan. Atur akses modulnya di halaman ini.'));
    }

    public function edit(Role $role, ModuleAccessService $access): View
    {
        $this->authorizeManager();
        abort_unless(Schema::hasTable('app_modules'), 503, __('Jalankan php artisan migrate untuk mengaktifkan akses modul.'));

        ModuleAccessCatalog::install();
        $access->flush();

        $modules = AppModule::query()->orderBy('sort_order')->orderBy('name')->get()
            ->reject(fn (AppModule $module) => $module->slug === 'verification')
            ->values();
        $granted = RoleModulePermission::query()
            ->where('role_id', $role->id)
            ->get()
            ->mapWithKeys(fn (RoleModulePermission $row) => [$row->module_id.'|'.$row->action => true]);

        return view('role-access.edit', [
            'role' => $role,
            'modules' => $modules,
            'grouped' => $modules->groupBy('group_name'),
            'granted' => $granted,
            'actions' => ModuleAccessCatalog::ACTIONS,
            'locked' => $role->name === Role::SUPER_ADMIN,
        ]);
    }

    public function update(Request $request, Role $role, ModuleAccessService $access): RedirectResponse
    {
        $this->authorizeManager();

        if ($role->name === Role::SUPER_ADMIN) {
            return redirect()
                ->route('role-access.edit', $role)
                ->with('error', __('Akses Super Admin selalu penuh dan tidak dapat dibatasi.'));
        }

        $modules = AppModule::query()->get()->keyBy('slug');
        abort_if($modules->isEmpty(), 503, __('Jalankan php artisan migrate untuk mengaktifkan akses modul.'));
        $allowed = array_fill_keys(ModuleAccessCatalog::actionKeys(), true);
        $input = $request->input('permissions', []);
        $now = now();
        $rows = [];

        if (is_array($input)) {
            foreach ($input as $slug => $actions) {
                $module = $modules->get((string) $slug);
                if (! $module || ! is_array($actions)) {
                    continue;
                }
                foreach ($actions as $action => $on) {
                    if (! isset($allowed[$action]) || ! $on) {
                        continue;
                    }
                    $rows[] = [
                        'role_id' => $role->id,
                        'module_id' => $module->id,
                        'action' => $action,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }
        }

        $verificationId = AppModule::query()->where('slug', 'verification')->value('id');
        if ($verificationId) {
            $kept = DB::table('role_module_permissions')
                ->where('role_id', $role->id)
                ->where('module_id', $verificationId)
                ->get();
            foreach ($kept as $keptRow) {
                $rows[] = [
                    'role_id' => $role->id,
                    'module_id' => $verificationId,
                    'action' => $keptRow->action,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        DB::transaction(function () use ($role, $rows): void {
            DB::table('role_module_permissions')->where('role_id', $role->id)->delete();
            foreach (array_chunk($rows, 400) as $chunk) {
                DB::table('role_module_permissions')->insert($chunk);
            }
        });

        $access->flush();

        return redirect()
            ->route('role-access.edit', $role)
            ->with('success', __('Akses role berhasil disimpan.'));
    }

    private function roleCode(string $value): string
    {
        $code = Str::of($value)
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '_')
            ->trim('_')
            ->toString();

        return mb_substr($code, 0, 50);
    }

    private function authorizeManager(): void
    {
        $user = auth()->user();
        abort_unless(
            $user && ($user->isStrictSuperAdmin() || $user->isAdminPusat()),
            403,
            __('Hanya Super Admin dan Admin Pusat yang dapat mengatur akses modul.')
        );
    }
}
