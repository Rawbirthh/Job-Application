<?php

namespace App\Http\Controllers\Candidate;

use App\Http\Controllers\Controller;
use App\Http\Requests\Candidate\StoreJobApplicationRequest;
use App\Models\CandidateResume;
use App\Models\Job as JobPosting;
use App\Models\JobApplication;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
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

        $application = JobApplication::create([
            'job_posting_id' => $job->id,
            'user_id' => $user->id,
            'candidate_resume_id' => $request->validated('candidate_resume_id'),
            'cover_letter' => $request->validated('cover_letter'),
            'status' => 'submitted',
        ]);

        $notificationService->applicationSubmitted($application);
        $notificationService->newJobApplication($application);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Your application has been submitted successfully.'),
        ]);

        return redirect()->route('jobs.show', $job);
    }
}
