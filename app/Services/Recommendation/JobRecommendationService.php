<?php

namespace App\Services\Recommendation;

use App\Models\CandidateProfile;
use App\Models\ExperienceLevel;
use App\Models\Job;
use App\Models\JobApplication;
use App\Models\SavedJob;
use App\Models\User;
use App\Services\JobSearchService;
use App\Support\ExperienceCalculator;
use App\Support\SkillNormalizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Builds the candidate's personalised job recommendations (Feature 34).
 *
 * The rules themselves live in {@see RecommendationScorer}; this class resolves
 * the candidate's signals, narrows the job pool with an indexed query, ranks
 * the bounded pool and paginates the result.
 */
final class JobRecommendationService
{
    /**
     * The maximum number of jobs loaded and scored per request.
     */
    public const POOL_SIZE = 200;

    /**
     * The number of recommendations shown per page.
     */
    public const PER_PAGE = 12;

    public function __construct(
        private readonly JobSearchService $searchService,
        private readonly RecommendationScorer $scorer,
    ) {}

    /**
     * Recommend jobs for the signed-in candidate.
     *
     * @return array{mode: 'matched'|'fallback', paginator: LengthAwarePaginator<int, RecommendationItem>, profileSummary: array<string, mixed>}
     */
    public function recommend(User $user): array
    {
        $profile = CandidateProfile::query()
            ->where('user_id', $user->id)
            ->with('experiences')
            ->first();

        // Rebuilt on every request: a memoised ranking would be served stale by
        // a long-lived container.
        $ranking = ExperienceLevelRanking::fromDatabase();

        $candidate = $this->buildCandidate($profile, $ranking);
        $years = $profile instanceof CandidateProfile ? ExperienceCalculator::years($profile) : 0.0;

        $mode = 'matched';
        $items = [];

        if ($candidate->signalCount() > 0) {
            $items = $this->rank($this->poolQuery($candidate, $ranking)->get(), $candidate, $ranking);
        }

        if ($items === []) {
            $mode = 'fallback';
            $items = $this->recentItems($this->recentQuery()->get());
        }

        return [
            'mode' => $mode,
            'paginator' => $this->paginate($items, $user),
            'profileSummary' => [
                'has_skills' => $candidate->hasSkills(),
                'has_category' => $candidate->preferredCategoryId !== null,
                'has_experience_level' => $candidate->hasTargetLevel(),
                'has_location' => $candidate->locationCity !== null,
                'has_workplace' => $candidate->preferredWorkplaceArrangementId !== null,
                'signals_available' => $candidate->signalCount(),
                'computed_years' => $years,
            ],
        ];
    }

    /**
     * Resolve the candidate's signals from their profile.
     */
    private function buildCandidate(?CandidateProfile $profile, ExperienceLevelRanking $ranking): RecommendationCandidate
    {
        $skills = [];
        $labels = [];

        foreach ($profile instanceof CandidateProfile ? ($profile->skills ?? []) : [] as $raw) {
            $canonical = SkillNormalizer::normalize($raw);

            if ($canonical === '' || isset($labels[$canonical])) {
                continue;
            }

            $skills[] = $canonical;
            $labels[$canonical] = trim($raw);
        }

        $targetLevel = null;

        if ($profile instanceof CandidateProfile && $profile->preferred_experience_level_id !== null) {
            $targetLevel = ExperienceLevel::query()->find($profile->preferred_experience_level_id);
        }

        if (! $targetLevel instanceof ExperienceLevel && $profile instanceof CandidateProfile) {
            $targetLevel = $this->deriveLevel(ExperienceCalculator::years($profile));
        }

        return new RecommendationCandidate(
            $skills,
            $labels,
            $profile instanceof CandidateProfile ? $profile->preferred_category_id : null,
            $targetLevel?->id,
            $ranking->rank($targetLevel?->id),
            $targetLevel?->name,
            $profile instanceof CandidateProfile ? $profile->location_city : null,
            $profile instanceof CandidateProfile ? $profile->preferred_workplace_arrangement_id : null,
        );
    }

    /**
     * The experience level a candidate's years of experience fall into.
     *
     * The level whose `min_years` is the greatest value not exceeding the
     * candidate's years wins; ties are broken by the greatest `sort_order`.
     */
    private function deriveLevel(float $years): ?ExperienceLevel
    {
        if ($years <= 0) {
            return null;
        }

        return ExperienceLevel::query()
            ->whereNotNull('min_years')
            ->where('min_years', '<=', $years)
            ->orderByDesc('min_years')
            ->orderByDesc('sort_order')
            ->first();
    }

    /**
     * The bounded pool of eligible jobs that match at least one stated signal.
     *
     * @return Builder<Job>
     */
    private function poolQuery(RecommendationCandidate $candidate, ExperienceLevelRanking $ranking): Builder
    {
        $query = $this->eligibleQuery();

        $query->with([
            'skillTags' => fn ($query) => $query->whereIn('skill', $candidate->skills)->orderBy('skill'),
        ]);
        $query->withCount('skillTags as total_skills_count');

        $bandLevelIds = $ranking->bandIds($candidate->targetExperienceRank);

        $query->where(function (Builder $query) use ($candidate, $bandLevelIds): void {
            if ($candidate->hasSkills()) {
                $query->orWhereHas('skillTags', fn (Builder $sub) => $sub->whereIn('skill', $candidate->skills));
            }

            if ($candidate->preferredCategoryId !== null) {
                $query->orWhere('category_id', $candidate->preferredCategoryId);
            }

            if ($bandLevelIds !== []) {
                $query->orWhereIn('experience_level_id', $bandLevelIds);
            }

            if ($candidate->locationCity !== null) {
                $query->orWhere('location_city', $candidate->locationCity);
            }

            if ($candidate->preferredWorkplaceArrangementId !== null) {
                $query->orWhere('workplace_arrangement_id', $candidate->preferredWorkplaceArrangementId);
            }
        });

        return $query->orderByDesc('created_at')->orderBy('id')->limit(self::POOL_SIZE);
    }

