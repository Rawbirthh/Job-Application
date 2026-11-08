<?php

namespace App\Services;

use App\Enums\InterviewStatus;
use App\Enums\JobApplicationStatus;
use App\Models\JobApplication;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ApplicationStatusTransitionService
{
    /**
     * Transition an application's status as an authorized employer.
     */
    public function transitionAsEmployer(
        JobApplication $application,
        JobApplicationStatus $newStatus,
        string $expectedCurrentStatus,
        User $actor,
        ?string $reason = null
    ): JobApplication {
        return DB::transaction(function () use ($application, $newStatus, $expectedCurrentStatus, $actor, $reason) {
            /** @var JobApplication $lockedApp */
            $lockedApp = JobApplication::where('id', $application->id)->lockForUpdate()->firstOrFail();

            if ($lockedApp->status->value !== $expectedCurrentStatus) {
                abort(409, __('The application status has changed since you loaded the page. Please refresh and try again.'));
            }

            if (! $lockedApp->status->canTransitionTo($newStatus, false)) {
                abort(422, __('Invalid status transition from :from to :to.', [
                    'from' => $lockedApp->status->label(),
                    'to' => $newStatus->label(),
                ]));
            }

            $priorStatus = $lockedApp->status;

            $lockedApp->statusHistories()->create([
                'actor_id' => $actor->id,
                'from_status' => $lockedApp->status,
                'to_status' => $newStatus,
                'reason' => $reason ? trim($reason) : null,
            ]);

            $lockedApp->status = $newStatus;
            $lockedApp->save();

            if (
                $priorStatus === JobApplicationStatus::InterviewScheduled
                && $newStatus !== JobApplicationStatus::InterviewScheduled
            ) {
                $timestamp = now();

                $lockedApp->interviews()
                    ->whereIn('status', InterviewStatus::activeValues())
                    ->update([
                        'status' => InterviewStatus::Cancelled->value,
                        'cancelled_at' => $timestamp,
                        'cancellation_reason' => 'Application moved to '.$newStatus->label().'.',
                        'updated_at' => $timestamp,
                    ]);
            }

            return $lockedApp;
        });
    }

    /**
     * Withdraw an application as the candidate.
     */
    public function withdrawAsCandidate(
        JobApplication $application,
        User $candidate,
        ?string $reason = null
    ): JobApplication {
        return DB::transaction(function () use ($application, $candidate, $reason) {
            /** @var JobApplication $lockedApp */
            $lockedApp = JobApplication::where('id', $application->id)->lockForUpdate()->firstOrFail();

            if ($lockedApp->status->isTerminal()) {
                abort(422, __('This application can no longer be withdrawn.'));
            }

            $lockedApp->statusHistories()->create([
                'actor_id' => $candidate->id,
                'from_status' => $lockedApp->status,
                'to_status' => JobApplicationStatus::Withdrawn,
                'reason' => $reason ? trim($reason) : null,
            ]);

            $lockedApp->status = JobApplicationStatus::Withdrawn;
            $lockedApp->save();

            $timestamp = now();

            $lockedApp->interviews()
                ->whereIn('status', InterviewStatus::activeValues())
                ->update([
                    'status' => InterviewStatus::Cancelled->value,
                    'cancelled_at' => $timestamp,
                    'cancellation_reason' => 'Application withdrawn.',
                    'updated_at' => $timestamp,
                ]);

            return $lockedApp;
        });
    }

    /**
     * Return the application to shortlisted after its last active interview is cancelled.
     *
     * This is a guarded, system-only transition and is intentionally not exposed in
     * the employer's manual transition list.
     */
    public function revertToShortlistedAfterLastInterviewCancellation(
        JobApplication $application,
        User $actor,
        string $reason
    ): JobApplication {
        return DB::transaction(function () use ($application, $actor, $reason) {
            /** @var JobApplication $lockedApp */
            $lockedApp = JobApplication::where('id', $application->id)->lockForUpdate()->firstOrFail();

            if ($lockedApp->status !== JobApplicationStatus::InterviewScheduled) {
                abort(409, __('The application status has changed since you loaded the page. Please refresh and try again.'));
            }

            $hasActiveInterviews = $lockedApp->interviews()
                ->whereIn('status', InterviewStatus::activeValues())
                ->exists();

            if ($hasActiveInterviews) {
                abort(422, __('The application still has active interview rounds.'));
            }

            $lockedApp->statusHistories()->create([
                'actor_id' => $actor->id,
                'from_status' => JobApplicationStatus::InterviewScheduled,
                'to_status' => JobApplicationStatus::Shortlisted,
                'reason' => trim($reason),
            ]);

            $lockedApp->status = JobApplicationStatus::Shortlisted;
            $lockedApp->save();

            return $lockedApp;
        });
    }
}
