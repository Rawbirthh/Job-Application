<?php

namespace App\Policies;

use App\Models\Interview;
use App\Models\JobApplication;
use App\Models\User;

class InterviewPolicy
{
    /**
     * Determine whether the actor may view the interview round.
     */
    public function view(User $actor, Interview $interview): bool
    {
        return $actor->is($interview->jobApplication->user)
            || $this->ownsApplicationCompany($actor, $interview->jobApplication);
    }

    /**
     * Determine whether the actor may reschedule or cancel the interview round.
     */
    public function manage(User $actor, Interview $interview): bool
    {
        return $this->ownsApplicationCompany($actor, $interview->jobApplication);
    }

    /**
     * Determine whether the actor may respond to the interview round.
     */
    public function respond(User $actor, Interview $interview): bool
    {
        return $actor->is($interview->jobApplication->user);
    }

    /**
     * Determine whether the actor may schedule a new interview round for the application.
     */
    public function createForApplication(User $actor, JobApplication $application): bool
    {
        return $this->ownsApplicationCompany($actor, $application);
    }

    private function ownsApplicationCompany(User $actor, JobApplication $application): bool
    {
        $company = $application->jobPosting->company;

        return $actor->isApprovedEmployer()
            && $company !== null
            && $company->owner_user_id === $actor->id;
    }
}
