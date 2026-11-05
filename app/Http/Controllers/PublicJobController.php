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
use Illuminate\Database\Eloquent\Builder;
use Inertia\Inertia;
use Inertia\Response;

class PublicJobController extends Controller
{
    public function index(): Response
    {
        $search = request()->string('search')->toString();
        $categoryId = request()->integer('category');
        $employmentTypeId = request()->integer('employment_type');
        $workplaceArrangementId = request()->integer('workplace_arrangement');
        $experienceLevelId = request()->integer('experience_level');

        $query = Job::query()
            ->published()
            ->whereHas('company', function (Builder $q) {
                $q->where('is_active', true)
                    ->whereHas('owner', function (Builder $ownerQuery) {
                        $ownerQuery->where('status', EmployerStatus::Approved->value);
                    });
            })
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
                'created_at',
            ])
            ->latest();

        if ($search !== '') {
            $query->where(function (Builder $q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('location', 'like', "%{$search}%");
            });
        }

        if ($categoryId > 0) {
            $query->where('category_id', $categoryId);
        }

        if ($employmentTypeId > 0) {
            $query->where('employment_type_id', $employmentTypeId);
        }

        if ($workplaceArrangementId > 0) {
            $query->where('workplace_arrangement_id', $workplaceArrangementId);
        }

        if ($experienceLevelId > 0) {
            $query->where('experience_level_id', $experienceLevelId);
        }

        $jobs = $query->paginate(12)->withQueryString();

        /** @var User|null $user */
        $user = request()->user();
        $savedJobIds = $user && $user->hasRole('job_seeker')
            ? $user->savedJobs()->pluck('job_posting_id')->all()
            : [];

        return Inertia::render('jobs/index', [
            'savedJobIds' => $savedJobIds,
            'jobs' => $jobs->through(fn (Job $job): array => [
                'id' => $job->id,
                'title' => $job->title,
                'location' => $job->location,
                'min_salary' => $job->salary_min,
                'max_salary' => $job->salary_max,
                'salary_currency' => $job->salary_currency,
                'salary_negotiable' => $job->salary_negotiable,
                'published_at' => $job->created_at?->toIso8601String(),
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
            ]),
            'categories' => JobCategory::query()->active()->select(['id', 'name'])->orderBy('name')->get(),
            'employmentTypes' => EmploymentType::query()->active()->select(['id', 'name'])->orderBy('sort_order')->get(),
            'workplaceArrangements' => WorkplaceArrangement::query()->active()->select(['id', 'name'])->orderBy('name')->get(),
            'experienceLevels' => ExperienceLevel::query()->active()->select(['id', 'name'])->orderBy('sort_order')->get(),
            'filters' => [
                'search' => $search !== '' ? $search : null,
                'category' => $categoryId > 0 ? $categoryId : null,
                'employment_type' => $employmentTypeId > 0 ? $employmentTypeId : null,
                'workplace_arrangement' => $workplaceArrangementId > 0 ? $workplaceArrangementId : null,
                'experience_level' => $experienceLevelId > 0 ? $experienceLevelId : null,
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
