<?php

namespace App\Policies;

use App\Enums\PermissionSlug;
use App\Models\Company;
use App\Models\Job;
use App\Models\User;

class JobPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission(PermissionSlug::JobsModerate);
    }

    public function create(User $actor, Company $company): bool
    {
        return $actor->isApprovedEmployer()
            && $company->is_active
            && $actor->hasCompanyPermission($company, 'jobs.create');
    }

    public function update(User $actor, Job $job): bool
    {
        return $actor->isApprovedEmployer()
            && $job->company !== null
            && $job->company->is_active
            && $actor->hasCompanyPermission($job->company, 'jobs.update')
            && in_array($job->status, ['draft', 'rejected', 'closed', 'published'], true);
    }

    public function submit(User $actor, Job $job): bool
    {
        return $actor->isApprovedEmployer()
            && $job->company !== null
            && $job->company->is_active
            && $actor->hasCompanyPermission($job->company, 'jobs.submit')
            && in_array($job->status, ['draft', 'rejected', 'closed'], true);
    }

    public function close(User $actor, Job $job): bool
    {
        if ($actor->hasPermission(PermissionSlug::JobsModerate) || $actor->hasPermission(PermissionSlug::AdminAccess)) {
            return true;
        }

        return $actor->isApprovedEmployer()
            && $job->company !== null
            && $job->company->is_active
            && $actor->hasCompanyPermission($job->company, 'jobs.close')
            && in_array($job->status, ['draft', 'rejected', 'pending_review', 'published'], true);
    }

    public function moderate(User $actor, Job $job): bool
    {
        return $actor->hasPermission(PermissionSlug::JobsModerate);
    }
}
