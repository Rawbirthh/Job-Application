<?php

namespace Tests\Feature\Admin;

use App\Enums\EmployerStatus;
use App\Enums\PermissionSlug;
use App\Enums\RoleSlug;
use App\Models\EmployerOnboardingRequest;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Authorization\Concerns\InteractsWithAuthorizationCatalogue;
use Tests\TestCase;

class EmployerTest extends TestCase
{
    use InteractsWithAuthorizationCatalogue, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAuthorizationCatalogue();
    }

    private function createEmployerUser(
        string $name,
        string $email,
        ?string $status = null,
        ?string $suspensionReason = null,
        ?array $requestFields = null,
    ): User {
        $user = User::factory()->create([
            'name' => $name,
            'email' => $email,
        ]);

        if ($status !== null) {
            $user->forceFill([
                'status' => $status,
                'suspension_reason' => $suspensionReason,
            ])->save();

            if (in_array($status, ['pending', 'rejected', 'approved', 'suspended'], true)) {
                $roleSlug = in_array($status, ['approved', 'suspended'], true)
                    ? RoleSlug::Employer
                    : RoleSlug::JobSeeker;

                $role = Role::where('slug', $roleSlug->value)->firstOrFail();
                $user->roles()->syncWithoutDetaching([$role->id]);
                $user->clearPermissionCache();
            }
        }

        if ($requestFields !== null) {
            $user->employerOnboardingRequest()->create(array_merge([
                'company_name' => 'Acme Corp',
                'job_title' => 'Founder',
                'submitted_at' => now(),
            ], $requestFields));
        }

        return $user->fresh();
    }

    public function test_platform_admin_can_view_the_employer_index_without_a_companies_relation(): void
    {
        $admin = User::factory()->platformAdmin()->create();

        $employers = collect([
            $this->createEmployerUser('Regression Employer Alpha', 'alpha.employer@example.com', 'approved', null, ['reviewed_at' => now()]),
            $this->createEmployerUser('Regression Employer Bravo', 'bravo.employer@example.com', 'approved', null, ['reviewed_at' => now()]),
            $this->createEmployerUser('Regression Employer Charlie', 'charlie.employer@example.com', 'approved', null, ['reviewed_at' => now()]),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.employers.index', ['search' => 'Regression Employer']))
            ->assertOk()
            ->assertInertia(function (Assert $page) use ($employers): void {
                $page->component('admin/employers/index')
                    ->where('employers.total', 3)
                    ->where('employers.per_page', 25)
                    ->has('employers.data', 3)
                    ->where('canManageEmployers', true)
                    ->where('filters.search', 'Regression Employer')
                    ->where('filters.status', null)
                    ->missing('users');

                $rows = $page->toArray()['props']['employers']['data'];

                $this->assertSame(
                    $employers->pluck('id')->sort()->values()->all(),
                    collect($rows)->pluck('id')->sort()->values()->all(),
                );

                foreach ($rows as $row) {
                    $this->assertArrayHasKey('onboarding_request', $row);
                    $this->assertArrayHasKey('suspension_reason', $row);
                    $this->assertNull($row['suspension_reason']);
                    $this->assertTrue($row['can_manage']);
                }
            });
    }

    public function test_view_only_role_can_view_the_index_but_cannot_manage_employers(): void
    {
        $viewerRole = $this->createCustomRoleWithPermissions(
            'employer-viewer',
            'Employer Viewer',
            [PermissionSlug::AdminAccess, PermissionSlug::EmployersView],
        );
        $viewer = User::factory()->withRole($viewerRole)->create();
        $this->createEmployerUser('Viewable Employer', 'viewable@example.com', 'approved', null, ['reviewed_at' => now()]);

        $this->actingAs($viewer)
            ->get(route('admin.employers.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/employers/index')
                ->where('canManageEmployers', false)
                ->where('employers.data.0.can_manage', false),
            );
    }

    public function test_job_seeker_cannot_access_the_employer_index(): void
    {
        $jobSeeker = User::factory()->jobSeeker()->create();

        $this->actingAs($jobSeeker)
            ->get(route('admin.employers.index'))
            ->assertForbidden();
    }

    public function test_guests_are_redirected_to_login_from_the_employer_index(): void
    {
        $this->get(route('admin.employers.index'))->assertRedirect(route('login'));
    }

    public function test_suspended_employer_row_exposes_its_suspension_reason(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $employer = $this->createEmployerUser(
            'Suspended Searchable Employer',
            'suspended.employer@example.com',
            'suspended',
            'Repeated policy violations',
        );

        $this->actingAs($admin)
            ->get(route('admin.employers.index', ['search' => 'Suspended Searchable Employer']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/employers/index')
                ->where('employers.total', 1)
                ->has('employers.data', 1)
                ->where('employers.data.0.id', $employer->id)
                ->where('employers.data.0.status', 'suspended')
                ->where('employers.data.0.status_badge', 'suspended')
                ->where('employers.data.0.suspension_reason', 'Repeated policy violations'),
            );
    }

    public function test_admin_can_approve_a_pending_employer(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $applicant = $this->createEmployerUser('Pending Applicant', 'pending@example.com', 'pending', null, [
            'company_name' => 'Acme',
            'job_title' => 'Founder',
            'submitted_at' => now(),
        ]);

        $response = $this->actingAs($admin)
            ->post(route('admin.employers.approve', $applicant));

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('admin.employers.show', $applicant));

        $applicant->refresh();
        $this->assertSame(EmployerStatus::Approved, $applicant->status);
        $this->assertNull($applicant->suspension_reason);
        $this->assertTrue($applicant->hasRole(RoleSlug::Employer));
        $this->assertTrue($applicant->hasRole(RoleSlug::JobSeeker));

        $request = EmployerOnboardingRequest::where('user_id', $applicant->id)->first();
        $this->assertNotNull($request->reviewed_at);
        $this->assertSame($admin->id, $request->reviewed_by_id);
        $this->assertNull($request->review_note);
    }

    public function test_approving_from_non_pending_status_fails(): void
    {
        $admin = User::factory()->platformAdmin()->create();

        foreach (['approved', 'rejected', 'suspended'] as $status) {
            $applicant = $this->createEmployerUser("User {$status}", "{$status}@example.com", $status, $status === 'suspended' ? 'reason' : null);

            $response = $this->actingAs($admin)
                ->post(route('admin.employers.approve', $applicant));

            $response->assertSessionHasErrors('status');

            $applicant->refresh();
            $this->assertSame($status, $applicant->status?->value);
        }
    }

    public function test_admin_can_reject_a_pending_employer_with_a_reason(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $applicant = $this->createEmployerUser('Rejectable Applicant', 'rejectable@example.com', 'pending', null, [
            'company_name' => 'Acme',
            'job_title' => 'Founder',
            'submitted_at' => now(),
        ]);

        $response = $this->actingAs($admin)
            ->post(route('admin.employers.reject', $applicant), [
                'reason' => 'Insufficient information',
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('admin.employers.show', $applicant));

        $applicant->refresh();
        $this->assertSame(EmployerStatus::Rejected, $applicant->status);
        $this->assertFalse($applicant->hasRole(RoleSlug::Employer));

        $request = EmployerOnboardingRequest::where('user_id', $applicant->id)->first();
        $this->assertSame('Insufficient information', $request->review_note);
        $this->assertSame($admin->id, $request->reviewed_by_id);
    }

    public function test_rejecting_without_a_reason_fails_validation(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $applicant = $this->createEmployerUser('Pending Applicant', 'pending2@example.com', 'pending', null, [
            'submitted_at' => now(),
        ]);

        $response = $this->actingAs($admin)
            ->from(route('admin.employers.show', $applicant))
            ->post(route('admin.employers.reject', $applicant), []);

        $response->assertSessionHasErrors('reason');

        $applicant->refresh();
        $this->assertSame(EmployerStatus::Pending, $applicant->status);
    }

    public function test_rejecting_from_non_pending_fails(): void
    {
        $admin = User::factory()->platformAdmin()->create();

        foreach (['approved', 'rejected', 'suspended'] as $status) {
            $applicant = $this->createEmployerUser("User {$status}", "rej{$status}@example.com", $status, $status === 'suspended' ? 'reason' : null);

            $response = $this->actingAs($admin)
                ->post(route('admin.employers.reject', $applicant), [
                    'reason' => 'Should fail',
                ]);

            $response->assertSessionHasErrors('status');
        }
    }

    public function test_admin_can_suspend_an_approved_employer_with_a_reason(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $employer = $this->createEmployerUser('Suspendable Employer', 'suspendable@example.com', 'approved', null, ['reviewed_at' => now()]);

        $response = $this->actingAs($admin)
            ->post(route('admin.employers.suspend', $employer), [
                'reason' => 'Failed verification review',
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('admin.employers.show', $employer));

        $this->assertDatabaseHas('users', [
            'id' => $employer->id,
            'status' => 'suspended',
            'suspension_reason' => 'Failed verification review',
        ]);

        $employer->refresh();
        $this->assertTrue($employer->hasRole(RoleSlug::Employer));
    }

    public function test_suspending_without_a_reason_fails_validation_and_preserves_the_status(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $employer = $this->createEmployerUser('Approved Employer', 'approved@example.com', 'approved', null, ['reviewed_at' => now()]);

        $response = $this->actingAs($admin)
            ->from(route('admin.employers.show', $employer))
            ->post(route('admin.employers.suspend', $employer), []);

        $response->assertSessionHasErrors('reason');

        $this->assertDatabaseHas('users', [
            'id' => $employer->id,
            'status' => 'approved',
            'suspension_reason' => null,
        ]);
    }

    public function test_suspending_from_non_approved_fails(): void
    {
        $admin = User::factory()->platformAdmin()->create();

        foreach (['pending', 'rejected', 'suspended'] as $status) {
            $applicant = $this->createEmployerUser("User {$status}", "susp{$status}@example.com", $status, $status === 'suspended' ? 'reason' : null);

            $response = $this->actingAs($admin)
                ->post(route('admin.employers.suspend', $applicant), [
                    'reason' => 'Should fail',
                ]);

            $response->assertSessionHasErrors('status');
        }
    }

    public function test_admin_can_reactivate_a_suspended_employer(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $employer = $this->createEmployerUser(
            'Reactivate Me',
            'reactivate@example.com',
            'suspended',
            'Temporary suspension',
        );

        $response = $this->actingAs($admin)
            ->post(route('admin.employers.reactivate', $employer));

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('admin.employers.show', $employer));

        $this->assertDatabaseHas('users', [
            'id' => $employer->id,
            'status' => 'approved',
            'suspension_reason' => null,
        ]);

        $employer->refresh();
        $this->assertTrue($employer->hasRole(RoleSlug::Employer));
    }

    public function test_reactivating_from_non_suspended_fails(): void
    {
        $admin = User::factory()->platformAdmin()->create();

        foreach (['pending', 'approved', 'rejected'] as $status) {
            $applicant = $this->createEmployerUser("User {$status}", "react{$status}@example.com", $status, null);

            $response = $this->actingAs($admin)
                ->post(route('admin.employers.reactivate', $applicant));

            $response->assertSessionHasErrors('status');
        }
    }

    public function test_view_only_role_cannot_suspend_an_employer(): void
    {
        $viewerRole = $this->createCustomRoleWithPermissions(
            'employer-viewer-no-manage',
            'Employer Viewer Without Manage',
            [PermissionSlug::AdminAccess, PermissionSlug::EmployersView],
        );
        $viewer = User::factory()->withRole($viewerRole)->create();
        $employer = $this->createEmployerUser('Protected Employer', 'protected@example.com', 'approved', null, ['reviewed_at' => now()]);

        $response = $this->actingAs($viewer)
            ->post(route('admin.employers.suspend', $employer), [
                'reason' => 'Should not be applied',
            ]);

        $response->assertForbidden();

        $this->assertDatabaseHas('users', [
            'id' => $employer->id,
            'status' => 'approved',
            'suspension_reason' => null,
        ]);
    }

    public function test_view_only_role_cannot_approve_reject_or_reactivate(): void
    {
        $viewerRole = $this->createCustomRoleWithPermissions(
            'employer-viewer-no-manage-2',
            'Employer Viewer Without Manage 2',
            [PermissionSlug::AdminAccess, PermissionSlug::EmployersView],
        );
        $viewer = User::factory()->withRole($viewerRole)->create();
        $pending = $this->createEmployerUser('Pending Protected', 'pending.protected@example.com', 'pending', null, ['submitted_at' => now()]);
        $suspended = $this->createEmployerUser('Suspended Protected', 'suspended.protected@example.com', 'suspended', 'reason');

        $this->actingAs($viewer)->post(route('admin.employers.approve', $pending))->assertForbidden();
        $this->actingAs($viewer)->post(route('admin.employers.reject', $pending), ['reason' => 'x'])->assertForbidden();
        $this->actingAs($viewer)->post(route('admin.employers.reactivate', $suspended))->assertForbidden();
    }

    public function test_search_filter_returns_only_the_matching_employer_by_name_or_email(): void
    {
        $admin = User::factory()->platformAdmin()->create();

        $matching = $this->createEmployerUser(
            'Unique Search Target',
            'unique.search.target@example.com',
            'approved',
            null,
            ['reviewed_at' => now()],
        );
        $this->createEmployerUser('Distractor One', 'distractor.one@example.com', 'approved', null, ['reviewed_at' => now()]);
        $this->createEmployerUser('Distractor Two', 'distractor.two@example.com', 'approved', null, ['reviewed_at' => now()]);

        foreach (['Unique Search Target', 'unique.search.target@example.com'] as $search) {
            $this->actingAs($admin)
                ->get(route('admin.employers.index', ['search' => $search]))
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->component('admin/employers/index')
                    ->where('employers.total', 1)
                    ->has('employers.data', 1)
                    ->where('employers.data.0.id', $matching->id)
                    ->where('employers.data.0.email', 'unique.search.target@example.com')
                    ->where('filters.search', $search),
                );
        }
    }

    public function test_status_filter_works_for_all_statuses(): void
    {
        $admin = User::factory()->platformAdmin()->create();

        $this->createEmployerUser('Pending User', 'pending.filter@example.com', 'pending', null, ['submitted_at' => now()]);
        $this->createEmployerUser('Approved User', 'approved.filter@example.com', 'approved', null, ['reviewed_at' => now()]);
        $this->createEmployerUser('Rejected User', 'rejected.filter@example.com', 'rejected', null, ['review_note' => 'no']);
        $this->createEmployerUser('Suspended User', 'suspended.filter@example.com', 'suspended', 'reason');

        foreach (['pending', 'approved', 'rejected', 'suspended'] as $status) {
            $this->actingAs($admin)
                ->get(route('admin.employers.index', ['status' => $status]))
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->where('filters.status', $status),
                );
        }

        $response = $this->actingAs($admin)
            ->get(route('admin.employers.index', ['status' => 'not-a-status']));

        $response->assertSessionHasErrors('status');
    }

    public function test_directory_excludes_unrelated_job_seekers(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        User::factory()->jobSeeker()->create(['name' => 'Plain Seeker']);

        $this->actingAs($admin)
            ->get(route('admin.employers.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('employers.total', 0),
            );
    }

    public function test_guest_and_job_seeker_cannot_view_show(): void
    {
        $employer = $this->createEmployerUser('Showable', 'showable@example.com', 'approved', null, ['reviewed_at' => now()]);

        $this->get(route('admin.employers.show', $employer))->assertRedirect(route('login'));

        $jobSeeker = User::factory()->jobSeeker()->create();
        $this->actingAs($jobSeeker)->get(route('admin.employers.show', $employer))->assertForbidden();
    }

    public function test_reviewing_an_unrelated_user_returns_404(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $plain = User::factory()->jobSeeker()->create();

        $this->actingAs($admin)
            ->get(route('admin.employers.show', $plain))
            ->assertNotFound();
    }

    public function test_review_page_exposes_request_details_and_action_flags(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $applicant = $this->createEmployerUser('Pending Applicant', 'pending.show@example.com', 'pending', null, [
            'company_name' => 'Acme',
            'company_website' => 'https://acme.example',
            'job_title' => 'Founder',
            'message' => 'Hello',
            'submitted_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.employers.show', $applicant))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/employers/show')
                ->where('employer.id', $applicant->id)
                ->where('employer.status', 'pending')
                ->where('employer.onboarding_request.company_name', 'Acme')
                ->where('employer.onboarding_request.company_website', 'https://acme.example')
                ->where('employer.onboarding_request.job_title', 'Founder')
                ->where('employer.onboarding_request.message', 'Hello')
                ->where('employer.actions.approve', true)
                ->where('employer.actions.reject', true)
                ->where('employer.actions.suspend', false)
                ->where('employer.actions.reactivate', false)
                ->where('canManageEmployers', true),
            );
    }

    public function test_review_page_is_read_only_for_view_only_admin(): void
    {
        $viewerRole = $this->createCustomRoleWithPermissions(
            'employer-viewer-ro',
            'Employer Viewer RO',
            [PermissionSlug::AdminAccess, PermissionSlug::EmployersView],
        );
        $viewer = User::factory()->withRole($viewerRole)->create();
        $applicant = $this->createEmployerUser('Pending Applicant', 'pending.ro@example.com', 'pending', null, ['submitted_at' => now()]);

        $this->actingAs($viewer)
            ->get(route('admin.employers.show', $applicant))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('canManageEmployers', false)
                ->where('employer.can_manage', false),
            );
    }
}
