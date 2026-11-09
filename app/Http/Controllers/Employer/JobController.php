<?php

namespace App\Http\Controllers\Employer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Employer\StoreJobRequest;
use App\Http\Requests\Employer\UpdateJobRequest;
use App\Models\Company;
use App\Models\EmploymentType;
use App\Models\ExperienceLevel;
use App\Models\Job;
use App\Models\JobCategory;
use App\Models\PayPeriod;
use App\Models\User;
use App\Models\WorkplaceArrangement;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class JobController extends Controller
{
    public function index(): Response
    {
        $this->authorize('create', Company::class);

        /** @var User $user */
        $user = request()->user();

        $companies = Company::query()
            ->forManagingUser($user)
            ->select(['id', 'name', 'slug'])
            ->orderBy('name')
            ->get();

        $companyId = request()->integer('company_id');
        $status = request()->string('status')->toString();
        $search = request()->string('search')->toString();

        $query = Job::query()
            ->forManagingUser($user)
            ->with([
                'company:id,name,slug',
                'category:id,name,slug',
                'employmentType:id,name,slug',
            ])
            ->latest();

        if ($companyId > 0) {
            $query->where('company_id', $companyId);
        }

        if ($status !== '' && $status !== 'all') {
            $query->where('status', $status);
        }

        if ($search !== '') {
            $query->where('title', 'like', "%{$search}%");
        }

        $jobs = $query->paginate(10)->withQueryString();

        return Inertia::render('employer/jobs/index', [
            'jobs' => $jobs->through(fn (Job $job): array => $this->jobPayload($job)),
            'companies' => $companies->map(fn (Company $c): array => [
                'id' => $c->id,
                'name' => $c->name,
                'slug' => $c->slug,
            ])->all(),
            'filters' => [
                'company_id' => $companyId > 0 ? $companyId : null,
                'status' => $status !== '' && $status !== 'all' ? $status : null,
                'search' => $search !== '' ? $search : null,
            ],
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Company::class);

        /** @var User $user */
        $user = request()->user();

        $companies = Company::query()
            ->forManagingUser($user)
            ->select(['id', 'name', 'slug'])
            ->orderBy('name')
            ->get()
            ->map(fn (Company $company): array => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
            ]);

        return Inertia::render('employer/jobs/create', [
            'companies' => $companies,
            'jobCategories' => $this->getActiveCategories(),
            'employmentTypes' => $this->getActiveEmploymentTypes(),
            'workplaceArrangements' => $this->getActiveWorkplaceArrangements(),
            'experienceLevels' => $this->getActiveExperienceLevels(),
            'payPeriods' => $this->getActivePayPeriods(),
        ]);
    }

    public function store(StoreJobRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->jobData();

        // Resolve company through managed scope to prevent IDOR
        /** @var Company $company */
        $company = Company::query()
            ->forManagingUser($user)
            ->findOrFail($validated['company_id']);

        $this->authorize('create', [Job::class, $company]);

        $job = new Job($validated);
        $job->company_id = $company->id;
        $job->status = 'draft';
        $job->save();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Job draft created.'),
        ]);

        return to_route('employer.jobs.edit', $job);
    }

    public function edit(Job $job): Response
    {
        $this->authorize('update', $job);

        $job->load([
            'company:id,name,slug',
            'category:id,name,slug',
            'employmentType:id,name,slug',
            'workplaceArrangement:id,name,slug',
            'experienceLevel:id,name,slug',
            'payPeriod:id,name,slug',
        ]);

        /** @var User $user */
        $user = request()->user();

        $companies = Company::query()
            ->forManagingUser($user)
            ->select(['id', 'name', 'slug'])
            ->orderBy('name')
            ->get()
            ->map(fn (Company $company): array => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
            ]);

        return Inertia::render('employer/jobs/edit', [
            'job' => $this->jobPayload($job),
            'companies' => $companies,
            'jobCategories' => $this->getActiveCategories(),
            'employmentTypes' => $this->getActiveEmploymentTypes(),
            'workplaceArrangements' => $this->getActiveWorkplaceArrangements(),
            'experienceLevels' => $this->getActiveExperienceLevels(),
            'payPeriods' => $this->getActivePayPeriods(),
        ]);
    }

    public function update(UpdateJobRequest $request, Job $job): RedirectResponse
    {
        $this->authorize('update', $job);

        $job->fill($request->jobData());
        if ($job->status === 'published') {
            $job->status = 'draft';
        }
        $job->save();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Job updated successfully.'),
        ]);

        return to_route('employer.jobs.edit', $job);
    }

    public function submit(Job $job, NotificationService $notificationService): RedirectResponse
    {
        $this->authorize('submit', $job);

        if (! in_array($job->status, ['draft', 'rejected', 'closed'], true)) {
            return back()->with('error', __('This job cannot be submitted for review.'));
        }

        $job->status = 'pending_review';
        $job->rejection_reason = null;
        $job->save();

        $notificationService->jobSubmittedForReview($job);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Job submitted for review successfully.'),
        ]);

        return to_route('employer.jobs.index');
    }

    public function close(Job $job): RedirectResponse
    {
        $this->authorize('close', $job);

        $job->status = 'closed';
        $job->save();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Job closed successfully.'),
        ]);

        return to_route('employer.jobs.index');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getActiveCategories(): array
    {
        return JobCategory::query()
            ->active()
            ->select(['id', 'name', 'slug'])
            ->orderBy('name')
            ->get()
            ->map(fn (JobCategory $category): array => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getActiveEmploymentTypes(): array
    {
        return EmploymentType::query()
            ->active()
            ->select(['id', 'name', 'slug'])
            ->orderBy('sort_order')
            ->get()
            ->map(fn (EmploymentType $type): array => [
                'id' => $type->id,
                'name' => $type->name,
                'slug' => $type->slug,
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getActiveWorkplaceArrangements(): array
    {
        return WorkplaceArrangement::query()
            ->active()
            ->select(['id', 'name', 'slug'])
            ->orderBy('sort_order')
            ->get()
            ->map(fn (WorkplaceArrangement $arrangement): array => [
                'id' => $arrangement->id,
                'name' => $arrangement->name,
                'slug' => $arrangement->slug,
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getActiveExperienceLevels(): array
    {
        return ExperienceLevel::query()
            ->active()
            ->select(['id', 'name', 'slug'])
            ->orderBy('sort_order')
            ->get()
            ->map(fn (ExperienceLevel $level): array => [
                'id' => $level->id,
                'name' => $level->name,
                'slug' => $level->slug,
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getActivePayPeriods(): array
    {
        return PayPeriod::query()
            ->active()
            ->select(['id', 'name', 'slug'])
            ->orderBy('sort_order')
            ->get()
            ->map(fn (PayPeriod $period): array => [
                'id' => $period->id,
                'name' => $period->name,
                'slug' => $period->slug,
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function jobPayload(Job $job): array
    {
        return [
            'id' => $job->id,
            'company_id' => $job->company_id,
            'title' => $job->title,
            'description' => $job->description,
            'responsibilities' => $job->responsibilities,
            'requirements' => $job->requirements,
            'category_id' => $job->category_id,
            'employment_type_id' => $job->employment_type_id,
            'workplace_arrangement_id' => $job->workplace_arrangement_id,
            'experience_level_id' => $job->experience_level_id,
            'salary_min' => $job->salary_min ? (float) $job->salary_min : null,
            'salary_max' => $job->salary_max ? (float) $job->salary_max : null,
            'salary_currency' => $job->salary_currency,
            'salary_negotiable' => $job->salary_negotiable,
            'pay_period_id' => $job->pay_period_id,
            'location' => $job->location,
            'application_deadline' => $job->application_deadline?->toIso8601String(),
            'status' => $job->status,
            'rejection_reason' => $job->rejection_reason,
            'applications_count' => 0,
            'created_at' => $job->created_at?->toIso8601String(),
            'updated_at' => $job->updated_at?->toIso8601String(),
            'company' => $job->company ? [
                'id' => $job->company->id,
                'name' => $job->company->name,
                'slug' => $job->company->slug,
            ] : null,
            'category' => $job->category ? [
                'id' => $job->category->id,
                'name' => $job->category->name,
                'slug' => $job->category->slug,
            ] : null,
            'employment_type' => $job->employmentType ? [
                'id' => $job->employmentType->id,
                'name' => $job->employmentType->name,
                'slug' => $job->employmentType->slug,
            ] : null,
            'workplace_arrangement' => $job->workplaceArrangement ? [
                'id' => $job->workplaceArrangement->id,
                'name' => $job->workplaceArrangement->name,
                'slug' => $job->workplaceArrangement->slug,
            ] : null,
            'experience_level' => $job->experienceLevel ? [
                'id' => $job->experienceLevel->id,
                'name' => $job->experienceLevel->name,
                'slug' => $job->experienceLevel->slug,
            ] : null,
            'pay_period' => $job->payPeriod ? [
                'id' => $job->payPeriod->id,
                'name' => $job->payPeriod->name,
                'slug' => $job->payPeriod->slug,
            ] : null,
        ];
    }
}
