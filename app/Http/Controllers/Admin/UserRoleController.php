<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AuditAction;
use App\Enums\PermissionSlug;
use App\Exceptions\LastAdministratorException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateUserRolesRequest;
use App\Models\Role;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use App\Services\Authorization\RoleAssignmentService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class UserRoleController extends Controller
{
    public function __construct(
        private readonly RoleAssignmentService $roleAssignmentService,
        private readonly AuditLogService $auditLogService,
    ) {}

    /**
     * Display the paginated user directory.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', User::class);

        $actor = $request->user();

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $search = trim((string) ($validated['search'] ?? ''));
        $prefix = $search === '' ? null : $this->escapeLike($search).'%';

        $users = User::query()
            ->select(['users.id', 'users.name', 'users.email'])
            ->with('roles:id,slug,name')
            ->when($prefix !== null, function (Builder $query) use ($prefix): void {
                $query->where(function (Builder $query) use ($prefix): void {
                    $query->where('users.name', 'like', $prefix)
                        ->orWhere('users.email', 'like', $prefix);
                });
            })
            ->orderBy('users.name')
            ->orderBy('users.id')
            ->paginate(20)
            ->withQueryString();

        $users->through(fn (User $user): array => $this->userPayload($user));

        return Inertia::render('admin/users/index', [
            'users' => $users,
            'filters' => ['search' => $search],
            'canManageRoles' => $actor instanceof User
                && $actor->hasPermission(PermissionSlug::UsersRolesManage),
        ]);
    }

    /**
     * Show the role assignment form for a user.
     */
    public function edit(Request $request, string $user): Response
    {
        $target = $this->resolveTargetUser($user);

        $this->authorize('updateRoles', $target);

        $actor = $request->user();

        // The role catalogue is intentionally small, bounded system configuration.
        $roles = Role::query()
            ->select(['id', 'slug', 'name', 'description', 'is_system'])
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        return Inertia::render('admin/users/roles', [
            'user' => $this->userPayload($target),
            'roles' => $roles->map(fn (Role $role): array => [
                ...$this->roleSummary($role),
                'description' => $role->description,
                'is_system' => $role->is_system,
            ])->all(),
            'canViewUsers' => $actor instanceof User
                && $actor->hasPermission(PermissionSlug::UsersView),
        ]);
    }

    /**
     * Replace the target user's global roles.
     */
    public function update(UpdateUserRolesRequest $request, string $user): RedirectResponse
    {
        $target = $this->resolveTargetUser($user);

        $this->authorize('updateRoles', $target);

        $actor = $request->user();

        try {
            // The outer transaction makes the role sync and its audit entry atomic;
            // the service opens a nested transaction (savepoint) of its own.
            DB::transaction(function () use ($target, $request, $actor): void {
                $rolesBefore = $target->roleSlugs();

                $this->roleAssignmentService->syncRoles($target, $request->roleIds());

                $rolesAfter = $target->roleSlugs();

                $this->auditLogService->record(AuditAction::UserRolesUpdated, $target, [
                    'roles_added' => array_values(array_diff($rolesAfter, $rolesBefore)),
                    'roles_removed' => array_values(array_diff($rolesBefore, $rolesAfter)),
                ], $actor instanceof User ? $actor : null);
            });
        } catch (LastAdministratorException) {
            throw ValidationException::withMessages([
                'roles' => __('At least one platform administrator must remain.'),
            ]);
        }

        // The service clears the target instance; the authenticated actor can be a
        // separate instance when the actor updates their own roles.
        if ($actor instanceof User && $actor->is($target)) {
            $actor->clearPermissionCache();
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('User roles updated.'),
        ]);

        if ($actor instanceof User && $actor->hasPermission(PermissionSlug::UsersView)) {
            return to_route('admin.users.index');
        }

        return to_route('dashboard');
    }

    private function resolveTargetUser(string $userId): User
    {
        if (! ctype_digit($userId)) {
            abort(404);
        }

        return User::query()
            ->select(['id', 'name', 'email'])
            ->with('roles:id,slug,name')
            ->findOrFail((int) $userId);
    }

    /**
     * @return array{id: int, name: string, email: string, roles: array<int, array{id: int, slug: string, name: string}>}
     */
    private function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'roles' => $user->roles
                ->map(fn (Role $role): array => $this->roleSummary($role))
                ->all(),
        ];
    }

    /**
     * @return array{id: int, slug: string, name: string}
     */
    private function roleSummary(Role $role): array
    {
        return [
            'id' => $role->id,
            'slug' => $role->slug,
            'name' => $role->name,
        ];
    }

    /**
     * Escape LIKE wildcards so the search term is matched literally.
     */
    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
