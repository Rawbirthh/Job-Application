<?php

namespace App\Http\Controllers\Admin;

use App\Enums\EmployerStatus;
use App\Enums\PermissionSlug;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RejectEmployerRequest;
use App\Http\Requests\Admin\SuspendEmployerRequest;
use App\Models\Company;
use App\Models\User;
use App\Services\Employer\EmployerOnboardingService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class EmployerController extends Controller
{
    public function __construct(
        private readonly EmployerOnboardingService $employerOnboardingService,
    ) {}

    /**
     * Display the admin employer management index.
     */
    public function index(Request $request): Response
    {
        $this->authorize('employers.viewAny');

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::enum(EmployerStatus::class)],
        ]);

        $search = trim((string) ($validated['search'] ?? ''));
        $statusFilter = $validated['status'] ?? null;
        $prefix = $search === '' ? null : $this->escapeLike($search).'%';
        $actor = $request->user();

        /** @var Builder<User> $query */
        $query = User::query()
            ->employerApplicants()
            ->select(['users.id', 'users.name', 'users.email', 'users.status', 'users.suspension_reason'])
            ->with([
                'employerOnboardingRequest' => function ($query): void {
                    $query->select([
                        'id',
                        'user_id',
                        'company_name',
                        'submitted_at',
                        'reviewed_at',
                        'review_note',
                    ]);
                },
                'companies',
            ]);

        if ($prefix !== null) {
            $query->where(function (Builder $query) use ($prefix): void {
                $query->where('users.name', 'like', $prefix)
                    ->orWhere('users.email', 'like', $prefix);
            });
        }

        if ($statusFilter !== null) {
            $query->where('users.status', $statusFilter);
        }

        /** @var LengthAwarePaginator<int, User> $users */
        $users = $query
            ->orderByRaw("CASE users.status WHEN 'pending' THEN 0 WHEN 'approved' THEN 1 WHEN 'rejected' THEN 2 WHEN 'suspended' THEN 3 ELSE 4 END")
            ->orderBy('users.name')
            ->orderBy('users.id')
            ->paginate(25)
            ->withQueryString();

        $users->through(function (User $user) use ($actor): array {
            $status = $user->status?->value;

            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'status' => $status,
                'status_badge' => $status ?? 'not_applied',
                'suspension_reason' => $user->status === EmployerStatus::Suspended
                    ? $user->suspension_reason
                    : null,
                'onboarding_request' => $user->employerOnboardingRequest === null ? null : [
                    'company_name' => $user->employerOnboardingRequest->company_name,
                    'submitted_at' => $user->employerOnboardingRequest->submitted_at?->toIso8601String(),
                    'reviewed_at' => $user->employerOnboardingRequest->reviewed_at?->toIso8601String(),
                    'review_note' => $user->employerOnboardingRequest->review_note,
                ],
                'company' => $this->companySummary($user->companies->first()),
                'can_manage' => $actor instanceof User
                    && $actor->hasPermission(PermissionSlug::EmployersManage),
            ];
        });

        return Inertia::render('admin/employers/index', [
            'employers' => $users,
            'filters' => ['search' => $search, 'status' => $statusFilter],
            'canManageEmployers' => $actor instanceof User
                && $actor->hasPermission(PermissionSlug::EmployersManage),
        ]);
    }

    /**
     * Display the specified user's employer profile.
     */
    public function show(Request $request, User $user): Response
    {
        $this->authorize('employers.view', $user);

        /** @var Builder<User> $employerQuery */
        $employerQuery = User::query()
            ->employerApplicants()
            ->select(['users.id', 'users.name', 'users.email', 'users.status', 'users.suspension_reason', 'users.created_at'])
            ->with([
                'employerOnboardingRequest' => function ($query): void {
                    $query->select([
                        'id',
                        'user_id',
                        'company_name',
                        'company_website',
                        'job_title',
                        'message',
                        'submitted_at',
                        'reviewed_at',
                        'reviewed_by_id',
                        'review_note',
                    ])->with('reviewer:id,name');
                },
                'roles:id,slug,name',
            ]);

        if (! User::query()->employerApplicants()->whereKey($user->id)->exists()) {
            abort(404);
        }

        $employer = $user;
        $employer->loadMissing('employerOnboardingRequest.reviewer', 'roles', 'companies');

        $actor = $request->user();
        $status = $employer->status?->value;

        $onboardingRequest = $employer->employerOnboardingRequest;

        return Inertia::render('admin/employers/show', [
            'employer' => [
                'id' => $employer->id,
                'name' => $employer->name,
                'email' => $employer->email,
                'status' => $status,
                'status_badge' => $status ?? 'not_applied',
                'suspension_reason' => $employer->status === EmployerStatus::Suspended
                    ? $employer->suspension_reason
                    : null,
                'onboarding_request' => $onboardingRequest === null ? null : [
                    'company_name' => $onboardingRequest->company_name,
                    'company_website' => $onboardingRequest->company_website,
                    'job_title' => $onboardingRequest->job_title,
                    'message' => $onboardingRequest->message,
                    'submitted_at' => $onboardingRequest->submitted_at?->toIso8601String(),
                    'reviewed_at' => $onboardingRequest->reviewed_at?->toIso8601String(),
                    'review_note' => $onboardingRequest->review_note,
                    'reviewer' => $onboardingRequest->reviewer === null ? null : [
                        'id' => $onboardingRequest->reviewer->id,
                        'name' => $onboardingRequest->reviewer->name,
                    ],
                ],
                'company' => $this->companySummary($employer->companies->first()),
                'roles' => $employer->roles->map(function ($role): array {
                    return [
                        'id' => $role->id,
                        'slug' => $role->slug,
                        'name' => $role->name,
                    ];
                })->values()->all(),
                'actions' => [
                    'approve' => $employer->status === EmployerStatus::Pending,
                    'reject' => $employer->status === EmployerStatus::Pending,
                    'suspend' => $employer->status === EmployerStatus::Approved,
                    'reactivate' => $employer->status === EmployerStatus::Suspended,
                ],
                'can_manage' => $actor instanceof User
                    && $actor->hasPermission(PermissionSlug::EmployersManage),
            ],
            'canManageEmployers' => $actor instanceof User
                && $actor->hasPermission(PermissionSlug::EmployersManage),
        ]);
    }

    /**
     * Approve an employer request.
     */
    public function approve(Request $request, User $user): RedirectResponse
    {
        $this->authorize('employers.review', $user);

        $employer = $this->resolveEmployer($user);

        $this->employerOnboardingService->approve($request->user(), $employer);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Employer approved.',
        ]);

        return to_route('admin.employers.show', $employer);
    }

    /**
     * Reject an employer request.
     */
    public function reject(RejectEmployerRequest $request, User $user): RedirectResponse
    {
        $this->authorize('employers.review', $user);

        $employer = $this->resolveEmployer($user);

        $this->employerOnboardingService->reject(
            $request->user(),
            $employer,
            $request->validated('reason'),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Employer rejected.',
        ]);

        return to_route('admin.employers.show', $employer);
    }

    /**
     * Suspend an approved employer.
     */
    public function suspend(SuspendEmployerRequest $request, User $user): RedirectResponse
    {
        $this->authorize('employers.suspend', $user);

        $employer = $this->resolveEmployer($user);

        $this->employerOnboardingService->suspend(
            $request->user(),
            $employer,
            $request->validated('reason'),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Employer suspended.',
        ]);

        return to_route('admin.employers.show', $employer);
    }

    /**
     * Reactivate a suspended employer.
     */
    public function reactivate(Request $request, User $user): RedirectResponse
    {
        $this->authorize('employers.reactivate', $user);

        $employer = $this->resolveEmployer($user);

        $this->employerOnboardingService->reactivate($request->user(), $employer);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Employer reactivated.',
        ]);

        return to_route('admin.employers.show', $employer);
    }

    /**
     * Resolve an employer through the applicant scope or 404.
     */
    private function resolveEmployer(User $user): User
    {
        if (! User::query()->employerApplicants()->whereKey($user->id)->exists()) {
            abort(404);
        }

        return $user;
    }

    /**
     * Escape LIKE wildcards.
     */
    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }

    /**
     * Build the company summary exposed on the admin employer payloads.
     *
     * @return array<string, mixed>|null
     */
    private function companySummary(?Company $company): ?array
    {
        if ($company === null) {
            return null;
        }

        return [
            'id' => $company->id,
            'name' => $company->name,
            'slug' => $company->slug,
            'overview' => $company->overview,
            'industry' => $company->industry,
            'website' => $company->website,
            'size_band' => $company->size_band,
            'location' => $company->location,
            'logo_url' => $company->logo_url,
            'is_active' => $company->is_active,
            'created_at' => $company->created_at?->toIso8601String(),
            'updated_at' => $company->updated_at?->toIso8601String(),
        ];
    }
}
