<?php

namespace Tests\Feature\Authorization;

use App\Enums\PermissionSlug;
use App\Enums\RoleSlug;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Authorization\Concerns\InteractsWithAuthorizationCatalogue;
use Tests\TestCase;

class AdminUserRolesTest extends TestCase
{
    use InteractsWithAuthorizationCatalogue, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAuthorizationCatalogue();
    }

    public function test_admin_can_view_the_role_edit_page_with_the_expected_props(): void
    {
        $administrator = User::factory()->platformAdmin()->create();
        $target = User::factory()->jobSeeker()->create([
            'name' => 'Target User',
            'email' => 'target@example.com',
        ]);

        $this->actingAs($administrator)
            ->get(route('admin.users.roles.edit', $target))
            ->assertOk()
            ->assertInertia(function (Assert $page) use ($target): void {
                $page->component('admin/users/roles')
                    ->where('user.id', $target->id)
                    ->where('user.name', $target->name)
                    ->where('user.email', $target->email)
                    ->has('user.roles', 1)
                    ->where('user.roles.0.slug', RoleSlug::JobSeeker->value)
                    ->has('roles', 3)
                    ->missing('user.password')
                    ->missing('user.two_factor_secret')
                    ->missing('user.two_factor_recovery_codes')
                    ->missing('user.two_factor_confirmed_at')
                    ->missing('user.remember_token');

                $payload = $page->toArray()['props'];

                $this->assertSame(['id', 'name', 'email', 'roles'], array_keys($payload['user']));
                $this->assertSame(
                    ['id', 'slug', 'name', 'description', 'is_system'],
                    array_keys($payload['roles'][0]),
                );

                foreach ($payload['roles'] as $role) {
                    $this->assertArrayNotHasKey('permissions', $role);
                }
            });
    }

    public function test_role_edit_page_exposes_can_view_users_true_for_a_platform_administrator(): void
    {
        $administrator = User::factory()->platformAdmin()->create();
        $target = User::factory()->jobSeeker()->create();

        $this->actingAs($administrator)
            ->get(route('admin.users.roles.edit', $target))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/users/roles')
                ->where('canViewUsers', true),
            );
    }

    public function test_role_edit_page_exposes_can_view_users_false_for_an_actor_without_users_view(): void
    {
        $managerRole = $this->createCustomRoleWithPermissions(
            'user-manager',
            'User Manager',
            [PermissionSlug::AdminAccess, PermissionSlug::UsersRolesManage],
        );
        $actor = User::factory()->withRole($managerRole)->create();
        $target = User::factory()->jobSeeker()->create();

        $this->actingAs($actor)
            ->get(route('admin.users.roles.edit', $target))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/users/roles')
                ->where('canViewUsers', false),
            );
    }

    public function test_admin_can_replace_a_users_roles(): void
    {
        $administrator = User::factory()->platformAdmin()->create();
        $target = User::factory()->jobSeeker()->create();
        $originalName = $target->name;
        $originalEmail = $target->email;

        $response = $this->actingAs($administrator)
            ->put(route('admin.users.roles.update', $target), [
                'roles' => [$this->roleId(RoleSlug::Employer)],
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('admin.users.index'));
        $response->assertInertiaFlash('toast', [
            'type' => 'success',
            'message' => 'User roles updated.',
        ]);

        $this->assertSame([RoleSlug::Employer->value], $target->roles()->pluck('slug')->all());
        $this->assertSame($originalName, $target->fresh()->name);
        $this->assertSame($originalEmail, $target->fresh()->email);
        $this->assertCount(0, $target->fresh()->roles()->first()?->permissions ?? []);
    }

    public function test_admin_can_remove_all_roles_from_a_non_administrator(): void
    {
        $administrator = User::factory()->platformAdmin()->create();
        $target = User::factory()->jobSeeker()->create();

        $response = $this->actingAs($administrator)
            ->put(route('admin.users.roles.update', $target), ['roles' => []]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('admin.users.index'));
        $this->assertSame([], $target->roles()->pluck('slug')->all());
    }

    public function test_unknown_role_ids_fail_validation_and_persist_no_change(): void
    {
        $administrator = User::factory()->platformAdmin()->create();
        $target = User::factory()->jobSeeker()->create();

        $response = $this->actingAs($administrator)
            ->put(route('admin.users.roles.update', $target), ['roles' => [999999]]);

        $response->assertSessionHasErrors('roles.0');
        $this->assertSame([RoleSlug::JobSeeker->value], $target->roles()->pluck('slug')->all());
    }

    public function test_non_integer_role_ids_fail_validation(): void
    {
        $administrator = User::factory()->platformAdmin()->create();
        $target = User::factory()->jobSeeker()->create();

        $response = $this->actingAs($administrator)
            ->put(route('admin.users.roles.update', $target), ['roles' => ['not-a-role-id']]);

        $response->assertSessionHasErrors('roles.0');
        $this->assertSame([RoleSlug::JobSeeker->value], $target->roles()->pluck('slug')->all());
    }

    public function test_duplicate_role_ids_fail_validation(): void
    {
        $administrator = User::factory()->platformAdmin()->create();
        $target = User::factory()->jobSeeker()->create();
        $employerRoleId = $this->roleId(RoleSlug::Employer);

        $response = $this->actingAs($administrator)
            ->put(route('admin.users.roles.update', $target), [
                'roles' => [$employerRoleId, $employerRoleId],
            ]);

        $response->assertSessionHasErrors('roles.1');
        $this->assertSame([RoleSlug::JobSeeker->value], $target->roles()->pluck('slug')->all());
    }

    public function test_forged_permission_and_identity_fields_are_ignored(): void
    {
        $administrator = User::factory()->platformAdmin()->create();
        $target = User::factory()->jobSeeker()->create();
        $administratorPermissionCount = Role::query()
            ->where('slug', RoleSlug::PlatformAdmin->value)
            ->firstOrFail()
            ->permissions()
            ->count();

        $response = $this->actingAs($administrator)
            ->put(route('admin.users.roles.update', $target), [
                'roles' => [$this->roleId(RoleSlug::Employer)],
                'permissions' => ['admin.access', 'users.roles.manage'],
                'is_admin' => true,
                'role' => 'platform_admin',
                'role_id' => $this->roleId(RoleSlug::PlatformAdmin),
                'account_status' => 'approved',
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('admin.users.index'));

        $target->refresh();

        $this->assertSame([RoleSlug::Employer->value], $target->roles()->pluck('slug')->all());
        $this->assertSame(0, $target->roles()->first()?->permissions()->count() ?? 0);
        $this->assertSame($administratorPermissionCount, Role::query()
            ->where('slug', RoleSlug::PlatformAdmin->value)
            ->firstOrFail()
            ->permissions()
            ->count());
        $this->assertNull($target->getAttribute('is_admin'));
        $this->assertNull($target->getAttribute('role'));
        $this->assertNull($target->getAttribute('role_id'));
        $this->assertNull($target->getAttribute('account_status'));
    }

    public function test_non_admin_cannot_view_or_update_roles(): void
    {
        $actor = User::factory()->jobSeeker()->create();
        $target = User::factory()->jobSeeker()->create();

        $this->actingAs($actor)
            ->get(route('admin.users.roles.edit', $target))
            ->assertForbidden();

        $this->actingAs($actor)
            ->put(route('admin.users.roles.update', $target), [
                'roles' => [$this->roleId(RoleSlug::Employer)],
            ])
            ->assertForbidden();

        $this->assertSame([RoleSlug::JobSeeker->value], $target->roles()->pluck('slug')->all());
    }

    public function test_actor_with_admin_access_but_without_roles_manage_permission_cannot_view_or_update_roles(): void
    {
        $viewerRole = $this->createCustomRoleWithPermissions(
            'user-viewer',
            'User Viewer',
            [PermissionSlug::AdminAccess, PermissionSlug::UsersView],
        );
        $actor = User::factory()->withRole($viewerRole)->create();
        $target = User::factory()->jobSeeker()->create();

        $this->actingAs($actor)
            ->get(route('admin.users.roles.edit', $target))
            ->assertForbidden();

        $this->actingAs($actor)
            ->put(route('admin.users.roles.update', $target), [
                'roles' => [$this->roleId(RoleSlug::Employer)],
            ])
            ->assertForbidden();

        $this->assertSame([RoleSlug::JobSeeker->value], $target->roles()->pluck('slug')->all());
    }

    public function test_missing_and_non_numeric_targets_return_not_found(): void
    {
        $administrator = User::factory()->platformAdmin()->create();

        $this->actingAs($administrator)
            ->get(route('admin.users.roles.edit', 999999))
            ->assertNotFound();

        $this->actingAs($administrator)
            ->put(route('admin.users.roles.update', 999999), ['roles' => []])
            ->assertNotFound();

        $this->actingAs($administrator)
            ->get('/admin/users/not-a-number/roles')
            ->assertNotFound();
    }

    public function test_last_administrator_cannot_demote_themselves(): void
    {
        $administrator = User::factory()->platformAdmin()->create();

        $response = $this->actingAs($administrator)
            ->from(route('admin.users.roles.edit', $administrator))
            ->put(route('admin.users.roles.update', $administrator), [
                'roles' => [$this->roleId(RoleSlug::Employer)],
            ]);

        $response->assertRedirect(route('admin.users.roles.edit', $administrator));
        $response->assertSessionHasErrors('roles');
        $this->assertAuthenticatedAs($administrator);
        $this->assertSame(
            [RoleSlug::PlatformAdmin->value],
            $administrator->roles()->pluck('slug')->all(),
        );
    }

    public function test_last_administrator_cannot_be_demoted_by_another_actor(): void
    {
        $managerRole = $this->createCustomRoleWithPermissions(
            'user-manager',
            'User Manager',
            [PermissionSlug::AdminAccess, PermissionSlug::UsersRolesManage],
        );
        $actor = User::factory()->withRole($managerRole)->create();
        $target = User::factory()->platformAdmin()->create();

        $response = $this->actingAs($actor)
            ->from(route('admin.users.roles.edit', $target))
            ->put(route('admin.users.roles.update', $target), ['roles' => []]);

        $response->assertRedirect(route('admin.users.roles.edit', $target));
        $response->assertSessionHasErrors('roles');
        $this->assertSame(
            [RoleSlug::PlatformAdmin->value],
            $target->roles()->pluck('slug')->all(),
        );
    }

    public function test_another_administrator_allows_demoting_an_administrator(): void
    {
        $actor = User::factory()->platformAdmin()->create();
        $target = User::factory()->platformAdmin()->create();

        $response = $this->actingAs($actor)
            ->put(route('admin.users.roles.update', $target), [
                'roles' => [$this->roleId(RoleSlug::Employer)],
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('admin.users.index'));
        $this->assertSame([RoleSlug::Employer->value], $target->roles()->pluck('slug')->all());
        $this->assertSame(
            [RoleSlug::PlatformAdmin->value],
            $actor->roles()->pluck('slug')->all(),
        );
    }

    public function test_platform_administrator_who_demotes_themselves_redirects_to_dashboard(): void
    {
        $administrator = User::factory()->platformAdmin()->create();
        User::factory()->platformAdmin()->create();

        $response = $this->actingAs($administrator)
            ->put(route('admin.users.roles.update', $administrator), [
                'roles' => [$this->roleId(RoleSlug::Employer)],
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('dashboard'));
        $response->assertInertiaFlash('toast', [
            'type' => 'success',
            'message' => 'User roles updated.',
        ]);

        $administrator->refresh();

        $this->assertFalse($administrator->hasRole(RoleSlug::PlatformAdmin));
        $this->assertSame([RoleSlug::Employer->value], $administrator->roles()->pluck('slug')->all());

        $this->actingAs($administrator)
            ->get(route('admin.users.index'))
            ->assertForbidden();
    }

    public function test_actor_without_users_view_can_update_roles_and_redirects_to_dashboard(): void
    {
        $managerRole = $this->createCustomRoleWithPermissions(
            'user-manager',
            'User Manager',
            [PermissionSlug::AdminAccess, PermissionSlug::UsersRolesManage],
        );
        $actor = User::factory()->withRole($managerRole)->create();
        $target = User::factory()->jobSeeker()->create();

        $this->actingAs($actor)
            ->get(route('admin.users.roles.edit', $target))
            ->assertOk();

        $response = $this->actingAs($actor)
            ->put(route('admin.users.roles.update', $target), [
                'roles' => [$this->roleId(RoleSlug::Employer)],
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('dashboard'));
        $response->assertInertiaFlash('toast', [
            'type' => 'success',
            'message' => 'User roles updated.',
        ]);

        $this->assertSame(
            [RoleSlug::Employer->value],
            $target->fresh()->roles()->pluck('slug')->all(),
        );
    }
}
