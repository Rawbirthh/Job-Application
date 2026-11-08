<?php

namespace App\Http\Controllers\Candidate;

use App\Enums\InterviewStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Candidate\RespondToInterviewRequest;
use App\Models\Interview;
use App\Services\InterviewService;
use App\Services\NotificationService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;

class InterviewController extends Controller
{
    use AuthorizesRequests;

    /**
     * Respond to an interview round as the applying candidate.
     */
    public function respond(
        RespondToInterviewRequest $request,
        Interview $interview,
        InterviewService $interviewService,
        NotificationService $notificationService
    ): RedirectResponse {
        $this->authorize('respond', $interview);

        $responded = $interviewService->respond(
            $interview,
            $request->user(),
            $request->respondData()
        );

        if ($responded->status === InterviewStatus::RescheduleRequested) {
            $notificationService->interviewRescheduleRequested($responded);
        } else {
            $notificationService->interviewResponded($responded);
        }

        return Redirect::back()->with('success', __('Your interview response has been recorded.'));
    }
}
