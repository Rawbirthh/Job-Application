<?php

namespace App\Broadcasting;

use App\Models\ApplicationConversation;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Authorization for private channels. Contains no state of its own — the
 * decision is derived from the application, job, company, and company
 * permission through ConversationPolicy, exactly as the HTTP endpoints do.
 */
class ConversationChannel
{
    /**
     * Whether the user may subscribe to the conversation's private channel.
     */
    public function join(User $user, string $conversationId): bool
    {
        $conversation = ApplicationConversation::query()
            ->whereKey($conversationId)
            ->first();

        if (! $conversation instanceof ApplicationConversation) {
            return false;
        }

        $conversation->loadMissing(['application.jobPosting.company']);

        return Gate::forUser($user)->allows('view', $conversation);
    }
}
