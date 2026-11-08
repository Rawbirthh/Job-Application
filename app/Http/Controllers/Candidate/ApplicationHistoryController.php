<?php

namespace App\Http\Controllers\Candidate;

use App\Enums\InterviewStatus;
use App\Enums\JobApplicationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Candidate\WithdrawApplicationRequest;
use App\Models\Interview;
use App\Models\JobApplication;
use App\Models\JobApplicationStatusHistory;
use App\Models\User;
use App\Services\ApplicationStatusTransitionService;
use App\Services\NotificationService;
use DateTimeZone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

class ApplicationHistoryController extends Controller
{
    /**
     * Display a paginated listing of the candidate's applications.
     */
    public function index(): Response
    {
        /** @var User $user */
        $user = request()->user();

        $applications = JobApplication::query()
            ->where('user_id', $user->id)
            ->with([
                'jobPosting' => function ($query): void {
                    $query->select([
                        'id',
                        'title',
                        'location',
                        'salary_min',
                        'salary_max',
                        'salary_currency',
                        'salary_negotiable',
                        'status',
                        'application_deadline',
                        'company_id',
                    ])
                        ->with([
                            'company' => function ($query): void {
                                $query->select(['id', 'name', 'slug', 'logo_path']);
                            },
                            'category:id,name,slug',
                            'employmentType:id,name,slug',
                            'workplaceArrangement:id,name,slug',
                            'experienceLevel:id,name,slug',
                            'payPeriod:id,name,slug',
                        ]);
                },
                'jobPosting.company:id,name,slug,logo_path',
            ])
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('candidate/applications/index', [
            'applications' => $applications->through(function (JobApplication $application): array {
                $job = $application->jobPosting;

                return [
                    'id' => $application->id,
                    'job_id' => $job->id,
                    'job_title' => $job->title,
                    'job_status' => $job->status,
                    'company' => $job->company ? [
                        'id' => $job->company->id,
                        'name' => $job->company->name,
                        'logo_path' => $job->company->logo_path,
                    ] : null,
                    'location' => $job->location,
                    'employment_type' => $job->employmentType ? $job->employmentType->name : null,
                    'workplace_arrangement' => $job->workplaceArrangement ? $job->workplaceArrangement->name : null,
                    'salary_min' => $job->salary_min,
                    'salary_max' => $job->salary_max,
                    'salary_currency' => $job->salary_currency,
                    'salary_negotiable' => $job->salary_negotiable,
                    'application_deadline' => $job->application_deadline?->toDateString(),
                    'status' => $application->status->value,
                    'applied_at' => $application->created_at?->toIso8601String(),
                    'cover_letter' => $application->cover_letter,
                ];
            }),
            'pagination' => [
                'current_page' => $applications->currentPage(),
                'last_page' => $applications->lastPage(),
                'per_page' => $applications->perPage(),
                'total' => $applications->total(),
                'prev_page_url' => $applications->previousPageUrl(),
                'next_page_url' => $applications->nextPageUrl(),
            ],
        ]);
    }

