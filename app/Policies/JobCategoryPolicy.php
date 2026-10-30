<?php

namespace App\Policies;

use App\Models\JobCategory;
use App\Models\User;

class JobCategoryPolicy
{
    /**
     * Determine whether the actor may view any job categories.
     */
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission('categories.view');
    }

    /**
     * Determine whether the actor may view the job category.
     */
    public function view(User $actor, JobCategory $jobCategory): bool
    {
        return $actor->hasPermission('categories.view');
    }

    /**
     * Determine whether the actor may create new job categories.
     */
    public function create(User $actor): bool
    {
        return $actor->hasPermission('categories.manage');
    }

    /**
     * Determine whether the actor may update the job category.
     */
    public function update(User $actor, JobCategory $jobCategory): bool
    {
        return $actor->hasPermission('categories.manage');
    }

    /**
     * Determine whether the actor may delete the job category.
     */
    public function delete(User $actor, JobCategory $jobCategory): bool
    {
        return $actor->hasPermission('categories.manage');
    }
}
