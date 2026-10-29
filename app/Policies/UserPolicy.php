<?php

namespace App\Policies;

use App\Enums\PermissionSlug;
use App\Models\User;

class UserPolicy
{
    /**
     * Determine whether the actor may browse the admin user directory.
     */
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission(PermissionSlug::UsersView);
    }

    /**
     * Determine whether the actor may change the target user's global roles.
     */
    public function updateRoles(User $actor, User $target): bool
    {
        return $actor->hasPermission(PermissionSlug::UsersRolesManage);
    }

    /**
     * Determine whether the actor may delete the target account.
     */
    public function delete(User $actor, User $target): bool
    {
        return $actor->is($target);
    }
}
