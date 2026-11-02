<?php

namespace App\Policies;

use App\Models\Job;
use App\Models\User;

class JobPolicy
{
    /**
     * Determine whether the actor may update the job.
     */
    public function update(User $actor, Job $job): bool
    {
        return $actor->isApprovedEmployer()
            && $job->status === 'draft'
            && $job->company->owner_user_id === $actor->id;
    }
}
