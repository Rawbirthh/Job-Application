<?php

namespace App\Http\Controllers\Candidate;

use App\Http\Controllers\Controller;
use App\Http\Requests\Candidate\StoreJobApplicationRequest;
use App\Models\CandidateResume;
use App\Models\Job as JobPosting;
use App\Models\JobApplication;
use App\Models\JobApplicationAnswer;
use App\Models\JobScreeningQuestion;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class JobApplicationController extends Controller
{
    use AuthorizesRequests;

    /**
     * Show the job application form.
     */
    public function create(JobPosting $job): Response|RedirectResponse
    {
        $this->authorize('create', JobApplication::class);

        if ($job->status !== 'published' || ($job->application_deadline && $job->application_deadline->isPast())) {
            return redirect()->route('jobs.show', $job)->with('error', __('This job is no longer accepting applications.'));
        }

        /** @var User $user */
        $user = request()->user();

        $alreadyApplied = JobApplication::query()
            ->where('user_id', $user->id)
            ->where('job_posting_id', $job->id)
            ->exists();

        if ($alreadyApplied) {
            return redirect()->route('jobs.show', $job)->with('info', __('You have already applied to this job.'));
        }

        $resumes = CandidateResume::query()
            ->where('user_id', $user->id)
            ->select(['id', 'original_filename', 'created_at'])
            ->latest('created_at')
            ->get();

        $job->load([
            'company:id,name,slug,logo_path',
            'employmentType:id,name',
            'workplaceArrangement:id,name',
        ]);

        $screeningQuestions = $job->screeningQuestions()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get(['id', 'type', 'label', 'options', 'is_required'])
            ->map(fn ($q) => [
                'id' => $q->id,
                'type' => $q->type->value,
                'label' => $q->label,
                'options' => $q->options,
                'is_required' => $q->is_required,
            ])->values()->all();

        return Inertia::render('candidate/applications/create', [
            'job' => [
                'id' => $job->id,
                'title' => $job->title,
                'location' => $job->location,
                'salary_min' => $job->salary_min,
                'salary_max' => $job->salary_max,
                'salary_currency' => $job->salary_currency,
                'company' => $job->company ? [
                    'id' => $job->company->id,
                    'name' => $job->company->name,
                    'logo_path' => $job->company->logo_path,
                ] : null,
                'employment_type' => $job->employmentType ? [
                    'name' => $job->employmentType->name,
                ] : null,
                'workplace_arrangement' => $job->workplaceArrangement ? [
                    'name' => $job->workplaceArrangement->name,
                ] : null,
            ],
            'resumes' => $resumes->map(fn ($r) => [
                'id' => $r->id,
                'original_filename' => $r->original_filename,
                'created_at' => $r->created_at?->toIso8601String(),
            ]),
            'alreadyApplied' => $alreadyApplied,
            'screening_questions' => $screeningQuestions,
        ]);
    }

    /**
     * Store a newly created job application.
     */
    public function store(
        StoreJobApplicationRequest $request,
        JobPosting $job,
        NotificationService $notificationService
    ): RedirectResponse {
        $this->authorize('create', JobApplication::class);

        /** @var User $user */
        $user = $request->user();

        $application = DB::transaction(function () use ($request, $job, $user): JobApplication {
            $application = JobApplication::create([
                'job_posting_id' => $job->id,
                'user_id' => $user->id,
                'candidate_resume_id' => $request->validated('candidate_resume_id'),
                'cover_letter' => $request->validated('cover_letter'),
                'status' => 'submitted',
            ]);

            foreach ($request->answersData() as $answer) {
                /** @var JobScreeningQuestion|null $question */
                $question = JobScreeningQuestion::query()
                    ->where('job_posting_id', $job->id)
                    ->where('is_active', true)
                    ->find($answer['question_id']);

                if (! $question) {
                    continue;
                }

                // Only persist if there is some answer content
                $hasContent = ($answer['answer_text'] !== null && $answer['answer_text'] !== '')
                    || $answer['answer_boolean'] !== null
                    || ($answer['selected_option'] !== null && $answer['selected_option'] !== '');

                if (! $hasContent) {
                    // optional unanswered question: skip row
                    continue;
                }

                JobApplicationAnswer::create([
                    'job_application_id' => $application->id,
                    'job_screening_question_id' => $question->id,
                    'answer_text' => $answer['answer_text'],
                    'answer_boolean' => $answer['answer_boolean'],
                    'selected_option' => $answer['selected_option'],
                ]);
            }

            return $application;
        });

        $notificationService->applicationSubmitted($application);
        $notificationService->newJobApplication($application);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Your application has been submitted successfully.'),
        ]);

        return redirect()->route('jobs.show', $job);
    }
}
