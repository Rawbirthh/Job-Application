<?php

namespace Tests\Feature\Authorization;

use App\Enums\PermissionSlug;
use App\Enums\RoleSlug;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Authorization\Concerns\InteractsWithAuthorizationCatalogue;
use Tests\TestCase;

class AdminRouteAccessTest extends TestCase
{
    use InteractsWithAuthorizationCatalogue, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAuthorizationCatalogue();
    }

    public function test_guests_are_redirected_to_login_from_the_admin_user_index(): void
    {
        $this->get(route('admin.users.index'))->assertRedirect(route('login'));
    }

    public function test_authenticated_user_without_roles_receives_forbidden(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('admin.users.index'))
            ->assertForbidden();
    }

    public function test_job_seeker_without_admin_permissions_receives_forbidden(): void
    {
        $user = User::factory()->jobSeeker()->create();

        $this->actingAs($user)
            ->get(route('admin.users.index'))
            ->assertForbidden();
    }

    public function test_user_with_only_admin_access_cannot_view_users(): void
    {
        $role = $this->createCustomRoleWithPermissions(
            'admin-access-only',
            'Admin Access Only',
            [PermissionSlug::AdminAccess],
        );
        $user = User::factory()->withRole($role)->create();

        $this->actingAs($user)
            ->get(route('admin.users.index'))
            ->assertForbidden();
    }

    public function test_user_with_only_users_view_cannot_enter_admin_boundary(): void
    {
        $role = $this->createCustomRoleWithPermissions(
            'users-view-only',
            'Users View Only',
            [PermissionSlug::UsersView],
        );
        $user = User::factory()->withRole($role)->create();

        $this->actingAs($user)
            ->get(route('admin.users.index'))
            ->assertForbidden();
    }

    public function test_platform_admin_can_view_the_admin_user_index(): void
    {
        $administrator = User::factory()->platformAdmin()->create();

        $this->actingAs($administrator)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/users/index')
                ->where('filters.search', ''),
            );
    }

    public function test_direct_role_update_without_permission_is_forbidden_and_persists_no_change(): void
    {
        $actor = User::factory()->jobSeeker()->create();
        $target = User::factory()->jobSeeker()->create();

        $response = $this->actingAs($actor)
            ->put(route('admin.users.roles.update', $target), [
                'roles' => [$this->roleId(RoleSlug::Employer)],
            ]);

        $response->assertForbidden();
        $this->assertSame([RoleSlug::JobSeeker->value], $target->roles()->pluck('slug')->all());
    }
}
