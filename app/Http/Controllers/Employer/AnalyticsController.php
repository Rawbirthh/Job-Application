<?php

namespace App\Http\Controllers\Employer;

use App\Enums\AuditAction;
use App\Enums\AuditTargetType;
use App\Enums\CompanyMemberRole;
use App\Enums\CompanyMemberStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Employer\IndexAnalyticsRequest;
use App\Models\Company;
use App\Models\CompanyMember;
use App\Models\Job;
use App\Models\User;
use App\Services\Analytics\AnalyticsService;
use App\Services\Audit\AuditLogService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class AnalyticsController extends Controller
{
    public function __construct(
        private readonly AnalyticsService $analyticsService,
        private readonly AuditLogService $auditLogService,
    ) {}

    /**
     * Render the employer-scoped analytics page.
     */
    public function index(IndexAnalyticsRequest $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        // The role relation is resolved by the company role checks below, so it
        // is loaded once instead of once per authorization call.
        $user->loadMissing('roles');

        $authorized = $this->authorizedCompanies($user);

        abort_if(
            $authorized->isEmpty(),
            403,
            __('You do not manage any company that grants analytics access.'),
        );

        $selectedCompany = $this->resolveSelectedCompany($request->integer('company_id'), $authorized);

        $window = $request->analyticsWindow();
        $from = $window['from'];
        $to = $window['to'];

        // A selected company narrows the scope to that company; otherwise every
        // authorized company is aggregated.
        $companyIds = $selectedCompany === null
            ? $authorized->map(fn (Company $company): int => $company->id)->all()
            : [$selectedCompany->id];

        $metrics = $this->analyticsService->employerMetrics($companyIds, $from, $to);
        $trend = $this->analyticsService->employerTrend($companyIds, $from, $to);

        $jobs = $this->analyticsService->employerJobsPaginator($companyIds, $from, $to)->through(
            fn (Job $job): array => $this->jobPayload($job),
        );

        $this->recordView([
            'company_ids' => $companyIds,
            'company_count' => count($companyIds),
            'from' => $from?->format('Y-m-d'),
            'to' => $to?->format('Y-m-d'),
            'window' => $window['preset'] === 'all' ? 'all_time' : 'bounded',
        ]);

        return Inertia::render('employer/analytics', [
            'window' => [
                'from' => $from?->format('Y-m-d'),
                'to' => $to?->format('Y-m-d'),
                'preset' => $window['preset'],
            ],
            'companies' => $authorized->map(fn (Company $company): array => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
            ])->all(),
            'selectedCompanyId' => $selectedCompany?->id,
            'summary' => $metrics['summary'],
            'jobsByStatus' => $metrics['jobsByStatus'],
            'applicationsByStatus' => $metrics['applicationsByStatus'],
            'trend' => $trend,
            'jobs' => $jobs,
            'definitions' => $this->analyticsService->metricDefinitions('employer'),
        ]);
    }

    /**
     * Resolve the companies the employer may read analytics for.
     *
     * The actor's own memberships are read once, so the company role is
     * resolved without one query per company.
     *
     * @return Collection<int, Company>
     */
    private function authorizedCompanies(User $user): Collection
    {
        // Only the actor's own active memberships are read. The full
        // membership list of every managed company is never loaded.
        $rolesByCompany = [];

        foreach ($this->activeMemberships($user) as $membership) {
            $rolesByCompany[$membership->company_id] = $membership->company_role;
        }

        // The collection is bounded by the number of companies the employer
        // manages, which is a small number in practice: a company membership
        // is granted individually by an owner, and the audit trail caps the
        // identifier list at twenty entries.
        return Company::query()
            ->forManagingUser($user)
            ->select(['id', 'name', 'slug', 'owner_user_id'])
            ->get()
            ->filter(fn (Company $company): bool => $this->grantsAnalytics($company, $user, $rolesByCompany))
            ->values();
    }

    /**
     * Read the active company memberships of the actor.
     *
     * @return Collection<int, CompanyMember>
     */
    private function activeMemberships(User $user): Collection
    {
        return CompanyMember::query()
            ->where('company_members.user_id', $user->id)
            ->where('company_members.status', CompanyMemberStatus::Active)
            ->get(['company_id', 'company_role']);
    }

    /**
     * Determine whether the company role of the actor grants analytics access.
     *
     * @param  array<int, CompanyMemberRole>  $rolesByCompany
     */
    private function grantsAnalytics(Company $company, User $user, array $rolesByCompany): bool
    {
        $role = $this->resolveCompanyRole($company, $user, $rolesByCompany);

        if ($role === null) {
            return false;
        }

        return in_array('analytics.view', $role->permissions(), true);
    }

    /**
     * Resolve the company role of the actor without an extra membership query.
     *
     * @param  array<int, CompanyMemberRole>  $rolesByCompany
     */
    private function resolveCompanyRole(Company $company, User $user, array $rolesByCompany): ?CompanyMemberRole
    {
        if ((int) $company->owner_user_id === (int) $user->id) {
            return CompanyMemberRole::Owner;
        }

        return $rolesByCompany[$company->id] ?? null;
    }

    /**
     * Resolve the selected company, rejecting anything outside the authorized set.
     *
     * @param  Collection<int, Company>  $authorized
     */
    private function resolveSelectedCompany(int $requestedCompanyId, Collection $authorized): ?Company
    {
        if ($requestedCompanyId <= 0) {
            return null;
        }

        $company = $authorized->firstWhere('id', $requestedCompanyId);

        if ($company === null) {
            // A denied company selection is a cross-tenant probe. It leaves no
            // audit row because no page was read, so it is reported to the
            // application log where a probing campaign is visible.
            Log::warning('Employer analytics access was denied for a company.', [
                'actor_id' => auth()->id(),
                'requested_company_id' => $requestedCompanyId,
            ]);

            abort(
                403,
                __('You are not allowed to view analytics for that company.'),
            );
        }

        $this->authorize('viewAnalytics', $company);

        return $company;
    }

    /**
     * Build the per-job performance row.
     *
     * @return array<string, mixed>
     */
    private function jobPayload(Job $job): array
    {
        $applicationsCount = (int) $job->getAttribute('applications_count');
        $advancedCount = (int) $job->getAttribute('advanced_count');

        return [
            'id' => $job->id,
            'title' => $job->title,
            'status' => $job->status,
            'company' => $job->company === null ? null : [
                'id' => $job->company->id,
                'name' => $job->company->name,
            ],
            'created_at' => $job->created_at?->toIso8601String(),
            'applications_count' => $applicationsCount,
            'advanced_count' => $advancedCount,
            'conversion_rate' => AnalyticsService::rate($advancedCount, $applicationsCount),
        ];
    }

    /**
     * Record that an employer read the analytics.
     *
     * Read-path writes are fail-soft: a logging problem must never break the
     * analytics page.
     *
     * @param  array<string, mixed>  $metadata
     */
    private function recordView(array $metadata): void
    {
        try {
            $this->auditLogService->recordWithoutModel(
                AuditAction::AnalyticsViewed,
                AuditTargetType::Analytics->value,
                null,
                'employer',
                ['scope' => 'employer', ...$metadata],
            );
        } catch (\Throwable $exception) {
            Log::error('Analytics view could not be recorded.', [
                'scope' => 'employer',
                'exception' => $exception->getMessage(),
            ]);
        }
    }
}
