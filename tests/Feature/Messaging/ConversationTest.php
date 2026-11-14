<?php

namespace Tests\Feature\Messaging;

use App\Broadcasting\ConversationChannel;
use App\Enums\JobApplicationStatus;
use App\Models\ApplicationConversation;
use App\Models\Company;
use App\Models\CompanyMember;
use App\Models\Job;
use App\Models\JobApplication;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Authorization\Concerns\InteractsWithAuthorizationCatalogue;
use Tests\TestCase;

class ConversationTest extends TestCase
{
    use InteractsWithAuthorizationCatalogue, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAuthorizationCatalogue();
    }

    // ------------------------------------------------------------ open / create

    public function test_guests_are_redirected_to_login(): void
    {
        $application = $this->application();

        $this->get(route('messages.index'))->assertRedirect(route('login'));
        $this->get(route('messages.show', $this->conversationFor($application)))->assertRedirect(route('login'));
        $this->post(route('messages.store', $this->conversationFor($application)), ['body' => 'hi'])
            ->assertRedirect(route('login'));
        $this->post(route('messages.open', $application))->assertRedirect(route('login'));
    }

    public function test_candidate_can_open_application_conversation(): void
    {
        $application = $this->application();
        $candidate = $application->user;

        $this->actingAs($candidate)
            ->post(route('messages.open', $application))
            ->assertRedirect();

        $this->assertNotNull(ApplicationConversation::query()->where('application_id', $application->id)->first());
    }

    public function test_opening_twice_redirects_to_existing_conversation(): void
    {
        $application = $this->application();
        $candidate = $application->user;

        $this->actingAs($candidate)->post(route('messages.open', $application));

        $this->actingAs($candidate)
            ->post(route('messages.open', $application))
            ->assertRedirect();

        $this->assertSame(1, ApplicationConversation::query()->where('application_id', $application->id)->count());
    }

    public function test_employer_owner_can_open_application_conversation(): void
    {
        $application = $this->application();
        $employer = $application->jobPosting->company->owner;

        $this->actingAs($employer)
            ->post(route('messages.open', $application))
            ->assertRedirect();

        $this->assertSame(1, ApplicationConversation::query()->where('application_id', $application->id)->count());
    }

    public function test_guessed_application_id_returns_404(): void
    {
        $candidate = User::factory()->jobSeeker()->create();

        $this->actingAs($candidate)->post('/applications/999999/conversation')->assertNotFound();
    }

    public function test_stranger_candidate_cannot_open_or_view_conversation(): void
    {
        $application = $this->application();
        $stranger = User::factory()->jobSeeker()->create();

        $this->actingAs($stranger)->post(route('messages.open', $application))->assertForbidden();

        $conversation = $this->conversationFor($application);

        $this->actingAs($stranger)->get(route('messages.show', $conversation))->assertForbidden();
        $this->actingAs($stranger)->post(route('messages.store', $conversation), ['body' => 'hi'])->assertForbidden();
        $this->actingAs($stranger)->post(route('messages.read', $conversation))->assertForbidden();
    }

    public function test_unrelated_employer_cannot_access_conversation(): void
    {
        $application = $this->application();
        $outsider = User::factory()->approvedEmployer()->create();
        Company::factory()->create(['owner_user_id' => $outsider->id]);

        $this->actingAs($outsider)->post(route('messages.open', $application))->assertForbidden();

        $conversation = $this->conversationFor($application);

        $this->actingAs($outsider)->get(route('messages.show', $conversation))->assertForbidden();
        $this->actingAs($outsider)->post(route('messages.store', $conversation), ['body' => 'hi'])->assertForbidden();
    }

    public function test_reviewer_can_view_but_not_send(): void
    {
        $application = $this->application();
        $reviewer = $this->memberOf($application->jobPosting->company, 'reviewer');

        $this->actingAs($reviewer)->post(route('messages.open', $application))->assertRedirect();

        $conversation = $this->conversationFor($application);

        $this->actingAs($reviewer)->get(route('messages.show', $conversation))->assertOk();
        $this->actingAs($reviewer)->post(route('messages.store', $conversation), ['body' => 'hi'])->assertForbidden();
    }

    public function test_revoked_member_loses_access(): void
    {
        $application = $this->application();
        $company = $application->jobPosting->company;
        $memberUser = $this->memberOf($company, 'recruiter');

        $this->actingAs($memberUser)->post(route('messages.open', $application))->assertRedirect();

        CompanyMember::query()
            ->where('company_id', $company->id)
            ->where('user_id', $memberUser->id)
            ->update(['status' => 'revoked', 'revoked_at' => now()]);

        $conversation = $this->conversationFor($application);

        $this->actingAs($memberUser)->get(route('messages.show', $conversation))->assertForbidden();
        $this->actingAs($memberUser)->post(route('messages.store', $conversation), ['body' => 'hi'])->assertForbidden();
    }

    public function test_unapproved_employer_cannot_access_conversation(): void
    {
        $application = $this->application();

        $unapproved = User::factory()->employer()->create();

        $this->actingAs($unapproved)->post(route('messages.open', $application))->assertForbidden();
    }

    public function test_platform_admin_has_no_access_to_conversations(): void
    {
        $application = $this->application();
        $admin = User::factory()->platformAdmin()->create();

        $this->actingAs($admin)->post(route('messages.open', $application))->assertForbidden();

        $conversation = $this->conversationFor($application);

        $this->actingAs($admin)->get(route('messages.show', $conversation))->assertForbidden();
        $this->actingAs($admin)
            ->get(route('messages.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('messages/index')
                ->has('conversations.data', 0)
                ->where('unread_total', 0));
    }

    // ------------------------------------------------------------ rename/auth helpers

    public function test_candidate_application_page_exposes_can_message(): void
    {
        $application = $this->application();
        $candidate = $application->user;

        $this->actingAs($candidate)
            ->get(route('candidate.applications.show', $application))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('candidate/applications/show')
                ->where('can_message', true));

        $application->update(['status' => JobApplicationStatus::Hired->value]);

        $this->actingAs($candidate)
            ->get(route('candidate.applications.show', $application))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('candidate/applications/show')
                ->where('can_message', false));
    }

    public function test_employer_application_page_exposes_can_message(): void
    {
        $application = $this->application();
        $employer = $application->jobPosting->company->owner;

        $this->actingAs($employer)
            ->get(route('employer.applications.show', $application))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('employer/applications/show')
                ->where('can_message', true));

        $application->update(['status' => JobApplicationStatus::Rejected->value]);

        $this->actingAs($employer)
            ->get(route('employer.applications.show', $application))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('employer/applications/show')
                ->where('can_message', false));
    }

    // ------------------------------------------------------------ thread + read state

    public function test_thread_marks_read_and_clears_unread_flag(): void
    {
        $application = $this->application();
        $candidate = $application->user;
        $employer = $application->jobPosting->company->owner;
        $conversation = $this->conversationFor($application);

        $this->actingAs($candidate)
            ->post(route('messages.store', $conversation), ['body' => 'Hello employer']);

        $this->actingAs($employer)
            ->get(route('messages.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('conversations.data.0.unread', true)
                ->where('unread_total', 1));

        $this->actingAs($employer)->get(route('messages.show', $conversation))->assertOk();

        $this->assertDatabaseHas('conversation_participants', [
            'conversation_id' => $conversation->id,
            'user_id' => $employer->id,
            'last_read_message_id' => ApplicationConversation::query()->find($conversation->id)->last_message_id,
        ]);

        $this->actingAs($employer)
            ->get(route('messages.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('conversations.data.0.unread', false)
                ->where('unread_total', 0));
    }

    public function test_sender_unread_stays_zero_after_send(): void
    {
        $application = $this->application();
        $candidate = $application->user;
        $conversation = $this->conversationFor($application);

        $this->actingAs($candidate)
            ->post(route('messages.store', $conversation), ['body' => 'Hello employer']);

        $this->actingAs($candidate)
            ->get(route('messages.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('conversations.data.0.unread', false)
                ->where('unread_total', 0));
    }

    public function test_inbox_scopes_employer_to_own_companies_only(): void
    {
        $applicationA = $this->application();
        $applicationB = $this->application();
        $this->conversationFor($applicationA);
        $this->conversationFor($applicationB);
        $employerA = $applicationA->jobPosting->company->owner;

        $this->actingAs($employerA)
            ->get(route('messages.index'))
            ->assertInertia(fn (Assert $page) => $page->component('messages/index')
                ->has('conversations.data', 1)
                ->where('conversations.data.0.job.title', $applicationA->jobPosting->title));

        $this->assertNotSame(
            $applicationA->jobPosting->company->id,
            $applicationB->jobPosting->company->id,
        );
    }

    public function test_inbox_orders_by_most_recent_activity(): void
    {
        $candidate = User::factory()->jobSeeker()->create();
        $employer = User::factory()->approvedEmployer()->create();
        $company = Company::factory()->create(['owner_user_id' => $employer->id]);
        $jobOld = Job::factory()->for($company)->create(['status' => 'published']);
        $jobNew = Job::factory()->for($company)->create(['status' => 'published']);
        $applicationOld = JobApplication::factory()->create(['user_id' => $candidate->id, 'job_posting_id' => $jobOld->id]);
        $applicationNew = JobApplication::factory()->create(['user_id' => $candidate->id, 'job_posting_id' => $jobNew->id]);

        $conversationOld = $this->conversationFor($applicationOld);
        $conversationNew = $this->conversationFor($applicationNew);

        // Force deterministic bucket order regardless of clock resolution.
        ApplicationConversation::query()->whereKey($conversationOld->id)->update([
            'created_at' => now()->subMinute(),
            'updated_at' => now()->subMinute(),
            'sort_at' => now()->subMinute(),
        ]);

        // Touch the newer conversation last so it sorts first.
        $this->actingAs($candidate)->post(route('messages.store', $conversationNew), ['body' => 'newest first?']);

        $this->actingAs($candidate)
            ->get(route('messages.index'))
            ->assertInertia(fn (Assert $page) => $page->component('messages/index')
                ->where('conversations.data.0.id', $conversationNew->id)
                ->where('conversations.data.1.id', $conversationOld->id));
    }

    public function test_show_renders_messages_chronologically(): void
    {
        $application = $this->application();
        $candidate = $application->user;
        $conversation = $this->conversationFor($application);

        $this->actingAs($candidate)->post(route('messages.store', $conversation), ['body' => 'first']);
        $this->actingAs($candidate)->post(route('messages.store', $conversation), ['body' => 'second']);

        $this->actingAs($candidate)
            ->get(route('messages.show', $conversation))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('messages/show')
                ->where('messages.data.0.body', 'first')
                ->where('messages.data.1.body', 'second')
                ->where('conversation.can_send', true));
    }

    // ------------------------------------------------------------ broadcast channel auth

    public function test_private_channel_join_authorizes_participants_only(): void
    {
        $application = $this->application();
        $candidate = $application->user;
        $employer = $application->jobPosting->company->owner;
        $reviewer = $this->memberOf($application->jobPosting->company, 'reviewer');
        $stranger = User::factory()->jobSeeker()->create();
        $outsider = User::factory()->approvedEmployer()->create();
        Company::factory()->create(['owner_user_id' => $outsider->id]);

        $conversation = $this->conversationFor($application);
        $channel = new ConversationChannel;

        // Reviewer (view-only) is allowed; unrelated parties are not.
        $this->assertTrue($channel->join($candidate, (string) $conversation->id));
        $this->assertTrue($channel->join($employer, (string) $conversation->id));
        $this->assertTrue($channel->join($reviewer, (string) $conversation->id));

        $this->assertFalse($channel->join($stranger, (string) $conversation->id));
        $this->assertFalse($channel->join($outsider, (string) $conversation->id));
        $this->assertFalse($channel->join($candidate, '999999'));
    }

    public function test_broadcasting_auth_endpoint_enforces_the_channel_callback(): void
    {
        // phpunit's default broadcaster is "null", whose auth() no-ops. Switching to
        // the reverb driver (the production path) re-evaluates the private-channel
        // callback; the HMAC signature is computed locally, no server is contacted.
        config(['broadcasting.default' => 'reverb']);
        require base_path('routes/channels.php');

        $application = $this->application();
        $candidate = $application->user;
        $employer = $application->jobPosting->company->owner;
        $stranger = User::factory()->jobSeeker()->create();

        $conversation = $this->conversationFor($application);
        $channel = 'private-conversation.'.$conversation->id;

        $this->actingAs($candidate)
            ->post('/broadcasting/auth', ['channel_name' => $channel, 'socket_id' => '1.1'])
            ->assertOk();

        $this->actingAs($employer)
            ->post('/broadcasting/auth', ['channel_name' => $channel, 'socket_id' => '1.1'])
            ->assertOk();

        $this->actingAs($stranger)
            ->post('/broadcasting/auth', ['channel_name' => $channel, 'socket_id' => '1.1'])
            ->assertForbidden();

        $this->get('/broadcasting/auth', ['channel_name' => $channel])
            ->assertForbidden();
    }

    public function test_inbox_paginates_at_fifteen_conversations_per_page(): void
    {
        $candidate = User::factory()->jobSeeker()->create();
        $employer = User::factory()->approvedEmployer()->create();
        $company = Company::factory()->create(['owner_user_id' => $employer->id]);

        for ($i = 0; $i < 16; $i++) {
            $job = Job::factory()->for($company)->create(['status' => 'published']);
            $application = JobApplication::factory()->create([
                'user_id' => $candidate->id,
                'job_posting_id' => $job->id,
            ]);

            $this->conversationFor($application);
        }

        $this->actingAs($candidate)
            ->get(route('messages.index'))
            ->assertInertia(fn (Assert $page) => $page->component('messages/index')
                ->has('conversations.data', 15)
                ->where('conversations.total', 16)
                ->where('conversations.last_page', 2)
                ->where('conversations.current_page', 1));

        $this->actingAs($candidate)
            ->get(route('messages.index', ['page' => 2]))
            ->assertInertia(fn (Assert $page) => $page->component('messages/index')
                ->has('conversations.data', 1)
                ->where('conversations.current_page', 2));
    }

    public function test_conversation_application_id_is_unique_at_the_database_level(): void
    {
        $application = $this->application();

        ApplicationConversation::query()->create(['application_id' => $application->id]);

        $this->expectException(QueryException::class);

        ApplicationConversation::query()->create(['application_id' => $application->id]);
    }

    public function test_shared_props_do_not_include_messages_unread_count(): void
    {
        $candidate = User::factory()->jobSeeker()->create();

        $this->actingAs($candidate)
            ->get(route('candidate.dashboard'))
            ->assertInertia(fn (Assert $page) => $page->missing('messages_unread_count'));
    }

    // ------------------------------------------------------------ helpers

    private function application(JobApplicationStatus $status = JobApplicationStatus::Submitted): JobApplication
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

        return $application;
    }

    private function conversationFor(JobApplication $application): ApplicationConversation
    {
        return ApplicationConversation::query()->firstOrCreate(
            ['application_id' => $application->id],
        );
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
