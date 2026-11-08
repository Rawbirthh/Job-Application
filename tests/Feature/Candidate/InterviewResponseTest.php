<?php

namespace Tests\Feature\Candidate;

use App\Enums\InterviewStatus;
use App\Enums\JobApplicationStatus;
use App\Models\Company;
use App\Models\Interview;
use App\Models\Job;
use App\Models\JobApplication;
use App\Models\User;
use App\Notifications\InterviewRescheduleRequestedNotification;
use App\Notifications\InterviewResponseNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Authorization\Concerns\InteractsWithAuthorizationCatalogue;
use Tests\TestCase;

class InterviewResponseTest extends TestCase
{
    use InteractsWithAuthorizationCatalogue, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAuthorizationCatalogue();
        Carbon::setTestNow(Carbon::parse('2026-11-01 09:00:00', 'UTC'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_candidate_accepts_their_scheduled_round(): void
    {
        Notification::fake();

        [$employer, $application, $candidate] = $this->context();
        $interview = $this->makeInterview($application);

        $this->actingAs($candidate)
            ->post(route('candidate.interviews.respond', $interview), $this->respondPayload($interview, [
                'response' => InterviewStatus::Accepted->value,
                'response_note' => 'Looking forward to it.',
            ]))
            ->assertRedirect();

        $interview->refresh();

        $this->assertSame(InterviewStatus::Accepted, $interview->status);
        $this->assertNotNull($interview->responded_at);
        $this->assertSame('Looking forward to it.', $interview->response_note);
        $this->assertNull($interview->requested_scheduled_at);
        $this->assertNull($interview->requested_timezone);

        $this->assertSame(
            JobApplicationStatus::InterviewScheduled,
            $application->fresh()->status
        );

        Notification::assertSentTo($employer, InterviewResponseNotification::class);
        Notification::assertNotSentTo($candidate, InterviewResponseNotification::class);
    }

    public function test_declining_the_last_active_round_does_not_revert_the_application(): void
    {
        Notification::fake();

        [$employer, $application, $candidate] = $this->context();
        $interview = $this->makeInterview($application);

        $this->actingAs($candidate)
            ->post(route('candidate.interviews.respond', $interview), $this->respondPayload($interview, [
                'response' => InterviewStatus::Declined->value,
                'response_note' => 'I have accepted another offer.',
            ]))
            ->assertRedirect();

        $this->assertSame(InterviewStatus::Declined, $interview->fresh()->status);
        $this->assertSame(
            JobApplicationStatus::InterviewScheduled,
            $application->fresh()->status
        );
        $this->assertDatabaseMissing('job_application_status_histories', [
            'job_application_id' => $application->id,
            'to_status' => JobApplicationStatus::Shortlisted->value,
        ]);

        Notification::assertSentTo(
            $employer,
            InterviewResponseNotification::class,
            function (InterviewResponseNotification $notification) use ($employer): bool {
                $data = $notification->toDatabase($employer);

                return $data['event'] === 'interview.response'
                    && str_contains($data['message'], 'Declined');
            }
        );
    }

    public function test_candidate_requests_a_new_time_and_it_is_stored_in_utc(): void
    {
        Notification::fake();

        [$employer, $application, $candidate] = $this->context();
        $interview = $this->makeInterview($application);

        $this->actingAs($candidate)
            ->post(route('candidate.interviews.respond', $interview), $this->respondPayload($interview, [
                'response' => InterviewStatus::RescheduleRequested->value,
                'requested_at_local' => '2026-11-20T09:00',
                'requested_timezone' => 'America/New_York',
                'response_note' => 'Friday morning works better for me.',
            ]))
            ->assertRedirect();

        $interview->refresh();

        $this->assertSame(InterviewStatus::RescheduleRequested, $interview->status);
        $this->assertSame('2026-11-20 14:00:00', $interview->requested_scheduled_at->utc()->format('Y-m-d H:i:s'));
        $this->assertSame('America/New_York', $interview->requested_timezone);
        $this->assertSame('Friday morning works better for me.', $interview->response_note);
        $this->assertNotNull($interview->responded_at);

        $this->assertSame(
            JobApplicationStatus::InterviewScheduled,
            $application->fresh()->status
        );

        Notification::assertSentTo(
            $employer,
            InterviewRescheduleRequestedNotification::class,
            function (InterviewRescheduleRequestedNotification $notification) use ($employer): bool {
                $data = $notification->toDatabase($employer);

                return $data['event'] === 'interview.reschedule_requested'
                    && str_contains($data['message'], 'Nov 20, 2026 9:00 AM (America/New_York)')
                    && str_contains($data['message'], 'Friday morning works better for me.');
            }
        );
        Notification::assertNotSentTo($candidate, InterviewRescheduleRequestedNotification::class);
    }

    public function test_candidate_cannot_respond_to_another_candidates_interview(): void
    {
        [$employer, $application] = $this->context();
        $interview = $this->makeInterview($application);
        $intruder = User::factory()->jobSeeker()->create();

        $this->actingAs($intruder)
            ->post(route('candidate.interviews.respond', $interview), $this->respondPayload($interview, [
                'response' => InterviewStatus::Accepted->value,
            ]))
            ->assertForbidden();

        $this->assertSame(InterviewStatus::Scheduled, $interview->fresh()->status);
    }

    public function test_candidate_cannot_view_another_candidates_application_or_interview_data(): void
    {
        [, $application] = $this->context();
        $this->makeInterview($application, ['internal_notes' => 'Should never leak.']);
        $intruder = User::factory()->jobSeeker()->create();

        $this->actingAs($intruder)
            ->get(route('candidate.applications.show', $application))
            ->assertForbidden();
    }

    public function test_guest_is_redirected_from_the_candidate_response_route(): void
    {
        [, $application] = $this->context();
        $interview = $this->makeInterview($application);

        $this->post(route('candidate.interviews.respond', $interview), $this->respondPayload($interview, [
            'response' => InterviewStatus::Accepted->value,
        ]))->assertRedirect(route('login'));
    }

    public function test_employers_cannot_use_the_candidate_response_route(): void
    {
        [$owner, $application] = $this->context();
        $interview = $this->makeInterview($application);

        $otherEmployer = User::factory()->approvedEmployer()->create();
        Company::factory()->for($otherEmployer, 'owner')->create();

        $this->actingAs($otherEmployer)
            ->post(route('candidate.interviews.respond', $interview), $this->respondPayload($interview, [
                'response' => InterviewStatus::Accepted->value,
            ]))
            ->assertForbidden();

        $this->assertSame(InterviewStatus::Scheduled, $interview->fresh()->status);
    }

    public function test_candidate_cannot_respond_twice(): void
    {
        [, $application, $candidate] = $this->context();
        $interview = $this->makeInterview($application);

        $this->actingAs($candidate)
            ->post(route('candidate.interviews.respond', $interview), $this->respondPayload($interview, [
                'response' => InterviewStatus::Accepted->value,
            ]))
            ->assertRedirect();

        $this->actingAs($candidate)
            ->post(route('candidate.interviews.respond', $interview), $this->respondPayload($interview, [
                'response' => InterviewStatus::Declined->value,
            ]))
            ->assertStatus(422);

        $this->assertSame(InterviewStatus::Accepted, $interview->fresh()->status);
    }

    public function test_candidate_cannot_respond_after_the_round_was_cancelled(): void
    {
        [, $application, $candidate] = $this->context();
        $interview = $this->makeInterview($application, ['status' => InterviewStatus::Cancelled]);

        $this->actingAs($candidate)
            ->post(route('candidate.interviews.respond', $interview), $this->respondPayload($interview, [
                'response' => InterviewStatus::Accepted->value,
            ]))
            ->assertStatus(422);

        $this->assertSame(InterviewStatus::Cancelled, $interview->fresh()->status);
    }

    public function test_candidate_cannot_respond_after_the_application_left_the_interview_stage(): void
    {
        [, $application, $candidate] = $this->context(JobApplicationStatus::OfferSent);
        $interview = $this->makeInterview($application);

        $this->actingAs($candidate)
            ->post(route('candidate.interviews.respond', $interview), $this->respondPayload($interview, [
                'response' => InterviewStatus::Accepted->value,
            ]))
            ->assertStatus(422);

        $this->assertSame(InterviewStatus::Scheduled, $interview->fresh()->status);
    }

    public function test_responding_with_stale_state_returns_conflict_and_changes_nothing(): void
    {
        [, $application, $candidate] = $this->context();
        $interview = $this->makeInterview($application);

        $this->actingAs($candidate)
            ->post(route('candidate.interviews.respond', $interview), [
                'current_status' => InterviewStatus::Accepted->value,
                'current_scheduled_at' => $interview->scheduled_at->toIso8601String(),
                'response' => InterviewStatus::Accepted->value,
            ])
            ->assertStatus(409);

        $this->actingAs($candidate)
            ->post(route('candidate.interviews.respond', $interview), [
                'current_status' => $interview->status->value,
                'current_scheduled_at' => Carbon::parse('2026-11-28 10:00:00', 'UTC')->toIso8601String(),
                'response' => InterviewStatus::Accepted->value,
            ])
            ->assertStatus(409);

        $this->assertSame(InterviewStatus::Scheduled, $interview->fresh()->status);
        $this->assertNull($interview->fresh()->responded_at);
    }

    public function test_invalid_response_value_is_rejected(): void
    {
        [, $application, $candidate] = $this->context();
        $interview = $this->makeInterview($application);

        $this->actingAs($candidate)
            ->post(route('candidate.interviews.respond', $interview), $this->respondPayload($interview, [
                'response' => 'maybe',
            ]))
            ->assertSessionHasErrors('response');

        $this->assertSame(InterviewStatus::Scheduled, $interview->fresh()->status);
    }

    public function test_requesting_a_new_time_requires_a_proposed_time_and_zone(): void
    {
        [, $application, $candidate] = $this->context();
        $interview = $this->makeInterview($application);

        $this->actingAs($candidate)
            ->post(route('candidate.interviews.respond', $interview), $this->respondPayload($interview, [
                'response' => InterviewStatus::RescheduleRequested->value,
            ]))
            ->assertSessionHasErrors(['requested_at_local', 'requested_timezone']);

        $this->assertSame(InterviewStatus::Scheduled, $interview->fresh()->status);
    }

    public function test_requesting_a_new_time_rejects_a_past_proposed_time(): void
    {
        [, $application, $candidate] = $this->context();
        $interview = $this->makeInterview($application);

        $this->actingAs($candidate)
            ->post(route('candidate.interviews.respond', $interview), $this->respondPayload($interview, [
                'response' => InterviewStatus::RescheduleRequested->value,
                'requested_at_local' => '2026-10-31T10:00',
                'requested_timezone' => 'UTC',
            ]))
            ->assertSessionHasErrors('requested_at_local');

        $this->assertSame(InterviewStatus::Scheduled, $interview->fresh()->status);
        $this->assertNull($interview->fresh()->requested_scheduled_at);
    }

    public function test_proposed_time_overlapping_another_active_round_is_rejected(): void
    {
        [, $application, $candidate] = $this->context();

        $this->makeInterview($application, [
            'scheduled_at' => Carbon::parse('2026-11-20 10:00:00', 'UTC'),
            'duration_minutes' => 60,
        ]);
        $interview = $this->makeInterview($application, [
            'scheduled_at' => Carbon::parse('2026-11-25 10:00:00', 'UTC'),
            'duration_minutes' => 60,
        ]);

        $this->actingAs($candidate)
            ->post(route('candidate.interviews.respond', $interview), $this->respondPayload($interview, [
                'response' => InterviewStatus::RescheduleRequested->value,
                'requested_at_local' => '2026-11-20T10:30',
                'requested_timezone' => 'UTC',
            ]))
            ->assertSessionHasErrors('requested_at_local');

        $this->assertSame(InterviewStatus::Scheduled, $interview->fresh()->status);
        $this->assertNull($interview->fresh()->requested_scheduled_at);
    }

    public function test_candidate_withdrawal_cancels_all_active_interviews(): void
    {
        [, $application, $candidate] = $this->context();

        $scheduled = $this->makeInterview($application);
        $accepted = $this->makeInterview($application, [
            'scheduled_at' => Carbon::parse('2026-11-22 10:00:00', 'UTC'),
            'status' => InterviewStatus::Accepted,
        ]);
        $declined = $this->makeInterview($application, [
            'scheduled_at' => Carbon::parse('2026-11-24 10:00:00', 'UTC'),
            'status' => InterviewStatus::Declined,
        ]);

        $this->actingAs($candidate)
            ->post(route('candidate.applications.withdraw', $application), [
                'reason' => 'Accepted another offer.',
            ])
            ->assertRedirect();

        $this->assertSame(JobApplicationStatus::Withdrawn, $application->fresh()->status);
        $this->assertSame(InterviewStatus::Cancelled, $scheduled->fresh()->status);
        $this->assertSame(InterviewStatus::Cancelled, $accepted->fresh()->status);
        $this->assertSame(InterviewStatus::Declined, $declined->fresh()->status);
        $this->assertSame('Application withdrawn.', $scheduled->fresh()->cancellation_reason);
        $this->assertNotNull($scheduled->fresh()->cancelled_at);
    }

    public function test_candidate_application_page_excludes_private_employer_fields(): void
    {
        [, $application, $candidate] = $this->context();

        $interview = $this->makeInterview($application, [
            'scheduled_at' => Carbon::parse('2026-11-20 10:00:00', 'UTC'),
            'location_details' => 'Meet at the lobby.',
            'candidate_instructions' => 'Bring a portfolio.',
            'internal_notes' => 'Do not share the salary band.',
        ]);

        $this->actingAs($candidate)
            ->get(route('candidate.applications.show', $application))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('candidate/applications/show')
                ->has('interviews', 1)
                ->where('interviews.0.id', $interview->id)
                ->where('interviews.0.status', InterviewStatus::Scheduled->value)
                ->where('interviews.0.location_details', 'Meet at the lobby.')
                ->where('interviews.0.candidate_instructions', 'Bring a portfolio.')
                ->where('interviews.0.scheduled_at', $interview->scheduled_at->toIso8601String())
                ->where('interviews.0.can_respond', true)
                ->missing('interviews.0.internal_notes')
                ->missing('interviews.0.scheduled_by_id')
                ->has('interview_pagination')
                ->has('timezones')
            );
    }

    public function test_candidate_rounds_are_paginated_newest_first(): void
    {
        [, $application, $candidate] = $this->context();

        $interviews = [];

        for ($i = 0; $i < 11; $i++) {
            $interview = $this->makeInterview($application, [
                'scheduled_at' => Carbon::now('UTC')->addDays(3)->addMinutes($i * 60),
            ]);
            $interview->forceFill(['created_at' => Carbon::now('UTC')->subMinutes($i)])->save();
            $interviews[$i] = $interview->fresh();
        }

        $this->actingAs($candidate)
            ->get(route('candidate.applications.show', [
                'application' => $application,
                'interview_page' => 2,
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('candidate/applications/show')
                ->has('interviews', 1)
                ->where('interviews.0.id', $interviews[10]->id)
                ->where('interview_pagination.current_page', 2)
                ->where('interview_pagination.per_page', 10)
                ->where('interview_pagination.total', 11)
                ->where('interview_pagination.prev_page_url', fn (?string $url): bool => is_string($url)
                    && str_contains($url, 'interview_page=1'))
            );
    }

    /**
     * @return array{0: User, 1: JobApplication, 2: User}
     */
    private function context(JobApplicationStatus $status = JobApplicationStatus::InterviewScheduled): array
    {
        $employer = User::factory()->approvedEmployer()->create();
        $company = Company::factory()->for($employer, 'owner')->create();
        $job = Job::factory()->for($company)->create();
        $candidate = User::factory()->jobSeeker()->create();

        $application = JobApplication::factory()
            ->for($job, 'jobPosting')
            ->for($candidate, 'user')
            ->create(['status' => $status]);

        return [$employer, $application, $candidate];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function makeInterview(JobApplication $application, array $attributes = []): Interview
    {
        return Interview::factory()
            ->for($application, 'jobApplication')
            ->create(array_merge([
                'scheduled_at' => Carbon::parse('2026-11-20 10:00:00', 'UTC'),
                'duration_minutes' => 60,
                'timezone' => 'UTC',
                'status' => InterviewStatus::Scheduled,
            ], $attributes));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function respondPayload(Interview $interview, array $overrides = []): array
    {
        return array_merge([
            'current_status' => $interview->status->value,
            'current_scheduled_at' => $interview->scheduled_at->toIso8601String(),
            'response' => InterviewStatus::Accepted->value,
        ], $overrides);
    }
}
