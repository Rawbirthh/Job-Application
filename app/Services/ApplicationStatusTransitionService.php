<?php

namespace App\Services;

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

            $lockedApp->statusHistories()->create([
                'actor_id' => $actor->id,
                'from_status' => $lockedApp->status,
                'to_status' => $newStatus,
                'reason' => $reason ? trim($reason) : null,
            ]);

            $lockedApp->status = $newStatus;
            $lockedApp->save();

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

            return $lockedApp;
        });
    }
}
