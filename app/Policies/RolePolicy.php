<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\User;

class RolePolicy
{
    /**
     * Determine whether the actor may view any roles.
     */
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission('roles.view');
    }

    /**
     * Determine whether the actor may view the role.
     */
    public function view(User $actor, Role $role): bool
    {
        return $actor->hasPermission('roles.view');
    }

    /**
     * Determine whether the actor may create new roles.
     */
    public function create(User $actor): bool
    {
        return $actor->hasPermission('roles.manage');
    }

    /**
     * Determine whether the actor may update the role.
     */
    public function update(User $actor, Role $role): bool
    {
        // Prevent modification of system roles and their system flag
        if ($role->is_system) {
            return false;
        }

        return $actor->hasPermission('roles.manage');
    }

    /**
     * Determine whether the actor may delete the role.
     */
    public function delete(User $actor, Role $role): bool
    {
        // Prevent deletion of system roles
        if ($role->is_system) {
            return false;
        }

        return $actor->hasPermission('roles.manage');
    }

    /**
     * Determine whether the actor may assign permissions to a role.
     */
    public function updatePermissions(User $actor, Role $role): bool
    {
        // Prevent updating permissions on system roles
        if ($role->is_system) {
            return false;
        }

        return $actor->hasPermission('roles.manage');
    }
}
