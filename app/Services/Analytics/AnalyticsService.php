<?php

namespace App\Services\Analytics;

use App\Enums\JobApplicationStatus;
use App\Models\Job;
use App\Models\JobApplication;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Read-only aggregation of the employer and platform analytics metrics.
 *
 * The service is the single definition of every metric formula, so the
 * employer-scoped and platform-scoped pages cannot drift apart. It never
 * receives a user: the caller resolves the authorized company scope and passes
 * the resulting identifiers, which makes it structurally impossible for the
 * service to widen the scope on its own.
 *
 * The service holds no mutable state. Each page-level method reads its
 * aggregates exactly once and derives every headline metric and breakdown from
 * them, so a page never executes the same aggregate twice and never serves a
 * value that a cached aggregate left behind.
 */
class AnalyticsService
{
    /**
     * The job posting statuses tracked by the platform.
     *
     * @var array<int, string>
     */
    public const JOB_STATUSES = ['draft', 'pending_review', 'published', 'rejected', 'closed'];

    /**
     * Human-readable labels for the job posting statuses.
     *
     * @var array<string, string>
     */
    public const JOB_STATUS_LABELS = [
        'draft' => 'Draft',
        'pending_review' => 'Pending review',
        'published' => 'Published',
        'rejected' => 'Rejected',
        'closed' => 'Closed',
    ];

    /**
     * The employer statuses tracked by the platform.
     *
     * @var array<int, string>
     */
    public const EMPLOYER_STATUSES = ['pending', 'approved', 'rejected', 'suspended'];

    /**
     * Human-readable labels for the employer statuses.
     *
     * @var array<string, string>
     */
    public const EMPLOYER_STATUS_LABELS = [
        'pending' => 'Pending',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
        'suspended' => 'Suspended',
    ];

    /**
     * The application statuses that count as advanced (shortlist or beyond).
     *
     * @return array<int, string>
     */
    public static function advancedApplicationStatuses(): array
    {
        return [
            JobApplicationStatus::Shortlisted->value,
            JobApplicationStatus::InterviewScheduled->value,
            JobApplicationStatus::OfferSent->value,
            JobApplicationStatus::Hired->value,
        ];
    }

    /**
     * The user-facing definition of every metric, rendered as a disclosure.
     *
     * @param  string  $scope  Either `employer` or `platform`.
     * @return array<int, array{id: string, label: string, description: string}>
     */
    public function metricDefinitions(string $scope): array
    {
        $definitions = [
            [
                'id' => 'total_jobs',
                'label' => 'Total jobs',
                'description' => 'Job postings created inside the selected window, counted by their current status.',
            ],
            [
                'id' => 'published_jobs',
                'label' => 'Published jobs',
                'description' => 'Job postings created inside the selected window whose current status is published.',
            ],
            [
                'id' => 'total_applications',
                'label' => 'Total applications',
                'description' => 'Applications submitted inside the selected window, including rejected and withdrawn ones. An application to a job that was created before the window is counted here but does not appear in the job performance table.',
            ],
            [
                'id' => 'advanced_applications',
                'label' => 'Advanced (shortlist or beyond)',
                'description' => 'Applications inside the selected window whose current status is shortlisted, interview scheduled, offer sent, or hired.',
            ],
            [
                'id' => 'conversion_rate',
                'label' => 'Conversion rate',
                'description' => 'Advanced applications divided by total applications. An application that was shortlisted and later rejected stays in the denominator only.',
            ],
            [
                'id' => 'hires',
                'label' => 'Hires',
                'description' => 'Applications inside the selected window whose current status is hired.',
            ],
        ];

        if ($scope === 'platform') {
            $definitions[] = [
                'id' => 'total_users',
                'label' => 'Registered users',
                'description' => 'Accounts created inside the selected window.',
            ];
            $definitions[] = [
                'id' => 'total_companies',
                'label' => 'Companies',
                'description' => 'Company profiles created inside the selected window.',
            ];
            $definitions[] = [
                'id' => 'employers_by_status',
                'label' => 'Employers by status',
                'description' => 'Employer accounts created inside the selected window, counted by their current review status.',
            ];
            $definitions[] = [
                'id' => 'top_categories',
                'label' => 'Top categories',
                'description' => 'Job categories ranked by the number of published postings created inside the selected window. The list is limited to the five highest categories.',
            ];
        }

        return $definitions;
    }

