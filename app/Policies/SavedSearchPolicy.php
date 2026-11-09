<?php

namespace App\Policies;

use App\Enums\RoleSlug;
use App\Models\SavedSearch;
use App\Models\User;

class SavedSearchPolicy
{
    /**
     * Determine whether the actor may view any saved searches.
     */
    public function viewAny(User $actor): bool
    {
        return $actor->hasRole(RoleSlug::JobSeeker);
    }

    /**
     * Determine whether the actor may save a search.
     */
    public function create(User $actor): bool
    {
        return $actor->hasRole(RoleSlug::JobSeeker);
    }

    /**
     * Determine whether the actor may view the saved search.
     */
    public function view(User $actor, SavedSearch $savedSearch): bool
    {
        return $actor->is($savedSearch->user);
    }

    /**
     * Determine whether the actor may update the saved search.
     */
    public function update(User $actor, SavedSearch $savedSearch): bool
    {
        return $actor->is($savedSearch->user);
    }

    /**
     * Determine whether the actor may delete the saved search.
     */
    public function delete(User $actor, SavedSearch $savedSearch): bool
    {
        return $actor->is($savedSearch->user);
    }
}
