<?php

namespace App\Services\Employer;

use App\Enums\AuditAction;
use App\Enums\EmployerStatus;
use App\Enums\RoleSlug;
use App\Models\EmployerOnboardingRequest;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use App\Services\Authorization\RoleAssignmentService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EmployerOnboardingService
{
    public function __construct(
        private readonly RoleAssignmentService $roleAssignmentService,
        private readonly AuditLogService $auditLogService,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function submit(User $user, array $data): EmployerOnboardingRequest
    {
        return DB::transaction(function () use ($user, $data): EmployerOnboardingRequest {
            $locked = User::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status !== null && $locked->status !== EmployerStatus::Rejected) {
                throw ValidationException::withMessages([
                    'status' => 'This account already has an employer application in progress.',
                ]);
            }

            $request = $locked->employerOnboardingRequest()->updateOrCreate(
                [],
                [
                    'company_name' => $data['company_name'],
                    'company_website' => $data['company_website'] ?? null,
                    'job_title' => $data['job_title'],
                    'message' => $data['message'] ?? null,
                    'submitted_at' => now(),
                    'reviewed_at' => null,
                    'reviewed_by_id' => null,
                    'review_note' => null,
                ],
            );

            $locked->status = EmployerStatus::Pending;
            $locked->suspension_reason = null;
            $locked->save();

            return $request;
        });
    }

    public function approve(User $admin, User $applicant): void
    {
        DB::transaction(function () use ($admin, $applicant): void {
            $locked = $this->lockApplicant($applicant);

            if ($locked->status !== EmployerStatus::Pending) {
                throw ValidationException::withMessages([
                    'status' => 'Only pending employer requests can be approved.',
                ]);
            }

            $previousStatus = $locked->status->value;

            $locked->status = EmployerStatus::Approved;
            $locked->suspension_reason = null;
            $locked->save();

            $request = EmployerOnboardingRequest::query()
                ->where('user_id', $locked->id)
                ->first();

            if ($request !== null) {
                $request->update([
                    'reviewed_at' => now(),
                    'reviewed_by_id' => $admin->id,
                    'review_note' => null,
                ]);
            }

            $this->roleAssignmentService->assignRoleBySlug($locked, RoleSlug::Employer);

            $this->auditLogService->record(AuditAction::EmployerApproved, $locked, [
                'previous_status' => $previousStatus,
                'granted_role' => RoleSlug::Employer->value,
            ], $admin);
        });
    }

    public function reject(User $admin, User $applicant, string $reason): void
    {
        DB::transaction(function () use ($admin, $applicant, $reason): void {
            $locked = $this->lockApplicant($applicant);

            if ($locked->status !== EmployerStatus::Pending) {
                throw ValidationException::withMessages([
                    'status' => 'Only pending employer requests can be rejected.',
                ]);
            }

            $request = EmployerOnboardingRequest::query()
                ->where('user_id', $locked->id)
                ->first();

            if ($request === null) {
                throw ValidationException::withMessages([
                    'status' => 'This account has no employer onboarding request to reject.',
                ]);
            }

            $previousStatus = $locked->status->value;

            $locked->status = EmployerStatus::Rejected;
            $locked->suspension_reason = null;
            $locked->save();

            $request->update([
                'reviewed_at' => now(),
                'reviewed_by_id' => $admin->id,
                'review_note' => $reason,
            ]);

            $this->auditLogService->record(AuditAction::EmployerRejected, $locked, [
                'previous_status' => $previousStatus,
                'reason' => $reason,
            ], $admin);
        });
    }

    public function suspend(User $admin, User $applicant, string $reason): void
    {
        DB::transaction(function () use ($admin, $applicant, $reason): void {
            $locked = $this->lockApplicant($applicant);

            if ($locked->status !== EmployerStatus::Approved) {
                throw ValidationException::withMessages([
                    'status' => 'Only approved employer accounts can be suspended.',
                ]);
            }

            $previousStatus = $locked->status->value;

            $locked->status = EmployerStatus::Suspended;
            $locked->suspension_reason = $reason;
            $locked->save();

            $this->auditLogService->record(AuditAction::EmployerSuspended, $locked, [
                'previous_status' => $previousStatus,
                'reason' => $reason,
            ], $admin);
        });
    }

    public function reactivate(User $admin, User $applicant): void
    {
        DB::transaction(function () use ($admin, $applicant): void {
            $locked = $this->lockApplicant($applicant);

            if ($locked->status !== EmployerStatus::Suspended) {
                throw ValidationException::withMessages([
                    'status' => 'Only suspended employer accounts can be reactivated.',
                ]);
            }

            $previousStatus = $locked->status->value;

            $locked->status = EmployerStatus::Approved;
            $locked->suspension_reason = null;
            $locked->save();

            $this->roleAssignmentService->assignRoleBySlug($locked, RoleSlug::Employer);

            $this->auditLogService->record(AuditAction::EmployerReactivated, $locked, [
                'previous_status' => $previousStatus,
            ], $admin);
        });
    }

    private function lockApplicant(User $applicant): User
    {
        return User::query()->whereKey($applicant->getKey())->lockForUpdate()->firstOrFail();
    }
}
