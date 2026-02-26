<?php

namespace App\Policies;

use App\Models\Branch;
use App\Models\User;

class BranchPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin() || $user->hasAnyRole([\App\Models\Role::ADMIN_CABANG]);
    }

    public function view(User $user, Branch $branch): bool
    {
        return $user->isSuperAdmin() || $user->hasAnyRole([\App\Models\Role::ADMIN_CABANG]);
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function update(User $user, Branch $branch): bool
    {
        return $user->isSuperAdmin() || $user->hasAnyRole([\App\Models\Role::ADMIN_CABANG]);
    }

    public function delete(User $user, Branch $branch): bool
    {
        return $user->isSuperAdmin();
    }
}
