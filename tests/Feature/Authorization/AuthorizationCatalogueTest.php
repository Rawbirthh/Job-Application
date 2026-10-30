<?php

namespace Tests\Feature\Authorization;

use App\Enums\PermissionSlug;
use App\Enums\RoleSlug;
use App\Models\Permission;
use App\Models\Role;
use Database\Seeders\AuthorizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Authorization\Concerns\InteractsWithAuthorizationCatalogue;
use Tests\TestCase;

class AuthorizationCatalogueTest extends TestCase
{
    use InteractsWithAuthorizationCatalogue, RefreshDatabase;

    public function test_seeder_creates_the_exact_system_catalogue_and_no_users(): void
    {
        $this->seedAuthorizationCatalogue();

        $expectedRoleSlugs = array_map(
            static fn (RoleSlug $role): string => $role->value,
            RoleSlug::cases(),
        );
        $actualRoleSlugs = Role::query()->pluck('slug')->all();

        sort($expectedRoleSlugs);
        sort($actualRoleSlugs);

        $this->assertSame($expectedRoleSlugs, $actualRoleSlugs);
        $this->assertDatabaseCount('roles', 3);

        $expectedPermissionSlugs = array_map(
            static fn (PermissionSlug $permission): string => $permission->value,
            PermissionSlug::cases(),
        );
        $actualPermissionSlugs = Permission::query()->pluck('slug')->all();

        sort($expectedPermissionSlugs);
        sort($actualPermissionSlugs);

        $this->assertSame($expectedPermissionSlugs, $actualPermissionSlugs);
        $this->assertDatabaseCount('permissions', 7);

        $this->assertSame(3, Role::query()->where('is_system', true)->count());
        $this->assertSame(7, Permission::query()->where('is_system', true)->count());

        $administratorRole = Role::query()
            ->where('slug', RoleSlug::PlatformAdmin->value)
            ->firstOrFail();

        $administratorPermissions = $administratorRole->permissions()->pluck('slug')->all();
        sort($administratorPermissions);

        $this->assertSame($expectedPermissionSlugs, $administratorPermissions);

        foreach ([RoleSlug::Employer, RoleSlug::JobSeeker] as $nonAdministratorRole) {
            $role = Role::query()->where('slug', $nonAdministratorRole->value)->firstOrFail();

            $this->assertCount(0, $role->permissions);
        }

        $this->assertDatabaseCount('users', 0);
    }

    public function test_reseeding_is_idempotent_and_preserves_custom_catalogue_data(): void
    {
        $this->seedAuthorizationCatalogue();

        $administratorRole = Role::query()
            ->where('slug', RoleSlug::PlatformAdmin->value)
            ->firstOrFail();
        $employerRole = Role::query()
            ->where('slug', RoleSlug::Employer->value)
            ->firstOrFail();

        $employerRole->update(['name' => 'Custom Employer Label']);

        $customRole = Role::create([
            'slug' => 'recruiter',
            'name' => 'Recruiter',
            'description' => 'A custom, non-system role.',
            'is_system' => false,
        ]);
        $customPermission = Permission::create([
            'slug' => 'jobs.publish',
            'name' => 'Publish jobs',
            'description' => 'A custom, non-system permission.',
            'is_system' => false,
        ]);

        $administratorRole->permissions()->attach($customPermission->id);

        $this->seed(AuthorizationSeeder::class);

        $this->assertDatabaseCount('roles', 4);
        $this->assertDatabaseCount('permissions', 8);

        $this->assertDatabaseHas('roles', [
            'id' => $customRole->id,
            'slug' => 'recruiter',
            'name' => 'Recruiter',
            'is_system' => false,
        ]);
        $this->assertDatabaseHas('permissions', [
            'id' => $customPermission->id,
            'slug' => 'jobs.publish',
            'name' => 'Publish jobs',
            'is_system' => false,
        ]);

        $this->assertSame('Custom Employer Label', $employerRole->fresh()->name);
        $this->assertTrue(
            $administratorRole->fresh()
                ->permissions()
                ->where('permissions.id', $customPermission->id)
                ->exists(),
        );
        $this->assertDatabaseCount('users', 0);
    }

    public function test_reseeding_restores_required_system_permission_mappings(): void
    {
        $this->seedAuthorizationCatalogue();

        $administratorRole = Role::query()
            ->where('slug', RoleSlug::PlatformAdmin->value)
            ->firstOrFail();
        $usersViewPermission = Permission::query()
            ->where('slug', PermissionSlug::UsersView->value)
            ->firstOrFail();

        $administratorRole->permissions()->detach($usersViewPermission->id);

        $this->assertFalse(
            $administratorRole->permissions()
                ->where('permissions.id', $usersViewPermission->id)
                ->exists(),
        );

        $this->seed(AuthorizationSeeder::class);

        $requiredPermissionSlugs = array_map(
            static fn (PermissionSlug $permission): string => $permission->value,
            PermissionSlug::cases(),
        );
        $attachedPermissionSlugs = $administratorRole->fresh()->permissions()->pluck('slug')->all();

        sort($requiredPermissionSlugs);
        sort($attachedPermissionSlugs);

        $this->assertSame($requiredPermissionSlugs, $attachedPermissionSlugs);
    }
}
