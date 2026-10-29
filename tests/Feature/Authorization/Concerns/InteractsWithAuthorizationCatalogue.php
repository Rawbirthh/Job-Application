<?php

namespace Tests\Feature\Authorization\Concerns;

use App\Enums\PermissionSlug;
use App\Enums\RoleSlug;
use App\Models\Permission;
use App\Models\Role;
use Database\Seeders\AuthorizationSeeder;

trait InteractsWithAuthorizationCatalogue
{
    protected function seedAuthorizationCatalogue(): void
    {
        $this->seed(AuthorizationSeeder::class);
    }

    protected function roleId(RoleSlug|string $slug): int
    {
        return (int) Role::query()
            ->where('slug', $slug instanceof RoleSlug ? $slug->value : $slug)
            ->firstOrFail(['id'])
            ->id;
    }

    /**
     * @param  array<int, PermissionSlug|string>  $permissions
     */
    protected function createCustomRoleWithPermissions(string $slug, string $name, array $permissions): Role
    {
        $role = Role::create([
            'slug' => $slug,
            'name' => $name,
            'description' => null,
            'is_system' => false,
        ]);

        $permissionIds = Permission::query()
            ->whereIn('slug', array_map(
                static fn (PermissionSlug|string $permission): string => $permission instanceof PermissionSlug
                    ? $permission->value
                    : $permission,
                $permissions,
            ))
            ->pluck('id')
            ->all();

        $this->assertCount(count($permissions), $permissionIds);

        $role->permissions()->sync($permissionIds);

        return $role;
    }
}
