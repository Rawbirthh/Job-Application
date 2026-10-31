<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WorkplaceArrangement;

class WorkplaceArrangementPolicy
{
    /**
     * Determine whether the actor may view any workplace arrangements.
     */
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission('workplace_arrangements.view');
    }

    /**
     * Determine whether the actor may view the workplace arrangement.
     */
    public function view(User $actor, WorkplaceArrangement $workplaceArrangement): bool
    {
        return $actor->hasPermission('workplace_arrangements.view');
    }

    /**
     * Determine whether the actor may create new workplace arrangements.
     */
    public function create(User $actor): bool
    {
        return $actor->hasPermission('workplace_arrangements.manage');
    }

    /**
     * Determine whether the actor may update the workplace arrangement.
     */
    public function update(User $actor, WorkplaceArrangement $workplaceArrangement): bool
    {
        return $actor->hasPermission('workplace_arrangements.manage');
    }

    /**
     * Determine whether the actor may delete the workplace arrangement.
     */
    public function delete(User $actor, WorkplaceArrangement $workplaceArrangement): bool
    {
        return $actor->hasPermission('workplace_arrangements.manage');
    }
}
