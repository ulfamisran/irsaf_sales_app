<?php

namespace App\Policies;

use App\Models\Branch;
use App\Models\User;

class BranchPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canModule('branches', 'select');
    }

    public function view(User $user, Branch $branch): bool
    {
        return $user->canModule('branches', 'select')
            && $user->canAccessBranch((int) $branch->id);
    }

    public function create(User $user): bool
    {
        return $user->canModule('branches', 'create');
    }

    public function update(User $user, Branch $branch): bool
    {
        return $user->canModule('branches', 'update')
            && $user->canAccessBranch((int) $branch->id);
    }

    public function delete(User $user, Branch $branch): bool
    {
        return $user->canModule('branches', 'delete');
    }
}
