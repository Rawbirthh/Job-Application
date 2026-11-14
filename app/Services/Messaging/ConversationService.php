<?php

namespace App\Services\Messaging;

use App\Enums\CompanyMemberStatus;
use App\Models\ApplicationConversation;
use App\Models\Company;
use App\Models\CompanyMember;
use App\Models\ConversationMessage;
use App\Models\ConversationParticipant;
use App\Models\JobApplication;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConversationService
{
    /**
     * Company ids whose applications the given user may read, bounded by the
     * requesting user's memberships. Used by the inbox query, the unread badge,
     * and channel authorisation via the policy.
     *
     * @return array<int, int>
     */
    public function allowedCompanyIdsFor(User $user): array
    {
        if (! $user->isApprovedEmployer()) {
            return [];
        }

        $owned = Company::query()->where('owner_user_id', $user->id)->pluck('id')->all();

        $memberIds = CompanyMember::query()
            ->where('user_id', $user->id)
            ->where('status', CompanyMemberStatus::Active->value)
            ->get(['company_id', 'company_role'])
            ->filter(fn (CompanyMember $membership): bool => in_array('applicants.view', $membership->company_role->permissions(), true))
            ->pluck('company_id')
            ->all();

        return array_values(array_unique(array_map(intval(...), [...$owned, ...$memberIds])));
    }

    /**
     * Number of conversations with unread messages — used on the inbox page
     * only, never on every request. Two index-driven branches (driven from
     * job_applications / job_postings), never a full scan of conversations.
     */
    public function unreadCountFor(User $user): int
    {
        $ids = array_map(
            intval(...),
            DB::table('job_applications as a')
                ->join('application_conversations as c', 'c.application_id', '=', 'a.id')
                ->leftJoin('conversation_participants as cp', function ($join) use ($user): void {
                    $join->on('cp.conversation_id', '=', 'c.id')
                        ->where('cp.user_id', '=', $user->id);
                })
                ->where('a.user_id', $user->id)
                ->whereRaw('COALESCE(c.last_message_id, 0) > COALESCE(cp.last_read_message_id, 0)')
                ->pluck('c.id')
                ->all()
        );

        $companyIds = $this->allowedCompanyIdsFor($user);

        if ($companyIds !== []) {
            array_push(
                $ids,
                ...array_map(
                    intval(...),
                    DB::table('job_postings as j')
                        ->join('job_applications as a', 'a.job_posting_id', '=', 'j.id')
                        ->join('application_conversations as c', 'c.application_id', '=', 'a.id')
                        ->leftJoin('conversation_participants as cp', function ($join) use ($user): void {
                            $join->on('cp.conversation_id', '=', 'c.id')
                                ->where('cp.user_id', '=', $user->id);
                        })
                        ->whereIn('j.company_id', $companyIds)
                        ->whereRaw('COALESCE(c.last_message_id, 0) > COALESCE(cp.last_read_message_id, 0)')
                        ->pluck('c.id')
                        ->all()
                )
            );
        }

        return count(array_unique($ids));
    }

    /**
     * Get-or-create the conversation for an application. Unique constraint on
     * application_id plus insert-catch keeps this race-safe.
     */
    public function openForApplication(JobApplication $application): ApplicationConversation
    {
        $existing = ApplicationConversation::query()
            ->where('application_id', $application->id)
            ->first();

        if ($existing instanceof ApplicationConversation) {
            return $existing;
        }

        try {
            return ApplicationConversation::query()->create([
                'application_id' => $application->id,
                'sort_at' => now(),
            ]);
        } catch (QueryException $e) {
            if (! $this->isUniqueViolation($e)) {
                throw $e;
            }

            return ApplicationConversation::query()
                ->where('application_id', $application->id)
                ->firstOrFail();
        }
    }

    /**
     * Persist a message inside one transaction (terminal status re-checked
     * under row lock), then return it. Broadcasting and notifications happen
     * after the commit in the controller.
     */
    public function send(ApplicationConversation $conversation, User $sender, string $body): ConversationMessage
    {
        return DB::transaction(function () use ($conversation, $sender, $body): ConversationMessage {
            $application = $this->lockedApplication($conversation->application_id);

            $message = ConversationMessage::query()->create([
                'conversation_id' => $conversation->id,
                'sender_id' => $sender->id,
                'body' => $body,
            ]);

            $timestamp = $message->created_at ?? now();
            $conversation->forceFill([
                'last_message_id' => $message->id,
                'last_message_at' => $timestamp,
                'sort_at' => $timestamp,
            ])->save();

            ConversationParticipant::query()->updateOrCreate(
                ['conversation_id' => $conversation->id, 'user_id' => $sender->id],
                ['last_read_message_id' => $message->id],
            );

            return $message;
        });
    }

    /**
     * Edit a message. Tombstones cannot be edited.
     */
    public function update(ConversationMessage $message, string $body): ConversationMessage
    {
        return DB::transaction(function () use ($message, $body): ConversationMessage {
            if ($message->trashed()) {
                throw ValidationException::withMessages([
                    'body' => __('This message can no longer be edited.'),
                ]);
            }

            $conversation = ApplicationConversation::query()->findOrFail($message->conversation_id);
            $this->lockedApplication($conversation->application_id);

            $message->forceFill([
                'body' => $body,
                'edited_at' => now(),
            ])->save();

            return $message->refresh();
        });
    }

    /**
     * Delete (tombstone) a message: body destroyed, row retained.
     */
    public function delete(ConversationMessage $message): ConversationMessage
    {
        return DB::transaction(function () use ($message): ConversationMessage {
            if ($message->trashed()) {
                throw ValidationException::withMessages([
                    'body' => __('This message can no longer be deleted.'),
                ]);
            }

            $conversation = ApplicationConversation::query()->findOrFail($message->conversation_id);
            $this->lockedApplication($conversation->application_id);

            $message->forceFill(['body' => null])->save();
            $message->delete();

            return $message->refresh();
        });
    }

    /**
     * Advance the viewer's read cursor to the newest message. Idempotent;
     * a no-op for empty conversations.
     */
    public function markRead(ApplicationConversation $conversation, User $viewer): void
    {
        if ($conversation->last_message_id === null) {
            return;
        }

        ConversationParticipant::query()->updateOrCreate(
            ['conversation_id' => $conversation->id, 'user_id' => $viewer->id],
            ['last_read_message_id' => $conversation->last_message_id],
        );
    }

    /**
     * Lock the application row and reject writes on terminal applications.
     */
    private function lockedApplication(int $applicationId): JobApplication
    {
        /** @var JobApplication $application */
        $application = JobApplication::query()->lockForUpdate()->findOrFail($applicationId);

        if ($application->status->isTerminal()) {
            throw ValidationException::withMessages([
                'body' => __('Messaging is closed for this application.'),
            ]);
        }

        return $application;
    }

    private function isUniqueViolation(QueryException $e): bool
    {
        $sqlState = (string) $e->getCode();
        $driverCode = (int) ($e->errorInfo[1] ?? 0);

        // MySQL duplicate-key is SQLSTATE 23000 / driver error 1062; SQLite
        // reports 23000 with a UNIQUE constraint message. FK violations are
        // also 23000 — excluded explicitly by driver code.
        return $driverCode === 1062
            || ($sqlState === '23000' && str_contains(strtolower($e->getMessage()), 'unique'));
    }
}
