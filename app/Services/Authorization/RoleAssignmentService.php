<?php

namespace App\Services\Authorization;

use App\Enums\PermissionSlug;
use App\Enums\RoleSlug;
use App\Exceptions\LastAdministratorException;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class RoleAssignmentService
{
    /**
     * Replace the user's global roles with the given role IDs.
     *
     * @param  array<int, int>  $roleIds
     */
    public function syncRoles(User $user, array $roleIds): void
    {
        $roleIds = array_values(array_unique($roleIds));

        DB::transaction(function () use ($user, $roleIds): void {
            $administratorRole = $this->lockPlatformAdministratorRole();

            if ($administratorRole !== null && ! in_array($administratorRole->id, $roleIds, true)) {
                $this->assertAdministratorRemains($user, $administratorRole);
            }

            $user->roles()->sync($roleIds);
        });

        $user->clearPermissionCache();
    }

    /**
     * Attach a role without detaching the user's existing roles.
     */
    public function assignRole(User $user, Role $role): void
    {
        DB::transaction(function () use ($user, $role): void {
            $user->roles()->syncWithoutDetaching([$role->id]);
        });

        $user->clearPermissionCache();
    }

    public function assignRoleBySlug(User $user, RoleSlug|string $slug): void
    {
        $slug = $slug instanceof RoleSlug ? $slug->value : $slug;

        $role = Role::query()
            ->where('slug', $slug)
            ->first(['id', 'slug']);

        if ($role === null) {
            throw new RuntimeException(
                "Role [{$slug}] is not defined. Run the AuthorizationSeeder before assigning roles.",
            );
        }

        $this->assignRole($user, $role);
    }

    /**
     * Assert that deleting the user would not remove the last platform administrator.
     *
     * The caller must wrap this check and the deletion in the same outer transaction
     * so the platform_admin row lock is held until the user row is deleted.
     */
    public function assertUserCanBeDeleted(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $administratorRole = $this->lockPlatformAdministratorRole();

            if ($administratorRole === null) {
                return;
            }

            $this->assertAdministratorRemains($user, $administratorRole);
        });
    }

    /**
     * Determine whether the user is the only remaining platform administrator.
     *
     * This is a read-only helper for policies and display; the deletion and role-update
     * paths enforce the invariant under a transaction lock.
     */
    public function isLastAdministrator(User $user): bool
    {
        $administratorRole = Role::query()
            ->where('slug', RoleSlug::PlatformAdmin->value)
            ->first(['id']);

        if ($administratorRole === null) {
            return false;
        }

        $holdsAdministratorRole = $user->roles()
            ->where('roles.id', $administratorRole->id)
            ->exists();

        if (! $holdsAdministratorRole) {
            return false;
        }

        return ! $this->anotherAdministratorExists($user, $administratorRole);
    }

    private function lockPlatformAdministratorRole(): ?Role
    {
        return Role::query()
            ->where('slug', RoleSlug::PlatformAdmin->value)
            ->lockForUpdate()
            ->first(['id', 'slug']);
    }

    private function assertAdministratorRemains(User $user, Role $administratorRole): void
    {
        $holdsAdministratorRole = $user->roles()
            ->where('roles.id', $administratorRole->id)
            ->exists();

        if (! $holdsAdministratorRole) {
            return;
        }

        if (! $this->anotherAdministratorExists($user, $administratorRole)) {
            throw new LastAdministratorException;
        }
    }

    private function anotherAdministratorExists(User $user, Role $administratorRole): bool
    {
        return User::query()
            ->whereKeyNot($user->getKey())
            ->whereHas('roles', function (Builder $query) use ($administratorRole): void {
                $query->where('roles.id', $administratorRole->id);
            })
            ->exists();
    }

    /**
     * Sync permissions to a role with last-admin protection.
     *
     * Cannot remove admin.access from platform_admin role if no other role has it.
     *
     * @param  array<int, int>  $permissionIds
     */
    public function syncPermissionsToRole(Role $role, array $permissionIds): void
    {
        $permissionIds = array_values(array_unique($permissionIds));

        // Check last-admin protection: if removing admin.access from platform_admin,
        // ensure another role retains it
        if ($role->slug === RoleSlug::PlatformAdmin->value) {
            $adminAccessPermission = Permission::query()
                ->where('slug', PermissionSlug::AdminAccess->value)
                ->first(['id']);

            if ($adminAccessPermission !== null) {
                $this->assertAdminAccessRetained($role, $adminAccessPermission->id, $permissionIds);
            }
        }

        $role->permissions()->sync($permissionIds);
    }

    /**
     * Assert that admin.access permission is retained by at least one role when modifying role permissions.
     *
     * @param  array<int, int>  $newPermissionIds
     */
    public function assertAdminAccessRetained(
        Role $role,
        int $adminAccessPermissionId,
        array $newPermissionIds
    ): void {
        $hasNotRemoved = in_array($adminAccessPermissionId, $newPermissionIds, true);

        if ($hasNotRemoved) {
            return;
        }

        // Check if any other role still has admin.access
        $otherRoleHasAdminAccess = Role::query()
            ->where('id', '!=', $role->id)
            ->whereHas('permissions', function (Builder $query) use ($adminAccessPermissionId): void {
                $query->where('permissions.id', $adminAccessPermissionId);
            })
            ->exists();

        if (! $otherRoleHasAdminAccess) {
            throw new LastAdministratorException(
                'At least one platform administrator role must retain admin.access permission.',
            );
        }
    }
}
