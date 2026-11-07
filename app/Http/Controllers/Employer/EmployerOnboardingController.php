<?php

namespace App\Http\Controllers\Employer;

use App\Enums\EmployerStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Employer\StoreEmployerOnboardingRequest;
use App\Models\EmployerOnboardingRequest;
use App\Models\User;
use App\Services\Employer\EmployerOnboardingService;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EmployerOnboardingController extends Controller
{
    public function __construct(
        private readonly EmployerOnboardingService $employerOnboardingService,
        private readonly NotificationService $notificationService,
    ) {}

    /**
     * Display the authenticated user's employer onboarding state.
     */
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        $user->load([
            'employerOnboardingRequest' => function ($query): void {
                $query->select([
                    'id',
                    'user_id',
                    'company_name',
                    'company_website',
                    'job_title',
                    'message',
                    'submitted_at',
                    'reviewed_at',
                    'review_note',
                ]);
            },
        ]);

        $onboardingRequest = $user->employerOnboardingRequest;

        if ($onboardingRequest !== null) {
            $this->authorize('view', $onboardingRequest);
        }

        return Inertia::render('employer/onboarding', [
            'status' => $user->status?->value,
            'request' => $onboardingRequest === null ? null : [
                'company_name' => $onboardingRequest->company_name,
                'company_website' => $onboardingRequest->company_website,
                'job_title' => $onboardingRequest->job_title,
                'message' => $onboardingRequest->message,
                'submitted_at' => $onboardingRequest->submitted_at?->toIso8601String(),
                'reviewed_at' => $onboardingRequest->reviewed_at?->toIso8601String(),
                'review_note' => $onboardingRequest->review_note,
            ],
            'suspension_reason' => $user->status === EmployerStatus::Suspended
                ? $user->suspension_reason
                : null,
            'can_apply' => $user->status === null || $user->status === EmployerStatus::Rejected,
            'can_access_employer_dashboard' => $user->isApprovedEmployer(),
        ]);
    }

    /**
     * Submit or resubmit an employer onboarding request.
     */
    public function store(StoreEmployerOnboardingRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $this->authorize('create', EmployerOnboardingRequest::class);

        $onboardingRequest = $this->employerOnboardingService->submit($user, $request->validated());

        $this->notificationService->employerOnboardingSubmitted($user, $onboardingRequest);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Employer application submitted for review.',
        ]);

        return to_route('employer.onboarding.index');
    }
}
