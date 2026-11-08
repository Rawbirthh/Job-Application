<?php

namespace Tests\Feature\Notifications;

use App\Enums\InterviewStatus;
use App\Enums\JobApplicationStatus;
use App\Models\Company;
use App\Models\Interview;
use App\Models\Job;
use App\Models\JobApplication;
use App\Models\User;
use App\Notifications\InterviewCancelledNotification;
use App\Notifications\InterviewRescheduledNotification;
use App\Notifications\InterviewRescheduleRequestedNotification;
use App\Notifications\InterviewResponseNotification;
use App\Notifications\InterviewScheduledNotification;
use Illuminate\Contracts\Notifications\Dispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\Authorization\Concerns\InteractsWithAuthorizationCatalogue;
use Tests\TestCase;

class InterviewNotificationTest extends TestCase
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

    public function test_scheduling_notifies_only_the_candidate_and_never_exposes_internal_notes(): void
    {
        Notification::fake();

        [$employer, $application, $candidate] = $this->context();

        $this->actingAs($employer)
            ->post(route('employer.applications.interviews.store', $application), $this->schedulePayload([
                'internal_notes' => 'Confidential salary discussion: 120k.',
                'candidate_instructions' => 'Use the side entrance on 5th street.',
            ]))
            ->assertRedirect();

        Notification::assertSentTo(
            $candidate,
            InterviewScheduledNotification::class,
            function (InterviewScheduledNotification $notification) use ($candidate, $application): bool {
                $data = $notification->toDatabase($candidate);

                return $data['event'] === 'interview.scheduled'
                    && str_contains($data['message'], $application->jobPosting->title)
                    && str_contains($data['message'], 'Nov 15, 2026 10:00 AM (UTC)')
                    && ! str_contains($data['message'], 'Confidential salary discussion')
                    && ! str_contains($data['message'], 'side entrance')
                    && $data['url'] === route('candidate.applications.show', $application);
            }
        );

        Notification::assertNotSentTo($employer, InterviewScheduledNotification::class);
    }

    public function test_rescheduling_notifies_the_candidate_only(): void
    {
        Notification::fake();

        [$employer, $application, $candidate] = $this->context(JobApplicationStatus::InterviewScheduled);
        $interview = $this->makeInterview($application, [
            'internal_notes' => 'Confidential reschedule note.',
        ]);

        $this->actingAs($employer)
            ->put(route('employer.interviews.update', $interview), $this->updatePayload($interview, [
                'scheduled_at_local' => '2026-11-22T15:00',
                'internal_notes' => 'Confidential reschedule note.',
            ]))
            ->assertRedirect();

        Notification::assertSentTo(
            $candidate,
            InterviewRescheduledNotification::class,
            function (InterviewRescheduledNotification $notification) use ($candidate): bool {
                $data = $notification->toDatabase($candidate);

                return $data['event'] === 'interview.rescheduled'
                    && str_contains($data['message'], 'Nov 22, 2026 3:00 PM (UTC)')
                    && ! str_contains($data['message'], 'Confidential reschedule note');
            }
        );

        Notification::assertNotSentTo($employer, InterviewRescheduledNotification::class);
    }

    public function test_cancelling_notifies_the_candidate_with_the_reason(): void
    {
        Notification::fake();

        [$employer, $application, $candidate] = $this->context(JobApplicationStatus::InterviewScheduled);
        $interview = $this->makeInterview($application, [
            'internal_notes' => 'Confidential cancellation note.',
        ]);
        $this->makeInterview($application, [
            'scheduled_at' => Carbon::parse('2026-11-25 10:00:00', 'UTC'),
        ]);

        $this->actingAs($employer)
            ->post(route('employer.interviews.cancel', $interview), [
                'current_status' => $interview->status->value,
                'current_scheduled_at' => $interview->scheduled_at->toIso8601String(),
                'cancellation_reason' => 'Interviewer is unavailable.',
            ])
            ->assertRedirect();

        Notification::assertSentTo(
            $candidate,
            InterviewCancelledNotification::class,
            function (InterviewCancelledNotification $notification) use ($candidate): bool {
                $data = $notification->toDatabase($candidate);

                return $data['event'] === 'interview.cancelled'
                    && str_contains($data['message'], 'Interviewer is unavailable.')
                    && ! str_contains($data['message'], 'Confidential cancellation note');
            }
        );

        Notification::assertNotSentTo($employer, InterviewCancelledNotification::class);
    }

    public function test_accepting_and_declining_notify_only_the_company_owner(): void
    {
        Notification::fake();

        [$employer, $application, $candidate] = $this->context(JobApplicationStatus::InterviewScheduled);
        $accepted = $this->makeInterview($application);
        $declined = $this->makeInterview($application, [
            'scheduled_at' => Carbon::parse('2026-11-22 10:00:00', 'UTC'),
        ]);

        $this->actingAs($candidate)
            ->post(route('candidate.interviews.respond', $accepted), $this->respondPayload($accepted, [
                'response' => InterviewStatus::Accepted->value,
            ]))
            ->assertRedirect();

        $this->actingAs($candidate)
            ->post(route('candidate.interviews.respond', $declined), $this->respondPayload($declined, [
                'response' => InterviewStatus::Declined->value,
            ]))
            ->assertRedirect();

        Notification::assertSentToTimes($employer, InterviewResponseNotification::class, 2);
        Notification::assertNotSentTo($candidate, InterviewResponseNotification::class);

        $messages = Notification::sent($employer, InterviewResponseNotification::class)
            ->map(fn (InterviewResponseNotification $notification): string => $notification->toDatabase($employer)['message'])
            ->implode(' ');

        $this->assertStringContainsString('Accepted', $messages);
        $this->assertStringContainsString('Declined', $messages);
    }

    public function test_new_time_request_notifies_the_owner_with_the_proposed_time_only(): void
    {
        Notification::fake();

        [$employer, $application, $candidate] = $this->context(JobApplicationStatus::InterviewScheduled);
        $interview = $this->makeInterview($application, [
            'internal_notes' => 'Confidential request note.',
        ]);

        $this->actingAs($candidate)
            ->post(route('candidate.interviews.respond', $interview), $this->respondPayload($interview, [
                'response' => InterviewStatus::RescheduleRequested->value,
                'requested_at_local' => '2026-11-27T09:00',
                'requested_timezone' => 'Europe/Berlin',
                'response_note' => 'Any time after 9 works.',
            ]))
            ->assertRedirect();

        Notification::assertSentTo(
            $employer,
            InterviewRescheduleRequestedNotification::class,
            function (InterviewRescheduleRequestedNotification $notification) use ($employer): bool {
                $data = $notification->toDatabase($employer);

                return $data['event'] === 'interview.reschedule_requested'
                    && str_contains($data['message'], 'Nov 27, 2026 9:00 AM (Europe/Berlin)')
                    && str_contains($data['message'], 'Any time after 9 works.')
                    && ! str_contains($data['message'], 'Confidential request note')
                    && $data['url'] === route('employer.applications.show', $notification->interview->jobApplication);
            }
        );

        Notification::assertNotSentTo($candidate, InterviewRescheduleRequestedNotification::class);
    }

    public function test_notification_delivery_failure_does_not_roll_back_the_interview_write(): void
    {
        Log::spy();

        $this->app->instance(Dispatcher::class, new class implements Dispatcher
        {
            public function send($notifiables, $notification)
            {
                throw new \RuntimeException('Notification delivery failed.');
            }

            public function sendNow($notifiables, $notification, ?array $channels = null)
            {
                throw new \RuntimeException('Notification delivery failed.');
            }
        });

        [$employer, $application, $candidate] = $this->context();

        $this->actingAs($employer)
            ->post(route('employer.applications.interviews.store', $application), $this->schedulePayload())
            ->assertRedirect();

        $this->assertDatabaseHas('interviews', [
            'job_application_id' => $application->id,
            'status' => InterviewStatus::Scheduled->value,
        ]);
        $this->assertSame(
            JobApplicationStatus::InterviewScheduled,
            $application->fresh()->status
        );
        $this->assertSame(0, $candidate->fresh()->notifications()->count());

        Log::shouldHaveReceived('warning')
            ->withArgs(fn (string $message): bool => $message === 'Notification creation failed')
            ->once();
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
    private function schedulePayload(array $overrides = []): array
    {
        return array_merge([
            'scheduled_at_local' => '2026-11-15T10:00',
            'timezone' => 'UTC',
            'duration_minutes' => 60,
            'mode' => 'online',
            'location_details' => 'https://meet.example.com/room-42',
            'candidate_instructions' => null,
            'internal_notes' => null,
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
