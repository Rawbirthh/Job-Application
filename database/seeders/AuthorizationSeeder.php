<?php

namespace Database\Seeders;

use App\Enums\PermissionSlug;
use App\Enums\RoleSlug;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class AuthorizationSeeder extends Seeder
{
    /**
     * Seed the required authorization catalogue.
     */
    public function run(): void
    {
        $roles = [
            RoleSlug::PlatformAdmin->value => 'Platform Administrator',
            RoleSlug::Employer->value => 'Employer',
            RoleSlug::JobSeeker->value => 'Job Seeker',
        ];

        foreach ($roles as $slug => $name) {
            $role = Role::query()->firstOrCreate(
                ['slug' => $slug],
                ['name' => $name, 'is_system' => true],
            );

            $this->markAsSystem($role);
        }

        $permissions = [
            PermissionSlug::AdminAccess->value => 'Access administration',
            PermissionSlug::UsersView->value => 'View users',
            PermissionSlug::UsersRolesManage->value => 'Manage user roles',
        ];

        foreach ($permissions as $slug => $name) {
            $permission = Permission::query()->firstOrCreate(
                ['slug' => $slug],
                ['name' => $name, 'is_system' => true],
            );

            $this->markAsSystem($permission);
        }

        $administratorRole = Role::query()
            ->where('slug', RoleSlug::PlatformAdmin->value)
            ->firstOrFail(['id']);

        // Only these approved permissions are granted to platform_admin. New
        // PermissionSlug cases must be mapped here deliberately.
        $platformAdminPermissionSlugs = [
            PermissionSlug::AdminAccess->value,
            PermissionSlug::UsersView->value,
            PermissionSlug::UsersRolesManage->value,
        ];

        $permissionIds = Permission::query()
            ->whereIn('slug', $platformAdminPermissionSlugs)
            ->pluck('id')
            ->all();

        $administratorRole->permissions()->syncWithoutDetaching($permissionIds);
    }

    private function markAsSystem(Role|Permission $definition): void
    {
        if ($definition->is_system) {
            return;
        }

        $definition->is_system = true;
        $definition->save();
    }
}
