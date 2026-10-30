<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PermissionSlug;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreRoleRequest;
use App\Http\Requests\Admin\UpdateRoleRequest;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\Authorization\RoleAssignmentService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RoleController extends Controller
{
    public function __construct(
        private readonly RoleAssignmentService $roleAssignmentService,
    ) {}

    /**
     * Display the paginated roles list.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Role::class);

        $actor = $request->user();

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $search = trim((string) ($validated['search'] ?? ''));
        $prefix = $search === '' ? null : $this->escapeLike($search).'%';

        $roles = Role::query()
            ->withCount('permissions')
            ->when($prefix !== null, function (Builder $query) use ($prefix): void {
                $query->where(function (Builder $query) use ($prefix): void {
                    $query->where('roles.name', 'like', $prefix)
                        ->orWhere('roles.slug', 'like', $prefix);
                });
            })
            ->orderBy('roles.name')
            ->orderBy('roles.id')
            ->paginate(20)
            ->withQueryString();

        $roles->through(fn (Role $role): array => [
            'id' => $role->id,
            'name' => $role->name,
            'slug' => $role->slug,
            'description' => $role->description,
            'is_system' => $role->is_system,
            'permissions_count' => $role->permissions_count,
            'permission_ids' => $role->permissions()->pluck('permissions.id')->all(),
        ]);

        // Load all permissions for the assignment form
        $permissions = Permission::query()
            ->select(['id', 'slug', 'name', 'description'])
            ->orderBy('name')
            ->get()
            ->map(fn (Permission $p): array => [
                'id' => $p->id,
                'slug' => $p->slug,
                'name' => $p->name,
                'description' => $p->description,
            ])->all();

        return Inertia::render('admin/roles/index', [
            'roles' => $roles,
            'permissions' => $permissions,
            'filters' => ['search' => $search],
            'canManageRoles' => $actor instanceof User
                && $actor->hasPermission(PermissionSlug::RolesManage),
        ]);
    }

    /**
     * Store a new role.
     */
    public function store(StoreRoleRequest $request): RedirectResponse
    {
        $this->authorize('create', Role::class);

        $role = Role::create($request->roleData());

        // Sync permissions if provided
        $permissionIds = $request->permissionIds();
        if (! empty($permissionIds)) {
            $this->roleAssignmentService->syncPermissionsToRole($role, $permissionIds);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Role created.'),
        ]);

        return to_route('admin.roles.index');
    }

    /**
     * Show the role edit form.
     */
    public function edit(Request $request, string $role): RedirectResponse
    {
        $role = Role::query()->findOrFail($role);

        $this->authorize('update', $role);

        return to_route('admin.roles.index');
    }

    /**
     * Update the role.
     */
    public function update(UpdateRoleRequest $request, string $role): RedirectResponse
    {
        $role = Role::findOrFail($role);

        $this->authorize('update', $role);

        $role->update($request->roleData());

        // Sync permissions
        $permissionIds = $request->permissionIds();
        $this->roleAssignmentService->syncPermissionsToRole($role, $permissionIds);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Role updated.'),
        ]);

        return to_route('admin.roles.index');
    }

    /**
     * Delete the role.
     */
    public function destroy(string $role): RedirectResponse
    {
        $role = Role::findOrFail($role);

        $this->authorize('delete', $role);

        $role->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Role deleted.'),
        ]);

        return to_route('admin.roles.index');
    }

    /**
     * Escape LIKE wildcards so the search term is matched literally.
     */
    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
