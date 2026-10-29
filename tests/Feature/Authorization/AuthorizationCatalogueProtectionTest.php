<?php

namespace Tests\Feature\Authorization;

use App\Enums\PermissionSlug;
use App\Enums\RoleSlug;
use App\Exceptions\ProtectedSystemDefinitionException;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Authorization\Concerns\InteractsWithAuthorizationCatalogue;
use Tests\TestCase;

class AuthorizationCatalogueProtectionTest extends TestCase
{
    use InteractsWithAuthorizationCatalogue, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAuthorizationCatalogue();
    }

    public function test_role_slug_and_name_must_be_unique(): void
    {
        $this->expectException(QueryException::class);

        Role::create([
            'slug' => RoleSlug::PlatformAdmin->value,
            'name' => 'Duplicate Role Slug',
            'is_system' => false,
        ]);
    }

    public function test_role_name_must_be_unique(): void
    {
        $this->expectException(QueryException::class);

        Role::create([
            'slug' => 'another-role',
            'name' => 'Platform Administrator',
            'is_system' => false,
        ]);
    }

    public function test_permission_slug_must_be_unique(): void
    {
        $this->expectException(QueryException::class);

        Permission::create([
            'slug' => PermissionSlug::AdminAccess->value,
            'name' => 'Duplicate Permission Slug',
            'is_system' => false,
        ]);
    }

    public function test_role_user_pivot_rejects_duplicate_assignments(): void
    {
        $user = User::factory()->jobSeeker()->create();
        $jobSeekerRoleId = $this->roleId(RoleSlug::JobSeeker);

        $this->expectException(QueryException::class);

        DB::table('role_user')->insert([
            'user_id' => $user->id,
            'role_id' => $jobSeekerRoleId,
        ]);
    }

    public function test_permission_role_pivot_rejects_duplicate_grants(): void
    {
        $administratorRoleId = $this->roleId(RoleSlug::PlatformAdmin);
        $adminAccessPermissionId = (int) Permission::query()
            ->where('slug', PermissionSlug::AdminAccess->value)
            ->value('id');

        $this->expectException(QueryException::class);

        DB::table('permission_role')->insert([
            'role_id' => $administratorRoleId,
            'permission_id' => $adminAccessPermissionId,
        ]);
    }

    public function test_system_role_cannot_be_deleted_renamed_or_marked_as_non_system(): void
    {
        $role = Role::query()
            ->where('slug', RoleSlug::PlatformAdmin->value)
            ->firstOrFail();

        $this->assertProtected(
            fn () => $role->delete(),
            'System role [platform_admin] cannot be deleted.',
        );
        $this->assertDatabaseHas('roles', ['id' => $role->id, 'slug' => RoleSlug::PlatformAdmin->value]);

        $role = Role::query()->findOrFail($role->id);
        $role->slug = 'renamed-platform-admin';

        $this->assertProtected(
            fn () => $role->save(),
            'cannot be changed.',
        );
        $this->assertSame(RoleSlug::PlatformAdmin->value, $role->fresh()->slug);

        $role = Role::query()->findOrFail($role->id);
        $role->is_system = false;

        $this->assertProtected(
            fn () => $role->save(),
            'System role [platform_admin] cannot be marked as non-system.',
        );
        $this->assertTrue($role->fresh()->is_system);
    }

    public function test_system_permission_cannot_be_deleted_renamed_or_marked_as_non_system(): void
    {
        $permission = Permission::query()
            ->where('slug', PermissionSlug::UsersView->value)
            ->firstOrFail();

        $this->assertProtected(
            fn () => $permission->delete(),
            'System permission [users.view] cannot be deleted.',
        );
        $this->assertDatabaseHas('permissions', ['id' => $permission->id, 'slug' => PermissionSlug::UsersView->value]);

        $permission = Permission::query()->findOrFail($permission->id);
        $permission->slug = 'renamed-users-view';

        $this->assertProtected(
            fn () => $permission->save(),
            'cannot be changed.',
        );
        $this->assertSame(PermissionSlug::UsersView->value, $permission->fresh()->slug);

        $permission = Permission::query()->findOrFail($permission->id);
        $permission->is_system = false;

        $this->assertProtected(
            fn () => $permission->save(),
            'System permission [users.view] cannot be marked as non-system.',
        );
        $this->assertTrue($permission->fresh()->is_system);
    }

    public function test_non_system_roles_and_permissions_can_be_deleted(): void
    {
        $role = Role::create([
            'slug' => 'temporary-role',
            'name' => 'Temporary Role',
            'is_system' => false,
        ]);
        $permission = Permission::create([
            'slug' => 'temporary.permission',
            'name' => 'Temporary Permission',
            'is_system' => false,
        ]);

        $role->delete();
        $permission->delete();

        $this->assertDatabaseMissing('roles', ['id' => $role->id]);
        $this->assertDatabaseMissing('permissions', ['id' => $permission->id]);
    }

    public function test_system_role_loaded_without_is_system_cannot_be_deleted(): void
    {
        $role = Role::query()
            ->select(['id', 'slug'])
            ->where('slug', RoleSlug::PlatformAdmin->value)
            ->firstOrFail();

        $this->assertArrayNotHasKey('is_system', $role->getAttributes());

        $this->assertProtected(
            fn () => $role->delete(),
            'System role [platform_admin] cannot be deleted.',
        );

        $this->assertDatabaseHas('roles', [
            'id' => $role->id,
            'slug' => RoleSlug::PlatformAdmin->value,
        ]);
    }

    public function test_system_role_loaded_without_is_system_cannot_have_its_slug_changed(): void
    {
        $role = Role::query()
            ->select(['id', 'slug'])
            ->where('slug', RoleSlug::PlatformAdmin->value)
            ->firstOrFail();

        $this->assertArrayNotHasKey('is_system', $role->getAttributes());

        $role->slug = 'renamed-platform-admin';

        $this->assertProtected(
            fn () => $role->save(),
            'cannot be changed.',
        );

        $this->assertSame(
            RoleSlug::PlatformAdmin->value,
            Role::query()->findOrFail($role->id)->slug,
        );
    }

    public function test_system_permission_loaded_without_is_system_cannot_be_deleted_or_renamed(): void
    {
        $permission = Permission::query()
            ->select(['id', 'slug'])
            ->where('slug', PermissionSlug::UsersView->value)
            ->firstOrFail();

        $this->assertArrayNotHasKey('is_system', $permission->getAttributes());

        $this->assertProtected(
            fn () => $permission->delete(),
            'System permission [users.view] cannot be deleted.',
        );

        $this->assertDatabaseHas('permissions', [
            'id' => $permission->id,
            'slug' => PermissionSlug::UsersView->value,
        ]);

        $permission = Permission::query()
            ->select(['id', 'slug'])
            ->where('slug', PermissionSlug::UsersView->value)
            ->firstOrFail();
        $permission->slug = 'renamed-users-view';

        $this->assertProtected(
            fn () => $permission->save(),
            'cannot be changed.',
        );

        $this->assertSame(
            PermissionSlug::UsersView->value,
            Permission::query()->findOrFail($permission->id)->slug,
        );
    }

    public function test_non_system_role_loaded_without_is_system_remains_deletable(): void
    {
        $role = Role::create([
            'slug' => 'temporary-role',
            'name' => 'Temporary Role',
            'is_system' => false,
        ]);

        $partialRole = Role::query()
            ->select(['id', 'slug'])
            ->whereKey($role->id)
            ->firstOrFail();

        $this->assertArrayNotHasKey('is_system', $partialRole->getAttributes());

        $partialRole->delete();

        $this->assertDatabaseMissing('roles', ['id' => $role->id]);
        $this->assertNull(Role::query()->find($role->id));
    }

    private function assertProtected(callable $callback, string $expectedMessage): void
    {
        try {
            $callback();
        } catch (ProtectedSystemDefinitionException $exception) {
            $this->assertStringContainsString($expectedMessage, $exception->getMessage());

            return;
        }

        $this->fail('Expected a ProtectedSystemDefinitionException to be thrown.');
    }
}
