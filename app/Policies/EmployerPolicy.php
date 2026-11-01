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
     * Determine whether the actor may approve or reject an employer request.
     */
    public function review(User $actor, User $user): bool
    {
        return $actor->hasPermission(PermissionSlug::EmployersManage);
    }

    /**
     * Determine whether the actor may suspend an approved employer.
     */
    public function suspend(User $actor, User $user): bool
    {
        return $actor->hasPermission(PermissionSlug::EmployersManage);
    }

    /**
     * Determine whether the actor may reactivate a suspended employer.
     */
    public function reactivate(User $actor, User $user): bool
    {
        return $actor->hasPermission(PermissionSlug::EmployersManage);
    }
}
