<?php

namespace App\Policies;

use App\Enums\PermissionSlug;
use App\Models\User;

class EmployerPolicy
{
    /**
     * Determine whether the actor may browse the admin employer directory.
     */
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission(PermissionSlug::EmployersView);
    }

    /**
     * Determine whether the actor may view the target user's employer profile.
     */
    public function view(User $actor, User $user): bool
    {
        return $actor->hasPermission(PermissionSlug::EmployersView);
    }

    /**
     * Determine whether the actor may create/manage employer accounts.
     */
    public function create(User $actor): bool
    {
        return $actor->hasPermission(PermissionSlug::EmployersManage);
    }

    /**
     * Determine whether the actor may update employer status (approve/reject/suspend).
     */
    public function update(User $actor, User $user): bool
    {
        return $actor->hasPermission(PermissionSlug::EmployersManage);
    }

    /**
     * Determine whether the actor may delete/remove employer accounts.
     */
    public function delete(User $actor, User $user): bool
    {
        return $actor->hasPermission(PermissionSlug::EmployersManage);
    }
}
