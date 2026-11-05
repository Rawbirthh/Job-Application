<?php

namespace App\Policies;

use App\Enums\RoleSlug;
use App\Models\CandidateResume;
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
        return $actor->is($resume->user);
    }

    /**
     * Determine whether the actor may delete the resume file.
     */
    public function delete(User $actor, CandidateResume $resume): bool
    {
        return $actor->is($resume->user);
    }
}
