<?php

namespace App\Policies;

use App\Models\EmploymentType;
use App\Models\User;

class EmploymentTypePolicy
{
    /**
     * Determine whether the actor may view any employment types.
     */
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission('employment_types.view');
    }

    /**
     * Determine whether the actor may view the employment type.
     */
    public function view(User $actor, EmploymentType $employmentType): bool
    {
        return $actor->hasPermission('employment_types.view');
    }

    /**
     * Determine whether the actor may create new employment types.
     */
    public function create(User $actor): bool
    {
        return $actor->hasPermission('employment_types.manage');
    }

    /**
     * Determine whether the actor may update the employment type.
     */
    public function update(User $actor, EmploymentType $employmentType): bool
    {
        return $actor->hasPermission('employment_types.manage');
    }

    /**
     * Determine whether the actor may delete the employment type.
     */
    public function delete(User $actor, EmploymentType $employmentType): bool
    {
        return $actor->hasPermission('employment_types.manage');
    }
}
