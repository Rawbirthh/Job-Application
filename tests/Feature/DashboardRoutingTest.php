<?php

namespace Tests\Feature;

use App\Enums\PermissionSlug;
use App\Enums\RoleSlug;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Authorization\Concerns\InteractsWithAuthorizationCatalogue;
use Tests\TestCase;

class DashboardRoutingTest extends TestCase
{
    use InteractsWithAuthorizationCatalogue, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAuthorizationCatalogue();
    }

    public function test_guests_are_redirected_to_login_from_all_dashboard_routes(): void
    {
        $routes = [
            'dashboard',
            'admin.dashboard',
            'employer.dashboard',
            'candidate.dashboard',
        ];

        foreach ($routes as $route) {
            $this->get(route($route))->assertRedirect(route('login'));
        }
    }

    public function test_admin_user_visiting_dashboard_is_redirected_to_admin_dashboard(): void
    {
        $admin = User::factory()->platformAdmin()->create();

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertRedirect(route('admin.dashboard'));
    }

    public function test_employer_user_visiting_dashboard_is_redirected_to_employer_dashboard(): void
    {
        $employer = User::factory()->employer()->create();

        $this->actingAs($employer)
            ->get(route('dashboard'))
            ->assertRedirect(route('employer.dashboard'));
    }

    public function test_job_seeker_user_visiting_dashboard_is_redirected_to_candidate_dashboard(): void
    {
        $jobSeeker = User::factory()->jobSeeker()->create();

        $this->actingAs($jobSeeker)
            ->get(route('dashboard'))
            ->assertRedirect(route('candidate.dashboard'));
    }

    public function test_multi_role_user_with_admin_access_prioritizes_admin_dashboard(): void
    {
        $user = User::factory()->create();

        $platformAdminRole = Role::where('slug', RoleSlug::PlatformAdmin->value)->firstOrFail();
        $employerRole = Role::where('slug', RoleSlug::Employer->value)->firstOrFail();

        $user->roles()->syncWithoutDetaching([
            $platformAdminRole->id,
            $employerRole->id,
        ]);
        $user->clearPermissionCache();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertRedirect(route('admin.dashboard'));
    }

    public function test_user_without_roles_renders_fallback_dashboard(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('dashboard'));
    }

    public function test_admin_can_access_admin_dashboard_directly(): void
    {
        $admin = User::factory()->platformAdmin()->create();

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('admin/dashboard'));
    }

    public function test_non_admin_cannot_access_admin_dashboard(): void
    {
        $employer = User::factory()->employer()->create();

        $this->actingAs($employer)
            ->get(route('admin.dashboard'))
            ->assertForbidden();

        $candidate = User::factory()->jobSeeker()->create();

        $this->actingAs($candidate)
            ->get(route('admin.dashboard'))
            ->assertForbidden();

        $unassigned = User::factory()->create();

        $this->actingAs($unassigned)
            ->get(route('admin.dashboard'))
            ->assertForbidden();
    }

    public function test_employer_can_access_employer_dashboard_directly(): void
    {
        $employer = User::factory()->employer()->create();

        $this->actingAs($employer)
            ->get(route('employer.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('employer/dashboard'));
    }

    public function test_non_employer_cannot_access_employer_dashboard(): void
    {
        $candidate = User::factory()->jobSeeker()->create();

        $this->actingAs($candidate)
            ->get(route('employer.dashboard'))
            ->assertForbidden();

        $admin = User::factory()->platformAdmin()->create();

        $this->actingAs($admin)
            ->get(route('employer.dashboard'))
            ->assertForbidden();

        $unassigned = User::factory()->create();

        $this->actingAs($unassigned)
            ->get(route('employer.dashboard'))
            ->assertForbidden();
    }

    public function test_candidate_can_access_candidate_dashboard_directly(): void
    {
        $candidate = User::factory()->jobSeeker()->create();

        $this->actingAs($candidate)
            ->get(route('candidate.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('candidate/dashboard'));
    }

    public function test_non_candidate_cannot_access_candidate_dashboard(): void
    {
        $employer = User::factory()->employer()->create();

        $this->actingAs($employer)
            ->get(route('candidate.dashboard'))
            ->assertForbidden();

        $admin = User::factory()->platformAdmin()->create();

        $this->actingAs($admin)
            ->get(route('candidate.dashboard'))
            ->assertForbidden();

        $unassigned = User::factory()->create();

        $this->actingAs($unassigned)
            ->get(route('candidate.dashboard'))
            ->assertForbidden();
    }

    public function test_inertia_shares_roles_and_permissions_props(): void
    {
        $admin = User::factory()->platformAdmin()->create();

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.roles', [RoleSlug::PlatformAdmin->value])
                ->where('auth.permissions', function ($permissions): bool {
                    $permissions = is_array($permissions) ? $permissions : $permissions->all();
                    $expected = [
                        PermissionSlug::AdminAccess->value,
                        PermissionSlug::UsersView->value,
                        PermissionSlug::UsersRolesManage->value,
                    ];

                    return count(array_intersect($expected, $permissions)) === count($expected);
                })
                ->where('auth.user.id', $admin->id)
                ->where('auth.user.name', $admin->name)
                ->where('auth.user.email', $admin->email)
            );

        $employer = User::factory()->employer()->create();

        $this->actingAs($employer)
            ->get(route('employer.dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.roles', [RoleSlug::Employer->value])
                ->where('auth.permissions', [])
            );

        $candidate = User::factory()->jobSeeker()->create();

        $this->actingAs($candidate)
            ->get(route('candidate.dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.roles', [RoleSlug::JobSeeker->value])
                ->where('auth.permissions', [])
            );
    }

    public function test_guest_has_empty_roles_and_permissions_props(): void
    {
        $this->get(route('home'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.user', null)
                ->where('auth.roles', [])
                ->where('auth.permissions', [])
            );
    }

    public function test_auth_user_has_only_expected_keys(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertInertia(function (Assert $page): void {
                $props = $page->toArray()['props'];

                $this->assertEqualsCanonicalizing(
                    ['id', 'name', 'email'],
                    array_keys($props['auth']['user']),
                );
            });
    }
}