    /**
     * Build the employer-scoped metrics.
     *
     * The job and application status counts are read once and every headline
     * metric and breakdown is derived from them, so the aggregates are never
     * executed twice for one page read.
     *
     * @param  array<int, int>  $companyIds  The already authorized company identifiers.
     * @return array{
     *     summary: array{totalJobs: int, publishedJobs: int, totalApplications: int, advancedApplications: int, conversionRate: float, hires: int},
     *     jobsByStatus: array<int, array{status: string, label: string, count: int}>,
     *     applicationsByStatus: array<int, array{status: string, label: string, count: int}>
     * }
     */
    public function employerMetrics(
        array $companyIds,
        ?CarbonImmutable $from,
        ?CarbonImmutable $to,
    ): array {
        $jobCounts = $this->jobStatusCounts($companyIds, $from, $to);
        $applicationCounts = $this->applicationStatusCounts($companyIds, $from, $to);

        return [
            'summary' => $this->buildSummary($jobCounts, $applicationCounts),
            'jobsByStatus' => $this->jobStatusBreakdown($jobCounts),
            'applicationsByStatus' => $this->applicationStatusBreakdown($applicationCounts),
        ];
    }

    /**
     * Build the employer-scoped daily trend.
     *
     * The trend is only produced for a fully bounded window; an open or
     * disabled window has no finite series.
     *
     * @param  array<int, int>  $companyIds
     * @return array<int, array{date: string, jobs: int, applications: int}>
     */
    public function employerTrend(array $companyIds, ?CarbonImmutable $from, ?CarbonImmutable $to): array
    {
        if ($from === null || $to === null) {
            return [];
        }

        $jobCounts = $this->dailyCounts(
            DB::table('job_postings')->whereIn('company_id', $companyIds),
            'job_postings.created_at',
            $from,
            $to,
        );

        $applicationCounts = $this->dailyCounts(
            DB::table('job_applications')
                ->join('job_postings', 'job_postings.id', '=', 'job_applications.job_posting_id')
                ->whereIn('job_postings.company_id', $companyIds),
            'job_applications.created_at',
            $from,
            $to,
        );

        $points = [];

        foreach ($this->dateKeys($from, $to) as $day) {
            $points[] = [
                'date' => $day,
                'jobs' => $jobCounts[$day] ?? 0,
                'applications' => $applicationCounts[$day] ?? 0,
            ];
        }

        return $points;
    }

    /**
     * Build the paginated employer-scoped per-job performance table.
     *
     * @param  array<int, int>  $companyIds
     * @return LengthAwarePaginator<int, Job>
     */
    public function employerJobsPaginator(
        array $companyIds,
        ?CarbonImmutable $from,
        ?CarbonImmutable $to,
        int $perPage = 10,
    ): LengthAwarePaginator {
        $query = Job::query()
            ->select(['job_postings.id', 'job_postings.title', 'job_postings.status', 'job_postings.company_id', 'job_postings.created_at'])
            ->whereIn('job_postings.company_id', $companyIds)
            ->with('company:id,name,slug')
            ->orderByDesc('job_postings.created_at')
            // The identifier breaks ties so that rows created in the same
            // second keep a stable order across paginated pages.
            ->orderByDesc('job_postings.id');

        if ($from !== null) {
            $query->where('job_postings.created_at', '>=', $from->startOfDay());
        }

        if ($to !== null) {
            $query->where('job_postings.created_at', '<=', $to->endOfDay());
        }

        $query->withCount([
            'applications as applications_count' => fn (EloquentBuilder $applications): EloquentBuilder => $this->constrainApplications($applications, $from, $to),
            'applications as advanced_count' => fn (EloquentBuilder $applications): EloquentBuilder => $this->constrainApplications($applications, $from, $to, true),
        ]);

        return $query->paginate($perPage)->withQueryString();
    }

