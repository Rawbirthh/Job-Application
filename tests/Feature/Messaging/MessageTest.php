<?php

namespace Tests\Feature\Messaging;

use App\Enums\JobApplicationStatus;
use App\Events\MessageDeleted;
use App\Events\MessageEdited;
use App\Events\MessageSent;
use App\Models\ApplicationConversation;
use App\Models\Company;
use App\Models\CompanyMember;
use App\Models\ConversationMessage;
use App\Models\Job;
use App\Models\JobApplication;
use App\Models\User;
use App\Notifications\NewConversationMessageNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Authorization\Concerns\InteractsWithAuthorizationCatalogue;
use Tests\TestCase;

class MessageTest extends TestCase
{
    use InteractsWithAuthorizationCatalogue, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAuthorizationCatalogue();
    }

    // ------------------------------------------------------------ send

    public function test_candidate_can_send_a_message(): void
    {
        [$application, $conversation, $candidate, $employer] = $this->context();

        $this->actingAs($candidate)
            ->post(route('messages.store', $conversation), ['body' => "  Hello employer\nplain text  "])
            ->assertRedirect();

        $this->assertDatabaseHas('conversation_messages', [
            'conversation_id' => $conversation->id,
            'sender_id' => $candidate->id,
            'body' => "Hello employer\nplain text",
        ]);

        $conversation->refresh();
        $message = ConversationMessage::query()->where('conversation_id', $conversation->id)->sole();

        $this->assertSame($message->id, $conversation->last_message_id);

        // The candidate reads their own message; the employer has a cursor of zero.
        $this->assertDatabaseHas('conversation_participants', [
            'conversation_id' => $conversation->id,
            'user_id' => $candidate->id,
            'last_read_message_id' => $message->id,
        ]);

        $this->assertDatabaseMissing('conversation_participants', [
            'conversation_id' => $conversation->id,
            'user_id' => $employer->id,
        ]);
    }

    public function test_employer_can_send_a_message(): void
    {
        [$application, $conversation, $candidate, $employer] = $this->context();

        $this->actingAs($employer)
            ->post(route('messages.store', $conversation), ['body' => 'Thanks for applying'])
            ->assertRedirect();

        $this->assertDatabaseHas('conversation_messages', [
            'conversation_id' => $conversation->id,
            'sender_id' => $employer->id,
        ]);
    }

    public function test_send_requires_a_non_empty_body(): void
    {
        [, $conversation, $candidate] = $this->context();

        $this->actingAs($candidate)
            ->post(route('messages.store', $conversation), ['body' => ''])
            ->assertSessionHasErrors('body');

        $this->actingAs($candidate)
            ->post(route('messages.store', $conversation), ['body' => "   \n  "])
            ->assertSessionHasErrors('body');

        $this->assertSame(0, ConversationMessage::query()->where('conversation_id', $conversation->id)->count());
    }

    public function test_send_rejects_oversized_body(): void
    {
        [, $conversation, $candidate] = $this->context();

        $this->actingAs($candidate)
            ->post(route('messages.store', $conversation), ['body' => str_repeat('a', 2001)])
            ->assertSessionHasErrors('body');

        $this->assertSame(0, ConversationMessage::query()->where('conversation_id', $conversation->id)->count());
    }

    public function test_send_blocked_on_terminal_applications(): void
    {
        foreach ([JobApplicationStatus::Hired, JobApplicationStatus::Rejected, JobApplicationStatus::Withdrawn] as $status) {
            [$application, $conversation, $candidate] = $this->context($status);

            Event::fake([MessageSent::class]);

            $this->actingAs($candidate)
                ->post(route('messages.store', $conversation), ['body' => 'too late'])
                ->assertSessionHasErrors('body');

            $this->assertSame(0, ConversationMessage::query()->where('conversation_id', $conversation->id)->count());
            Event::assertNotDispatched(MessageSent::class);
        }
    }

    // ------------------------------------------------------------ edit

    public function test_sender_can_edit_their_message(): void
    {
        [, $conversation, $candidate] = $this->context();

        $this->actingAs($candidate)->post(route('messages.store', $conversation), ['body' => 'origianl']);
        $message = ConversationMessage::query()->sole();

        $this->actingAs($candidate)
            ->put(route('messages.update', $message), ['body' => 'original'])
            ->assertRedirect();

        $message->refresh();
        $this->assertSame('original', $message->body);
        $this->assertNotNull($message->edited_at);
    }

    public function test_edit_validation(): void
    {
        [, $conversation, $candidate] = $this->context();

        $this->actingAs($candidate)->post(route('messages.store', $conversation), ['body' => 'hello']);
        $message = ConversationMessage::query()->sole();

        $this->actingAs($candidate)
            ->put(route('messages.update', $message), ['body' => ''])
            ->assertSessionHasErrors('body');

        $this->actingAs($candidate)
            ->put(route('messages.update', $message), ['body' => str_repeat('b', 2001)])
            ->assertSessionHasErrors('body');

        $this->assertSame('hello', $message->refresh()->body);
    }

    public function test_other_participant_cannot_edit_or_delete_a_message(): void
    {
        [, $conversation, $candidate, $employer] = $this->context();

        $this->actingAs($candidate)->post(route('messages.store', $conversation), ['body' => 'mine']);
        $message = ConversationMessage::query()->sole();

        $this->actingAs($employer)->put(route('messages.update', $message), ['body' => 'hijacked'])->assertForbidden();
        $this->actingAs($employer)->delete(route('messages.destroy', $message))->assertForbidden();

        $this->assertSame('mine', $message->refresh()->body);
        $this->assertNull($message->deleted_at);
    }

    public function test_revoked_member_cannot_edit_their_own_past_messages(): void
    {
        $application = JobApplication::factory()->create();
        $application->load(['user', 'jobPosting.company']);
        $company = $application->jobPosting->company;
        $recruiter = $this->memberOf($company, 'recruiter');

        $this->actingAs($recruiter)->post(route('messages.open', $application));
        $conversation = ApplicationConversation::query()->where('application_id', $application->id)->sole();

        $this->actingAs($recruiter)->post(route('messages.store', $conversation), ['body' => 'from before revocation']);
        $message = ConversationMessage::query()->sole();

        CompanyMember::query()
            ->where('company_id', $company->id)
            ->where('user_id', $recruiter->id)
            ->update(['status' => 'revoked', 'revoked_at' => now()]);

        $this->actingAs($recruiter)->put(route('messages.update', $message), ['body' => 'edited later'])->assertForbidden();
        $this->actingAs($recruiter)->delete(route('messages.destroy', $message))->assertForbidden();
    }

    public function test_edit_and_delete_blocked_on_terminal_applications(): void
    {
        foreach ([JobApplicationStatus::Hired, JobApplicationStatus::Rejected, JobApplicationStatus::Withdrawn] as $status) {
            [$application, $conversation, $candidate] = $this->context();

            $this->actingAs($candidate)->post(route('messages.store', $conversation), ['body' => 'before terminal']);
            $message = ConversationMessage::query()->where('conversation_id', $conversation->id)->sole();

            $application->update(['status' => $status->value]);

            $this->actingAs($candidate)->put(route('messages.update', $message), ['body' => 'nope'])
                ->assertSessionHasErrors('body');
            $this->actingAs($candidate)->delete(route('messages.destroy', $message))
                ->assertSessionHasErrors('body');
            $this->assertSame('before terminal', $message->refresh()->body);
            $this->assertNull($message->refresh()->deleted_at);
        }
    }

    public function test_editing_after_delete_is_rejected(): void
    {
        [, $conversation, $candidate] = $this->context();

        $this->actingAs($candidate)->post(route('messages.store', $conversation), ['body' => 'temporary']);
        $message = ConversationMessage::query()->sole();

        $this->actingAs($candidate)->delete(route('messages.destroy', $message))->assertRedirect();

        $this->actingAs($candidate)->put(route('messages.update', $message), ['body' => 'revived'])
            ->assertSessionHasErrors('body');
    }

    // ------------------------------------------------------------ delete

    public function test_delete_tombstones_message_and_destroys_body(): void
    {
        [, $conversation, $candidate] = $this->context();

        $this->actingAs($candidate)->post(route('messages.store', $conversation), ['body' => 'destroy me']);
        $message = ConversationMessage::query()->sole();

        $this->actingAs($candidate)->delete(route('messages.destroy', $message))->assertRedirect();

        $message->refresh();
        $this->assertNull($message->body);
        $this->assertNotNull($message->deleted_at);
    }

    public function test_thread_payload_renders_tombstone_with_null_body(): void
    {
        [, $conversation, $candidate, $employer] = $this->context();

        $this->actingAs($candidate)->post(route('messages.store', $conversation), ['body' => 'destroy me']);
        $message = ConversationMessage::query()->sole();
        $this->actingAs($candidate)->delete(route('messages.destroy', $message));

        $this->actingAs($employer)
            ->get(route('messages.show', $conversation))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('messages/show')
                ->where('messages.data.0.body', null)
                ->where('messages.data.0.deleted_at', fn ($value) => $value !== null));
    }

    // ------------------------------------------------------------ broadcast

    public function test_send_dispatches_message_sent_after_commit_with_safe_payload(): void
    {
        Event::fake([MessageSent::class]);

        [, $conversation, $candidate] = $this->context();

        $this->actingAs($candidate)->post(route('messages.store', $conversation), ['body' => 'broadcast me']);

        Event::assertDispatched(MessageSent::class, function (MessageSent $event) use ($candidate, $conversation): bool {
            $this->assertSame('private-conversation.'.$conversation->id, $event->broadcastOn()->name);
            $payload = $event->broadcastWith()['message'];

            return $payload['conversation_id'] === $conversation->id
                && $payload['sender_id'] === $candidate->id
                && $payload['sender_name'] === $candidate->name
                && $payload['body'] === 'broadcast me'
                && ! array_key_exists('application_id', $payload);
        });
    }

    public function test_edit_and_delete_dispatch_their_events(): void
    {
        Event::fake([MessageSent::class, MessageEdited::class, MessageDeleted::class]);

        [, $conversation, $candidate] = $this->context();

        $this->actingAs($candidate)->post(route('messages.store', $conversation), ['body' => 'first']);
        $message = ConversationMessage::query()->sole();

        $this->actingAs($candidate)->put(route('messages.update', $message), ['body' => 'edited']);
        Event::assertDispatched(MessageEdited::class, fn (MessageEdited $event): bool => $event->broadcastOn()->name === 'private-conversation.'.$conversation->id
            && $event->broadcastWith()['message']['body'] === 'edited');

        $this->actingAs($candidate)->delete(route('messages.destroy', $message));
        Event::assertDispatched(MessageDeleted::class, fn (MessageDeleted $event): bool => $event->broadcastOn()->name === 'private-conversation.'.$conversation->id
            && ! array_key_exists('body', $event->broadcastWith()['message']));
    }

    // ------------------------------------------------------------ notification

    public function test_candidate_message_notifies_owner_and_recruiters_but_not_reviewer(): void
    {
        Notification::fake();

        [$application, $conversation, $candidate] = $this->context();
        $company = $application->jobPosting->company;
        $owner = $company->owner;
        $recruiter = $this->memberOf($company, 'recruiter');
        $reviewer = $this->memberOf($company, 'reviewer');

        $this->actingAs($candidate)->post(route('messages.store', $conversation), ['body' => 'hello']);

        Notification::assertSentTo($owner, NewConversationMessageNotification::class);
        Notification::assertSentTo($recruiter, NewConversationMessageNotification::class);
        Notification::assertNotSentTo($reviewer, NewConversationMessageNotification::class);
    }

    public function test_employer_message_notifies_the_candidate_only(): void
    {
        Notification::fake();

        [$application, $conversation, $candidate, $employer] = $this->context();
        $recruiter = $this->memberOf($application->jobPosting->company, 'recruiter');

        $this->actingAs($employer)->post(route('messages.store', $conversation), ['body' => 'hi candidate']);

        Notification::assertSentTo($candidate, NewConversationMessageNotification::class);
        Notification::assertNotSentTo($recruiter, NewConversationMessageNotification::class);
        Notification::assertNotSentTo($employer, NewConversationMessageNotification::class);
    }

    public function test_notification_payload_contains_no_message_body(): void
    {
        Notification::fake();

        [$application, $conversation, $candidate] = $this->context();
        $owner = $application->jobPosting->company->owner;

        $this->actingAs($candidate)->post(route('messages.store', $conversation), ['body' => 'super secret body text']);

        Notification::assertSentTo(
            $owner,
            NewConversationMessageNotification::class,
            function (NewConversationMessageNotification $notification) use ($candidate, $conversation): bool {
                $data = $notification->toDatabase($candidate);

                return $data['event'] === 'message.received'
                    && ! str_contains(json_encode($data), 'super secret body text')
                    && $data['conversation_id'] === $conversation->id
                    && str_contains($data['message'], $candidate->name)
                    && isset($data['url'], $data['message']);
            }
        );
    }

    public function test_unread_message_notification_coalesces_to_one_per_conversation(): void
    {
        [$application, $conversation, $candidate, $employer] = $this->context();
        $owner = $application->jobPosting->company->owner;

        $this->actingAs($candidate)->post(route('messages.store', $conversation), ['body' => 'one']);
        $this->actingAs($candidate)->post(route('messages.store', $conversation), ['body' => 'two']);

        $unread = $owner->unreadNotifications()
            ->where('type', NewConversationMessageNotification::class)
            ->get()
            ->filter(fn ($notification): bool => (int) ($notification->data['conversation_id'] ?? 0) === $conversation->id);

        $this->assertCount(1, $unread);
    }

    public function test_edit_and_delete_create_no_notification(): void
    {
        Notification::fake();

        [, $conversation, $candidate] = $this->context();

        $this->actingAs($candidate)->post(route('messages.store', $conversation), ['body' => 'original']);
        $message = ConversationMessage::query()->sole();

        Notification::fake(); // reset

        $this->actingAs($candidate)->put(route('messages.update', $message), ['body' => 'edited']);
        $this->actingAs($candidate)->delete(route('messages.destroy', $message));

        Notification::assertNothingSent();
    }

    // ------------------------------------------------------------ rate limit

    public function test_send_is_rate_limited(): void
    {
        Event::fake([MessageSent::class]);
        Notification::fake();

        [, $conversation, $candidate] = $this->context();

        for ($i = 0; $i < 30; $i++) {
            $this->actingAs($candidate)->post(route('messages.store', $conversation), ['body' => "message {$i}"]);
        }

        $this->actingAs($candidate)
            ->post(route('messages.store', $conversation), ['body' => 'one too many'])
            ->assertStatus(429);
    }

    public function test_non_string_body_is_rejected(): void
    {
        [, $conversation, $candidate] = $this->context();

        $this->actingAs($candidate)
            ->post(route('messages.store', $conversation), ['body' => ['not', 'a', 'string']])
            ->assertSessionHasErrors('body');

        $this->assertSame(0, ConversationMessage::query()->where('conversation_id', $conversation->id)->count());
    }

    public function test_messages_read_returns_204_and_advances_cursor(): void
    {
        [$application, $conversation, $candidate, $employer] = $this->context();

        $this->actingAs($candidate)->post(route('messages.store', $conversation), ['body' => 'hello']);

        $this->actingAs($employer)
            ->post(route('messages.read', $conversation))
            ->assertNoContent();

        $lastId = ApplicationConversation::query()->find($conversation->id)->last_message_id;

        $this->assertDatabaseHas('conversation_participants', [
            'conversation_id' => $conversation->id,
            'user_id' => $employer->id,
            'last_read_message_id' => $lastId,
        ]);
    }

    public function test_inbox_preview_falls_back_when_latest_message_is_tombstoned(): void
    {
        [, $conversation, $candidate, $employer] = $this->context();

        $this->actingAs($candidate)->post(route('messages.store', $conversation), ['body' => 'keep me']);
        $this->actingAs($candidate)->post(route('messages.store', $conversation), ['body' => 'tombstone me']);

        $latest = ConversationMessage::query()->orderByDesc('id')->firstOrFail();
        $this->actingAs($candidate)->delete(route('messages.destroy', $latest));

        $this->actingAs($employer)
            ->get(route('messages.index'))
            ->assertInertia(fn (Assert $page) => $page->component('messages/index')
                ->where('conversations.data.0.last_message.body', 'keep me'));
    }

    public function test_thread_load_earlier_uses_keyset_cursor(): void
    {
        [, $conversation, $candidate] = $this->context();

        // Seed the thread directly: the store route is rate-limited to 30/min,
        // which is not what this test exercises.
        foreach (range(1, 35) as $index) {
            ConversationMessage::query()->create([
                'conversation_id' => $conversation->id,
                'sender_id' => $candidate->id,
                'body' => 'message '.$index,
            ]);
        }

        $firstPage = $this->actingAs($candidate)
            ->get(route('messages.show', $conversation))
            ->assertOk();

        $firstPage->assertInertia(fn (Assert $page) => $page->component('messages/show')
            ->where('messages.data.0.body', 'message 6')
            ->where('messages.data.29.body', 'message 35')
            ->has('messages.next_page_url'));

        $nextPageUrl = $firstPage->inertiaProps('messages.next_page_url');

        $this->actingAs($candidate)
            ->get($nextPageUrl)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('messages/show')
                ->has('messages.data', 5)
                ->where('messages.data.0.body', 'message 1')
                ->where('messages.data.4.body', 'message 5')
                ->where('messages.next_page_url', null));
    }

    // ------------------------------------------------------------ helpers

    /**
     * @return array{0: JobApplication, 1: ApplicationConversation, 2: User, 3: User}
     */
    private function context(JobApplicationStatus $status = JobApplicationStatus::Submitted): array
    {
        $candidate = User::factory()->jobSeeker()->create();
        $employer = User::factory()->approvedEmployer()->create();
        $company = Company::factory()->create(['owner_user_id' => $employer->id]);
        $job = Job::factory()->for($company)->create(['status' => 'published']);

        $application = JobApplication::factory()->create([
            'user_id' => $candidate->id,
            'job_posting_id' => $job->id,
            'status' => $status->value,
        ]);
        $application->load(['user', 'jobPosting.company.owner']);

        $conversation = ApplicationConversation::query()->create(['application_id' => $application->id]);

        return [$application, $conversation, $candidate, $employer];
    }

    private function memberOf(Company $company, string $role): User
    {
        $member = User::factory()->approvedEmployer()->create();

        CompanyMember::create([
            'company_id' => $company->id,
            'user_id' => $member->id,
            'company_role' => $role,
            'status' => 'active',
            'invited_at' => now(),
            'accepted_at' => now(),
        ]);

        return $member;
    }
}
