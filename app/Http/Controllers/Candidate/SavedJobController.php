<?php

namespace App\Http\Controllers\Candidate;

use App\Http\Controllers\Controller;
use App\Http\Requests\Candidate\SaveJobRequest;
use App\Models\Job as JobPosting;
use App\Models\SavedJob;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class SavedJobController extends Controller
{
    use AuthorizesRequests;

    /**
     * Display a paginated listing of the candidate's saved jobs.
     */
    public function index(): Response
    {
        $this->authorize('viewAny', SavedJob::class);

        /** @var User $user */
        $user = request()->user();

        $savedJobs = SavedJob::query()
            ->where('user_id', $user->id)
            ->with([
                'jobPosting' => function (BelongsTo $query): void {
                    $query->select([
                        'id',
                        'company_id',
                        'category_id',
                        'employment_type_id',
                        'workplace_arrangement_id',
                        'title',
                        'location',
                        'salary_min',
                        'salary_max',
                        'salary_currency',
                        'salary_negotiable',
                        'status',
                        'application_deadline',
                    ])->with([
                        'company:id,name,slug,logo_path',
                        'category:id,name,slug',
                        'employmentType:id,name,slug',
                        'workplaceArrangement:id,name,slug',
                    ]);
                },
            ])
            ->select(['id', 'user_id', 'job_posting_id', 'created_at'])
            ->latest('created_at')
            ->paginate(10)
            ->withQueryString()
            ->through(function (SavedJob $savedJob): array {
                $job = $savedJob->jobPosting;

                return [
                    'id' => $savedJob->id,
                    'job_posting_id' => $savedJob->job_posting_id,
                    'created_at' => $savedJob->created_at?->toIso8601String(),
                    'job' => [
                        'id' => $job->id,
                        'title' => $job->title,
                        'location' => $job->location,
                        'status' => $job->status,
                        'salary_min' => $job->salary_min,
                        'salary_max' => $job->salary_max,
                        'salary_currency' => $job->salary_currency,
                        'salary_negotiable' => $job->salary_negotiable,
                        'application_deadline' => $job->application_deadline?->toDateString(),
                        'company' => $job->company ? [
                            'id' => $job->company->id,
                            'name' => $job->company->name,
                            'slug' => $job->company->slug,
                            'logo_path' => $job->company->logo_path,
                        ] : null,
                        'category' => $job->category ? [
                            'id' => $job->category->id,
                            'name' => $job->category->name,
                        ] : null,
                        'employment_type' => $job->employmentType ? [
                            'id' => $job->employmentType->id,
                            'name' => $job->employmentType->name,
                        ] : null,
                        'workplace_arrangement' => $job->workplaceArrangement ? [
                            'id' => $job->workplaceArrangement->id,
                            'name' => $job->workplaceArrangement->name,
                        ] : null,
                    ],
                ];
            });

        return Inertia::render('candidate/saved-jobs/index', [
            'savedJobs' => $savedJobs,
        ]);
    }

    /**
     * Save a job posting for the candidate.
     */
    public function store(SaveJobRequest $request): RedirectResponse
    {
        $this->authorize('create', SavedJob::class);

        /** @var User $user */
        $user = $request->user();

        $jobPostingId = (int) $request->validated('job_posting_id');

        SavedJob::firstOrCreate([
            'user_id' => $user->id,
            'job_posting_id' => $jobPostingId,
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Job saved successfully.'),
        ]);

        return redirect()->route('candidate.saved-jobs.index');
    }

    /**
     * Remove a saved job posting for the candidate.
     */
    public function destroy(JobPosting $jobPosting): RedirectResponse
    {
        /** @var User $user */
        $user = request()->user();

        $savedJob = SavedJob::query()
            ->where('user_id', $user->id)
            ->where('job_posting_id', $jobPosting->id)
            ->first();

        if ($savedJob !== null) {
            $this->authorize('delete', $savedJob);
            $savedJob->delete();
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Job removed from saved jobs.'),
        ]);

        return redirect()->route('candidate.saved-jobs.index');
    }
}
