<?php

namespace App\Services;

use App\Enums\EmployerStatus;
use App\Models\Job;
use Illuminate\Database\Eloquent\Builder;

class JobSearchService
{
    /**
     * Extract the public job listing filters from the current request.
     *
     * @return array{search: string, category: int, employment_type: int, workplace_arrangement: int, experience_level: int}
     */
    public function filtersFromRequest(): array
    {
        return [
            'search' => request()->string('search')->toString(),
            'category' => request()->integer('category'),
            'employment_type' => request()->integer('employment_type'),
            'workplace_arrangement' => request()->integer('workplace_arrangement'),
            'experience_level' => request()->integer('experience_level'),
        ];
    }

    /**
     * Apply the public listing criteria to the given job query.
     *
     * @param  Builder<Job>  $jobQuery
     * @param  array{search: string|null, category: int|null, employment_type: int|null, workplace_arrangement: int|null, experience_level: int|null}  $filters
     * @return Builder<Job>
     */
    public function apply(Builder $jobQuery, array $filters): Builder
    {
        $search = $filters['search'];

        if (is_string($search) && $search !== '') {
            $jobQuery->where(function (Builder $q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('location', 'like', "%{$search}%");
            });
        }

        $categoryId = $filters['category'] ?? 0;

        if ($categoryId > 0) {
            $jobQuery->where('category_id', $categoryId);
        }

        $employmentTypeId = $filters['employment_type'] ?? 0;

        if ($employmentTypeId > 0) {
            $jobQuery->where('employment_type_id', $employmentTypeId);
        }

        $workplaceArrangementId = $filters['workplace_arrangement'] ?? 0;

        if ($workplaceArrangementId > 0) {
            $jobQuery->where('workplace_arrangement_id', $workplaceArrangementId);
        }

        $experienceLevelId = $filters['experience_level'] ?? 0;

        if ($experienceLevelId > 0) {
            $jobQuery->where('experience_level_id', $experienceLevelId);
        }

        return $jobQuery;
    }

    /**
     * Restrict the query to publicly visible jobs: published, active company, approved owner.
     *
     * @param  Builder<Job>  $jobQuery
     * @return Builder<Job>
     */
    public function visibility(Builder $jobQuery): Builder
    {
        return $jobQuery
            ->published()
            ->whereHas('company', function (Builder $q) {
                $q->where('is_active', true)
                    ->whereHas('owner', function (Builder $ownerQuery) {
                        $ownerQuery->where('status', EmployerStatus::Approved->value);
                    });
            });
    }
}
