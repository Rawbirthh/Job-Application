<?php

namespace App\Policies;

use App\Enums\RoleSlug;
use App\Models\SavedJob;
use App\Models\User;

class SavedJobPolicy
{
    /**
     * Determine whether the actor may view any saved jobs.
     */
    public function viewAny(User $actor): bool
    {
        return $actor->hasRole(RoleSlug::JobSeeker);
    }

    /**
     * Determine whether the actor may save a job.
     */
    public function create(User $actor): bool
    {
        return $actor->hasRole(RoleSlug::JobSeeker);
    }

    /**
     * Determine whether the actor may delete the saved job.
     */
    public function delete(User $actor, SavedJob $savedJob): bool
    {
        return $actor->is($savedJob->user);
    }
}
