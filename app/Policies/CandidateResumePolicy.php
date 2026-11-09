<?php

namespace App\Policies;

use App\Enums\RoleSlug;
use App\Models\CandidateResume;
use App\Models\JobApplication;
use App\Models\User;

class CandidateResumePolicy
{
    public function create(User $actor): bool
    {
        return $actor->hasRole(RoleSlug::JobSeeker);
    }

    public function view(User $actor, CandidateResume $resume): bool
    {
        if ($actor->is($resume->user)) {
            return true;
        }

        return $actor->isApprovedEmployer()
            && JobApplication::query()
                ->where('candidate_resume_id', $resume->id)
                ->whereHas('jobPosting.company', fn ($query) => $query->forManagingUser($actor))
                ->exists();
    }

    public function delete(User $actor, CandidateResume $resume): bool
    {
        return $actor->is($resume->user);
    }
}
