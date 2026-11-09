<?php

namespace App\Policies;

use App\Enums\RoleSlug;
use App\Models\Job;
use App\Models\JobApplication;
use App\Models\User;

class JobApplicationPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasRole(RoleSlug::JobSeeker);
    }

    public function viewAnyForJob(User $actor, Job $job): bool
    {
        return $actor->isApprovedEmployer()
            && $job->company !== null
            && $actor->hasCompanyPermission($job->company, 'applicants.view');
    }

    public function create(User $actor): bool
    {
        return $actor->hasRole(RoleSlug::JobSeeker);
    }

    public function view(User $actor, JobApplication $jobApplication): bool
    {
        if ($actor->is($jobApplication->user)) {
            return true;
        }

        return $this->viewAsEmployer($actor, $jobApplication);
    }

    public function transitionStatus(User $actor, JobApplication $jobApplication): bool
    {
        $job = $jobApplication->jobPosting;

        return $actor->isApprovedEmployer()
            && $job->company !== null
            && $actor->hasCompanyPermission($job->company, 'applicants.manage');
    }

    public function withdraw(User $actor, JobApplication $jobApplication): bool
    {
        return $actor->is($jobApplication->user);
    }

    public function viewAsEmployer(User $actor, JobApplication $jobApplication): bool
    {
        $job = $jobApplication->jobPosting;

        return $actor->isApprovedEmployer()
            && $job->company !== null
            && $actor->hasCompanyPermission($job->company, 'applicants.view');
    }
}
