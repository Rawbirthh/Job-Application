<?php

namespace App\Policies;

use App\Enums\RoleSlug;
use App\Models\CandidateProfile;
use App\Models\User;

class CandidateProfilePolicy
{
    public function view(User $actor, CandidateProfile $profile): bool
    {
        return $actor->is($profile->user);
    }

    public function update(User $actor, CandidateProfile $profile): bool
    {
        return $actor->is($profile->user);
    }

    public function create(User $actor): bool
    {
        return $actor->hasRole(RoleSlug::JobSeeker);
    }
}
