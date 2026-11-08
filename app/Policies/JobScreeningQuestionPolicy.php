<?php

namespace App\Policies;

use App\Models\Job;
use App\Models\JobScreeningQuestion;
use App\Models\User;

class JobScreeningQuestionPolicy
{
    private function eligible(User $actor, Job $job): bool
    {
        return $actor->isApprovedEmployer()
            && $job->company !== null
            && $job->company->owner_user_id === $actor->id
            && in_array($job->status, ['draft', 'rejected', 'pending_review', 'published'], true);
    }

    public function viewAny(User $actor, Job $job): bool
    {
        return $this->eligible($actor, $job);
    }

    public function create(User $actor, Job $job): bool
    {
        return $this->eligible($actor, $job);
    }

    public function applyTemplate(User $actor, Job $job): bool
    {
        return $this->eligible($actor, $job);
    }

    public function reorder(User $actor, Job $job): bool
    {
        return $this->eligible($actor, $job);
    }

    public function update(User $actor, JobScreeningQuestion $question): bool
    {
        return $this->eligible($actor, $question->jobPosting);
    }

    public function delete(User $actor, JobScreeningQuestion $question): bool
    {
        return $this->eligible($actor, $question->jobPosting);
    }
}
