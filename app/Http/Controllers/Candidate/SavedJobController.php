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
            ->through(fn (SavedJob $savedJob): array => [
                'id' => $savedJob->id,
                'job_posting_id' => $savedJob->job_posting_id,
                'created_at' => $savedJob->created_at?->toIso8601String(),
                'job' => $savedJob->jobPosting ? [
                    'id' => $savedJob->jobPosting->id,
                    'title' => $savedJob->jobPosting->title,
                    'location' => $savedJob->jobPosting->location,
                    'status' => $savedJob->jobPosting->status,
                    'salary_min' => $savedJob->jobPosting->salary_min,
                    'salary_max' => $savedJob->jobPosting->salary_max,
                    'salary_currency' => $savedJob->jobPosting->salary_currency,
                    'salary_negotiable' => $savedJob->jobPosting->salary_negotiable,
                    'application_deadline' => $savedJob->jobPosting->application_deadline?->toDateString(),
                    'company' => $savedJob->jobPosting->company ? [
                        'id' => $savedJob->jobPosting->company->id,
                        'name' => $savedJob->jobPosting->company->name,
                        'slug' => $savedJob->jobPosting->company->slug,
                        'logo_path' => $savedJob->jobPosting->company->logo_path,
                    ] : null,
                    'category' => $savedJob->jobPosting->category ? [
                        'id' => $savedJob->jobPosting->category->id,
                        'name' => $savedJob->jobPosting->category->name,
                    ] : null,
                    'employment_type' => $savedJob->jobPosting->employmentType ? [
                        'id' => $savedJob->jobPosting->employmentType->id,
                        'name' => $savedJob->jobPosting->employmentType->name,
                    ] : null,
                    'workplace_arrangement' => $savedJob->jobPosting->workplaceArrangement ? [
                        'id' => $savedJob->jobPosting->workplaceArrangement->id,
                        'name' => $savedJob->jobPosting->workplaceArrangement->name,
                    ] : null,
                ] : null,
            ]);

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
