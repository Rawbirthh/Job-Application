<?php

namespace App\Policies;

use App\Models\Permission;
use App\Models\User;

class PermissionPolicy
{
    /**
     * Determine whether the actor may view any permissions.
     */
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission('permissions.view');
    }

    /**
     * Determine whether the actor may view the permission.
     */
    public function view(User $actor, Permission $permission): bool
    {
        return $actor->hasPermission('permissions.view');
    }

    /**
     * Determine whether the actor may create new permissions.
     */
    public function create(User $actor): bool
    {
        return $actor->hasPermission('permissions.manage');
    }

    /**
     * Determine whether the actor may update the permission.
     */
    public function update(User $actor, Permission $permission): bool
    {
        // Prevent modification of system permissions and their system flag
        if ($permission->is_system) {
            return false;
        }

        return $actor->hasPermission('permissions.manage');
    }

    /**
     * Determine whether the actor may delete the permission.
     */
    public function delete(User $actor, Permission $permission): bool
    {
        // Prevent deletion of system permissions
        if ($permission->is_system) {
            return false;
        }

        return $actor->hasPermission('permissions.manage');
    }
}
