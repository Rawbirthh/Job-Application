<?php

namespace Tests\Feature\Employer;

use App\Enums\InterviewStatus;
use App\Enums\JobApplicationStatus;
use App\Models\Company;
use App\Models\Interview;
use App\Models\Job;
use App\Models\JobApplication;
use App\Models\User;
use App\Notifications\InterviewCancelledNotification;
use App\Notifications\InterviewRescheduledNotification;
use App\Notifications\InterviewScheduledNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Authorization\Concerns\InteractsWithAuthorizationCatalogue;
use Tests\TestCase;

class InterviewSchedulingTest extends TestCase
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

    public function test_approved_owner_schedules_first_round_from_shortlisted(): void
    {
        Notification::fake();

        [$employer, $application, $candidate] = $this->context();

        $response = $this->actingAs($employer)
            ->post(route('employer.applications.interviews.store', $application), $this->schedulePayload());

        $response->assertRedirect();

        $this->assertDatabaseHas('interviews', [
            'job_application_id' => $application->id,
            'scheduled_by_id' => $employer->id,
            'scheduled_at' => '2026-11-15 10:00:00',
            'duration_minutes' => 60,
            'timezone' => 'UTC',
            'mode' => 'online',
            'location_details' => 'https://meet.example.com/room-42',
            'candidate_instructions' => 'Please join five minutes early.',
            'internal_notes' => 'Panel prefers the backend candidate.',
            'status' => InterviewStatus::Scheduled->value,
        ]);

        $this->assertSame(
            JobApplicationStatus::InterviewScheduled,
            $application->fresh()->status
        );

        $this->assertDatabaseHas('job_application_status_histories', [
            'job_application_id' => $application->id,
            'actor_id' => $employer->id,
            'from_status' => JobApplicationStatus::Shortlisted->value,
            'to_status' => JobApplicationStatus::InterviewScheduled->value,
            'reason' => 'Interview scheduled',
        ]);

        Notification::assertSentTo($candidate, InterviewScheduledNotification::class);
        Notification::assertNotSentTo($employer, InterviewScheduledNotification::class);
    }

    public function test_scheduling_additional_rounds_does_not_duplicate_status_history(): void
    {
        [$employer, $application] = $this->context();

        $this->actingAs($employer)
            ->post(route('employer.applications.interviews.store', $application), $this->schedulePayload())
            ->assertRedirect();

        $this->actingAs($employer)
            ->post(route('employer.applications.interviews.store', $application), $this->schedulePayload([
                'scheduled_at_local' => '2026-11-16T15:00',
                'duration_minutes' => 45,
            ]))
            ->assertRedirect();

        $this->assertSame(2, $application->interviews()->count());
        $this->assertSame(
            JobApplicationStatus::InterviewScheduled,
            $application->fresh()->status
        );
        $this->assertSame(1, $application->statusHistories()->where('to_status', 'interview_scheduled')->count());
        $this->assertDatabaseCount('job_application_status_histories', 1);
    }

    public function test_rounds_are_paginated_newest_first_ten_per_page_and_preserve_interview_page(): void
    {
        [$employer, $application] = $this->context(JobApplicationStatus::InterviewScheduled);

        $interviews = [];

        for ($i = 0; $i < 12; $i++) {
            $interview = $this->makeInterview($application, [
                'scheduled_at' => Carbon::now('UTC')->addDays(3)->addMinutes($i * 60),
            ]);
            $interview->forceFill(['created_at' => Carbon::now('UTC')->subMinutes($i)])->save();
            $interviews[$i] = $interview->fresh();
        }

        $this->actingAs($employer)
            ->get(route('employer.applications.show', $application))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('employer/applications/show')
                ->has('interviews', 10)
                ->where('interviews.0.id', $interviews[0]->id)
                ->where('interviews.9.id', $interviews[9]->id)
                ->where('interview_pagination.current_page', 1)
                ->where('interview_pagination.per_page', 10)
                ->where('interview_pagination.total', 12)
                ->where('interview_pagination.next_page_url', fn (?string $url): bool => is_string($url)
                    && str_contains($url, 'interview_page=2'))
            );

        $this->actingAs($employer)
            ->get(route('employer.applications.show', [
                'application' => $application,
                'interview_page' => 2,
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('employer/applications/show')
                ->has('interviews', 2)
                ->where('interviews.0.id', $interviews[10]->id)
                ->where('interviews.1.id', $interviews[11]->id)
                ->where('interview_pagination.current_page', 2)
                ->where('interview_pagination.total', 12)
                ->where('interview_pagination.prev_page_url', fn (?string $url): bool => is_string($url)
                    && str_contains($url, 'interview_page=1'))
            );
    }

    public function test_owner_reschedules_active_round_and_clears_response_and_request_metadata(): void
    {
        Notification::fake();

        [$employer, $application, $candidate] = $this->context(JobApplicationStatus::InterviewScheduled);

        $interview = $this->makeInterview($application, [
            'scheduled_at' => Carbon::parse('2026-11-20 10:00:00', 'UTC'),
            'status' => InterviewStatus::RescheduleRequested,
            'responded_at' => Carbon::parse('2026-11-02 08:00:00', 'UTC'),
            'requested_scheduled_at' => Carbon::parse('2026-11-25 09:00:00', 'UTC'),
            'requested_timezone' => 'Europe/Berlin',
            'response_note' => 'Could we move this?',
        ]);

        $response = $this->actingAs($employer)
            ->put(route('employer.interviews.update', $interview), $this->updatePayload($interview, [
                'scheduled_at_local' => '2026-11-25T09:00',
                'timezone' => 'America/New_York',
                'duration_minutes' => 90,
                'mode' => 'onsite',
                'location_details' => 'HQ Room 5',
                'candidate_instructions' => null,
                'internal_notes' => 'Bring the portfolio.',
            ]));

        $response->assertRedirect();

        $interview->refresh();

        $this->assertSame(InterviewStatus::Scheduled, $interview->status);
        $this->assertSame('2026-11-25 14:00:00', $interview->scheduled_at->utc()->format('Y-m-d H:i:s'));
        $this->assertSame('America/New_York', $interview->timezone);
        $this->assertSame(90, $interview->duration_minutes);
        $this->assertSame('HQ Room 5', $interview->location_details);
        $this->assertSame('Bring the portfolio.', $interview->internal_notes);
        $this->assertNull($interview->responded_at);
        $this->assertNull($interview->requested_scheduled_at);
        $this->assertNull($interview->requested_timezone);
        $this->assertNull($interview->response_note);
        $this->assertNull($interview->cancelled_at);
        $this->assertNull($interview->cancellation_reason);

        $this->assertSame($employer->id, $interview->scheduled_by_id);

        Notification::assertSentTo($candidate, InterviewRescheduledNotification::class);
        Notification::assertNotSentTo($employer, InterviewRescheduledNotification::class);
    }

    public function test_owner_cancels_one_of_several_rounds_with_reason_and_candidate_is_notified(): void
    {
        Notification::fake();

        [$employer, $application, $candidate] = $this->context(JobApplicationStatus::InterviewScheduled);

        $interview = $this->makeInterview($application, [
            'scheduled_at' => Carbon::parse('2026-11-20 10:00:00', 'UTC'),
        ]);
        $otherRound = $this->makeInterview($application, [
            'scheduled_at' => Carbon::parse('2026-11-22 10:00:00', 'UTC'),
        ]);

        $this->actingAs($employer)
            ->post(route('employer.interviews.cancel', $interview), [
                'current_status' => $interview->status->value,
                'current_scheduled_at' => $interview->scheduled_at->toIso8601String(),
                'cancellation_reason' => 'Panel schedule conflict.',
            ])
            ->assertRedirect();

        $interview->refresh();

        $this->assertSame(InterviewStatus::Cancelled, $interview->status);
        $this->assertSame('Panel schedule conflict.', $interview->cancellation_reason);
        $this->assertNotNull($interview->cancelled_at);
        $this->assertSame(InterviewStatus::Scheduled, $otherRound->fresh()->status);

        $this->assertSame(
            JobApplicationStatus::InterviewScheduled,
            $application->fresh()->status
        );
        $this->assertDatabaseMissing('job_application_status_histories', [
            'job_application_id' => $application->id,
            'to_status' => JobApplicationStatus::Shortlisted->value,
        ]);

        Notification::assertSentTo(
            $candidate,
            InterviewCancelledNotification::class,
            function (InterviewCancelledNotification $notification) use ($candidate): bool {
                $data = $notification->toDatabase($candidate);

                return $data['event'] === 'interview.cancelled'
                    && str_contains($data['message'], 'Panel schedule conflict.')
                    && ! str_contains($data['message'], 'shortlisted');
            }
        );
        Notification::assertNotSentTo($employer, InterviewCancelledNotification::class);
    }

    public function test_cancelling_the_last_active_round_returns_application_to_shortlisted_with_history(): void
    {
        Notification::fake();

        [$employer, $application, $candidate] = $this->context(JobApplicationStatus::InterviewScheduled);

        $interview = $this->makeInterview($application, [
            'scheduled_at' => Carbon::parse('2026-11-20 10:00:00', 'UTC'),
        ]);

        $this->actingAs($employer)
            ->post(route('employer.interviews.cancel', $interview), [
                'current_status' => $interview->status->value,
                'current_scheduled_at' => $interview->scheduled_at->toIso8601String(),
                'cancellation_reason' => 'Role has been put on hold.',
            ])
            ->assertRedirect();

        $this->assertSame(
            JobApplicationStatus::Shortlisted,
            $application->fresh()->status
        );

        $this->assertDatabaseHas('job_application_status_histories', [
            'job_application_id' => $application->id,
            'actor_id' => $employer->id,
            'from_status' => JobApplicationStatus::InterviewScheduled->value,
            'to_status' => JobApplicationStatus::Shortlisted->value,
            'reason' => 'Last interview cancelled.',
        ]);

        Notification::assertSentTo(
            $candidate,
            InterviewCancelledNotification::class,
            fn (InterviewCancelledNotification $notification): bool => str_contains(
                $notification->toDatabase($candidate)['message'],
                'returned to the shortlisted stage'
            )
        );
    }

    public function test_scheduling_again_after_last_cancellation_reenters_interview_stage(): void
    {
        [$employer, $application] = $this->context();

        $this->actingAs($employer)
            ->post(route('employer.applications.interviews.store', $application), $this->schedulePayload())
            ->assertRedirect();

        $interview = $application->interviews()->firstOrFail();

        $this->actingAs($employer)
            ->post(route('employer.interviews.cancel', $interview), [
                'current_status' => $interview->status->value,
                'current_scheduled_at' => $interview->scheduled_at->toIso8601String(),
                'cancellation_reason' => 'Need to reconsider the timings.',
            ])
            ->assertRedirect();

        $this->assertSame(JobApplicationStatus::Shortlisted, $application->fresh()->status);

        $this->actingAs($employer)
            ->post(route('employer.applications.interviews.store', $application), $this->schedulePayload([
                'scheduled_at_local' => '2026-11-18T11:00',
            ]))
            ->assertRedirect();

        $this->assertSame(
            JobApplicationStatus::InterviewScheduled,
            $application->fresh()->status
        );
        $this->assertSame(3, $application->statusHistories()->count());
        $this->assertSame(
            2,
            $application->statusHistories()->where('from_status', 'shortlisted')->where('to_status', 'interview_scheduled')->count()
        );
        $this->assertSame(2, $application->interviews()->count());
    }

    public function test_scheduling_from_non_eligible_application_statuses_is_rejected(): void
    {
        $statuses = [
            JobApplicationStatus::Submitted,
            JobApplicationStatus::Reviewing,
            JobApplicationStatus::OfferSent,
            JobApplicationStatus::Hired,
            JobApplicationStatus::Rejected,
            JobApplicationStatus::Withdrawn,
        ];

        foreach ($statuses as $status) {
            [$employer, $application] = $this->context($status);

            $this->actingAs($employer)
                ->post(route('employer.applications.interviews.store', $application), $this->schedulePayload())
                ->assertStatus(422);

            $this->assertDatabaseMissing('interviews', [
                'job_application_id' => $application->id,
            ]);
            $this->assertSame($status, $application->fresh()->status);
        }
    }

    public function test_scheduling_with_a_past_local_time_fails_validation(): void
    {
        [$employer, $application] = $this->context();

        $this->actingAs($employer)
            ->post(route('employer.applications.interviews.store', $application), $this->schedulePayload([
                'scheduled_at_local' => '2026-10-31T10:00',
            ]))
            ->assertSessionHasErrors('scheduled_at_local');

        $this->assertDatabaseMissing('interviews', ['job_application_id' => $application->id]);
        $this->assertSame(JobApplicationStatus::Shortlisted, $application->fresh()->status);
    }

    public function test_scheduling_with_an_invalid_iana_timezone_fails_validation(): void
    {
        [$employer, $application] = $this->context();

        $this->actingAs($employer)
            ->post(route('employer.applications.interviews.store', $application), $this->schedulePayload([
                'timezone' => 'Mars/Olympus_Mons',
            ]))
            ->assertSessionHasErrors('timezone');

        $this->assertDatabaseMissing('interviews', ['job_application_id' => $application->id]);
    }

    public function test_scheduling_with_a_malformed_local_datetime_fails_validation(): void
    {
        [$employer, $application] = $this->context();

        $this->actingAs($employer)
            ->post(route('employer.applications.interviews.store', $application), $this->schedulePayload([
                'scheduled_at_local' => '2026-11-15 10:00',
            ]))
            ->assertSessionHasErrors('scheduled_at_local');

        $this->assertDatabaseMissing('interviews', ['job_application_id' => $application->id]);
    }

    public function test_scheduling_validates_duration_mode_and_location_bounds(): void
    {
        [$employer, $application] = $this->context();

        $cases = [
            ['payload' => ['duration_minutes' => 14], 'field' => 'duration_minutes'],
            ['payload' => ['duration_minutes' => 481], 'field' => 'duration_minutes'],
            ['payload' => ['mode' => 'telepathy'], 'field' => 'mode'],
            ['payload' => ['location_details' => ''], 'field' => 'location_details'],
            ['payload' => ['location_details' => str_repeat('a', 501)], 'field' => 'location_details'],
        ];

        foreach ($cases as $case) {
            $this->actingAs($employer)
                ->post(route('employer.applications.interviews.store', $application), $this->schedulePayload($case['payload']))
                ->assertSessionHasErrors($case['field']);
        }

        $this->assertDatabaseMissing('interviews', ['job_application_id' => $application->id]);
    }

    public function test_blank_optional_fields_are_normalized_to_null(): void
    {
        [$employer, $application] = $this->context();

        $this->actingAs($employer)
            ->post(route('employer.applications.interviews.store', $application), $this->schedulePayload([
                'candidate_instructions' => '   ',
                'internal_notes' => '',
            ]))
            ->assertRedirect();

        $interview = $application->interviews()->firstOrFail();

        $this->assertNull($interview->candidate_instructions);
        $this->assertNull($interview->internal_notes);
    }

    public function test_overlapping_active_round_is_rejected_and_adjacent_interval_is_allowed(): void
    {
        [$employer, $application] = $this->context(JobApplicationStatus::InterviewScheduled);

        $first = $this->makeInterview($application, [
            'scheduled_at' => Carbon::parse('2026-11-20 10:00:00', 'UTC'),
            'duration_minutes' => 60,
        ]);

        $this->actingAs($employer)
            ->post(route('employer.applications.interviews.store', $application), $this->schedulePayload([
                'scheduled_at_local' => '2026-11-20T10:30',
            ]))
            ->assertSessionHasErrors('scheduled_at_local');

        $this->assertSame(1, $application->interviews()->count());

        $this->actingAs($employer)
            ->post(route('employer.applications.interviews.store', $application), $this->schedulePayload([
                'scheduled_at_local' => '2026-11-20T11:00',
            ]))
            ->assertRedirect();

        $this->assertSame(2, $application->interviews()->count());

        $second = $application->interviews()->whereKeyNot($first->id)->firstOrFail();

        // Rescheduling the second round onto the first round's interval is also rejected.
        $this->actingAs($employer)
            ->put(route('employer.interviews.update', $second), $this->updatePayload($second, [
                'scheduled_at_local' => '2026-11-20T10:15',
                'duration_minutes' => 30,
            ]))
            ->assertSessionHasErrors('scheduled_at_local');

        $this->assertSame(2, $application->interviews()->count());
        $this->assertSame('2026-11-20 11:00:00', $second->fresh()->scheduled_at->utc()->format('Y-m-d H:i:s'));
    }

    public function test_cancelled_and_declined_rounds_do_not_block_a_new_overlapping_round(): void
    {
        [$employer, $application] = $this->context(JobApplicationStatus::InterviewScheduled);

        $this->makeInterview($application, [
            'scheduled_at' => Carbon::parse('2026-11-20 10:00:00', 'UTC'),
            'duration_minutes' => 60,
            'status' => InterviewStatus::Cancelled,
        ]);
        $this->makeInterview($application, [
            'scheduled_at' => Carbon::parse('2026-11-20 10:00:00', 'UTC'),
            'duration_minutes' => 60,
            'status' => InterviewStatus::Declined,
        ]);

        $this->actingAs($employer)
            ->post(route('employer.applications.interviews.store', $application), $this->schedulePayload([
                'scheduled_at_local' => '2026-11-20T10:30',
            ]))
            ->assertRedirect();

        $this->assertSame(3, $application->interviews()->count());
        $this->assertSame(1, $application->interviews()->where('status', InterviewStatus::Scheduled->value)->count());
    }

    public function test_non_utc_local_wall_time_is_stored_as_correct_utc_including_a_dst_boundary(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-01-15 00:00:00', 'UTC'));

        [$employer, $application] = $this->context();

        // 2026-03-01 is before US daylight saving time (EST, UTC-5).
        $this->actingAs($employer)
            ->post(route('employer.applications.interviews.store', $application), $this->schedulePayload([
                'scheduled_at_local' => '2026-03-01T09:00',
                'timezone' => 'America/New_York',
            ]))
            ->assertRedirect();

        // 2026-03-08 is after the US DST transition (EDT, UTC-4).
        $this->actingAs($employer)
            ->post(route('employer.applications.interviews.store', $application), $this->schedulePayload([
                'scheduled_at_local' => '2026-03-08T09:00',
                'timezone' => 'America/New_York',
            ]))
            ->assertRedirect();

        $this->assertDatabaseHas('interviews', [
            'job_application_id' => $application->id,
            'scheduled_at' => '2026-03-01 14:00:00',
            'timezone' => 'America/New_York',
        ]);

        $this->assertDatabaseHas('interviews', [
            'job_application_id' => $application->id,
            'scheduled_at' => '2026-03-08 13:00:00',
            'timezone' => 'America/New_York',
        ]);

        $this->assertSame(2, $application->interviews()->count());
    }

    public function test_reschedule_and_cancel_with_stale_state_return_conflict_and_change_nothing(): void
    {
        [$employer, $application] = $this->context(JobApplicationStatus::InterviewScheduled);

        $interview = $this->makeInterview($application, [
            'scheduled_at' => Carbon::parse('2026-11-20 10:00:00', 'UTC'),
        ]);

        $this->actingAs($employer)
            ->put(route('employer.interviews.update', $interview), array_merge(
                $this->schedulePayload(['scheduled_at_local' => '2026-11-25T10:00']),
                [
                    'current_status' => $interview->status->value,
                    'current_scheduled_at' => Carbon::parse('2026-11-21 10:00:00', 'UTC')->toIso8601String(),
                ],
            ))
            ->assertStatus(409);

        $this->actingAs($employer)
            ->put(route('employer.interviews.update', $interview), array_merge(
                $this->schedulePayload(['scheduled_at_local' => '2026-11-25T10:00']),
                [
                    'current_status' => InterviewStatus::Accepted->value,
                    'current_scheduled_at' => $interview->scheduled_at->toIso8601String(),
                ],
            ))
            ->assertStatus(409);

        $this->actingAs($employer)
            ->post(route('employer.interviews.cancel', $interview), [
                'current_status' => InterviewStatus::Accepted->value,
                'current_scheduled_at' => $interview->scheduled_at->toIso8601String(),
                'cancellation_reason' => 'Stale cancel attempt.',
            ])
            ->assertStatus(409);

        $interview->refresh();

        $this->assertSame(InterviewStatus::Scheduled, $interview->status);
        $this->assertSame('2026-11-20 10:00:00', $interview->scheduled_at->utc()->format('Y-m-d H:i:s'));
        $this->assertNull($interview->cancellation_reason);
    }

    public function test_cancelled_or_declined_rounds_cannot_be_rescheduled_or_cancelled(): void
    {
        [$employer, $application] = $this->context(JobApplicationStatus::InterviewScheduled);

        $cancelled = $this->makeInterview($application, [
            'scheduled_at' => Carbon::parse('2026-11-20 10:00:00', 'UTC'),
            'status' => InterviewStatus::Cancelled,
        ]);
        $declined = $this->makeInterview($application, [
            'scheduled_at' => Carbon::parse('2026-11-22 10:00:00', 'UTC'),
            'status' => InterviewStatus::Declined,
        ]);

        $this->actingAs($employer)
            ->put(route('employer.interviews.update', $cancelled), $this->updatePayload($cancelled))
            ->assertStatus(422);

        $this->actingAs($employer)
            ->post(route('employer.interviews.cancel', $cancelled), [
                'current_status' => $cancelled->status->value,
                'current_scheduled_at' => $cancelled->scheduled_at->toIso8601String(),
                'cancellation_reason' => 'Trying to cancel again.',
            ])
            ->assertStatus(422);

        $this->actingAs($employer)
            ->post(route('employer.interviews.cancel', $declined), [
                'current_status' => $declined->status->value,
                'current_scheduled_at' => $declined->scheduled_at->toIso8601String(),
                'cancellation_reason' => 'Trying to cancel a declined round.',
            ])
            ->assertStatus(422);

        $this->assertSame(InterviewStatus::Cancelled, $cancelled->fresh()->status);
        $this->assertSame(InterviewStatus::Declined, $declined->fresh()->status);
    }

    public function test_cancellation_reason_bounds_are_enforced(): void
    {
        [$employer, $application] = $this->context(JobApplicationStatus::InterviewScheduled);

        $interview = $this->makeInterview($application, [
            'scheduled_at' => Carbon::parse('2026-11-20 10:00:00', 'UTC'),
        ]);

        $this->actingAs($employer)
            ->post(route('employer.interviews.cancel', $interview), [
                'current_status' => $interview->status->value,
                'current_scheduled_at' => $interview->scheduled_at->toIso8601String(),
                'cancellation_reason' => '',
            ])
            ->assertSessionHasErrors('cancellation_reason');

        $this->actingAs($employer)
            ->post(route('employer.interviews.cancel', $interview), [
                'current_status' => $interview->status->value,
                'current_scheduled_at' => $interview->scheduled_at->toIso8601String(),
                'cancellation_reason' => 'ab',
            ])
            ->assertSessionHasErrors('cancellation_reason');

        $this->actingAs($employer)
            ->post(route('employer.interviews.cancel', $interview), [
                'current_status' => $interview->status->value,
                'current_scheduled_at' => $interview->scheduled_at->toIso8601String(),
                'cancellation_reason' => str_repeat('a', 501),
            ])
            ->assertSessionHasErrors('cancellation_reason');

        $this->assertSame(InterviewStatus::Scheduled, $interview->fresh()->status);
    }

    public function test_employer_transition_to_offer_or_rejected_cancels_all_active_interviews(): void
    {
        foreach ([JobApplicationStatus::OfferSent, JobApplicationStatus::Rejected] as $targetStatus) {
            [$employer, $application, $candidate] = $this->context(JobApplicationStatus::InterviewScheduled);

            $scheduled = $this->makeInterview($application);
            $accepted = $this->makeInterview($application, [
                'scheduled_at' => Carbon::parse('2026-11-22 10:00:00', 'UTC'),
                'status' => InterviewStatus::Accepted,
            ]);
            $declined = $this->makeInterview($application, [
                'scheduled_at' => Carbon::parse('2026-11-24 10:00:00', 'UTC'),
                'status' => InterviewStatus::Declined,
            ]);

            $this->actingAs($employer)
                ->post(route('employer.applications.update-status', $application), [
                    'current_status' => JobApplicationStatus::InterviewScheduled->value,
                    'new_status' => $targetStatus->value,
                ])
                ->assertRedirect();

            $this->assertSame($targetStatus, $application->fresh()->status);
            $this->assertSame(InterviewStatus::Cancelled, $scheduled->fresh()->status);
            $this->assertSame(InterviewStatus::Cancelled, $accepted->fresh()->status);
            $this->assertSame(InterviewStatus::Declined, $declined->fresh()->status);

            $expectedReason = 'Application moved to '.$targetStatus->label().'.';

            $this->assertSame($expectedReason, $scheduled->fresh()->cancellation_reason);
            $this->assertNotNull($scheduled->fresh()->cancelled_at);

            // Only the application status notification is authoritative for lifecycle cancellations.
            $this->assertSame(1, $candidate->fresh()->notifications()->count());
            $this->assertSame(
                'application.status_changed',
                $candidate->fresh()->notifications()->first()->data['event'] ?? null
            );
        }
    }

    public function test_employer_dropdown_does_not_offer_shortlisted_from_interview_scheduled(): void
    {
        [$employer, $application] = $this->context(JobApplicationStatus::InterviewScheduled);

        $this->makeInterview($application);

        $this->actingAs($employer)
            ->get(route('employer.applications.show', $application))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('employer/applications/show')
                ->where('interview_eligible', true)
                ->where('allowed_transitions', fn ($transitions): bool => collect($transitions)
                    ->pluck('value')
                    ->doesntContain(JobApplicationStatus::Shortlisted->value)
                    && collect($transitions)->pluck('value')->contains(JobApplicationStatus::OfferSent->value)
                    && collect($transitions)->pluck('value')->contains(JobApplicationStatus::Rejected->value))
            );
    }

    public function test_employer_application_props_include_internal_notes_and_round_details(): void
    {
        [$employer, $application] = $this->context(JobApplicationStatus::InterviewScheduled);

        $interview = $this->makeInterview($application, [
            'scheduled_at' => Carbon::parse('2026-11-20 10:00:00', 'UTC'),
            'internal_notes' => 'Employer-only note.',
            'candidate_instructions' => 'Ask for the hiring manager.',
        ]);

        $this->actingAs($employer)
            ->get(route('employer.applications.show', $application))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('employer/applications/show')
                ->has('interviews', 1)
                ->where('interviews.0.id', $interview->id)
                ->where('interviews.0.internal_notes', 'Employer-only note.')
                ->where('interviews.0.location_details', $interview->location_details)
                ->where('interviews.0.can_manage', true)
                ->where('interviews.0.scheduled_at', $interview->scheduled_at->toIso8601String())
                ->has('interviews.0.response_note')
                ->has('interview_pagination')
                ->has('timezones')
            );
    }

    public function test_guests_are_redirected_from_employer_interview_routes(): void
    {
        [, $application] = $this->context(JobApplicationStatus::InterviewScheduled);
        $interview = $this->makeInterview($application);

        $this->post(route('employer.applications.interviews.store', $application), $this->schedulePayload())
            ->assertRedirect(route('login'));

        $this->put(route('employer.interviews.update', $interview), $this->updatePayload($interview))
            ->assertRedirect(route('login'));

        $this->post(route('employer.interviews.cancel', $interview), [
            'current_status' => $interview->status->value,
            'current_scheduled_at' => $interview->scheduled_at->toIso8601String(),
            'cancellation_reason' => 'Guest attempt.',
        ])->assertRedirect(route('login'));
    }

    public function test_candidates_cannot_access_employer_interview_routes(): void
    {
        [$employer, $application] = $this->context(JobApplicationStatus::InterviewScheduled);
        $interview = $this->makeInterview($application);
        $candidate = $application->user;

        $this->actingAs($candidate)
            ->post(route('employer.applications.interviews.store', $application), $this->schedulePayload())
            ->assertForbidden();

        $this->actingAs($candidate)
            ->put(route('employer.interviews.update', $interview), $this->updatePayload($interview))
            ->assertForbidden();

        $this->actingAs($candidate)
            ->post(route('employer.interviews.cancel', $interview), [
                'current_status' => $interview->status->value,
                'current_scheduled_at' => $interview->scheduled_at->toIso8601String(),
                'cancellation_reason' => 'Candidate attempt.',
            ])
            ->assertForbidden();

        $this->assertSame(InterviewStatus::Scheduled, $interview->fresh()->status);
    }

    public function test_unapproved_employers_cannot_schedule_interviews(): void
    {
        $employer = User::factory()->employer()->create();
        $company = Company::factory()->for($employer, 'owner')->create();
        $job = Job::factory()->for($company)->create();
        $candidate = User::factory()->jobSeeker()->create();
        $application = JobApplication::factory()
            ->for($job, 'jobPosting')
            ->for($candidate, 'user')
            ->create(['status' => JobApplicationStatus::Shortlisted]);

        $this->actingAs($employer)
            ->post(route('employer.applications.interviews.store', $application), $this->schedulePayload())
            ->assertForbidden();

        $this->assertDatabaseMissing('interviews', ['job_application_id' => $application->id]);
    }

    public function test_unrelated_employers_cannot_schedule_reschedule_or_cancel_another_companys_round(): void
    {
        [$owner, $application] = $this->context(JobApplicationStatus::InterviewScheduled);
        $interview = $this->makeInterview($application);

        $intruder = User::factory()->approvedEmployer()->create();
        Company::factory()->for($intruder, 'owner')->create();

        $this->actingAs($intruder)
            ->post(route('employer.applications.interviews.store', $application), $this->schedulePayload())
            ->assertForbidden();

        $this->actingAs($intruder)
            ->put(route('employer.interviews.update', $interview), $this->updatePayload($interview))
            ->assertForbidden();

        $this->actingAs($intruder)
            ->post(route('employer.interviews.cancel', $interview), [
                'current_status' => $interview->status->value,
                'current_scheduled_at' => $interview->scheduled_at->toIso8601String(),
                'cancellation_reason' => 'Intruder attempt.',
            ])
            ->assertForbidden();

        $this->assertSame(InterviewStatus::Scheduled, $interview->fresh()->status);
        $this->assertSame(1, $application->interviews()->count());
    }

    /**
     * @return array{0: User, 1: JobApplication, 2: User}
     */
    private function context(JobApplicationStatus $status = JobApplicationStatus::Shortlisted): array
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
     * @param  array<string, mixed>  $extras
     * @return array<string, mixed>
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
    private function schedulePayload(array $overrides = []): array
    {
        return array_merge([
            'scheduled_at_local' => '2026-11-15T10:00',
            'timezone' => 'UTC',
            'duration_minutes' => 60,
            'mode' => 'online',
            'location_details' => 'https://meet.example.com/room-42',
            'candidate_instructions' => 'Please join five minutes early.',
            'internal_notes' => 'Panel prefers the backend candidate.',
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function updatePayload(Interview $interview, array $overrides = []): array
    {
        return array_merge($this->schedulePayload($overrides), [
            'current_status' => $interview->status->value,
            'current_scheduled_at' => $interview->scheduled_at->toIso8601String(),
        ]);
    }
}
