<?php

namespace App\Policies;

use App\Enums\RoleSlug;
use App\Models\JobApplication;
use App\Models\User;

class JobApplicationPolicy
{
    /**
     * Determine whether the actor may view any job applications.
     */
    public function viewAny(User $actor): bool
    {
        return $actor->hasRole(RoleSlug::JobSeeker);
    }

    /**
     * Determine whether the actor may create a job application.
     */
    public function create(User $actor): bool
    {
        return $actor->hasRole(RoleSlug::JobSeeker);
    }

    /**
     * Determine whether the actor may view the job application.
     */
    public function view(User $actor, JobApplication $jobApplication): bool
    {
        return $actor->is($jobApplication->user);
    }
}
