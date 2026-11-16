<?php

namespace App\Services;

use App\Enums\JobApplicationStatus;
use App\Models\Company;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Read-only query composer for the recruitment pipeline board.
 *
 * It owns the shared board filter scope so the per-status counts, the job filter
 * options and the column cards can never drift apart, mirroring the existing
 * JobSearchService precedent. It performs no writes; application status is only
 * ever changed by ApplicationStatusTransitionService.
 *
 * The board reads through the query builder because it projects a fixed column
 * list across three joined tables. Every projected column is whitelisted, so
 * private recruiter data (notes, evaluations, resumes, cover letters, screening
 * answers, message content) can never reach a board card.
 */
class RecruitmentPipelineService
{
    /**
     * The columns projected onto a board card.
     *
     * @var array<int, string>
     */
    private const CARD_COLUMNS = [
        'job_applications.id',
        'job_applications.status',
        'job_applications.created_at',
        'job_applications.job_posting_id',
        'job_applications.user_id',
    ];

    /**
     * Apply the board filters to the given application query.
     *
     * @param  array{job_id: int|null, search: string|null}  $filters
     */
    public function apply(Builder $query, Company $company, array $filters): Builder
    {
        $query->join('job_postings', 'job_postings.id', '=', 'job_applications.job_posting_id')
            ->where('job_postings.company_id', $company->id);

        if ($filters['search'] !== null) {
            $query->join('users', 'users.id', '=', 'job_applications.user_id');
        }

        if ($filters['job_id'] !== null) {
            $query->where('job_postings.id', $filters['job_id']);
        }

        $search = $filters['search'];

        if ($search !== null) {
            $query->where('users.name', 'like', '%'.$this->sanitizeSearch($search).'%');
        }

        return $query;
    }

    /**
     * Count the applications per status for the filtered board scope.
     *
     * @param  array{job_id: int|null, search: string|null}  $filters
     * @return array<string, int>
     */
    public function statusCounts(Company $company, array $filters): array
    {
        $counts = [];

        foreach (JobApplicationStatus::cases() as $status) {
            $counts[$status->value] = 0;
        }

        $rows = $this->apply($this->base(), $company, $filters)
            ->selectRaw('job_applications.status, count(*) as aggregate')
            ->groupBy('job_applications.status')
            ->get();

        foreach ($rows as $row) {
            $data = (array) $row;

            $counts[(string) $data['status']] = (int) $data['aggregate'];
        }

        return $counts;
    }

    /**
     * List the jobs of the company that match the board scope, with their counts.
     *
     * The job filter itself is not applied here so the recruiter can always switch
     * between jobs; every other active filter is applied.
     *
     * @param  array{job_id: int|null, search: string|null}  $filters
     * @return array<int, array{id: int, title: string, count: int}>
     */
    public function jobOptions(Company $company, array $filters): array
    {
        $rows = $this->apply($this->base(), $company, [
            'job_id' => null,
            'search' => $filters['search'],
        ])
            ->selectRaw('job_postings.id as job_id, job_postings.title as job_title, count(*) as aggregate')
            ->groupBy('job_postings.id', 'job_postings.title')
            ->orderByRaw('count(*) desc')
            ->orderBy('job_postings.title')
            ->get();

        return $rows->map(function (object $row): array {
            $data = (array) $row;

            return [
                'id' => (int) $data['job_id'],
                'title' => (string) $data['job_title'],
                'count' => (int) $data['aggregate'],
            ];
        })->values()->all();
    }

    /**
     * Fetch the cards of a single status column, newest first.
     *
     * The bounded card set is resolved first and the display columns are joined
     * afterwards. Resolving the limit before the joins keeps the number of joined
     * rows at the column limit instead of joining every application of the column.
     *
     * @param  array{job_id: int|null, search: string|null}  $filters
     * @return array<int, array{id: int, status: string, applied_at: string|null, candidate: array{id: int, name: string, headline: string|null, location: string|null}, job: array{id: int, title: string, status: string}}>
     */
    public function columnCards(
        Company $company,
        JobApplicationStatus $status,
        array $filters,
        int $limit
    ): array {
        $cards = $this->apply($this->base(), $company, $filters)
            ->select(self::CARD_COLUMNS)
            ->where('job_applications.status', $status->value)
            ->orderByDesc('job_applications.created_at')
            ->orderByDesc('job_applications.id')
            ->limit($limit);

        $rows = DB::query()
            ->fromSub($cards, 'cards')
            ->join('users', 'users.id', '=', 'cards.user_id')
            ->leftJoin('candidate_profiles', 'candidate_profiles.user_id', '=', 'cards.user_id')
            ->join('job_postings', 'job_postings.id', '=', 'cards.job_posting_id')
            ->select([
                'cards.id',
                'cards.status',
                'cards.created_at',
                'cards.job_posting_id',
                'job_postings.status as job_status',
                'users.id as candidate_id',
                'users.name as candidate_name',
                'candidate_profiles.headline',
                'candidate_profiles.location',
                'job_postings.title as job_title',
            ])
            ->get();

        return $rows->map(function (object $row): array {
            $data = (array) $row;

            return [
                'id' => (int) $data['id'],
                'status' => (string) $data['status'],
                'applied_at' => $this->formatTimestamp($data['created_at'] ?? null),
                'candidate' => [
                    'id' => (int) $data['candidate_id'],
                    'name' => (string) $data['candidate_name'],
                    'headline' => $this->formatString($data['headline'] ?? null),
                    'location' => $this->formatString($data['location'] ?? null),
                ],
                'job' => [
                    'id' => (int) $data['job_posting_id'],
                    'title' => (string) $data['job_title'],
                    'status' => (string) $data['job_status'],
                ],
            ];
        })->values()->all();
    }

    /**
     * The base query for the board.
     */
    private function base(): Builder
    {
        return DB::table('job_applications');
    }

    /**
     * Format a nullable text column.
     */
    private function formatString(mixed $value): ?string
    {
        return is_string($value) ? $value : null;
    }

    /**
     * Format a nullable timestamp column as an ISO 8601 string.
     */
    private function formatTimestamp(mixed $value): ?string
    {
        return is_string($value) ? Carbon::parse($value)->toIso8601String() : null;
    }

    /**
     * Strip the LIKE wildcard characters from the search term.
     *
     * The term is always bound as a parameter, so this only prevents a search term
     * from widening the match. The behaviour is identical on MySQL and SQLite.
     */
    private function sanitizeSearch(string $search): string
    {
        return str_replace(['\\', '%', '_'], '', $search);
    }
}
