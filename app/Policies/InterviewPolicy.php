<?php

namespace App\Policies;

use App\Models\Interview;
use App\Models\JobApplication;
use App\Models\User;

class InterviewPolicy
{
    public function view(User $actor, Interview $interview): bool
    {
        return $actor->is($interview->jobApplication->user)
            || $this->hasCompanyPermission($actor, $interview->jobApplication, 'applicants.view');
    }

    public function manage(User $actor, Interview $interview): bool
    {
        return $this->hasCompanyPermission($actor, $interview->jobApplication, 'applicants.manage');
    }

    public function respond(User $actor, Interview $interview): bool
    {
        return $actor->is($interview->jobApplication->user);
    }

    public function createForApplication(User $actor, JobApplication $application): bool
    {
        return $this->hasCompanyPermission($actor, $application, 'applicants.manage');
    }

    private function hasCompanyPermission(User $actor, JobApplication $application, string $permission): bool
    {
        $company = $application->jobPosting->company;

        return $actor->isApprovedEmployer()
            && $company !== null
            && $actor->hasCompanyPermission($company, $permission);
    }
}
