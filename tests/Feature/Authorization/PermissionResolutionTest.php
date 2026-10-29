<?php

namespace Tests\Feature\Authorization;

use App\Enums\PermissionSlug;
use App\Enums\RoleSlug;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\Authorization\RoleAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Authorization\Concerns\InteractsWithAuthorizationCatalogue;
use Tests\TestCase;

class PermissionResolutionTest extends TestCase
{
    use InteractsWithAuthorizationCatalogue, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAuthorizationCatalogue();
    }

    public function test_user_without_roles_has_no_permissions(): void
    {
        $user = User::factory()->create();

        foreach (PermissionSlug::cases() as $permission) {
            $this->assertFalse($user->hasPermission($permission));
        }

        $this->assertFalse($user->hasPermission('admin.access'));
        $this->assertFalse($user->hasRole(RoleSlug::PlatformAdmin));
    }

    public function test_a_role_grants_its_attached_permissions(): void
    {
        $administrator = User::factory()->platformAdmin()->create();
        $jobSeeker = User::factory()->jobSeeker()->create();

        foreach (PermissionSlug::cases() as $permission) {
            $this->assertTrue($administrator->hasPermission($permission));
            $this->assertFalse($jobSeeker->hasPermission($permission));
        }
    }

    public function test_multiple_roles_union_their_permissions(): void
    {
        $customPermission = Permission::create([
            'slug' => 'reports.view',
            'name' => 'View reports',
            'is_system' => false,
        ]);
        $customRole = Role::create([
            'slug' => 'reporter',
            'name' => 'Reporter',
            'is_system' => false,
        ]);
        $customRole->permissions()->attach($customPermission->id);

        $user = User::factory()->platformAdmin()->withRole($customRole)->create();

        $this->assertTrue($user->hasRole(RoleSlug::PlatformAdmin));
        $this->assertTrue($user->hasRole('reporter'));
        $this->assertTrue($user->hasPermission(PermissionSlug::UsersView));
        $this->assertTrue($user->hasPermission('reports.view'));
    }

    public function test_replacing_roles_invalidates_the_memoized_permission_set(): void
    {
        User::factory()->platformAdmin()->create();
        $user = User::factory()->platformAdmin()->create();

        $this->assertTrue($user->hasPermission(PermissionSlug::UsersView));

        $this->app->make(RoleAssignmentService::class)
            ->syncRoles($user, [$this->roleId(RoleSlug::JobSeeker)]);

        $this->assertFalse($user->hasPermission(PermissionSlug::UsersView));
        $this->assertFalse($user->hasRole(RoleSlug::PlatformAdmin));
        $this->assertTrue($user->hasRole(RoleSlug::JobSeeker));
        $this->assertFalse($user->fresh()->hasPermission(PermissionSlug::UsersView));
    }
}
