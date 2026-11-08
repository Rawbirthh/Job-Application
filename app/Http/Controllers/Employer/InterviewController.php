<?php

namespace App\Http\Controllers\Employer;

use App\Enums\JobApplicationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Employer\CancelInterviewRequest;
use App\Http\Requests\Employer\StoreInterviewRequest;
use App\Http\Requests\Employer\UpdateInterviewRequest;
use App\Models\Interview;
use App\Models\JobApplication;
use App\Services\InterviewService;
use App\Services\NotificationService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;

class InterviewController extends Controller
{
    use AuthorizesRequests;

    /**
     * Schedule a new interview round for an application.
     */
    public function store(
        StoreInterviewRequest $request,
        JobApplication $application,
        InterviewService $interviewService,
        NotificationService $notificationService
    ): RedirectResponse {
        $this->authorize('createForApplication', [Interview::class, $application]);

        $interview = $interviewService->schedule(
            $application,
            $request->user(),
            $request->scheduleData()
        );

        $notificationService->interviewScheduled($interview);

        return Redirect::back()->with('success', __('Interview scheduled successfully.'));
    }

    /**
     * Reschedule an active interview round.
     */
    public function update(
        UpdateInterviewRequest $request,
        Interview $interview,
        InterviewService $interviewService,
        NotificationService $notificationService
    ): RedirectResponse {
        $this->authorize('manage', $interview);

        $updated = $interviewService->update(
            $interview,
            $request->user(),
            $request->updateData()
        );

        $notificationService->interviewRescheduled($updated);

        return Redirect::back()->with('success', __('Interview rescheduled successfully.'));
    }

    /**
     * Cancel an active interview round.
     */
    public function cancel(
        CancelInterviewRequest $request,
        Interview $interview,
        InterviewService $interviewService,
        NotificationService $notificationService
    ): RedirectResponse {
        $this->authorize('manage', $interview);

        $cancelled = $interviewService->cancel(
            $interview,
            $request->user(),
            $request->cancelData()
        );

        $application = $cancelled->jobApplication()->first();
        $applicationReverted = $application?->status === JobApplicationStatus::Shortlisted;

        $notificationService->interviewCancelled($cancelled, $applicationReverted);

        return Redirect::back()->with('success', __('Interview cancelled successfully.'));
    }
}
