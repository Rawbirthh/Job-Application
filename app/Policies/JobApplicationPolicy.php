<?php

namespace App\Policies;

use App\Enums\RoleSlug;
use App\Models\Job;
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
     * Determine whether the actor may view applicants for a specific job.
     */
    public function viewAnyForJob(User $actor, Job $job): bool
    {
        return $actor->isApprovedEmployer()
            && $job->company !== null
            && $job->company->owner_user_id === $actor->id;
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
        if ($actor->is($jobApplication->user)) {
            return true;
        }

        return $this->viewAsEmployer($actor, $jobApplication);
    }

    /**
     * Determine whether the actor may view the application as an employer.
     */
    public function viewAsEmployer(User $actor, JobApplication $jobApplication): bool
    {
        $job = $jobApplication->jobPosting;

        return $actor->isApprovedEmployer()
            && $job->company !== null
            && $job->company->owner_user_id === $actor->id;
    }
}
