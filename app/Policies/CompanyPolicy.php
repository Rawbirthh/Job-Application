<?php

namespace App\Policies;

use App\Models\Company;
use App\Models\User;

class CompanyPolicy
{
    /**
     * Determine whether the actor may view any companies.
     */
    public function viewAny(User $actor): bool
    {
        return $actor->isApprovedEmployer();
    }

    /**
     * Determine whether the actor may view the company.
     */
    public function view(User $actor, Company $company): bool
    {
        return $actor->isApprovedEmployer() && $actor->id === $company->owner_user_id;
    }

    /**
     * Determine whether the actor may create a company.
     */
    public function create(User $actor): bool
    {
        return $actor->isApprovedEmployer();
    }

    /**
     * Determine whether the actor may update the company.
     */
    public function update(User $actor, Company $company): bool
    {
        return $actor->isApprovedEmployer() && $actor->id === $company->owner_user_id;
    }
}
