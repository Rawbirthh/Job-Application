<?php

namespace App\Policies;

use App\Models\ApplicationNote;
use App\Models\JobApplication;
use App\Models\User;

class ApplicationNotePolicy
{
    /**
     * Private recruitment data: any currently authorized company participant
     * (company-scoped applicants.view) may read.
     */
    public function view(User $actor, ApplicationNote $note): bool
    {
        return $this->hasEmployerPermission($actor, $note->application, 'applicants.view');
    }

    public function createForApplication(User $actor, JobApplication $application): bool
    {
        return $this->hasEmployerPermission($actor, $application, 'applicants.manage');
    }

    /**
     * Author-only edit, still requiring the live applicants.manage predicate
     * (a revoked or demoted member cannot edit even their own past notes).
     */
    public function update(User $actor, ApplicationNote $note): bool
    {
        return $actor->is($note->author) && $this->createForApplication($actor, $note->application);
    }

    public function delete(User $actor, ApplicationNote $note): bool
    {
        return $this->update($actor, $note);
    }

    private function hasEmployerPermission(User $actor, JobApplication $application, string $permission): bool
    {
        $company = $application->jobPosting->company;

        return $actor->isApprovedEmployer()
            && $company !== null
            && $actor->hasCompanyPermission($company, $permission);
    }
}
