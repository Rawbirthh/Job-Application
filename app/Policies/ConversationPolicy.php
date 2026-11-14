<?php

namespace App\Policies;

use App\Models\ApplicationConversation;
use App\Models\ConversationMessage;
use App\Models\JobApplication;
use App\Models\User;

class ConversationPolicy
{
    /**
     * The application's candidate, or an approved employer/company participant
     * whose company grants the given company-scoped permission.
     */
    public function viewApplication(User $actor, JobApplication $application): bool
    {
        if ($actor->is($application->user)) {
            return true;
        }

        return $this->hasEmployerPermission($actor, $application, 'applicants.view');
    }

    /**
     * Same predicate as viewApplication, with send permission on the employer side.
     */
    public function sendApplication(User $actor, JobApplication $application): bool
    {
        if ($actor->is($application->user)) {
            return true;
        }

        return $this->hasEmployerPermission($actor, $application, 'applicants.manage');
    }

    public function view(User $actor, ApplicationConversation $conversation): bool
    {
        return $this->viewApplication($actor, $conversation->application);
    }

    public function send(User $actor, ApplicationConversation $conversation): bool
    {
        return $this->sendApplication($actor, $conversation->application);
    }

    /**
     * Sender-only edit, still requiring the live send predicate (a revoked or
     * demoted employer member cannot edit even their own past messages).
     */
    public function edit(User $actor, ConversationMessage $message): bool
    {
        return $actor->is($message->sender)
            && $this->sendApplication($actor, $message->conversation->application);
    }

    /**
     * Sender-only delete, still requiring the live send predicate.
     */
    public function delete(User $actor, ConversationMessage $message): bool
    {
        return $this->edit($actor, $message);
    }

    /**
     * Inbox listing deliberately has no policy method — the inbox query itself
     * is the authorization boundary (only visible rows are returned).
     */
    private function hasEmployerPermission(User $actor, JobApplication $application, string $permission): bool
    {
        $company = $application->jobPosting->company;

        return $actor->isApprovedEmployer()
            && $company !== null
            && $actor->hasCompanyPermission($company, $permission);
    }
}
