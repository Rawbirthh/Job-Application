<?php

namespace App\Models\Concerns;

use App\Enums\PermissionSlug;
use App\Enums\RoleSlug;
use App\Models\Role;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property-read Collection<int, Role> $roles
 */
trait HasRolesAndPermissions
{
    /**
     * @var array<int, string>|null
     */
    private ?array $cachedPermissionSlugs = null;

    /**
     * @return BelongsToMany<Role, $this>
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_user');
    }

    public function hasRole(RoleSlug|string $slug): bool
    {
        $slug = $slug instanceof RoleSlug ? $slug->value : $slug;

        if ($this->relationLoaded('roles')) {
            return $this->roles->contains('slug', $slug);
        }

        return $this->roles()->where('roles.slug', $slug)->exists();
    }

    /**
     * @return array<int, string>
     */
    public function roleSlugs(): array
    {
        $this->loadMissing([
            'roles' => fn (BelongsToMany $query) => $query->select(['roles.id', 'roles.slug']),
        ]);

        return $this->roles->pluck('slug')->values()->all();
    }

    public function hasPermission(PermissionSlug|string $slug): bool
    {
        $slug = $slug instanceof PermissionSlug ? $slug->value : $slug;

        return in_array($slug, $this->permissionSlugs(), true);
    }

    /**
     * Clear the per-instance permission memoization after assignments change.
     */
    public function clearPermissionCache(): void
    {
        $this->cachedPermissionSlugs = null;
        $this->unsetRelation('roles');
    }

    /**
     * @return array<int, string>
     */
    public function permissionSlugs(): array
    {
        if ($this->cachedPermissionSlugs !== null) {
            return $this->cachedPermissionSlugs;
        }

        $this->loadMissing([
            'roles' => fn (BelongsToMany $query) => $query->select(['roles.id', 'roles.slug']),
            'roles.permissions' => fn (BelongsToMany $query) => $query->select(['permissions.id', 'permissions.slug']),
        ]);

        $slugs = [];

        foreach ($this->roles as $role) {
            foreach ($role->permissions as $permission) {
                $slugs[$permission->slug] = true;
            }
        }

        return $this->cachedPermissionSlugs = array_keys($slugs);
    }
}
