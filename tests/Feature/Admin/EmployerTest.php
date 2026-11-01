<?php

namespace Tests\Feature\Admin;

use App\Enums\PermissionSlug;
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

    public function test_platform_admin_can_view_the_employer_index_without_a_companies_relation(): void
    {
        $admin = User::factory()->platformAdmin()->create();

        $employers = collect([
            $this->createEmployerUser('Regression Employer Alpha', 'alpha.employer@example.com', 'approved'),
            $this->createEmployerUser('Regression Employer Bravo', 'bravo.employer@example.com', 'approved'),
            $this->createEmployerUser('Regression Employer Charlie', 'charlie.employer@example.com', 'approved'),
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
                    $this->assertArrayHasKey('company_count', $row);
                    $this->assertNull($row['company_count'], 'The employer index must not depend on a companies relation.');
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
                ->where('employers.data.0.suspension_reason', 'Repeated policy violations')
                ->whereNull('employers.data.0.company_count'),
            );
    }

    public function test_admin_can_suspend_an_approved_employer_with_a_reason(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $employer = $this->createEmployerUser('Suspendable Employer', 'suspendable@example.com', 'approved');

        $response = $this->actingAs($admin)
            ->post(route('admin.employers.suspend', $employer), [
                'status' => 'suspended',
                'suspension_reason' => 'Failed verification review',
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('admin.employers.index'));

        $this->assertDatabaseHas('users', [
            'id' => $employer->id,
            'status' => 'suspended',
            'suspension_reason' => 'Failed verification review',
        ]);
    }

    public function test_suspending_without_a_reason_fails_validation_and_preserves_the_status(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $employer = $this->createEmployerUser('Approved Employer', 'approved@example.com', 'approved');

        $response = $this->actingAs($admin)
            ->from(route('admin.employers.index'))
            ->post(route('admin.employers.suspend', $employer), [
                'status' => 'suspended',
            ]);

        $response->assertRedirect(route('admin.employers.index'));
        $response->assertSessionHasErrors('suspension_reason');

        $this->assertDatabaseHas('users', [
            'id' => $employer->id,
            'status' => 'approved',
            'suspension_reason' => null,
        ]);
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
            ->post(route('admin.employers.reactivate', $employer), [
                'status' => 'approved',
                'suspension_reason' => null,
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('admin.employers.index'));

        $this->assertDatabaseHas('users', [
            'id' => $employer->id,
            'status' => 'approved',
            'suspension_reason' => null,
        ]);
    }

    public function test_view_only_role_cannot_suspend_an_employer(): void
    {
        $viewerRole = $this->createCustomRoleWithPermissions(
            'employer-viewer-no-manage',
            'Employer Viewer Without Manage',
            [PermissionSlug::AdminAccess, PermissionSlug::EmployersView],
        );
        $viewer = User::factory()->withRole($viewerRole)->create();
        $employer = $this->createEmployerUser('Protected Employer', 'protected@example.com', 'approved');

        $response = $this->actingAs($viewer)
            ->post(route('admin.employers.suspend', $employer), [
                'status' => 'suspended',
                'suspension_reason' => 'Should not be applied',
            ]);

        $response->assertForbidden();

        $this->assertDatabaseHas('users', [
            'id' => $employer->id,
            'status' => 'approved',
            'suspension_reason' => null,
        ]);
    }

    public function test_search_filter_returns_only_the_matching_employer_by_name_or_email(): void
    {
        $admin = User::factory()->platformAdmin()->create();

        $matching = $this->createEmployerUser(
            'Unique Search Target',
            'unique.search.target@example.com',
            'approved',
        );
        $this->createEmployerUser('Distractor One', 'distractor.one@example.com', 'approved');
        $this->createEmployerUser('Distractor Two', 'distractor.two@example.com', 'approved');

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

    private function createEmployerUser(
        string $name,
        string $email,
        ?string $status = null,
        ?string $suspensionReason = null,
    ): User {
        $user = User::factory()->employer()->create([
            'name' => $name,
            'email' => $email,
        ]);

        if ($status !== null) {
            $user->status = $status;
            $user->suspension_reason = $suspensionReason;
            $user->save();
        }

        return $user;
    }
}
