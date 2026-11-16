<?php

namespace App\Http\Controllers\Employer;

use App\Enums\JobApplicationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Employer\RecruitmentPipelineRequest;
use App\Models\Company;
use App\Models\CompanyMember;
use App\Models\Job;
use App\Models\User;
use App\Services\RecruitmentPipelineService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Renders the visual recruitment pipeline board for a single company.
 *
 * The board is a read surface over the existing Feature 24 workflow: it renders
 * the applications of one company grouped by their current status. It never
 * changes an application status itself — moves go through the existing
 * Feature 24 transition route.
 */
class RecruitmentPipelineController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private readonly RecruitmentPipelineService $pipeline) {}

    /**
     * Display the recruitment pipeline board.
     */
    public function index(RecruitmentPipelineRequest $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        $companies = Company::query()
            ->forUserWithPermission($user, 'applicants.view')
            // owner_user_id is required so CompanyPolicy::viewPipeline() can resolve
            // the company role from the model that is handed to the Gate.
            ->select(['id', 'name', 'owner_user_id'])
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        $company = $this->resolveCompany($request, $companies);

        if ($company === null) {
            $this->assertNoRestrictedCompany($user);

            return Inertia::render('employer/pipeline/index', [
                'board' => $this->emptyBoard($companies),
            ]);
        }

        $this->authorize('viewPipeline', $company);

        $jobId = $request->jobId();
        $search = $request->search();

        if ($jobId !== null) {
            $jobBelongsToCompany = Job::query()
                ->where('company_id', $company->id)
                ->whereKey($jobId)
                ->exists();

            if (! $jobBelongsToCompany) {
                abort(404, __('Job not found.'));
            }
        }

        $filters = [
            'job_id' => $jobId,
            'search' => $search,
        ];

        $limits = $request->columnLimits();
        $counts = $this->pipeline->statusCounts($company, $filters);

        $columns = [];

        foreach (JobApplicationStatus::cases() as $status) {
            $count = $counts[$status->value] ?? 0;
            $limit = $limits[$status->value];

            $cards = $count > 0
                ? $this->pipeline->columnCards($company, $status, $filters, $limit)
                : [];

            $columns[] = [
                'status' => $status->value,
                'label' => $status->label(),
                'count' => $count,
                'shown' => count($cards),
                'has_more' => $count > count($cards),
                'items' => $cards,
            ];
        }

        return Inertia::render('employer/pipeline/index', [
            'board' => [
                'company' => [
                    'id' => $company->id,
                    'name' => $company->name,
                ],
                'companies' => $companies
                    ->map(fn (Company $item): array => [
                        'id' => $item->id,
                        'name' => $item->name,
                    ])
                    ->values()
                    ->all(),
                'jobs' => $this->pipeline->jobOptions($company, $filters),
                'filters' => [
                    'company_id' => $company->id,
                    'job_id' => $jobId,
                    'search' => $search,
                ],
                'status_counts' => $counts,
                'columns' => $columns,
                'can_move' => $user->hasCompanyPermission($company, 'applicants.manage'),
                'transition_matrix' => $this->transitionMatrix(),
                'status_labels' => $this->statusLabels(),
            ],
        ]);
    }

    /**
     * Deny the board when the actor has a company relationship but no read permission.
     *
     * A revoked member must not silently receive an empty board, because that would
     * read as "you have no company" instead of "your access was removed". The check
     * only fires for members: a company owner always holds the read permission.
     */
    private function assertNoRestrictedCompany(User $user): void
    {
        $hasMembership = CompanyMember::query()
            ->where('user_id', $user->id)
            ->exists();

        if ($hasMembership) {
            abort(403, __('You are not allowed to view the recruitment pipeline.'));
        }
    }

    /**
     * Resolve the company the board is scoped to.
     *
     * A supplied company id is resolved even when the actor may not manage it so
     * that the policy check produces a 403 instead of silently falling back to
     * another company.
     *
     * @param  Collection<int, Company>  $companies
     */
    private function resolveCompany(RecruitmentPipelineRequest $request, Collection $companies): ?Company
    {
        $companyId = $request->companyId();

        if ($companyId === null) {
            $first = $companies->first();

            return $first instanceof Company ? $first : null;
        }

        return Company::query()->findOrFail($companyId);
    }

    /**
     * Build the board payload for an actor without any manageable company.
     *
     * @param  Collection<int, Company>  $companies
     * @return array<string, mixed>
     */
    private function emptyBoard(Collection $companies): array
    {
        $columns = [];

        foreach (JobApplicationStatus::cases() as $status) {
            $columns[] = [
                'status' => $status->value,
                'label' => $status->label(),
                'count' => 0,
                'shown' => 0,
                'has_more' => false,
                'items' => [],
            ];
        }

        return [
            'company' => null,
            'companies' => $companies
                ->map(fn (Company $item): array => [
                    'id' => $item->id,
                    'name' => $item->name,
                ])
                ->values()
                ->all(),
            'jobs' => [],
            'filters' => [
                'company_id' => null,
                'job_id' => null,
                'search' => null,
            ],
            'status_counts' => $this->statusCounts(),
            'columns' => $columns,
            'can_move' => false,
            'transition_matrix' => $this->transitionMatrix(),
            'status_labels' => $this->statusLabels(),
        ];
    }

    /**
     * The employer transitions allowed from each status.
     *
     * The matrix is derived from the Feature 24 enum so the client never has to
     * decide which transitions are legal.
     *
     * @return array<string, array<int, string>>
     */
    private function transitionMatrix(): array
    {
        $matrix = [];

        foreach (JobApplicationStatus::cases() as $status) {
            $matrix[$status->value] = array_map(
                fn (JobApplicationStatus $target): string => $target->value,
                $status->allowedEmployerTransitions()
            );
        }

        return $matrix;
    }

    /**
     * The human readable label of each status.
     *
     * @return array<string, string>
     */
    private function statusLabels(): array
    {
        $labels = [];

        foreach (JobApplicationStatus::cases() as $status) {
            $labels[$status->value] = $status->label();
        }

        return $labels;
    }

    /**
     * A zeroed count for every status.
     *
     * @return array<string, int>
     */
    private function statusCounts(): array
    {
        $counts = [];

        foreach (JobApplicationStatus::cases() as $status) {
            $counts[$status->value] = 0;
        }

        return $counts;
    }
}
