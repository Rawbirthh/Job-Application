<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PermissionSlug;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePermissionRequest;
use App\Http\Requests\Admin\UpdatePermissionRequest;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PermissionController extends Controller
{
    /**
     * Display the paginated permissions list.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Permission::class);

        $actor = $request->user();

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $search = trim((string) ($validated['search'] ?? ''));
        $prefix = $search === '' ? null : $this->escapeLike($search).'%';

        $permissions = Permission::query()
            ->withCount('roles')
            ->when($prefix !== null, function (Builder $query) use ($prefix): void {
                $query->where(function (Builder $query) use ($prefix): void {
                    $query->where('permissions.name', 'like', $prefix)
                        ->orWhere('permissions.slug', 'like', $prefix);
                });
            })
            ->orderBy('permissions.name')
            ->orderBy('permissions.id')
            ->paginate(20)
            ->withQueryString();

        $permissions->through(fn (Permission $permission): array => [
            'id' => $permission->id,
            'name' => $permission->name,
            'slug' => $permission->slug,
            'description' => $permission->description,
            'is_system' => $permission->is_system,
            'roles_count' => $permission->roles_count,
        ]);

        return Inertia::render('admin/permissions/index', [
            'permissions' => $permissions,
            'filters' => ['search' => $search],
            'canManagePermissions' => $actor instanceof User
                && $actor->hasPermission(PermissionSlug::PermissionsManage),
        ]);
    }

    /**
     * Store a new permission.
     */
    public function store(StorePermissionRequest $request): RedirectResponse
    {
        $this->authorize('create', Permission::class);

        Permission::create($request->permissionData());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Permission created.'),
        ]);

        return to_route('admin.permissions.index');
    }

    /**
     * Show the permission edit form.
     */
    public function edit(string $permission): Response
    {
        $permission = Permission::withCount('roles')->findOrFail($permission);

        $this->authorize('update', $permission);

        return Inertia::render('admin/permissions/edit', [
            'permission' => [
                'id' => $permission->id,
                'name' => $permission->name,
                'slug' => $permission->slug,
                'description' => $permission->description,
                'is_system' => $permission->is_system,
                'roles_count' => $permission->roles_count,
            ],
        ]);
    }

    /**
     * Update the permission.
     */
    public function update(UpdatePermissionRequest $request, string $permission): RedirectResponse
    {
        $permission = Permission::findOrFail($permission);

        $this->authorize('update', $permission);

        $permission->update($request->permissionData());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Permission updated.'),
        ]);

        return to_route('admin.permissions.index');
    }

    /**
     * Delete the permission.
     */
    public function destroy(string $permission): RedirectResponse
    {
        $permission = Permission::findOrFail($permission);

        $this->authorize('delete', $permission);

        // Detach from all roles before deletion
        $permission->roles()->detach();

        $permission->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Permission deleted.'),
        ]);

        return to_route('admin.permissions.index');
    }

    /**
     * Escape LIKE wildcards so the search term is matched literally.
     */
    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
