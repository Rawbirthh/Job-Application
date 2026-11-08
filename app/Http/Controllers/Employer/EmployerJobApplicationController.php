<?php

namespace App\Http\Controllers\Employer;

use App\Enums\JobApplicationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Employer\TransitionApplicationStatusRequest;
use App\Models\CandidateEducation;
use App\Models\CandidateExperience;
use App\Models\Interview;
use App\Models\Job;
use App\Models\JobApplication;
use App\Models\JobApplicationStatusHistory;
use App\Services\ApplicationStatusTransitionService;
use App\Services\NotificationService;
use DateTimeZone;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EmployerJobApplicationController extends Controller
{
    use AuthorizesRequests;

    /**
     * Display a paginated listing of applicants for a specific job.
     */
    public function index(Request $request, Job $job): Response
    {
        $this->authorize('viewAnyForJob', [JobApplication::class, $job]);

        $job->loadMissing('company:id,name,slug');

        $applications = JobApplication::query()
            ->where('job_posting_id', $job->id)
            ->with([
                'user:id,name,email',
                'user.candidateProfile:id,user_id,headline,location',
                'candidateResume:id,original_filename,mime_type,size',
            ])
            ->latest('created_at')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('employer/jobs/applicants/index', [
            'job' => [
                'id' => $job->id,
                'title' => $job->title,
                'status' => $job->status,
                'location' => $job->location,
                'company' => $job->company ? [
                    'id' => $job->company->id,
                    'name' => $job->company->name,
                    'slug' => $job->company->slug,
                ] : null,
            ],
            'applications' => $applications->through(function (JobApplication $application): array {
                $user = $application->user;
                $profile = $user->candidateProfile;

                return [
                    'id' => $application->id,
                    'status' => $application->status->value,
                    'applied_at' => $application->created_at?->toIso8601String(),
                    'candidate' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'headline' => $profile?->headline,
                        'location' => $profile?->location,
                    ],
                    'has_resume' => (bool) $application->candidate_resume_id,
                    'has_cover_letter' => ! empty($application->cover_letter),
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

    /**
     * Display the details of an application for the employer.
     */
    public function show(JobApplication $application): Response
    {
        $this->authorize('viewAsEmployer', $application);

        $application->load([
            'jobPosting.company:id,name,slug,logo_path',
            'user:id,name,email',
            'user.candidateProfile.experiences',
            'user.candidateProfile.educations',
            'candidateResume',
            'statusHistories.actor:id,name',
            'screeningAnswers.jobScreeningQuestion',
        ]);

        $job = $application->jobPosting;
        $user = $application->user;
        $profile = $user->candidateProfile;

        $interviewPaginator = $application->interviews()
            ->select([
                'id',
                'job_application_id',
                'scheduled_by_id',
                'scheduled_at',
                'duration_minutes',
                'timezone',
                'mode',
                'location_details',
                'candidate_instructions',
                'internal_notes',
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
            'internal_notes' => $interview->internal_notes,
            'responded_at' => $interview->responded_at?->toIso8601String(),
            'requested_scheduled_at' => $interview->requested_scheduled_at?->toIso8601String(),
            'requested_timezone' => $interview->requested_timezone,
            'response_note' => $interview->response_note,
            'cancelled_at' => $interview->cancelled_at?->toIso8601String(),
            'cancellation_reason' => $interview->cancellation_reason,
            'updated_at' => $interview->updated_at?->toIso8601String(),
            'can_manage' => $interview->status->isActive(),
        ])->all();

        $screeningAnswers = $application->screeningAnswers
            ->sortBy(fn ($a) => $a->jobScreeningQuestion->sort_order)
            ->values()
            ->map(fn ($a): array => [
                'question_id' => $a->job_screening_question_id,
                'question_label' => $a->jobScreeningQuestion?->label,
                'question_type' => $a->jobScreeningQuestion?->type?->value,
                'options' => $a->jobScreeningQuestion?->options,
                'answer_text' => $a->answer_text,
                'answer_boolean' => $a->answer_boolean,
                'selected_option' => $a->selected_option,
            ])->all();

        return Inertia::render('employer/applications/show', [
            'application' => [
                'id' => $application->id,
                'status' => $application->status->value,
                'applied_at' => $application->created_at?->toIso8601String(),
                'cover_letter' => $application->cover_letter,
                'resume' => $application->candidateResume ? [
                    'id' => $application->candidateResume->id,
                    'original_filename' => $application->candidateResume->original_filename,
                    'mime_type' => $application->candidateResume->mime_type,
                    'size' => $application->candidateResume->size,
                ] : null,
            ],
            'allowed_transitions' => array_map(
                fn (JobApplicationStatus $s): array => [
                    'value' => $s->value,
                    'label' => $s->label(),
                ],
                $application->status->allowedEmployerTransitions()
            ),
            'status_histories' => $application->statusHistories->map(fn (JobApplicationStatusHistory $history): array => [
                'id' => $history->id,
                'from_status' => $history->from_status->value,
                'from_status_label' => $history->from_status->label(),
                'to_status' => $history->to_status->value,
                'to_status_label' => $history->to_status->label(),
                'reason' => $history->reason,
                'actor_name' => $history->actor->name ?? __('System'),
                'created_at' => $history->created_at?->toIso8601String(),
            ])->values()->all(),
            'candidate' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'headline' => $profile?->headline,
                'summary' => $profile?->summary,
                'location' => $profile?->location,
                'skills' => $profile ? ($profile->skills ?? []) : [],
                'experiences' => $profile?->experiences->map(fn (CandidateExperience $exp): array => [
                    'id' => $exp->id,
                    'job_title' => $exp->job_title,
                    'company_name' => $exp->company_name,
                    'location' => $exp->location,
                    'start_date' => $exp->start_date?->toDateString(),
                    'end_date' => $exp->end_date?->toDateString(),
                    'is_current' => (bool) $exp->is_current,
                    'description' => $exp->description,
                ])->values()->all() ?? [],
                'educations' => $profile?->educations->map(fn (CandidateEducation $edu): array => [
                    'id' => $edu->id,
                    'institution' => $edu->institution,
                    'degree' => $edu->degree,
                    'field_of_study' => $edu->field_of_study,
                    'start_date' => $edu->start_date?->toDateString(),
                    'end_date' => $edu->end_date?->toDateString(),
                    'description' => $edu->description,
                ])->values()->all() ?? [],
            ],
            'job' => [
                'id' => $job->id,
                'title' => $job->title,
                'status' => $job->status,
                'company' => $job->company ? [
                    'id' => $job->company->id,
                    'name' => $job->company->name,
                    'slug' => $job->company->slug,
                ] : null,
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
            'interview_eligible' => in_array($application->status, [
                JobApplicationStatus::Shortlisted,
                JobApplicationStatus::InterviewScheduled,
            ], true),
            'timezones' => DateTimeZone::listIdentifiers(),
            'screening_answers' => $screeningAnswers,
        ]);
    }

    /**
     * Update the status of an application.
     */
    public function updateStatus(
        TransitionApplicationStatusRequest $request,
        JobApplication $application,
        ApplicationStatusTransitionService $transitionService,
        NotificationService $notificationService
    ): RedirectResponse {
        $from = $application->status;
        $newStatus = JobApplicationStatus::from($request->validated('new_status'));

        $updated = $transitionService->transitionAsEmployer(
            $application,
            $newStatus,
            $request->validated('current_status'),
            $request->user(),
            $request->validated('reason')
        );

        $notificationService->applicationStatusChanged($updated, $from, $newStatus);

        return Redirect::back()->with('success', __('Application status updated successfully.'));
    }

    /**
     * Download the resume attached to an application.
     */
    public function downloadResume(JobApplication $application): StreamedResponse
    {
        $this->authorize('viewAsEmployer', $application);

        $resume = $application->candidateResume;

        if (! $resume || ! Storage::disk('local')->exists($resume->path)) {
            abort(404, __('Resume file not found.'));
        }

        return Storage::disk('local')->download(
            $resume->path,
            $resume->original_filename
        );
    }
}
