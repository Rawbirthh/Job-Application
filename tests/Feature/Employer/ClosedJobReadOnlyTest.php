<?php

namespace Tests\Feature\Employer;

use App\Enums\JobApplicationStatus;
use App\Models\ApplicationConversation;
use App\Models\ApplicationNote;
use App\Models\CandidateProfile;
use App\Models\Company;
use App\Models\ConversationMessage;
use App\Models\Job;
use App\Models\JobApplication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Authorization\Concerns\InteractsWithAuthorizationCatalogue;
use Tests\TestCase;

/**
 * Closed-job read-only regression suite.
 *
 * A closed job locks the whole application record for employer write actions,
 * mirroring the read-only rule for terminal applications: status transitions,
 * interview management, recruiter notes/evaluations and messaging are all
 * rejected — even though the recording itself remains readable. Candidate
 * withdrawal stays available.
 */
class ClosedJobReadOnlyTest extends TestCase
{
    use InteractsWithAuthorizationCatalogue, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAuthorizationCatalogue();
    }

    /**
     * @return array{employer: User, company: Company, job: Job, candidate: User, application: JobApplication}
     */
    private function context(string $jobStatus = 'closed', string $applicationStatus = 'submitted'): array
    {
        $employer = User::factory()->approvedEmployer()->create();
        $company = Company::factory()->for($employer, 'owner')->create();
        $job = Job::factory()->for($company)->create([
            'title' => 'Laptop Technician',
            'status' => $jobStatus,
        ]);
        $candidate = User::factory()->jobSeeker()->create();
        CandidateProfile::factory()->for($candidate, 'user')->create();

        $application = JobApplication::factory()
            ->for($job, 'jobPosting')
            ->for($candidate, 'user')
            ->create(['status' => $applicationStatus]);

        return compact('employer', 'company', 'job', 'candidate', 'application');
    }

    /**
     * @return array{employer: User, company: Company, job: Job, candidate: User, application: JobApplication}
     */
    private function openJobContext(): array
    {
        return $this->context('published', 'shortlisted');
    }

    // --------------------------------------------------------- status moves

    public function test_closed_job_rejects_status_transitions(): void
    {
        ['employer' => $employer, 'application' => $application] = $this->context();

        $this->actingAs($employer)
            ->postJson(route('employer.applications.update-status', $application), [
                'current_status' => 'submitted',
                'new_status' => 'reviewing',
            ])
            ->assertForbidden();

        $this->assertEquals(JobApplicationStatus::Submitted, $application->fresh()->status);
        $this->assertDatabaseCount('job_application_status_histories', 0);
    }

    public function test_open_job_still_allows_status_transitions(): void
    {
        ['employer' => $employer, 'application' => $application] = $this->context('published', 'submitted');

        $this->actingAs($employer)
            ->postJson(route('employer.applications.update-status', $application), [
                'current_status' => 'submitted',
                'new_status' => 'reviewing',
            ])
            ->assertOk()
            ->assertJson(['status' => 'reviewing']);

        $this->assertEquals(JobApplicationStatus::Reviewing, $application->fresh()->status);
    }

    // ----------------------------------------------------------- interviews

    public function test_closed_job_rejects_scheduling_interviews(): void
    {
        ['employer' => $employer, 'application' => $application] = $this->context();

        $this->actingAs($employer)
            ->post(route('employer.applications.interviews.store', $application), $this->validInterviewPayload())
            ->assertForbidden();

        $this->assertDatabaseCount('interviews', 0);
    }

    public function test_open_job_allows_scheduling_interviews(): void
    {
        ['employer' => $employer, 'application' => $application] = $this->openJobContext();

        $this->actingAs($employer)
            ->post(route('employer.applications.interviews.store', $application), $this->validInterviewPayload())
            ->assertRedirect();

        $this->assertDatabaseCount('interviews', 1);
    }

    // ---------------------------------------------------------------- notes

    public function test_closed_job_rejects_creating_notes(): void
    {
        ['employer' => $employer, 'application' => $application] = $this->context();

        $this->actingAs($employer)
            ->post(route('employer.applications.notes.store', $application), ['body' => 'Hidden note'])
            ->assertForbidden();

        $this->assertDatabaseCount('application_notes', 0);
    }

    public function test_closed_job_rejects_editing_and_deleting_notes_created_before_closure(): void
    {
        ['employer' => $employer, 'job' => $job, 'application' => $application] = $this->openJobContext();

        $this->actingAs($employer)
            ->post(route('employer.applications.notes.store', $application), ['body' => 'Note authored before closure']);

        $job->status = 'closed';
        $job->save();

        $note = ApplicationNote::query()->firstOrFail();

        $this->actingAs($employer)
            ->put(route('employer.applications.notes.update', [$application, $note]), ['body' => 'Edited after closure'])
            ->assertForbidden();

        $this->actingAs($employer)
            ->delete(route('employer.applications.notes.destroy', [$application, $note]))
            ->assertForbidden();

        $this->assertDatabaseHas('application_notes', [
            'id' => $note->id,
            'body' => 'Note authored before closure',
        ]);
    }

    // ---------------------------------------------------------- evaluations

    public function test_closed_job_rejects_evaluations(): void
    {
        ['employer' => $employer, 'application' => $application] = $this->context();

        $this->actingAs($employer)
            ->post(route('employer.applications.evaluations.store', $application), [
                'relevant_experience' => 5,
                'technical_skills' => 5,
                'communication' => 5,
                'interview_performance' => 5,
                'comment' => 'Should not be stored',
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('application_evaluations', 0);
    }

    // -------------------------------------------------------------- messages

    public function test_closed_job_rejects_messages_from_both_participants(): void
    {
        ['employer' => $employer, 'candidate' => $candidate, 'application' => $application] = $this->openJobContext();

        $conversation = ApplicationConversation::create([
            'application_id' => $application->id,
            'sort_at' => now(),
        ]);

        ConversationMessage::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $employer->id,
            'body' => 'Hello before closure',
        ]);

        $application->jobPosting->status = 'closed';
        $application->jobPosting->save();

        $this->actingAs($employer)
            ->postJson(route('messages.store', $conversation), ['body' => 'Employer after closure'])
            ->assertForbidden();

        $this->actingAs($candidate)
            ->postJson(route('messages.store', $conversation), ['body' => 'Candidate after closure'])
            ->assertForbidden();

        $this->assertDatabaseMissing('conversation_messages', ['body' => 'Employer after closure']);
        $this->assertDatabaseMissing('conversation_messages', ['body' => 'Candidate after closure']);

        // The message written before closure is still editable only while the job
        // was open — both sender edit and delete are now rejected.
        $message = ConversationMessage::query()->firstOrFail();

        $this->actingAs($employer)
            ->putJson(route('messages.update', $message), ['body' => 'Edited after closure'])
            ->assertForbidden();

        $this->actingAs($employer)
            ->deleteJson(route('messages.destroy', $message))
            ->assertForbidden();

        $this->assertDatabaseHas('conversation_messages', [
            'id' => $message->id,
        ]);
    }

    // --------------------------------------------------------- read-only UI

    public function test_detail_page_props_are_read_only_when_job_is_closed(): void
    {
        ['employer' => $employer, 'application' => $application] = $this->context();

        $this->actingAs($employer)
            ->get(route('employer.applications.show', $application))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('job_closed', true)
                ->where('can_update_status', false)
                ->where('can_schedule_interview', false)
                ->where('can_manage_notes', false)
                ->where('can_message', false)
                ->where('interview_eligible', false)
                ->where('allowed_transitions', []));
    }

    public function test_detail_page_props_are_writable_when_job_is_open(): void
    {
        ['employer' => $employer, 'application' => $application] = $this->openJobContext();

        $this->actingAs($employer)
            ->get(route('employer.applications.show', $application))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('job_closed', false)
                ->where('can_update_status', true)
                ->where('can_schedule_interview', true)
                ->where('can_manage_notes', true)
                ->where('can_message', true));
    }

    public function test_pipeline_card_reports_closed_job_status(): void
    {
        ['employer' => $employer, 'application' => $application] = $this->context();

        $this->actingAs($employer)
            ->get(route('employer.pipeline.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('board.columns.0.items.0.job.status', 'closed')
                ->where('board.columns.0.items.0.job.title', 'Laptop Technician'));
    }

    public function test_candidate_cannot_be_blocked_from_withdrawing_on_a_closed_job(): void
    {
        ['candidate' => $candidate, 'application' => $application] = $this->context('closed', 'reviewing');

        $this->actingAs($candidate)
            ->get(route('candidate.applications.show', $application))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('can_withdraw', true)
                ->where('can_message', false));
    }

    // -------------------------------------------------------------- helpers

    /**
     * @return array{0: string, scheduled_at_local: string, timezone: string, scheduled_at_utc: string, duration_minutes: int, mode: string, location_details: string}
     */
    private function validInterviewPayload(): array
    {
        $start = now()->addDays(3)->format('Y-m-d\TH:i');

        return [
            'scheduled_at_local' => $start,
            'timezone' => 'Asia/Singapore',
            'scheduled_at_utc' => now()->addDays(3)->toIso8601String(),
            'duration_minutes' => 45,
            'mode' => 'online',
            'location_details' => 'https://meet.example.com/laptop-tech',
        ];
    }
}