    /**
     * Build the platform-wide metrics.
     *
     * @return array{
     *     summary: array{totalJobs: int, publishedJobs: int, totalApplications: int, advancedApplications: int, conversionRate: float, hires: int, totalUsers: int, totalCompanies: int},
     *     jobsByStatus: array<int, array{status: string, label: string, count: int}>,
     *     applicationsByStatus: array<int, array{status: string, label: string, count: int}>,
     *     employersByStatus: array<int, array{status: string, label: string, count: int}>
     * }
     */
    public function platformMetrics(?CarbonImmutable $from, ?CarbonImmutable $to): array
    {
        $jobCounts = $this->jobStatusCounts(null, $from, $to);
        $applicationCounts = $this->applicationStatusCounts(null, $from, $to);
        $account = $this->platformAccountMetrics($from, $to);

        return [
            'summary' => [
                ...$this->buildSummary($jobCounts, $applicationCounts),
                'totalUsers' => $account['totalUsers'],
                'totalCompanies' => $this->companyCount($from, $to),
            ],
            'jobsByStatus' => $this->jobStatusBreakdown($jobCounts),
            'applicationsByStatus' => $this->applicationStatusBreakdown($applicationCounts),
            'employersByStatus' => $account['employersByStatus'],
        ];
    }

    /**
     * Build the bounded list of top job categories by published postings.
     *
     * @return array<int, array{id: int, name: string, publishedJobs: int}>
     */
    public function platformTopCategories(?CarbonImmutable $from, ?CarbonImmutable $to, int $limit = 5): array
    {
        $query = DB::table('job_postings')
            ->join('job_categories', 'job_categories.id', '=', 'job_postings.category_id')
            ->where('job_postings.status', 'published');

        $this->applyWindow($query, 'job_postings.created_at', $from, $to);

        $rows = $query
            ->select('job_categories.id', 'job_categories.name', DB::raw('COUNT(*) as metric_count'))
            ->groupBy('job_categories.id', 'job_categories.name')
            ->orderByDesc('metric_count')
            ->orderBy('job_categories.name')
            ->limit($limit)
            ->get();

        return $rows->map(fn (object $row): array => [
            'id' => (int) $row->id,
            'name' => (string) $row->name,
            'publishedJobs' => (int) $row->metric_count,
        ])->all();
    }

    /**
     * Build the platform-wide daily trend.
     *
     * @return array<int, array{date: string, jobs: int, applications: int, users: int}>
     */
    public function platformTrend(?CarbonImmutable $from, ?CarbonImmutable $to): array
    {
        if ($from === null || $to === null) {
            return [];
        }

        $jobCounts = $this->dailyCounts(
            DB::table('job_postings'),
            'job_postings.created_at',
            $from,
            $to,
        );

        $applicationCounts = $this->dailyCounts(
            DB::table('job_applications'),
            'job_applications.created_at',
            $from,
            $to,
        );

        $userCounts = $this->dailyCounts(
            DB::table('users'),
            'users.created_at',
            $from,
            $to,
        );

        $points = [];

        foreach ($this->dateKeys($from, $to) as $day) {
            $points[] = [
                'date' => $day,
                'jobs' => $jobCounts[$day] ?? 0,
                'applications' => $applicationCounts[$day] ?? 0,
                'users' => $userCounts[$day] ?? 0,
            ];
        }

        return $points;
    }

    /**
     * Derive the headline metrics from the status counts.
     *
     * @param  array<string, int>  $jobCounts
     * @param  array<string, int>  $applicationCounts
     * @return array{totalJobs: int, publishedJobs: int, totalApplications: int, advancedApplications: int, conversionRate: float, hires: int}
     */
    private function buildSummary(array $jobCounts, array $applicationCounts): array
    {
        $totalApplications = array_sum($applicationCounts);
        $advancedApplications = $this->advancedCount($applicationCounts);

        return [
            'totalJobs' => array_sum($jobCounts),
            'publishedJobs' => $jobCounts['published'] ?? 0,
            'totalApplications' => $totalApplications,
            'advancedApplications' => $advancedApplications,
            'conversionRate' => self::rate($advancedApplications, $totalApplications),
            'hires' => $applicationCounts[JobApplicationStatus::Hired->value] ?? 0,
        ];
    }

