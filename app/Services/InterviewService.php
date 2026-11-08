<?php

namespace App\Services;

use App\Enums\InterviewMode;
use App\Enums\InterviewStatus;
use App\Enums\JobApplicationStatus;
use App\Models\Interview;
use App\Models\JobApplication;
use App\Models\User;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InterviewService
{
    public function __construct(
        private readonly ApplicationStatusTransitionService $statusTransitionService,
    ) {}

    /**
     * Schedule a new interview round for the application.
     *
     * @param  array<string, mixed>  $data
     */
    public function schedule(JobApplication $application, User $actor, array $data): Interview
    {
        return DB::transaction(function () use ($application, $actor, $data): Interview {
            /** @var JobApplication $lockedApplication */
            $lockedApplication = JobApplication::query()
                ->whereKey($application->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($lockedApplication->status, [
                JobApplicationStatus::Shortlisted,
                JobApplicationStatus::InterviewScheduled,
            ], true)) {
                abort(422, __('Interviews can only be scheduled for shortlisted applications.'));
            }

            $scheduledAt = $this->utcInstant($data['scheduled_at_utc'] ?? null);
            $durationMinutes = (int) ($data['duration_minutes'] ?? 0);

            if ($scheduledAt === null || $durationMinutes < 1) {
                throw ValidationException::withMessages([
                    'scheduled_at_local' => __('Choose a valid interview date and time.'),
                ]);
            }

            $this->assertNoOverlap($lockedApplication, $scheduledAt, $durationMinutes, null, 'scheduled_at_local');

            /** @var Interview $interview */
            $interview = $lockedApplication->interviews()->create([
                'scheduled_by_id' => $actor->id,
                'scheduled_at' => $scheduledAt,
                'duration_minutes' => $durationMinutes,
                'timezone' => (string) ($data['timezone'] ?? 'UTC'),
                'mode' => InterviewMode::from((string) ($data['mode'] ?? InterviewMode::Online->value)),
                'location_details' => (string) ($data['location_details'] ?? ''),
                'candidate_instructions' => $data['candidate_instructions'] ?? null,
                'internal_notes' => $data['internal_notes'] ?? null,
                'status' => InterviewStatus::Scheduled,
            ]);

            if ($lockedApplication->status === JobApplicationStatus::Shortlisted) {
                $this->statusTransitionService->transitionAsEmployer(
                    $lockedApplication,
                    JobApplicationStatus::InterviewScheduled,
                    JobApplicationStatus::Shortlisted->value,
                    $actor,
                    'Interview scheduled',
                );
            }

            return $interview;
        });
    }

    /**
     * Reschedule an active interview round.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Interview $interview, User $actor, array $data): Interview
    {
        return DB::transaction(function () use ($interview, $actor, $data): Interview {
            /** @var JobApplication $lockedApplication */
            $lockedApplication = JobApplication::query()
                ->whereKey($interview->job_application_id)
                ->lockForUpdate()
                ->firstOrFail();

            /** @var Interview $lockedInterview */
            $lockedInterview = Interview::query()
                ->whereKey($interview->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedApplication->status !== JobApplicationStatus::InterviewScheduled) {
                abort(422, __('Interviews can only be rescheduled while the application is in the interview stage.'));
            }

            if (! $lockedInterview->status->isActive()) {
                abort(422, __('Only active interview rounds can be rescheduled.'));
            }

            $this->assertCurrentState($lockedInterview, $data);

            $scheduledAt = $this->utcInstant($data['scheduled_at_utc'] ?? null);
            $durationMinutes = (int) ($data['duration_minutes'] ?? $lockedInterview->duration_minutes);

            if ($scheduledAt === null || $durationMinutes < 1) {
                throw ValidationException::withMessages([
                    'scheduled_at_local' => __('Choose a valid interview date and time.'),
                ]);
            }

            $this->assertNoOverlap(
                $lockedApplication,
                $scheduledAt,
                $durationMinutes,
                $lockedInterview->id,
                'scheduled_at_local',
            );

            $lockedInterview->fill([
                'scheduled_by_id' => $actor->id,
                'scheduled_at' => $scheduledAt,
                'duration_minutes' => $durationMinutes,
                'timezone' => (string) ($data['timezone'] ?? $lockedInterview->timezone),
                'mode' => InterviewMode::from((string) ($data['mode'] ?? $lockedInterview->mode->value)),
                'location_details' => (string) ($data['location_details'] ?? $lockedInterview->location_details),
                'candidate_instructions' => $data['candidate_instructions'] ?? null,
                'internal_notes' => $data['internal_notes'] ?? null,
                'status' => InterviewStatus::Scheduled,
                'responded_at' => null,
                'requested_scheduled_at' => null,
                'requested_timezone' => null,
                'response_note' => null,
                'cancelled_at' => null,
                'cancellation_reason' => null,
            ]);

            $lockedInterview->save();

            return $lockedInterview;
        });
    }

    /**
     * Cancel an active interview round.
     *
     * @param  array<string, mixed>  $data
     */
    public function cancel(Interview $interview, User $actor, array $data): Interview
    {
        return DB::transaction(function () use ($interview, $actor, $data): Interview {
            /** @var JobApplication $lockedApplication */
            $lockedApplication = JobApplication::query()
                ->whereKey($interview->job_application_id)
                ->lockForUpdate()
                ->firstOrFail();

            /** @var Interview $lockedInterview */
            $lockedInterview = Interview::query()
                ->whereKey($interview->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedApplication->status !== JobApplicationStatus::InterviewScheduled) {
                abort(422, __('Interviews can only be cancelled while the application is in the interview stage.'));
            }

            if (! $lockedInterview->status->isActive()) {
                abort(422, __('Only active interview rounds can be cancelled.'));
            }

            $this->assertCurrentState($lockedInterview, $data);

            $lockedInterview->status = InterviewStatus::Cancelled;
            $lockedInterview->cancelled_at = now();
            $lockedInterview->cancellation_reason = (string) ($data['cancellation_reason'] ?? '');
            $lockedInterview->save();

            $hasActiveRounds = $lockedApplication->interviews()
                ->whereIn('status', InterviewStatus::activeValues())
                ->exists();

            if (! $hasActiveRounds) {
                $this->statusTransitionService->revertToShortlistedAfterLastInterviewCancellation(
                    $lockedApplication,
                    $actor,
                    'Last interview cancelled.',
                );
            }

            return $lockedInterview;
        });
    }

    /**
     * Record the candidate's response to an interview round.
     *
     * @param  array<string, mixed>  $data
     */
    public function respond(Interview $interview, User $candidate, array $data): Interview
    {
        return DB::transaction(function () use ($interview, $data): Interview {
            /** @var JobApplication $lockedApplication */
            $lockedApplication = JobApplication::query()
                ->whereKey($interview->job_application_id)
                ->lockForUpdate()
                ->firstOrFail();

            /** @var Interview $lockedInterview */
            $lockedInterview = Interview::query()
                ->whereKey($interview->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedApplication->status !== JobApplicationStatus::InterviewScheduled) {
                abort(422, __('This interview can no longer be answered.'));
            }

            if ($lockedInterview->status !== InterviewStatus::Scheduled) {
                abort(422, __('This interview has already been answered.'));
            }

            $this->assertCurrentState($lockedInterview, $data);

            $response = InterviewStatus::tryFrom((string) ($data['response'] ?? ''));

            if ($response === null || ! in_array($response, [
                InterviewStatus::Accepted,
                InterviewStatus::Declined,
                InterviewStatus::RescheduleRequested,
            ], true)) {
                throw ValidationException::withMessages([
                    'response' => __('Choose whether to accept, decline, or request a new time.'),
                ]);
            }

            $lockedInterview->responded_at = now();
            $lockedInterview->response_note = $data['response_note'] ?? null;

            if ($response === InterviewStatus::RescheduleRequested) {
                $requestedAt = $this->utcInstant($data['requested_at_utc'] ?? null);

                if ($requestedAt === null || $requestedAt->lessThanOrEqualTo(CarbonImmutable::now('UTC'))) {
                    throw ValidationException::withMessages([
                        'requested_at_local' => __('Choose a future date and time for the proposed interview.'),
                    ]);
                }

                $this->assertNoOverlap(
                    $lockedApplication,
                    $requestedAt,
                    (int) $lockedInterview->duration_minutes,
                    $lockedInterview->id,
                    'requested_at_local',
                );

                $lockedInterview->status = InterviewStatus::RescheduleRequested;
                $lockedInterview->requested_scheduled_at = $requestedAt;
                $lockedInterview->requested_timezone = (string) ($data['requested_timezone'] ?? 'UTC');
            } else {
                $lockedInterview->status = $response;
                $lockedInterview->requested_scheduled_at = null;
                $lockedInterview->requested_timezone = null;
            }

            $lockedInterview->save();

            return $lockedInterview;
        });
    }

    /**
     * Reject an interval that overlaps another active round for the same application.
     */
    private function assertNoOverlap(
        JobApplication $application,
        CarbonImmutable $start,
        int $durationMinutes,
        ?int $excludeInterviewId,
        string $errorField,
    ): void {
        $end = $start->addMinutes($durationMinutes);
        $windowStart = $start->subMinutes(480);

        $query = $application->interviews()
            ->whereIn('status', InterviewStatus::activeValues())
            ->where('scheduled_at', '>=', $windowStart->format('Y-m-d H:i:s'))
            ->where('scheduled_at', '<', $end->format('Y-m-d H:i:s'));

        if ($excludeInterviewId !== null) {
            $query->whereKeyNot($excludeInterviewId);
        }

        $existingRounds = $query->get(['id', 'scheduled_at', 'duration_minutes']);

        foreach ($existingRounds as $round) {
            $roundStart = $this->utcInstant($round->scheduled_at);

            if ($roundStart === null) {
                continue;
            }

            $roundEnd = $roundStart->addMinutes((int) $round->duration_minutes);

            if ($roundStart->lessThan($end) && $roundEnd->greaterThan($start)) {
                throw ValidationException::withMessages([
                    $errorField => __('This time overlaps another active interview round for this application.'),
                ]);
            }
        }
    }

    /**
     * Ensure the submitted status and scheduled time still match the stored round.
     *
     * @param  array<string, mixed>  $data
     */
    private function assertCurrentState(Interview $interview, array $data): void
    {
        $expectedStatus = InterviewStatus::tryFrom((string) ($data['current_status'] ?? ''));
        $expectedScheduledAt = $this->utcInstant($data['current_scheduled_at'] ?? null);

        if (
            $expectedStatus === null
            || $interview->status !== $expectedStatus
            || $expectedScheduledAt === null
            || $interview->scheduled_at->getTimestamp() !== $expectedScheduledAt->getTimestamp()
        ) {
            abort(409, __('This interview has changed since you loaded the page. Please refresh and try again.'));
        }
    }

    /**
     * Convert the given value to a UTC instant, returning null when it is invalid.
     */
    private function utcInstant(mixed $value): ?CarbonImmutable
    {
        if ($value instanceof DateTimeInterface) {
            return CarbonImmutable::instance($value)->utc();
        }

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value, 'UTC')->utc();
        } catch (\Throwable) {
            return null;
        }
    }
}
