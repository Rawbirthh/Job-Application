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
            PermissionSlug::RolesView->value => 'View roles',
            PermissionSlug::RolesManage->value => 'Manage roles',
            PermissionSlug::PermissionsView->value => 'View permissions',
            PermissionSlug::PermissionsManage->value => 'Manage permissions',
            PermissionSlug::CategoriesView->value => 'View job categories',
            PermissionSlug::CategoriesManage->value => 'Manage job categories',
            PermissionSlug::EmploymentTypesView->value => 'View employment types',
            PermissionSlug::EmploymentTypesManage->value => 'Manage employment types',
            PermissionSlug::WorkplaceArrangementsView->value => 'View workplace arrangements',
            PermissionSlug::WorkplaceArrangementsManage->value => 'Manage workplace arrangements',
            PermissionSlug::ExperienceLevelsView->value => 'View experience levels',
            PermissionSlug::ExperienceLevelsManage->value => 'Manage experience levels',
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
            PermissionSlug::RolesView->value,
            PermissionSlug::RolesManage->value,
            PermissionSlug::PermissionsView->value,
            PermissionSlug::PermissionsManage->value,
            PermissionSlug::CategoriesView->value,
            PermissionSlug::CategoriesManage->value,
            PermissionSlug::EmploymentTypesView->value,
            PermissionSlug::EmploymentTypesManage->value,
            PermissionSlug::WorkplaceArrangementsView->value,
            PermissionSlug::WorkplaceArrangementsManage->value,
            PermissionSlug::ExperienceLevelsView->value,
            PermissionSlug::ExperienceLevelsManage->value,
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