    /**
     * Build the job status breakdown from the job status counts.
     *
     * @param  array<string, int>  $counts
     * @return array<int, array{status: string, label: string, count: int}>
     */
    private function jobStatusBreakdown(array $counts): array
    {
        // The known statuses are always listed so a zero count is visible, and
        // any status observed in the data is appended so the breakdown can
        // never silently omit a row the headline total counted.
        $statuses = array_values(array_unique(array_merge(self::JOB_STATUSES, array_keys($counts))));

        return array_map(
            fn (string $status): array => [
                'status' => $status,
                'label' => self::jobStatusLabel($status),
                'count' => $counts[$status] ?? 0,
            ],
            $statuses,
        );
    }

    /**
     * Build the application status breakdown from the application status counts.
     *
     * @param  array<string, int>  $counts
     * @return array<int, array{status: string, label: string, count: int}>
     */
    private function applicationStatusBreakdown(array $counts): array
    {
        return array_map(
            fn (JobApplicationStatus $status): array => [
                'status' => $status->value,
                'label' => $status->label(),
                'count' => $counts[$status->value] ?? 0,
            ],
            JobApplicationStatus::cases(),
        );
    }

    /**
     * Count the job postings of a scope grouped by status.
     *
     * @param  array<int, int>|null  $companyIds  Null scopes every company.
     * @return array<string, int>
     */
    private function jobStatusCounts(?array $companyIds, ?CarbonImmutable $from, ?CarbonImmutable $to): array
    {
        $query = DB::table('job_postings');

        if ($companyIds !== null) {
            $query->whereIn('job_postings.company_id', $companyIds);
        }

        $this->applyWindow($query, 'job_postings.created_at', $from, $to);

        $rows = $query
            ->select('job_postings.status', DB::raw('COUNT(*) as metric_count'))
            ->groupBy('job_postings.status')
            ->get();

        $counts = [];

        foreach ($rows as $row) {
            $counts[(string) $row->status] = (int) $row->metric_count;
        }

        return $counts;
    }

    /**
     * Count the job applications of a scope grouped by status.
     *
     * @param  array<int, int>|null  $companyIds  Null scopes every company.
     * @return array<string, int>
     */
    private function applicationStatusCounts(?array $companyIds, ?CarbonImmutable $from, ?CarbonImmutable $to): array
    {
        $query = DB::table('job_applications');

        if ($companyIds !== null) {
            $query->join('job_postings', 'job_postings.id', '=', 'job_applications.job_posting_id')
                ->whereIn('job_postings.company_id', $companyIds);
        }

        $this->applyWindow($query, 'job_applications.created_at', $from, $to);

        $rows = $query
            ->select('job_applications.status', DB::raw('COUNT(*) as metric_count'))
            ->groupBy('job_applications.status')
            ->get();

        $counts = [];

        foreach ($rows as $row) {
            $counts[(string) $row->status] = (int) $row->metric_count;
        }

        return $counts;
    }

    /**
     * Count the platform accounts of a window.
     *
     * A single grouped query yields both the total account count and the
     * employer status breakdown, so the accounts table is read once per page.
     *
     * @return array{totalUsers: int, employersByStatus: array<int, array{status: string, label: string, count: int}>}
     */
    private function platformAccountMetrics(?CarbonImmutable $from, ?CarbonImmutable $to): array
    {
        $query = DB::table('users');

        $this->applyWindow($query, 'users.created_at', $from, $to);

        $rows = $query
            ->select('users.status', DB::raw('COUNT(*) as metric_count'))
            ->groupBy('users.status')
            ->get();

        $byStatus = [];
        $totalUsers = 0;

        foreach ($rows as $row) {
            $count = (int) $row->metric_count;
            $totalUsers += $count;

            if ($row->status === null) {
                continue;
            }

            $byStatus[(string) $row->status] = $count;
        }

        $employersByStatus = array_map(
            fn (string $status): array => [
                'status' => $status,
                'label' => self::employerStatusLabel($status),
                'count' => $byStatus[$status] ?? 0,
            ],
            self::EMPLOYER_STATUSES,
        );

        return [
            'totalUsers' => $totalUsers,
            'employersByStatus' => $employersByStatus,
        ];
    }

