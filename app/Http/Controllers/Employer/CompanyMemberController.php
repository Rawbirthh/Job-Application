<?php

namespace App\Http\Controllers\Employer;

use App\Enums\CompanyMemberRole;
use App\Enums\CompanyMemberStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Employer\StoreCompanyMemberRequest;
use App\Http\Requests\Employer\UpdateCompanyMemberRequest;
use App\Models\Company;
use App\Models\CompanyMember;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class CompanyMemberController extends Controller
{
    public function index(Company $company): Response
    {
        $this->authorize('viewMembers', $company);

        $members = CompanyMember::query()
            ->where('company_id', $company->id)
            ->with('user:id,name,email')
            ->orderBy('created_at')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('employer/companies/members', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
            ],
            'members' => $members->through(fn (CompanyMember $member): array => [
                'id' => $member->id,
                'user_id' => $member->user_id,
                'name' => $member->user?->name,
                'email' => $member->user?->email,
                'company_role' => $member->company_role->value,
                'status' => $member->status->value,
                'created_at' => $member->created_at?->toIso8601String(),
            ]),
            'canManage' => true,
            'roles' => ['owner', 'recruiter', 'reviewer'],
        ]);
    }

    public function store(StoreCompanyMemberRequest $request, Company $company, NotificationService $notificationService): RedirectResponse
    {
        $this->authorize('manageMembers', $company);

        /** @var User $actor */
        $actor = $request->user();

        $validated = $request->validated();
        $target = User::where('email', $validated['email'])->firstOrFail();

        $role = $validated['company_role'];

        try {
            DB::transaction(function () use ($company, $target, $validated, $actor): void {
                $existing = CompanyMember::query()
                    ->where('company_id', $company->id)
                    ->where('user_id', $target->id)
                    ->lockForUpdate()
                    ->first();

                if ($existing !== null && in_array($existing->status->value, ['active', 'pending'], true)) {
                    throw ValidationException::withMessages([
                        'email' => __('This user is already a member of the company.'),
                    ]);
                }

                if ($existing !== null) {
                    $existing->fill([
                        'company_role' => CompanyMemberRole::from($validated['company_role']),
                        'status' => CompanyMemberStatus::Active,
                        'invited_by_user_id' => $actor->id,
                        'invited_at' => now(),
                        'accepted_at' => now(),
                        'revoked_at' => null,
                    ]);
                    $existing->save();
                } else {
                    CompanyMember::create([
                        'company_id' => $company->id,
                        'user_id' => $target->id,
                        'company_role' => CompanyMemberRole::from($validated['company_role']),
                        'status' => CompanyMemberStatus::Active,
                        'invited_by_user_id' => $actor->id,
                        'invited_at' => now(),
                        'accepted_at' => now(),
                    ]);
                }
            });
        } catch (QueryException $exception) {
            throw ValidationException::withMessages([
                'email' => __('This user is already a member of the company.'),
            ]);
        }

        $notificationService->companyMemberAdded($target, $company, $role);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Company member added.'),
        ]);

        return to_route('employer.companies.members.index', $company);
    }

    public function update(UpdateCompanyMemberRequest $request, Company $company, CompanyMember $member, NotificationService $notificationService): RedirectResponse
    {
        $this->authorize('manageMembers', $company);

        if ((int) $member->company_id !== (int) $company->id) {
            abort(404);
        }

        $newRole = $request->validated()['company_role'];
        $oldRole = $member->company_role->value;

        DB::transaction(function () use ($company, $member, $newRole): void {
            $locked = CompanyMember::query()
                ->where('id', $member->id)
                ->lockForUpdate()
                ->firstOrFail();

            $currentRole = $locked->company_role->value;

            $status = $locked->status->value;

            if ($status === 'revoked') {
                throw ValidationException::withMessages([
                    'company_role' => __('Revoked members cannot be modified. Reactivate the member first.'),
                ]);
            }

            if ((int) $locked->user_id === (int) $company->owner_user_id) {
                throw ValidationException::withMessages([
                    'company_role' => __('The primary company owner cannot be modified.'),
                ]);
            }

            if ($currentRole === 'owner' && $status === 'active' && $newRole !== 'owner') {
                $activeOwnerCount = CompanyMember::query()
                    ->where('company_id', $company->id)
                    ->where('company_role', 'owner')
                    ->where('status', 'active')
                    ->lockForUpdate()
                    ->count();

                if ($activeOwnerCount <= 1) {
                    throw ValidationException::withMessages([
                        'company_role' => __('Cannot demote the last owner.'),
                    ]);
                }
            }

            $locked->company_role = CompanyMemberRole::from($newRole);
            $locked->save();
        });

        if ($oldRole !== $newRole) {
            $member->refresh();
            $notificationService->companyMemberRoleChanged($member->user, $company, $oldRole, $newRole);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Company member updated.'),
        ]);

        return to_route('employer.companies.members.index', $company);
    }

    public function destroy(Company $company, CompanyMember $member, NotificationService $notificationService): RedirectResponse
    {
        $this->authorize('manageMembers', $company);

        if ((int) $member->company_id !== (int) $company->id) {
            abort(404);
        }

        $memberUser = $member->user;

        DB::transaction(function () use ($company, $member): void {
            $locked = CompanyMember::query()
                ->where('id', $member->id)
                ->lockForUpdate()
                ->firstOrFail();

            $currentRole = $locked->company_role->value;

            $status = $locked->status->value;

            if ((int) $locked->user_id === (int) $company->owner_user_id) {
                throw ValidationException::withMessages([
                    'company_role' => __('The primary company owner cannot be removed.'),
                ]);
            }

            if ($currentRole === 'owner' && $status === 'active') {
                $activeOwnerCount = CompanyMember::query()
                    ->where('company_id', $company->id)
                    ->where('company_role', 'owner')
                    ->where('status', 'active')
                    ->lockForUpdate()
                    ->count();

                if ($activeOwnerCount <= 1) {
                    throw ValidationException::withMessages([
                        'company_role' => __('Cannot remove the last owner.'),
                    ]);
                }
            }

            $locked->status = CompanyMemberStatus::Revoked;
            $locked->revoked_at = now();
            $locked->save();
        });

        if ($memberUser instanceof User) {
            $notificationService->companyMemberRevoked($memberUser, $company);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Company member revoked.'),
        ]);

        return to_route('employer.companies.members.index', $company);
    }
}
