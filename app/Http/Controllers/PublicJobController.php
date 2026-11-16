<?php

namespace App\Http\Controllers;

use App\Enums\EmployerStatus;
use App\Models\EmploymentType;
use App\Models\ExperienceLevel;
use App\Models\Job;
use App\Models\JobApplication;
use App\Models\JobCategory;
use App\Models\User;
use App\Models\WorkplaceArrangement;
use App\Services\JobSearchService;
use Inertia\Inertia;
use Inertia\Response;

class PublicJobController extends Controller
{
    public function index(JobSearchService $searchService): Response
    {
        $filters = $searchService->filtersFromRequest();

        $query = Job::query()
            ->with([
                'company:id,name,slug,logo_path',
                'category:id,name,slug',
                'employmentType:id,name,slug',
                'workplaceArrangement:id,name,slug',
                'experienceLevel:id,name,slug',
                'payPeriod:id,name,slug',
            ])
            ->select([
                'id',
                'company_id',
                'category_id',
                'employment_type_id',
                'workplace_arrangement_id',
                'experience_level_id',
                'title',
                'location',
                'salary_min',
                'salary_max',
                'salary_currency',
                'salary_negotiable',
                'pay_period_id',
                'application_deadline',
                'skills',
                // Full text fields: rendered in the split-view preview panel.
                'description',
                'responsibilities',
                'requirements',
                'created_at',
            ]);

        $searchService->visibility($query);
        $searchService->apply($query, $filters);

        // Order by created_at with an id tiebreak: identical timestamps are
        // common (seeded rows), and a non-unique cursor column would skip or
        // duplicate rows between pages. The existing
        // job_postings_status_created_at_index covers the range + sort.
        $jobs = $query
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->cursorPaginate(12)
            ->withQueryString();

        /** @var User|null $user */
        $user = request()->user();
        $savedJobIds = $user && $user->hasRole('job_seeker')
            ? $user->savedJobs()->pluck('job_posting_id')->all()
            : [];

        return Inertia::render('jobs/index', [
            'savedJobIds' => $savedJobIds,
            // Infinite scroll: merged with client-side data on partial reloads;
            // the React <InfiniteScroll data="jobs"> triggers the next page.
            'jobs' => Inertia::scroll($jobs->through(fn (Job $job): array => [
                'id' => $job->id,
                'title' => $job->title,
                'location' => $job->location,
                'min_salary' => $job->salary_min,
                'max_salary' => $job->salary_max,
                'salary_currency' => $job->salary_currency,
                'salary_negotiable' => $job->salary_negotiable,
                // Raw Carbon, not an ISO string: the cursor paginator reads the
                // ORDER BY columns out of the transformed items, and Carbon's
                // __toString() gives a bindable 'Y-m-d H:i:s' datetime. The
                // JSON payload still serializes it to ISO-8601 for the client.
                'created_at' => $job->created_at,
                'description' => $job->description,
                'responsibilities' => $job->responsibilities,
                'requirements' => $job->requirements,
                'skills' => array_slice(array_values(array_filter(
                    is_array($job->skills) ? $job->skills : [],
                    'is_string',
                )), 0, 12),
                'application_deadline' => $job->application_deadline?->toIso8601String(),
                'company' => $job->company ? [
                    'id' => $job->company->id,
                    'name' => $job->company->name,
                    'slug' => $job->company->slug,
                    'logo_url' => $job->company->logo_url,
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
                'experience_level' => $job->experienceLevel ? [
                    'id' => $job->experienceLevel->id,
                    'name' => $job->experienceLevel->name,
                ] : null,
                'pay_period' => $job->payPeriod ? [
                    'id' => $job->payPeriod->id,
                    'name' => $job->payPeriod->name,
                ] : null,
            ])),
            'categories' => JobCategory::query()->active()->select(['id', 'name'])->orderBy('name')->get(),
            'employmentTypes' => EmploymentType::query()->active()->select(['id', 'name'])->orderBy('sort_order')->get(),
            'workplaceArrangements' => WorkplaceArrangement::query()->active()->select(['id', 'name'])->orderBy('name')->get(),
            'experienceLevels' => ExperienceLevel::query()->active()->select(['id', 'name'])->orderBy('sort_order')->get(),
            'filters' => [
                'search' => $filters['search'] !== '' ? $filters['search'] : null,
                'location' => $filters['location'] !== '' ? $filters['location'] : null,
                'category' => $filters['category'] > 0 ? $filters['category'] : null,
                'employment_type' => $filters['employment_type'] > 0 ? $filters['employment_type'] : null,
                'workplace_arrangement' => $filters['workplace_arrangement'] > 0 ? $filters['workplace_arrangement'] : null,
                'experience_level' => $filters['experience_level'] > 0 ? $filters['experience_level'] : null,
            ],
        ]);
    }

    public function show(Job $job): Response
    {
        // Visibility check: only published jobs from active companies with approved owners are viewable
        if (
            $job->status !== 'published' ||
            ! $job->company ||
            ! $job->company->is_active ||
            $job->company->owner->status !== EmployerStatus::Approved
        ) {
            abort(404);
        }

        // Eager load relationships
        $job->load([
            'company:id,name,slug,logo_path,overview,website,location,industry,size_band',
            'category:id,name',
            'employmentType:id,name',
            'workplaceArrangement:id,name',
            'experienceLevel:id,name',
            'payPeriod:id,name',
        ]);

        /** @var User|null $user */
        $user = request()->user();
        $isSaved = $user && $user->hasRole('job_seeker')
            ? $user->savedJobs()->where('job_posting_id', $job->id)->exists()
            : false;
        $hasApplied = $user && $user->hasRole('job_seeker')
            ? JobApplication::query()
                ->where('user_id', $user->id)
                ->where('job_posting_id', $job->id)
                ->exists()
            : false;

        return Inertia::render('jobs/show', [
            'job' => [
                'id' => $job->id,
                'title' => $job->title,
                'description' => $job->description,
                'responsibilities' => $job->responsibilities,
                'requirements' => $job->requirements,
                'location' => $job->location,
                'skills' => $job->skills ?? [],
                'salary_min' => $job->salary_min,
                'salary_max' => $job->salary_max,
                'salary_currency' => $job->salary_currency,
                'salary_negotiable' => $job->salary_negotiable,
                'application_deadline' => $job->application_deadline?->toIso8601String(),
                'published_at' => $job->created_at?->toIso8601String(),
                'is_saved' => $isSaved,
                'has_applied' => $hasApplied,
                'company' => $job->company ? [
                    'id' => $job->company->id,
                    'name' => $job->company->name,
                    'slug' => $job->company->slug,
                    'logo_url' => $job->company->logo_url,
                    'overview' => $job->company->overview,
                    'website' => $job->company->website,
                    'location' => $job->company->location,
                    'industry' => $job->company->industry,
                    'size_band' => $job->company->size_band,
                ] : null,
                'category' => $job->category ? ['id' => $job->category->id, 'name' => $job->category->name] : null,
                'employment_type' => $job->employmentType ? ['id' => $job->employmentType->id, 'name' => $job->employmentType->name] : null,
                'workplace_arrangement' => $job->workplaceArrangement ? ['id' => $job->workplaceArrangement->id, 'name' => $job->workplaceArrangement->name] : null,
                'experience_level' => $job->experienceLevel ? ['id' => $job->experienceLevel->id, 'name' => $job->experienceLevel->name] : null,
                'pay_period' => $job->payPeriod ? ['id' => $job->payPeriod->id, 'name' => $job->payPeriod->name] : null,
            ],
        ]);
    }
}
