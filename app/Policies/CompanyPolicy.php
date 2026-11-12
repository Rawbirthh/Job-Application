<?php

namespace App\Policies;

use App\Models\Company;
use App\Models\User;

class CompanyPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->isApprovedEmployer();
    }

    public function view(User $actor, Company $company): bool
    {
        return $actor->isApprovedEmployer() && $actor->companyRole($company) !== null;
    }

    public function create(User $actor): bool
    {
        return $actor->isApprovedEmployer();
    }

    public function update(User $actor, Company $company): bool
    {
        return $actor->isApprovedEmployer() && $actor->hasCompanyPermission($company, 'company.update');
    }

    public function viewMembers(User $actor, Company $company): bool
    {
        return $actor->isApprovedEmployer() && $actor->hasCompanyPermission($company, 'members.manage');
    }

    public function manageMembers(User $actor, Company $company): bool
    {
        return $actor->isApprovedEmployer() && $actor->hasCompanyPermission($company, 'members.manage');
    }

    /**
     * Determine whether the actor may read the analytics of the given company.
     *
     * Company analytics are aggregate-only and are scoped to a single company,
     * so the check mirrors the other company-scoped policy methods.
     */
    public function viewAnalytics(User $actor, Company $company): bool
    {
        return $actor->isApprovedEmployer() && $actor->hasCompanyPermission($company, 'analytics.view');
    }
}
