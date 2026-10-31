<?php

namespace App\Policies;

use App\Models\ExperienceLevel;
use App\Models\User;

class ExperienceLevelPolicy
{
    /**
     * Determine whether the actor may view any experience levels.
     */
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission('experience_levels.view');
    }

    /**
     * Determine whether the actor may view the experience level.
     */
    public function view(User $actor, ExperienceLevel $experienceLevel): bool
    {
        return $actor->hasPermission('experience_levels.view');
    }

    /**
     * Determine whether the actor may create new experience levels.
     */
    public function create(User $actor): bool
    {
        return $actor->hasPermission('experience_levels.manage');
    }

    /**
     * Determine whether the actor may update the experience level.
     */
    public function update(User $actor, ExperienceLevel $experienceLevel): bool
    {
        return $actor->hasPermission('experience_levels.manage');
    }

    /**
     * Determine whether the actor may delete the experience level.
     */
    public function delete(User $actor, ExperienceLevel $experienceLevel): bool
    {
        return $actor->hasPermission('experience_levels.manage');
    }
}
