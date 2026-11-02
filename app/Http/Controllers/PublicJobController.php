<?php

namespace App\Http\Controllers;

use App\Enums\EmployerStatus;
use App\Models\EmploymentType;
use App\Models\ExperienceLevel;
use App\Models\Job;
use App\Models\JobCategory;
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

        return Inertia::render('jobs/index', [
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
}
