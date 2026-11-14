<?php

namespace App\Policies;

use App\Models\ApplicationEvaluation;
use App\Models\JobApplication;
use App\Models\User;

class ApplicationEvaluationPolicy
{
    /**
     * Private recruitment data: any currently authorized company participant
     * (company-scoped applicants.view) may read.
     */
    public function view(User $actor, ApplicationEvaluation $evaluation): bool
    {
        return $this->hasEmployerPermission($actor, $evaluation->application, 'applicants.view');
    }

    public function createForApplication(User $actor, JobApplication $application): bool
    {
        return $this->hasEmployerPermission($actor, $application, 'applicants.manage');
    }

    // Intentionally no update()/delete(): evaluations are append-only (D3).

    private function hasEmployerPermission(User $actor, JobApplication $application, string $permission): bool
    {
        $company = $application->jobPosting->company;

        return $actor->isApprovedEmployer()
            && $company !== null
            && $actor->hasCompanyPermission($company, $permission);
    }
}
