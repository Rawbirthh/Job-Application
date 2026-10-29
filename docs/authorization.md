# Authorization

Feature 3 implements the application's custom role and permission foundation. Roles and permissions live in the application database; no third-party authorization package is used.

## Catalogue

Roles are global and a user may hold more than one. Effective permissions are the union of the permissions attached to all of the user's roles. A user with no roles can authenticate but cannot access permission-protected routes.

| Role slug        | Display name           | Seeded permissions                                 |
| ---------------- | ---------------------- | -------------------------------------------------- |
| `platform_admin` | Platform Administrator | `admin.access`, `users.view`, `users.roles.manage` |
| `employer`       | Employer               | None yet                                           |
| `job_seeker`     | Job Seeker             | None yet                                           |

| Permission slug      | Display name          | Purpose                                 |
| -------------------- | --------------------- | --------------------------------------- |
| `admin.access`       | Access administration | Enter the `/admin` route boundary       |
| `users.view`         | View users            | Read the paginated admin user directory |
| `users.roles.manage` | Manage user roles     | View and update global role assignments |

Permissions are granted through roles only. There is no `permission_user` table, which keeps grants explicit and auditable. System code uses `App\Enums\RoleSlug` and `App\Enums\PermissionSlug`; custom definitions added later may use plain slug strings.

## Seeding

```text
php artisan db:seed --class=AuthorizationSeeder
```

The seeder is idempotent:

- It creates the three catalogue roles and permissions by slug if they are missing.
- It marks existing catalogue rows as `is_system = true`.
- It preserves existing display names and descriptions.
- It attaches the three catalogue permissions to `platform_admin` with `syncWithoutDetaching`, so custom grants are not removed.
- It never creates users or administrator credentials.

`DatabaseSeeder` calls `AuthorizationSeeder` and assigns the starter test user the `job_seeker` role.

Re-running the seeder intentionally restores any missing required system mappings. Do not use the seeder as a role-management tool for custom definitions.

## Assigning roles

### Trusted first-administrator command

```text
php artisan auth:assign-role admin@example.com platform_admin
```

The command only works with an existing user and an existing role slug. It is idempotent, does not accept a password, and does not create users. Unknown users or roles produce an actionable error.

The command is the provisioning path for the first platform administrator. There is no public administrator registration route.

### Admin screen

An authenticated user with both `admin.access` and `users.roles.manage` can open:

- `GET /admin/users` — paginated and searchable user directory.
- `GET /admin/users/{user}/roles` — role assignment form.
- `PUT /admin/users/{user}/roles` — replace a user's roles.

The screen assigns global roles only. It cannot create, rename, or delete roles or permissions. A user may intentionally be given no roles. The admin URL is not linked from the shared navigation yet; Feature 4 owns role-aware navigation.

After a successful update, the actor is redirected to `admin.users.index` when they still hold `users.view` after the change; otherwise (for example after self-demotion, or when their custom role lacks `users.view`) they are redirected to `dashboard`. The success toast is shown in either case.

### Existing users

Feature 3 performs no bulk backfill. Existing users remain roleless unless an operator assigns a role explicitly with the trusted command or the admin screen. Do not add a migration or seeder that guesses a role for existing accounts.

## System definition protections

Seeded roles and permissions are marked `is_system`. Eloquent model guards prevent:

- deleting a system role or permission;
- changing the `slug` of a system definition;
- clearing `is_system` through a normal model update.

The guards throw `App\Exceptions\ProtectedSystemDefinitionException`. Non-system definitions remain deletable and renamable by trusted server code in future features.

If a model instance was loaded without the `is_system` column, the guard hydrates the persisted value by primary key before deciding, so protection does not fail open.

These guards apply to model saves/deletes. Bulk query-builder deletes bypass model events and must not be used against system definitions.

## Last-administrator protection

The last remaining user holding `platform_admin` cannot:

- have that role removed through the admin role form;
- delete their own account through profile settings.

Both paths run inside a database transaction and lock the `platform_admin` role row before checking and writing. The invariant is implemented once in `App\Services\Authorization\RoleAssignmentService` and reports `App\Exceptions\LastAdministratorException`:

- role updates translate it to a `roles` validation error;
- account deletion translates it to an `account` validation error.

On MySQL the row lock serializes concurrent removals/deletions. SQLite test runs cannot prove concurrency locking; local tests cover the sequential invariant only.

Only role-name checks for this system invariant use `platform_admin` directly. Application authorization otherwise uses permissions through `hasPermission()`.

## Adding a permission

For a permission that is part of the code-defined system catalogue:

1. Add a case to `App\Enums\PermissionSlug`.
2. Add the slug and display name to `AuthorizationSeeder`.
3. Attach it to the appropriate role(s) in the seeder. Use `syncWithoutDetaching` so custom grants survive.
4. Enforce it with route middleware (`permission:slug`) for the route boundary and/or a Policy method for model actions. Regenerate any cached route/permission metadata if applicable.
5. Add feature tests for a permitted user, a user without the permission, and a guest where relevant.
6. Deploy by running migrations if the schema changed, then run the authorization seeder. If the new permission must exist before traffic, run the seeder before releasing the code path that requires it.

Register a middleware alias invocation as `permission:admin.access`; several `permission` entries are combined with AND semantics.

For a future non-code system permission managed by trusted application code, create the permission row explicitly, attach it to roles explicitly, and add the corresponding checks. There is no permission catalogue editor in Feature 3.

## Deployment order

1. Run migrations (`php artisan migrate`). The authorization migration only creates tables and a `users.name` index; it does not backfill users or create credentials.
2. Run `php artisan db:seed --class=AuthorizationSeeder` before accepting registrations. Registration fails closed if `job_seeker` is missing, and the user insert rolls back.
3. Register or create the first operator through normal application flow.
4. Assign the first administrator from the server console:

    ```text
    php artisan auth:assign-role operator@example.com platform_admin
    ```

5. Confirm the operator can access `/admin/users` before relying on the admin screen.

Rolling back the authorization migration drops the role/permission tables and all role assignments. This does not delete user rows, but it is destructive to assignments; back them up before rolling back on a shared environment.

## Implementation notes

- `User` uses the `HasRolesAndPermissions` concern. Permissions are resolved with one eager load of `roles.permissions` and memoized per model instance for the request.
- Role/permission writes clear the memoized set and unset the loaded `roles` relation. There is no cross-request cache, so changes take effect on the next request.
- Authorization is enforced server-side by route middleware plus Policies. Frontend checks are presentation only.