    /**
     * The most recent eligible jobs, used for the fallback state.
     *
     * @return Builder<Job>
     */
    private function recentQuery(): Builder
    {
        return $this->eligibleQuery()
            ->orderByDesc('created_at')
            ->orderBy('id')
            ->limit(self::POOL_SIZE);
    }

    /**
     * The shared eligibility gate: published, visible company, approved owner
     * and still accepting applications.
     *
     * @return Builder<Job>
     */
    private function eligibleQuery(): Builder
    {
        $query = Job::query()
            ->select([
                'id', 'company_id', 'category_id', 'employment_type_id', 'workplace_arrangement_id',
                'experience_level_id', 'title', 'location', 'location_city', 'salary_min', 'salary_max',
                'salary_currency', 'salary_negotiable', 'pay_period_id', 'application_deadline', 'created_at',
            ])
            ->with([
                'company:id,name,slug,logo_path',
                'category:id,name,slug',
                'employmentType:id,name,slug',
                'workplaceArrangement:id,name,slug',
                'experienceLevel:id,name,slug,sort_order',
                'payPeriod:id,name,slug',
            ])
            ->published();

        $this->searchService->visibility($query);

        $query->where(function (Builder $query): void {
            $query->whereNull('application_deadline')
                ->orWhere('application_deadline', '>=', today());
        });

        return $query;
    }

    /**
     * Score and rank the pool.
     *
     * @param  Collection<int, Job>  $jobs
     * @return array<int, RecommendationItem>
     */
    private function rank(Collection $jobs, RecommendationCandidate $candidate, ExperienceLevelRanking $ranking): array
    {
        $items = [];

        foreach ($jobs as $job) {
            $outcome = $this->scorer->score($candidate, $this->snapshot($job, $candidate, $ranking));

            if (! $outcome instanceof RecommendationOutcome) {
                continue;
            }

            $items[] = new RecommendationItem($job, $outcome->score, $outcome->reasons);
        }

        usort($items, static function (RecommendationItem $a, RecommendationItem $b): int {
            if ($a->score !== $b->score) {
                return $b->score <=> $a->score;
            }

            $aCreated = $a->job->created_at?->getTimestamp() ?? 0;
            $bCreated = $b->job->created_at?->getTimestamp() ?? 0;

            if ($aCreated !== $bCreated) {
                return $bCreated <=> $aCreated;
            }

            return $a->job->id <=> $b->job->id;
        });

        return $items;
    }

    /**
     * Build the unscored fallback items.
     *
     * @param  Collection<int, Job>  $jobs
     * @return array<int, RecommendationItem>
     */
    private function recentItems(Collection $jobs): array
    {
        $items = [];

        foreach ($jobs as $job) {
            $items[] = new RecommendationItem($job, null);
        }

        return $items;
    }

    /**
     * Read the job-side values the rules need.
     */
    private function snapshot(Job $job, RecommendationCandidate $candidate, ExperienceLevelRanking $ranking): RecommendationJob
    {
        $matchedTokens = $job->skillTags->pluck('skill')->all();

        $labels = [];
        foreach ($matchedTokens as $token) {
            $labels[] = $candidate->skillLabels[(string) $token] ?? (string) $token;
        }

        return new RecommendationJob(
            (int) $job->id,
            (int) $job->category_id,
            $job->experience_level_id !== null ? (int) $job->experience_level_id : null,
            $ranking->rank($job->experience_level_id !== null ? (int) $job->experience_level_id : null),
            $job->experienceLevel?->name,
            $job->location_city,
            $job->workplace_arrangement_id !== null ? (int) $job->workplace_arrangement_id : null,
            $job->workplaceArrangement?->name,
            $job->category?->name,
            (int) $job->getAttribute('total_skills_count'),
            count($matchedTokens),
            $labels,
        );
    }

    /**
     * Slice the ranked pool into a page and attach the candidate's flags.
     *
     * @param  array<int, RecommendationItem>  $items
     * @return LengthAwarePaginator<int, RecommendationItem>
     */
    private function paginate(array $items, User $user): LengthAwarePaginator
    {
        $currentPage = LengthAwarePaginator::resolveCurrentPage();
        $total = count($items);
        $pageItems = array_slice($items, ($currentPage - 1) * self::PER_PAGE, self::PER_PAGE);

        $jobIds = array_map(static fn (RecommendationItem $item): int => (int) $item->job->id, $pageItems);

        $appliedIds = $jobIds === [] ? [] : JobApplication::query()
            ->where('user_id', $user->id)
            ->whereIn('job_posting_id', $jobIds)
            ->pluck('job_posting_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();

        $savedIds = $jobIds === [] ? [] : SavedJob::query()
            ->where('user_id', $user->id)
            ->whereIn('job_posting_id', $jobIds)
            ->pluck('job_posting_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();

        $pageItems = array_map(
            static fn (RecommendationItem $item): RecommendationItem => $item->withFlags(
                in_array((int) $item->job->id, $appliedIds, true),
                in_array((int) $item->job->id, $savedIds, true),
            ),
            $pageItems,
        );

        return new LengthAwarePaginator($pageItems, $total, self::PER_PAGE, $currentPage, [
            'path' => request()->url(),
            'query' => request()->query(),
        ]);
    }
}
