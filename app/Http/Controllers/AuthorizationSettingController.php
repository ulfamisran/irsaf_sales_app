<?php

namespace App\Http\Controllers;

use App\Models\AuthorizationAssignee;
use App\Models\AuthorizationSetting;
use App\Models\Role;
use App\Models\User;
use App\Services\AuthorizationService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class AuthorizationSettingController extends Controller
{
    public function __construct(private AuthorizationService $authorizations) {}

    public function edit(): View
    {
        $this->authorizeManager();
        $settings = AuthorizationSetting::query()->get()->keyBy('module');
        $assignees = AuthorizationAssignee::query()->get()->groupBy('module');
        $roles = Role::query()->orderBy('display_name')->orderBy('name')->get();
        $pickerRoleIds = $this->authorizations->pickerRoleIds();
        $users = $this->eligibleUsers($pickerRoleIds);

        return view('authorization-settings.edit', [
            'modules' => AuthorizationService::MODULES,
            'settings' => $settings,
            'assignees' => $assignees,
            'users' => $users,
            'roles' => $roles,
            'pickerRoleIds' => $pickerRoleIds,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $this->authorizeManager();

        $allRoleIds = Role::query()->pluck('id')->map(fn ($id) => (int) $id)->all();
        $postedRoleIds = array_values(array_intersect(
            array_map('intval', (array) $request->input('picker_role_ids', [])),
            $allRoleIds
        ));
        $this->authorizations->savePickerRoles($postedRoleIds);

        $allowedIds = $this->eligibleUsers($postedRoleIds)->pluck('id')->map(fn ($id) => (int) $id)->all();
        $modules = $request->input('modules', []);
        foreach ($modules as $slug => $row) {
            $posted = array_map('intval', (array) ($row['user_ids'] ?? []));
            $modules[$slug]['user_ids'] = array_values(array_intersect($posted, $allowedIds));
        }
        $this->authorizations->saveSettings($modules);

        return redirect()->route('authorization-settings.edit')->with('success', __('Konfigurasi otorisasi disimpan.'));
    }

    /**
     * @param  list<int>  $roleIds
     * @return Collection<int, User>
     */
    private function eligibleUsers(array $roleIds): Collection
    {
        if ($roleIds === [] || ! Schema::hasTable('role_user')) {
            return new Collection;
        }

        return User::query()
            ->with(['branch', 'warehouse', 'roles'])
            ->whereHas('roles', fn ($query) => $query->whereIn('roles.id', $roleIds))
            ->orderBy('name')
            ->get();
    }

    private function authorizeManager(): void
    {
        $user = auth()->user();
        abort_unless(
            $user && ($user->isStrictSuperAdmin() || $user->isAdminPusat()),
            403,
            __('Hanya Super Admin dan Admin Pusat yang dapat mengatur otorisasi.')
        );
    }
}
