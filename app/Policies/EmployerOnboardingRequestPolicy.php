<?php

namespace App\Policies;

use App\Models\EmployerOnboardingRequest;
use App\Models\User;

class EmployerOnboardingRequestPolicy
{
    /**
     * Determine whether the actor may view the onboarding request.
     */
    public function view(User $actor, EmployerOnboardingRequest $onboardingRequest): bool
    {
        return $actor->is($onboardingRequest->user);
    }

    /**
     * Determine whether the actor may submit an onboarding request.
     */
    public function create(User $actor): bool
    {
        return true;
    }
}