    /**
     * Count the companies created inside a window.
     */
    private function companyCount(?CarbonImmutable $from, ?CarbonImmutable $to): int
    {
        $query = DB::table('companies');

        $this->applyWindow($query, 'companies.created_at', $from, $to);

        return (int) $query->count();
    }

    /**
     * Count the records of a query grouped by their calendar day.
     *
     * @param  literal-string  $column
     * @return array<string, int>
     */
    private function dailyCounts(QueryBuilder $query, string $column, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $this->applyWindow($query, $column, $from, $to);

        $rows = $query
            ->selectRaw('DATE('.$column.') as metric_day, COUNT(*) as metric_count')
            ->groupByRaw('DATE('.$column.')')
            ->get();

        $counts = [];

        foreach ($rows as $row) {
            $counts[(string) $row->metric_day] = (int) $row->metric_count;
        }

        return $counts;
    }

    /**
     * Apply the inclusive window to a query.
     */
    private function applyWindow(QueryBuilder $query, string $column, ?CarbonImmutable $from, ?CarbonImmutable $to): void
    {
        if ($from !== null) {
            $query->where($column, '>=', $from->startOfDay());
        }

        if ($to !== null) {
            $query->where($column, '<=', $to->endOfDay());
        }
    }

    /**
     * Constrain the per-job application count sub-select.
     *
     * @param  EloquentBuilder<JobApplication>  $query
     * @return EloquentBuilder<JobApplication>
     */
    private function constrainApplications(
        EloquentBuilder $query,
        ?CarbonImmutable $from,
        ?CarbonImmutable $to,
        bool $advancedOnly = false,
    ): EloquentBuilder {
        if ($from !== null) {
            $query->where('job_applications.created_at', '>=', $from->startOfDay());
        }

        if ($to !== null) {
            $query->where('job_applications.created_at', '<=', $to->endOfDay());
        }

        if ($advancedOnly) {
            $query->whereIn('job_applications.status', self::advancedApplicationStatuses());
        }

        return $query;
    }

    /**
     * Count the applications that reached shortlist or beyond.
     *
     * @param  array<string, int>  $counts
     */
    private function advancedCount(array $counts): int
    {
        $advanced = 0;

        foreach (self::advancedApplicationStatuses() as $status) {
            $advanced += $counts[$status] ?? 0;
        }

        return $advanced;
    }

    /**
     * Resolve the human-readable label of a job posting status.
     */
    private static function jobStatusLabel(string $status): string
    {
        return self::JOB_STATUS_LABELS[$status] ?? $status;
    }

    /**
     * Resolve the human-readable label of an employer status.
     */
    private static function employerStatusLabel(string $status): string
    {
        return self::EMPLOYER_STATUS_LABELS[$status] ?? $status;
    }

    /**
     * Calculate a ratio, guarding against a zero denominator.
     */
    public static function rate(int $numerator, int $denominator): float
    {
        if ($denominator <= 0) {
            return 0.0;
        }

        return round($numerator / $denominator, 4);
    }

    /**
     * List every calendar day of an inclusive window.
     *
     * @return array<int, string>
     */
    private function dateKeys(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $keys = [];
        $cursor = $from->startOfDay();
        $end = $to->endOfDay();

        while ($cursor->lte($end)) {
            $keys[] = $cursor->format('Y-m-d');
            $cursor = $cursor->addDay();
        }

        return $keys;
    }
}
