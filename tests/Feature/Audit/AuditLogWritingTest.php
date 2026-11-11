<?php

namespace Tests\Feature\Audit;

use App\Enums\AuditAction;
use App\Enums\AuditTargetType;
use App\Enums\PermissionSlug;
use App\Enums\RoleSlug;
use App\Exceptions\AuditLogImmutableException;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\CompanyMember;
use App\Models\Job;
use App\Models\Permission;
use App\Models\Report;
use App\Models\Role;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\Feature\Authorization\Concerns\InteractsWithAuthorizationCatalogue;
use Tests\TestCase;

class AuditLogWritingTest extends TestCase
{
    use InteractsWithAuthorizationCatalogue, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAuthorizationCatalogue();
    }

    // ---------------------------------------------------------------- helpers

    /**
     * Create a job posting that is waiting for moderation.
     */
    private function pendingJob(): Job
    {
        $owner = User::factory()->approvedEmployer()->create();
        $company = Company::factory()->for($owner, 'owner')->create();

        return Job::factory()->for($company)->create([
            'status' => 'pending_review',
            'title' => 'Senior Backend Engineer',
        ]);
    }

    /**
     * Create an applicant that has submitted an employer onboarding request.
     */
    private function pendingApplicant(string $email = 'applicant@example.com'): User
    {
        $applicant = User::factory()->jobSeeker()->create(['email' => $email]);

        $this->actingAs($applicant)
            ->post(route('employer.onboarding.store'), [
                'company_name' => 'Acme Corp',
                'job_title' => 'Founder',
            ])
            ->assertSessionHasNoErrors();

        return $applicant->fresh();
    }

    /**
     * Seed a report row explicitly — there is intentionally no Report factory.
     */
    private function pendingReport(): Report
    {
        $reporter = User::factory()->jobSeeker()->create();
        $job = $this->pendingJob();

        return Report::query()->create([
            'reporter_id' => $reporter->id,
            'job_posting_id' => $job->id,
            'company_id' => null,
            'subject_key' => Report::subjectKeyFor($job->id, null),
            'reason' => 'spam',
            'status' => 'pending',
        ]);
    }

    /**
     * Fetch the single audit entry written for the given action.
     */
    private function entryFor(AuditAction $action): AuditLog
    {
        $entries = AuditLog::query()
            ->where('action', $action->value)
            ->get();

        $this->assertCount(1, $entries, sprintf(
            'Expected exactly one [%s] audit entry, found %d.',
            $action->value,
            $entries->count(),
        ));

        return $entries->first();
    }

    // ------------------------------------------------------- employer review

    public function test_employer_approval_writes_exactly_one_employer_approved_entry(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $applicant = $this->pendingApplicant();

        $this->actingAs($admin)
            ->post(route('admin.employers.approve', $applicant))
            ->assertSessionHasNoErrors();

        $this->assertSame(1, AuditLog::query()->count());

        $entry = $this->entryFor(AuditAction::EmployerApproved);

        $this->assertSame($admin->id, $entry->actor_id);
        $this->assertSame($admin->email, $entry->actor_email);
        $this->assertSame(AuditTargetType::User->value, $entry->target_type);
        $this->assertSame($applicant->id, $entry->target_id);
        $this->assertSame($applicant->email, $entry->target_label);
        $this->assertSame(['previous_status', 'granted_role'], array_keys($entry->metadata));
        $this->assertSame('pending', $entry->metadata['previous_status']);
        $this->assertSame(RoleSlug::Employer->value, $entry->metadata['granted_role']);
        $this->assertNotNull($entry->request_id);
        $this->assertNotNull($entry->ip_address);
    }

    public function test_employer_rejection_writes_an_employer_rejected_entry_with_the_reason(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $applicant = $this->pendingApplicant();

        $this->actingAs($admin)
            ->post(route('admin.employers.reject', $applicant), [
                'reason' => 'Incomplete company details',
            ])
            ->assertSessionHasNoErrors();

        $entry = $this->entryFor(AuditAction::EmployerRejected);

        $this->assertSame($admin->id, $entry->actor_id);
        $this->assertSame(AuditTargetType::User->value, $entry->target_type);
        $this->assertSame($applicant->id, $entry->target_id);
        $this->assertSame('pending', $entry->metadata['previous_status']);
        $this->assertSame('Incomplete company details', $entry->metadata['reason']);
    }

    public function test_employer_suspension_writes_an_employer_suspended_entry_with_the_reason(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $applicant = $this->pendingApplicant();

        $this->actingAs($admin)
            ->post(route('admin.employers.approve', $applicant))
            ->assertSessionHasNoErrors();

        $this->actingAs($admin)
            ->post(route('admin.employers.suspend', $applicant), [
                'reason' => 'Repeated policy violations',
            ])
            ->assertSessionHasNoErrors();

        $entry = $this->entryFor(AuditAction::EmployerSuspended);

        $this->assertSame($admin->id, $entry->actor_id);
        $this->assertSame(AuditTargetType::User->value, $entry->target_type);
        $this->assertSame($applicant->id, $entry->target_id);
        $this->assertSame('approved', $entry->metadata['previous_status']);
        $this->assertSame('Repeated policy violations', $entry->metadata['reason']);
    }

    public function test_employer_reactivation_writes_an_employer_reactivated_entry(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $applicant = $this->pendingApplicant();

        $this->actingAs($admin)
            ->post(route('admin.employers.approve', $applicant))
            ->assertSessionHasNoErrors();

        $this->actingAs($admin)
            ->post(route('admin.employers.suspend', $applicant), [
                'reason' => 'Repeated policy violations',
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($admin)
            ->post(route('admin.employers.reactivate', $applicant))
            ->assertSessionHasNoErrors();

        $entry = $this->entryFor(AuditAction::EmployerReactivated);

        $this->assertSame($admin->id, $entry->actor_id);
        $this->assertSame(AuditTargetType::User->value, $entry->target_type);
        $this->assertSame($applicant->id, $entry->target_id);
        $this->assertSame(['previous_status'], array_keys($entry->metadata));
        $this->assertSame('suspended', $entry->metadata['previous_status']);
    }

    public function test_an_invalid_employer_transition_writes_no_audit_entry(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $alreadyApproved = User::factory()->approvedEmployer()->create();
        $pending = $this->pendingApplicant('still-pending@example.com');

        // Approving an employer that is not pending is rejected.
        $this->actingAs($admin)
            ->post(route('admin.employers.approve', $alreadyApproved))
            ->assertSessionHasErrors('status');

        // Suspending an employer that was never approved is rejected too.
        $this->actingAs($admin)
            ->post(route('admin.employers.suspend', $pending), [
                'reason' => 'Not an approved employer',
            ])
            ->assertSessionHasErrors('status');

        $this->assertSame(0, AuditLog::query()->count());
    }

    // -------------------------------------------------------- job moderation

    public function test_job_approval_writes_a_job_approved_entry(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $job = $this->pendingJob();

        $this->actingAs($admin)
            ->post(route('admin.jobs.approve', $job))
            ->assertRedirect(route('admin.jobs.index'));

        $entry = $this->entryFor(AuditAction::JobApproved);

        $this->assertSame($admin->id, $entry->actor_id);
        $this->assertSame(AuditTargetType::JobPosting->value, $entry->target_type);
        $this->assertSame($job->id, $entry->target_id);
        $this->assertSame($job->title, $entry->target_label);
        $this->assertSame('pending_review', $entry->metadata['previous_status']);
    }

    public function test_job_rejection_writes_a_job_rejected_entry_with_the_reason(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $job = $this->pendingJob();

        $this->actingAs($admin)
            ->post(route('admin.jobs.reject', $job), [
                'rejection_reason' => 'Violation of community standards.',
            ])
            ->assertRedirect(route('admin.jobs.index'));

        $entry = $this->entryFor(AuditAction::JobRejected);

        $this->assertSame($admin->id, $entry->actor_id);
        $this->assertSame(AuditTargetType::JobPosting->value, $entry->target_type);
        $this->assertSame($job->id, $entry->target_id);
        $this->assertSame($job->title, $entry->target_label);
        $this->assertSame('pending_review', $entry->metadata['previous_status']);
        $this->assertSame('Violation of community standards.', $entry->metadata['rejection_reason']);
    }

    public function test_job_closure_writes_a_job_closed_entry(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $job = $this->pendingJob();

        $this->actingAs($admin)
            ->post(route('admin.jobs.close', $job))
            ->assertRedirect();

        $entry = $this->entryFor(AuditAction::JobClosed);

        $this->assertSame($admin->id, $entry->actor_id);
        $this->assertSame(AuditTargetType::JobPosting->value, $entry->target_type);
        $this->assertSame($job->id, $entry->target_id);
        $this->assertSame($job->title, $entry->target_label);
        $this->assertSame('pending_review', $entry->metadata['previous_status']);
    }

    public function test_approving_a_job_that_is_not_pending_review_writes_no_audit_entry(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $owner = User::factory()->approvedEmployer()->create();
        $company = Company::factory()->for($owner, 'owner')->create();
        $job = Job::factory()->for($company)->create(['status' => 'published']);

        $this->actingAs($admin)
            ->from(route('admin.jobs.index'))
            ->post(route('admin.jobs.approve', $job))
            ->assertRedirect(route('admin.jobs.index'));

        $this->assertSame(0, AuditLog::query()->count());
    }

    // ------------------------------------------------------ report moderation

    public function test_report_investigation_writes_a_report_investigated_entry(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $report = $this->pendingReport();

        $this->actingAs($admin)
            ->from(route('admin.reports.show', $report))
            ->post(route('admin.reports.investigate', $report))
            ->assertRedirect(route('admin.reports.show', $report));

        $entry = $this->entryFor(AuditAction::ReportInvestigated);

        $this->assertSame($admin->id, $entry->actor_id);
        $this->assertSame(AuditTargetType::Report->value, $entry->target_type);
        $this->assertSame($report->id, $entry->target_id);
        $this->assertSame('pending', $entry->metadata['previous_status']);
    }

    public function test_report_resolution_writes_a_report_resolved_entry_with_the_resolution_action(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $report = $this->pendingReport();

        $this->actingAs($admin)
            ->post(route('admin.reports.resolve', $report), [
                'resolution_action' => 'content_removed',
                'resolution_message' => 'The listing was removed.',
            ])
            ->assertRedirect(route('admin.reports.index'));

        $entry = $this->entryFor(AuditAction::ReportResolved);

        $this->assertSame($admin->id, $entry->actor_id);
        $this->assertSame(AuditTargetType::Report->value, $entry->target_type);
        $this->assertSame($report->id, $entry->target_id);
        $this->assertSame('pending', $entry->metadata['previous_status']);
        $this->assertSame('content_removed', $entry->metadata['resolution_action']);
    }

    public function test_report_dismissal_writes_a_report_dismissed_entry(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $report = $this->pendingReport();

        $this->actingAs($admin)
            ->post(route('admin.reports.dismiss', $report), [
                'internal_note' => 'No evidence of wrongdoing.',
            ])
            ->assertRedirect(route('admin.reports.index'));

        $entry = $this->entryFor(AuditAction::ReportDismissed);

        $this->assertSame($admin->id, $entry->actor_id);
        $this->assertSame(AuditTargetType::Report->value, $entry->target_type);
        $this->assertSame($report->id, $entry->target_id);
        $this->assertSame('pending', $entry->metadata['previous_status']);
    }

    // ------------------------------------------------------- role management

    public function test_role_management_writes_audit_entries(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $jobsModerate = Permission::query()
            ->where('slug', PermissionSlug::JobsModerate->value)
            ->firstOrFail();
        $reportsModerate = Permission::query()
            ->where('slug', PermissionSlug::ReportsModerate->value)
            ->firstOrFail();

        $this->actingAs($admin)
            ->post(route('admin.roles.store'), [
                'name' => 'Content Editor',
                'slug' => 'content-editor',
                'description' => 'Can edit content',
                'permissions' => [$jobsModerate->id],
            ])
            ->assertSessionHasNoErrors();

        $role = Role::query()->where('slug', 'content-editor')->firstOrFail();

        $created = $this->entryFor(AuditAction::RoleCreated);
        $this->assertSame($admin->id, $created->actor_id);
        $this->assertSame(AuditTargetType::Role->value, $created->target_type);
        $this->assertSame($role->id, $created->target_id);
        $this->assertSame('content-editor', $created->target_label);
        $this->assertSame('content-editor', $created->metadata['slug']);
        $this->assertSame([PermissionSlug::JobsModerate->value], $created->metadata['permissions']);

        $this->actingAs($admin)
            ->put(route('admin.roles.update', $role), [
                'name' => 'Content Editor',
                'description' => 'Can edit content',
                'permissions' => [$jobsModerate->id, $reportsModerate->id],
            ])
            ->assertSessionHasNoErrors();

        $updated = $this->entryFor(AuditAction::RoleUpdated);
        $this->assertSame($role->id, $updated->target_id);
        $this->assertSame([PermissionSlug::ReportsModerate->value], $updated->metadata['permissions_added']);
        $this->assertSame([], $updated->metadata['permissions_removed']);

        $this->actingAs($admin)
            ->delete(route('admin.roles.destroy', $role))
            ->assertRedirect(route('admin.roles.index'));

        $deleted = $this->entryFor(AuditAction::RoleDeleted);
        $this->assertSame($role->id, $deleted->target_id);
        $this->assertSame('content-editor', $deleted->target_label);
        $this->assertSame('content-editor', $deleted->metadata['slug']);
    }

    public function test_permission_management_writes_audit_entries_with_the_slug(): void
    {
        $admin = User::factory()->platformAdmin()->create();

        $this->actingAs($admin)
            ->post(route('admin.permissions.store'), [
                'name' => 'View Reports',
                'slug' => 'reports.view',
                'description' => 'Can view platform reports',
            ])
            ->assertSessionHasNoErrors();

        $permission = Permission::query()->where('slug', 'reports.view')->firstOrFail();

        $created = $this->entryFor(AuditAction::PermissionCreated);
        $this->assertSame($admin->id, $created->actor_id);
        $this->assertSame(AuditTargetType::Permission->value, $created->target_type);
        $this->assertSame($permission->id, $created->target_id);
        $this->assertSame('reports.view', $created->target_label);
        $this->assertSame('reports.view', $created->metadata['slug']);

        $this->actingAs($admin)
            ->put(route('admin.permissions.update', $permission), [
                'name' => 'View Moderation Reports',
                'description' => 'Can view platform reports',
            ])
            ->assertSessionHasNoErrors();

        $updated = $this->entryFor(AuditAction::PermissionUpdated);
        $this->assertSame($permission->id, $updated->target_id);
        $this->assertSame('reports.view', $updated->metadata['slug']);

        $this->actingAs($admin)
            ->delete(route('admin.permissions.destroy', $permission))
            ->assertRedirect(route('admin.permissions.index'));

        $deleted = $this->entryFor(AuditAction::PermissionDeleted);
        $this->assertSame($permission->id, $deleted->target_id);
        $this->assertSame('reports.view', $deleted->metadata['slug']);
    }

    // --------------------------------------------------------- role assignment

    public function test_user_role_sync_writes_a_user_roles_updated_entry(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $target = User::factory()->jobSeeker()->create();

        $this->actingAs($admin)
            ->put(route('admin.users.roles.update', $target), [
                'roles' => [$this->roleId(RoleSlug::Employer)],
            ])
            ->assertSessionHasNoErrors();

        $entry = $this->entryFor(AuditAction::UserRolesUpdated);

        $this->assertSame($admin->id, $entry->actor_id);
        $this->assertSame($admin->email, $entry->actor_email);
        $this->assertSame(AuditTargetType::User->value, $entry->target_type);
        $this->assertSame($target->id, $entry->target_id);
        $this->assertSame([RoleSlug::Employer->value], $entry->metadata['roles_added']);
        $this->assertSame([RoleSlug::JobSeeker->value], $entry->metadata['roles_removed']);
    }

    public function test_the_console_role_assignment_writes_an_entry_without_an_actor(): void
    {
        User::factory()->create(['email' => 'operator@example.com']);

        $this->artisan('auth:assign-role', ['email' => 'operator@example.com'])
            ->assertSuccessful();

        $entry = $this->entryFor(AuditAction::UserRolesUpdated);

        $this->assertNull($entry->actor_id);
        $this->assertNull($entry->actor_email);
        $this->assertNull($entry->request_id);
        $this->assertNull($entry->ip_address);
        $this->assertSame('console', $entry->metadata['source']);
        $this->assertSame([RoleSlug::PlatformAdmin->value], $entry->metadata['roles_added']);
    }

    // -------------------------------------------------------- company members

    public function test_company_member_changes_write_audit_entries(): void
    {
        $owner = User::factory()->approvedEmployer()->create();
        $company = Company::factory()->for($owner, 'owner')->create();
        $member = User::factory()->approvedEmployer()->create();

        $this->actingAs($owner)
            ->post(route('employer.companies.members.store', $company), [
                'email' => $member->email,
                'company_role' => 'recruiter',
            ])
            ->assertSessionHasNoErrors();

        $added = $this->entryFor(AuditAction::CompanyMemberAdded);
        $this->assertSame($owner->id, $added->actor_id);
        $this->assertSame(AuditTargetType::CompanyMember->value, $added->target_type);
        $this->assertSame($company->id, $added->metadata['company_id']);
        $this->assertSame($member->id, $added->metadata['member_user_id']);
        $this->assertSame('recruiter', $added->metadata['company_role']);

        $companyMember = CompanyMember::query()
            ->where('company_id', $company->id)
            ->where('user_id', $member->id)
            ->firstOrFail();

        $this->actingAs($owner)
            ->patch(route('employer.companies.members.update', [$company, $companyMember]), [
                'company_role' => 'reviewer',
            ])
            ->assertSessionHasNoErrors();

        $roleChanged = $this->entryFor(AuditAction::CompanyMemberRoleChanged);
        $this->assertSame(AuditTargetType::CompanyMember->value, $roleChanged->target_type);
        $this->assertSame('recruiter', $roleChanged->metadata['old_role']);
        $this->assertSame('reviewer', $roleChanged->metadata['new_role']);

        $this->actingAs($owner)
            ->delete(route('employer.companies.members.destroy', [$company, $companyMember]))
            ->assertSessionHasNoErrors();

        $revoked = $this->entryFor(AuditAction::CompanyMemberRevoked);
        $this->assertSame(AuditTargetType::CompanyMember->value, $revoked->target_type);
        $this->assertSame($company->id, $revoked->metadata['company_id']);
        $this->assertSame('reviewer', $revoked->metadata['company_role']);
    }

    // ------------------------------------------------------ account deletion

    public function test_account_deletion_writes_an_entry_that_survives_the_deleted_user(): void
    {
        $user = User::factory()->jobSeeker()->create(['email' => 'leaver@example.com']);

        $this->actingAs($user)
            ->delete(route('profile.destroy'), ['password' => 'password'])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('users', ['id' => $user->id]);

        $entry = $this->entryFor(AuditAction::AccountDeleted);

        $this->assertNull($entry->actor_id);
        $this->assertSame('leaver@example.com', $entry->actor_email);
        $this->assertSame(AuditTargetType::User->value, $entry->target_type);
        $this->assertSame($user->id, $entry->target_id);
        $this->assertSame('leaver@example.com', $entry->target_label);
        $this->assertSame(['self_service' => true], $entry->metadata);
    }

    public function test_an_entry_survives_the_deletion_of_its_actor(): void
    {
        $actor = User::factory()->create(['email' => 'departed@example.com']);

        $entry = app(AuditLogService::class)->record(
            AuditAction::JobApproved,
            $this->pendingJob(),
            [],
            $actor,
        );

        $actor->delete();

        $fresh = $entry->fresh();

        $this->assertNotNull($fresh);
        $this->assertNull($fresh->actor_id);
        $this->assertSame('departed@example.com', $fresh->actor_email);
        $this->assertSame(AuditTargetType::JobPosting->value, $fresh->target_type);
    }

    // -------------------------------------------------------- authentication

    public function test_a_successful_login_and_logout_write_authentication_entries(): void
    {
        User::factory()->create(['email' => 'signin@example.com']);

        $this->post(route('login.store'), [
            'email' => 'signin@example.com',
            'password' => 'password',
        ])->assertRedirect(route('dashboard', absolute: false));

        $login = $this->entryFor(AuditAction::AuthLoggedIn);
        $this->assertSame('signin@example.com', $login->actor_email);
        $this->assertSame(AuditTargetType::User->value, $login->target_type);
        $this->assertSame(['remember' => false], $login->metadata);
        $this->assertNotNull($login->request_id);
        $this->assertNotNull($login->ip_address);

        $this->post(route('logout'))->assertRedirect(route('home'));

        $logout = $this->entryFor(AuditAction::AuthLoggedOut);
        $this->assertSame('signin@example.com', $logout->actor_email);
        $this->assertSame(AuditTargetType::User->value, $logout->target_type);
        $this->assertSame($login->target_id, $logout->target_id);
    }

    // --------------------------------------------------------- denied actions

    public function test_a_denied_action_writes_no_audit_entry(): void
    {
        $employer = User::factory()->approvedEmployer()->create();
        $job = $this->pendingJob();

        $this->actingAs($employer)
            ->post(route('admin.jobs.approve', $job))
            ->assertForbidden();

        $this->assertSame(0, AuditLog::query()->count());
    }

    // -------------------------------------------------------------- redaction

    public function test_metadata_is_redacted_and_size_capped(): void
    {
        $service = app(AuditLogService::class);

        $entry = $service->record(AuditAction::JobApproved, $this->pendingJob(), [
            'password' => 'super-secret-value',
            'remember_token' => 'remember-me',
            'resume_path' => 'resumes/cv.pdf',
            'cover_letter' => 'Dear hiring manager',
            'session_id' => 'session-value',
            'note' => str_repeat('a', 500),
            'items' => range(1, 50),
        ]);

        $metadata = $entry->fresh()->metadata;

        $this->assertIsArray($metadata);

        foreach (['password', 'remember_token', 'resume_path', 'cover_letter', 'session_id'] as $denied) {
            $this->assertArrayNotHasKey($denied, $metadata);
        }

        $this->assertSame(['note', 'items'], array_keys($metadata));
        $this->assertSame(200, mb_strlen($metadata['note']));
        $this->assertCount(20, $metadata['items']);

        $raw = DB::table('audit_logs')->where('id', $entry->id)->value('metadata');

        $this->assertIsString($raw);
        $this->assertStringNotContainsString('password', $raw);
        $this->assertStringNotContainsString('super-secret-value', $raw);
        $this->assertStringNotContainsString('remember-me', $raw);
        $this->assertStringNotContainsString('resumes/cv.pdf', $raw);
        $this->assertStringNotContainsString('Dear hiring manager', $raw);
        $this->assertStringNotContainsString('session-value', $raw);
    }

    // ---------------------------------------------------- transaction integrity

    public function test_an_audit_entry_written_inside_a_failed_transaction_leaves_no_orphan_row(): void
    {
        $service = app(AuditLogService::class);
        $job = $this->pendingJob();

        try {
            DB::transaction(function () use ($service, $job): void {
                $service->record(AuditAction::JobApproved, $job);

                throw new RuntimeException('boom');
            });

            $this->fail('The transaction was expected to fail.');
        } catch (RuntimeException $exception) {
            $this->assertSame('boom', $exception->getMessage());
        }

        $this->assertSame(0, AuditLog::query()->count());
    }

    // ------------------------------------------------------------- append only

    public function test_audit_entries_cannot_be_updated_or_deleted(): void
    {
        $entry = app(AuditLogService::class)->record(AuditAction::JobApproved, $this->pendingJob());
        $id = $entry->id;

        try {
            $entry->update(['action' => AuditAction::JobClosed->value]);

            $this->fail('Updating an audit entry must be rejected.');
        } catch (AuditLogImmutableException) {
            // Expected: the trail is append-only.
        }

        try {
            $entry->delete();

            $this->fail('Deleting an audit entry must be rejected.');
        } catch (AuditLogImmutableException) {
            // Expected: the trail is append-only.
        }

        $this->assertDatabaseHas('audit_logs', [
            'id' => $id,
            'action' => AuditAction::JobApproved->value,
        ]);
        $this->assertSame(1, AuditLog::query()->count());
    }

    // -------------------------------------------------------------- no noise

    public function test_non_audited_actions_write_no_audit_entries(): void
    {
        $candidate = User::factory()->jobSeeker()->create();

        $this->get(route('jobs.index'))->assertOk();

        $this->actingAs($candidate)
            ->get(route('candidate.dashboard'))
            ->assertOk();

        $this->actingAs($candidate)
            ->put(route('candidate.profile.update'), [
                'headline' => 'Senior Software Engineer',
                'skills' => ['PHP', 'Laravel'],
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(0, AuditLog::query()->count());
    }

    // ------------------------------------------------------------ audit reads

    public function test_opening_the_audit_trail_and_an_entry_records_the_reads(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $entry = app(AuditLogService::class)->record(AuditAction::JobApproved, $this->pendingJob());

        $this->actingAs($admin)
            ->get(route('admin.audit-logs.index'))
            ->assertOk();

        $indexRead = AuditLog::query()
            ->where('action', AuditAction::AuditLogViewed->value)
            ->whereNull('target_id')
            ->firstOrFail();

        $this->assertSame($admin->id, $indexRead->actor_id);
        $this->assertSame(AuditTargetType::AuditLog->value, $indexRead->target_type);
        $this->assertSame(['scope' => 'index'], $indexRead->metadata);

        $this->actingAs($admin)
            ->get(route('admin.audit-logs.show', $entry))
            ->assertOk();

        $showRead = AuditLog::query()
            ->where('action', AuditAction::AuditLogViewed->value)
            ->where('target_id', $entry->id)
            ->firstOrFail();

        $this->assertSame($admin->id, $showRead->actor_id);
        $this->assertSame(AuditTargetType::AuditLog->value, $showRead->target_type);
        $this->assertSame(['scope' => 'show', 'entry_id' => $entry->id], $showRead->metadata);
    }
}
