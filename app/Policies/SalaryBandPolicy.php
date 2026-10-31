<?php

namespace App\Policies;

use App\Models\SalaryBand;
use App\Models\User;

class SalaryBandPolicy
{
    /**
     * Determine whether the actor may view any salary bands.
     */
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission('salary_bands.view');
    }

    /**
     * Determine whether the actor may view the salary band.
     */
    public function view(User $actor, SalaryBand $salaryBand): bool
    {
        return $actor->hasPermission('salary_bands.view');
    }

    /**
     * Determine whether the actor may create new salary bands.
     */
    public function create(User $actor): bool
    {
        return $actor->hasPermission('salary_bands.manage');
    }

    /**
     * Determine whether the actor may update the salary band.
     */
    public function update(User $actor, SalaryBand $salaryBand): bool
    {
        return $actor->hasPermission('salary_bands.manage');
    }

    /**
     * Determine whether the actor may delete the salary band.
     */
    public function delete(User $actor, SalaryBand $salaryBand): bool
    {
        return $actor->hasPermission('salary_bands.manage');
    }
}