    public function show(JobApplication $application): Response
    {
        /** @var User $user */
        $user = request()->user();

        $this->authorize('view', $application);

        $application->load([
            'jobPosting.company',
            'jobPosting.employmentType',
            'jobPosting.workplaceArrangement',
            'jobPosting.experienceLevel',
            'jobPosting.payPeriod',
            'candidateResume',
            'statusHistories.actor:id,name',
        ]);

        $job = $application->jobPosting;
        $company = $job->company;

        $interviewPaginator = $application->interviews()
            ->select([
                'id',
                'job_application_id',
                'scheduled_at',
                'duration_minutes',
                'timezone',
                'mode',
                'location_details',
                'candidate_instructions',
                'status',
                'responded_at',
                'requested_scheduled_at',
                'requested_timezone',
                'response_note',
                'cancelled_at',
                'cancellation_reason',
                'created_at',
                'updated_at',
            ])
            ->latest('created_at')
            ->paginate(10, ['*'], 'interview_page')
            ->withQueryString();

        $interviews = $interviewPaginator->getCollection()->map(fn (Interview $interview): array => [
            'id' => $interview->id,
            'status' => $interview->status->value,
            'status_label' => $interview->status->label(),
            'mode' => $interview->mode->value,
            'mode_label' => $interview->mode->label(),
            'scheduled_at' => $interview->scheduled_at->toIso8601String(),
            'timezone' => $interview->timezone,
            'duration_minutes' => $interview->duration_minutes,
            'location_details' => $interview->location_details,
            'candidate_instructions' => $interview->candidate_instructions,
            'responded_at' => $interview->responded_at?->toIso8601String(),
            'requested_scheduled_at' => $interview->requested_scheduled_at?->toIso8601String(),
            'requested_timezone' => $interview->requested_timezone,
            'response_note' => $interview->response_note,
            'cancelled_at' => $interview->cancelled_at?->toIso8601String(),
            'cancellation_reason' => $interview->cancellation_reason,
            'updated_at' => $interview->updated_at?->toIso8601String(),
            'can_respond' => $interview->status === InterviewStatus::Scheduled
                && $application->status === JobApplicationStatus::InterviewScheduled,
        ])->all();

        return Inertia::render('candidate/applications/show', [
            'application' => [
                'id' => $application->id,
                'status' => $application->status->value,
                'applied_at' => $application->created_at?->toIso8601String(),
                'cover_letter' => $application->cover_letter,
                'resume' => $application->candidateResume
                    ? [
                        'id' => $application->candidateResume->id,
                        'original_filename' => $application->candidateResume->original_filename,
                        'mime_type' => $application->candidateResume->mime_type,
                        'size' => $application->candidateResume->size,
                    ]
                    : null,
            ],
            'can_withdraw' => ! $application->status->isTerminal(),
            'status_histories' => $application->statusHistories->map(fn (JobApplicationStatusHistory $history): array => [
                'id' => $history->id,
                'from_status' => $history->from_status->value,
                'from_status_label' => $history->from_status->label(),
                'to_status' => $history->to_status->value,
                'to_status_label' => $history->to_status->label(),
                'reason' => $history->reason,
                'created_at' => $history->created_at?->toIso8601String(),
            ])->values()->all(),
            'job' => [
                'id' => $job->id,
                'title' => $job->title,
                'status' => $job->status,
                'description' => $job->description,
                'responsibilities' => $job->responsibilities,
                'requirements' => $job->requirements,
                'location' => $job->location,
                'salary_min' => $job->salary_min,
                'salary_max' => $job->salary_max,
                'salary_currency' => $job->salary_currency,
                'salary_negotiable' => $job->salary_negotiable,
                'application_deadline' => $job->application_deadline?->toIso8601String(),
                'company' => $company ? [
                    'id' => $company->id,
                    'name' => $company->name,
                    'logo_path' => $company->logo_path,
                    'overview' => $company->overview,
                    'website' => $company->website,
                    'location' => $company->location,
                    'industry' => $company->industry,
                    'size_band' => $company->size_band,
                ] : null,
                'employment_type' => $job->employmentType ? ['name' => $job->employmentType->name] : null,
                'workplace_arrangement' => $job->workplaceArrangement ? ['name' => $job->workplaceArrangement->name] : null,
                'experience_level' => $job->experienceLevel ? ['name' => $job->experienceLevel->name] : null,
                'pay_period' => $job->payPeriod ? ['name' => $job->payPeriod->name] : null,
            ],
            'interviews' => $interviews,
            'interview_pagination' => [
                'current_page' => $interviewPaginator->currentPage(),
                'last_page' => $interviewPaginator->lastPage(),
                'per_page' => $interviewPaginator->perPage(),
                'total' => $interviewPaginator->total(),
                'prev_page_url' => $interviewPaginator->previousPageUrl(),
                'next_page_url' => $interviewPaginator->nextPageUrl(),
            ],
            'timezones' => DateTimeZone::listIdentifiers(),
        ]);
    }

    /**
     * Withdraw an application as the candidate.
     */
    public function withdraw(
        WithdrawApplicationRequest $request,
        JobApplication $application,
        ApplicationStatusTransitionService $transitionService,
        NotificationService $notificationService
    ): RedirectResponse {
        $transitionService->withdrawAsCandidate(
            $application,
            $request->user(),
            $request->validated('reason')
        );

        $notificationService->applicationWithdrawn($application);

        return Redirect::back()->with('success', __('Application withdrawn successfully.'));
    }
}
