<?php

namespace App\Policies;

use App\Enums\RoleSlug;
use App\Models\CandidateResume;
use App\Models\JobApplication;
use App\Models\User;

class CandidateResumePolicy
{
    /**
     * Determine whether the actor may upload a resume.
     */
    public function create(User $actor): bool
    {
        return $actor->hasRole(RoleSlug::JobSeeker);
    }

    /**
     * Determine whether the actor may view the resume details.
     */
    public function view(User $actor, CandidateResume $resume): bool
    {
        if ($actor->is($resume->user)) {
            return true;
        }

        return $actor->isApprovedEmployer()
            && JobApplication::query()
                ->where('candidate_resume_id', $resume->id)
                ->whereHas('jobPosting.company', fn ($query) => $query->where('owner_user_id', $actor->id))
                ->exists();
    }

    /**
     * Determine whether the actor may delete the resume file.
     */
    public function delete(User $actor, CandidateResume $resume): bool
    {
        return $actor->is($resume->user);
    }
}
