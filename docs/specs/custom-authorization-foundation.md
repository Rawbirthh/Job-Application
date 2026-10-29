# Feature 3 — Custom Roles, Permissions, Middleware, and Policies

- **Feature:** 3
- **Slug:** `custom-authorization-foundation`
- **Status:** Approved
- **Date:** 2026-10-28
- **Dependencies:** Feature 2 (implemented)
- **Scope type:** Database-backed authorization foundation plus one minimal administrative slice for assigning global roles to users

## 1. Goal

Implement a small, auditable authorization foundation that:

- Stores roles and permissions in the application database without a third-party role package.
- Assigns permissions to roles and roles to users through explicit pivots.
- Provides server-side permission checks for routes, policies, controllers, and future features.
- Seeds only the roles and permissions needed for this phase.
- Supports a trusted first-administrator provisioning path.
- Prevents the last usable platform administrator and system authorization definitions from being accidentally removed.
- Establishes the Policy and Form Request patterns that later features will follow.

The feature includes a narrow admin user-role assignment screen so the authorization foundation is exercised end to end. It does not build a role/permission catalogue editor.

## 2. Non-goals

- Admin UI for creating, renaming, or deleting roles or permissions.
- Direct user-to-permission assignments. Permissions are granted through roles only.
- Company hiring-team membership or company-scoped roles (Feature 28).
- Employer onboarding or automatic employer-role assignment (Feature 11).
- Job, company, application, or moderation permissions.
- Shared role-aware navigation, breadcrumbs, and persona landing pages (Feature 4).
- Audit logging for role changes (Feature 31).
- User suspension/account-status restrictions (Feature 10).
- API tokens, Sanctum, SSO, social login, or a new authorization package.
- Backfilling production users automatically or creating default administrator credentials.
- Email verification changes.

## 3. Current-state evidence

- `app/Models/User.php` has only `name`, `email`, and `password` fillable fields. It has no role relationship, no permission helpers, and no `is_admin` flag.
- `app/Http/Controllers/Controller.php` is an empty abstract class and does not use Laravel's `AuthorizesRequests` trait.
- There are no role, permission, Policy, Gate, or authorization-middleware files in `app/`.
- `routes/web.php` contains only public `home` and authenticated `dashboard`; `routes/settings.php` contains self-service settings routes.
- `app/Actions/Fortify/CreateNewUser.php` maps only name, email, and password into `User::create()`.
- `app/Http/Controllers/Settings/ProfileController.php` hard-deletes the authenticated user after password confirmation. A last-administrator guard must cover this existing deletion path.
- `app/Http/Middleware/HandleInertiaRequests.php` shares only `auth.user` `id`, `name`, and `email`.
- No shared Pagination component or table primitive exists under `resources/js/`; existing list pages are starter-kit settings pages.
- `tests/` uses PHPUnit-style feature test classes with Pest 5 as the runner. The last verified full suite was 61 tests / 316 assertions.
- The repository is pre-production and has no business-domain schema. Frontend was built successfully in Feature 2.

## 4. Assumptions and decisions to confirm

These are explicit assumptions, not blockers. Confirm or amend them before implementation.

- **A1 — Multiple global roles are allowed.** A user may hold multiple roles; effective permissions are the union. Feature 4 must define deterministic landing precedence when a user has more than one persona role. If single-role users are required, change the `role_user` uniqueness rule and admin UI to a single select.
- **A2 — A zero-role user is allowed.** Such a user can still authenticate and use auth-only pages, but cannot access permission-protected pages. This avoids inventing a “must always have a role” product rule.
- **A3 — Feature 3 includes a minimal admin user-role assignment screen.** It is not a role/permission catalogue editor. This provides a real route/Policy/Form Request slice against the existing `User` resource. If this screen is unwanted, keep the schema, service, command, middleware, and policies, but omit the admin UI/routes in implementation.
- **A4 — First-admin provisioning assigns a role to an existing user.** The trusted CLI path does not create users or accept passwords. The operator registers/creates the user through the normal flow, then assigns `platform_admin` from the server console.
- **A5 — Existing pre-production users are not bulk-backfilled.** No migration will guess that every existing user is a job seeker. Existing users can be assigned explicitly with the trusted command; new registrations receive `job_seeker`.
- **A6 — Database target remains unresolved.** `AGENTS.md` states MySQL; local/test currently use SQLite. The schema and queries will remain portable. The last-administrator transaction lock is meaningful on MySQL; SQLite does not provide equivalent row locking.
- **A7 — Permissions are bundled only through roles.** There is no `permission_user` pivot. This keeps assignments understandable and auditable.
- **A8 — System permissions and roles are seeded, but no admin credentials are seeded.** The command is the only first-admin path.
- **A9 — Account suspension is future work.** “Last usable administrator” currently means the last user holding the `platform_admin` role. Feature 10/11 must extend this definition when account status exists.

## 5. Initial role and permission catalogue

| Role slug        | Display name           | Permissions                                        |
| ---------------- | ---------------------- | -------------------------------------------------- |
| `platform_admin` | Platform Administrator | `admin.access`, `users.view`, `users.roles.manage` |
| `employer`       | Employer               | None yet                                           |
| `job_seeker`     | Job Seeker             | None yet                                           |

| Permission slug      | Display name          | Resource/action mapping                           |
| -------------------- | --------------------- | ------------------------------------------------- |
| `admin.access`       | Access administration | Enter the `/admin` route boundary                 |
| `users.view`         | View users            | Read the paginated admin user directory           |
| `users.roles.manage` | Manage user roles     | View and update global role assignments on a user |

Only these three permissions are seeded. Feature 4 may add persona landing/access permissions; this feature must not prebuild future business permissions.

All seeded roles and permissions are marked `is_system = true`. Future custom roles/permissions may be added later, but no catalogue UI is part of this feature.

## 6. User flows and acceptance criteria

### F1 — Public registration assigns only the default job-seeker role

A guest registers with valid name, email, and password. The action creates the user and assigns `job_seeker` in the same transaction. The browser cannot submit a role, permission, admin flag, or role ID that changes the result.

Acceptance criteria:

- New registrations have exactly the `job_seeker` role after registration.
- The request payload fields `role`, `role_id`, `is_admin`, `permissions`, and similar fields have no effect.
- The user and role assignment commit atomically; a failed role assignment does not leave a user without a role.
- Registration fails closed if the `job_seeker` catalogue row is missing; this is a deployment error, not a reason to self-create catalogue data in the registration request.
- Existing auth behavior and redirects remain unchanged.

### F2 — First platform administrator provisioning

An operator runs migrations and the authorization seeder, creates/registers a user through the normal flow, then runs the trusted command:

```text
php artisan auth:assign-role admin@example.com platform_admin
```

Acceptance criteria:

- The command requires an existing user and existing role slug.
- The command assigns the role without exposing a public administrator registration route.
- Re-running the command is idempotent.
- The command prints actionable errors for an unknown user or role.
- It does not create users, accept a password argument, or seed an administrator automatically.

### F3 — Admin lists and searches users

An authenticated user with `admin.access` and `users.view` opens `/admin/users`. The page shows a paginated, searchable list of users with their global roles.

Acceptance criteria:

- Guests are redirected to login; authenticated users without both permissions receive 403.
- Search matches name and email prefixes and preserves the query string across pagination.
- The list selects only `id`, `name`, `email`, and related role summary fields.
- Roles are eager-loaded; rendering the page does not issue a query per user.
- The page has an empty state for no users and for no search results.
- Pagination is server-driven at 20 users per page.

### F4 — Admin edits a user's global roles

From the user list, an authorized administrator opens a role form, selects roles, and submits. The server validates role IDs, enforces the last-administrator invariant, and redirects back with feedback.

Acceptance criteria:

- The edit page and update action require `admin.access` and `users.roles.manage`.
- The submitted value is a list of role IDs; no role names, permission IDs, or system flags are trusted.
- Unknown or duplicate role IDs fail validation.
- The update uses a transaction and replaces role assignments with the validated set.
- A roleless user can be created intentionally; the form allows an empty selection.
- Removing `platform_admin` from the last administrator is rejected with a visible error and persists no change.
- Removing the last administrator is also blocked if the administrator attempts self-demotion.
- If another administrator exists, an administrator may be demoted.
- Only roles are changed; the target user's profile identity and permissions defined on roles are untouched.
- Success redirects to the user list and shows a toast. If the actor no longer
  has `users.view` after the update (for example a self-demotion) or never held
  it, redirect to the dashboard instead while keeping the toast.
- The user list hides role-management links when the actor lacks
  `users.roles.manage`; the role form's back/cancel links fall back to the
  dashboard when the actor lacks `users.view`.

### F5 — Existing account deletion respects the last-administrator invariant

The existing `/settings/profile` deletion flow remains self-service, but the authenticated user must be allowed by Policy and the account must not be the last platform administrator.

Acceptance criteria:

- `UserPolicy::delete` permits only the authenticated user to delete their own account.
- The deletion controller calls `$this->authorize('delete', $user)`.
- The service checks the last-administrator invariant inside a transaction and locks the `platform_admin` role row before deleting.
- The last administrator receives a visible `account` error instead of being logged out/deleted.
- A non-administrator can still delete their account with the correct password.
- An administrator can delete their account when another administrator exists.
- Failed authorization or invariant checks do not log the user out or delete data.

### F6 — System definitions cannot be silently removed

Acceptance criteria:

- A system role or permission cannot be deleted through an Eloquent model deletion.
- A system role/permission `slug` cannot be changed, and `is_system` cannot be cleared through normal model updates.
- Non-system definitions can still be deleted/renamed by trusted server code in future features.
- The seeder never creates an administrator account or overwrites a catalogue display name.

## 7. Routes, middleware, and access boundaries

New file: `routes/admin.php`, required from `routes/web.php`.

| Method | URI                         | Name                       | Middleware                                                                | Handler                           |
| ------ | --------------------------- | -------------------------- | ------------------------------------------------------------------------- | --------------------------------- |
| GET    | `/admin/users`              | `admin.users.index`        | `web`, `auth`, `permission:admin.access`, `permission:users.view`         | `Admin\UserRoleController@index`  |
| GET    | `/admin/users/{user}/roles` | `admin.users.roles.edit`   | `web`, `auth`, `permission:admin.access`, `permission:users.roles.manage` | `Admin\UserRoleController@edit`   |
| PUT    | `/admin/users/{user}/roles` | `admin.users.roles.update` | `web`, `auth`, `permission:admin.access`, `permission:users.roles.manage` | `Admin\UserRoleController@update` |

- The route group uses `prefix('admin')->name('admin.')`.
- `{user}` is resolved by the controller with an explicit selected query, not implicit binding, so the admin page does not load password/2FA fields. Missing users return 404.
- `permission` is registered as a middleware alias in `bootstrap/app.php`.
- Each `permission` invocation requires the named permission; multiple middleware entries are combined as AND. OR semantics are not needed in this feature.
- Guest access is handled by `auth` before the permission middleware. Authenticated users missing a permission receive 403 via `abort(403)`.
- Existing routes keep their current boundaries. No admin route is added to the public auth flow.
- `app/Http/Controllers/Controller.php` gains Laravel's `AuthorizesRequests` trait so controllers can call `$this->authorize()`.
- No sidebar/navigation link is added. Feature 4 owns the shared shell and role-aware navigation; the admin URL is reachable directly until then.

## 8. Authorization resolution and Policies

### Permission resolution

Add a custom `HasRolesAndPermissions` concern to `User`:

- `roles(): BelongsToMany`
- `hasRole(RoleSlug|string $slug): bool`
- `hasPermission(PermissionSlug|string $slug): bool`
- A per-model-instance memoized permission-slug set populated with one eager load of `roles.permissions`, selecting only `id` and `slug`.
- A method to clear the memoized permission set when assignments change.

No global permission cache is introduced. Permissions are resolved fresh on the next request. Role/permission writes go through the service so the current model instance is invalidated.

### Policies

Create `app/Policies/UserPolicy.php`.

| Action        | Rule                                                                                          |
| ------------- | --------------------------------------------------------------------------------------------- |
| `viewAny`     | Actor has `users.view`                                                                        |
| `updateRoles` | Actor has `users.roles.manage`                                                                |
| `delete`      | Actor is the target user; the last-admin invariant is enforced in the service during deletion |

- Policies use `hasPermission`, never role-name comparisons, except where the domain concept is literally the system administrator role (`platform_admin` protection).
- Controllers call `$this->authorize()` before reading protected data or mutating a target.
- Route middleware remains the access boundary; Policies are defense in depth and the model-action authority.
- No `Gate::before` superuser bypass is added.

## 9. Form Requests and validation

### `app/Http/Requests/Admin/UpdateUserRolesRequest.php`

- `roles` is `present|array`.
- `roles.*` is `integer|distinct|exists:roles,id`.
- An empty array is valid; removing all roles is allowed except when the target is the last platform administrator.
- No permission IDs, role slugs, `is_system`, or user identity fields are accepted.
- Authorization is performed by the controller through `UserPolicy::updateRoles`. The Form Request may return `true` because the controller performs the actual Policy check.
- Last-administrator rejection is translated to a `roles` field error, not a server error page.

### `app/Http/Requests/Settings/ProfileDeleteRequest.php`

- Existing password confirmation rules remain unchanged.
- Authorization remains in the controller through `UserPolicy::delete`; the last-admin invariant is enforced by the service in the delete transaction.
- The last-admin error is returned under `account` and displayed in the existing delete-account dialog.

### Search input

The admin list validates `search` as an optional string with a maximum length of 100 characters. `%` and `_` are escaped before use in a prefix `LIKE` query. No arbitrary ordering or page-size parameter is accepted.

## 10. Data model, migration, constraints, and indexes

One new migration (timestamped after the existing migrations) creates four tables and one user index.

### `roles`

| Column        | Definition                              |
| ------------- | --------------------------------------- |
| `id`          | bigint primary key                      |
| `slug`        | string(64), unique — stable machine key |
| `name`        | string(100), unique — display label     |
| `description` | string(255), nullable                   |
| `is_system`   | boolean, default false                  |
| `timestamps`  | created_at, updated_at                  |

### `permissions`

| Column        | Definition                               |
| ------------- | ---------------------------------------- |
| `id`          | bigint primary key                       |
| `slug`        | string(100), unique — stable machine key |
| `name`        | string(100), unique — display label      |
| `description` | string(255), nullable                    |
| `is_system`   | boolean, default false                   |
| `timestamps`  | created_at, updated_at                   |

### `role_user`

- `user_id` foreign key to `users`, cascade on delete.
- `role_id` foreign key to `roles`, cascade on delete.
- Composite primary key `(user_id, role_id)` prevents duplicate assignments.
- Index on `role_id` for role-to-user lookups and last-admin counts.
- No timestamps; role-change auditing belongs to Feature 31.

### `permission_role`

- `role_id` foreign key to `roles`, cascade on delete.
- `permission_id` foreign key to `permissions`, cascade on delete.
- Composite primary key `(role_id, permission_id)` prevents duplicate grants.
- Index on `permission_id` for permission-to-role reverse lookups.
- No timestamps.

### Existing table change

- Add an index on `users.name` to support the admin name-prefix search. `users.email` is already unique.
- No role column is added to `users`; no `is_admin` shortcut is introduced.
- Migration `down()` drops the user index and the four tables in dependency order. This is destructive only to role assignments, not user rows.

## 11. Query and data payload plan

### Admin user index

- Select only `users.id`, `users.name`, `users.email`.
- Eager-load `roles:id,slug,name` (role summaries only).
- Optional prefix search on `name` or `email`; escape wildcards and trim the term.
- Order by `name`, then `id` for deterministic pagination.
- `paginate(20)->withQueryString()`.
- Map the paginator through a typed projection; do not send full user models.
- No `SELECT *` and no per-row role queries.

Expected query count for one page: count query, page query, role pivot query. The test suite should assert no user-per-role query growth.

### Role edit page

- Resolve the target with `id`, `name`, `email`, and eager-loaded `roles:id,slug,name`.
- Fetch the role catalogue with `id`, `slug`, `name`, `description`, `is_system`, ordered by name.
- The role catalogue is a small, bounded system configuration table. If custom role counts grow later, the catalogue feature must add active filtering/pagination.
- No permissions, password fields, 2FA fields, or remember-token fields are sent to Inertia.

### Permission checks

- `hasPermission` performs at most one `roles` query and one `roles.permissions` query per user model instance, then memoizes the slug set for the request.
- Policies and middleware share the memoized result on the same user instance.
- Role/permission writes call `unsetRelation('roles')` and clear the memoized set.
- No cross-request cache; role changes take effect on the next request.

### Last-administrator count

- `exists`/`count` exists through the `roles` relation, using the `role_user.role_id` index.
- The admin role row is selected with `lockForUpdate()` before counting and mutating in the transaction.
- On MySQL, concurrent role removals and account deletions serialize on that row. SQLite test runs cannot prove concurrency locking and are documented as such.

## 12. Service / Repository decision

### Add `app/Services/Authorization/RoleAssignmentService.php`

Reasons:

- Role replacement is multi-write and must be transactional.
- The last-administrator invariant is shared by role updates, account deletion, and future role-management features.
- The service centralizes locking, invariant checks, role lookup, and cache invalidation.
- The trusted command and registration action need the same server-side assignment rules.

Methods:

- `syncRoles(User $user, array $roleIds): void`
- `assignRole(User $user, Role $role): void`
- `assignRoleBySlug(User $user, RoleSlug|string $slug): void`
- `assertUserCanBeDeleted(User $user): void`
- `isLastAdministrator(User $user): bool` (read-only policy/display helper)

The service throws a dedicated `LastAdministratorException`. The admin controller translates it to a `roles` validation error; the profile controller translates it to an `account` validation error.

### No repository

Eloquent relationships and `whereHas`/`exists` are sufficient. There is no complex reusable aggregate query or cross-entity read model that justifies a repository.

### No separate authorization read service

The `User` concern is intentionally small: one eager load and one memoized set. A service would add indirection without a second caller or query shape.

### Centralized slugs

Add `App\Enums\RoleSlug` and `App\Enums\PermissionSlug` for the seeded keys. System code, seeders, policies, middleware tests, and the registration action use the enums; custom future permissions may use strings.

## 13. Seeding, provisioning, and documentation

### `database/seeders/AuthorizationSeeder.php`

- Idempotently create the three roles and three permissions by slug.
- Set `is_system` on seeded definitions without overwriting existing display names/descriptions.
- Attach all three permissions to `platform_admin` with `syncWithoutDetaching`; do not remove custom mappings.
- Do not remove custom roles, custom permissions, or custom grants.
- Re-running the seeder restores missing required system mappings intentionally; document that behavior.
- Seed no users and no administrator credentials.

`DatabaseSeeder` calls `AuthorizationSeeder`, then assigns `job_seeker` to its starter test user.

### Trusted admin command

`app/Console/Commands/AssignRoleCommand.php`:

```text
php artisan auth:assign-role {email : Existing user email} {role=platform_admin : Role slug}
```

- Normalize the email for lookup.
- Fail clearly when the user or role is missing.
- Assign idempotently through `RoleAssignmentService`.
- Do not create users, prompt for passwords, or expose a web route.

### Documentation

Add `docs/authorization.md` covering:

- The role/permission catalogue and why permissions are role-based.
- How to add a system permission safely: enum case, seeder definition, role mapping, middleware/Policy check, tests, and deployment seed.
- How to add a non-code/system permission later.
- How to assign roles with the trusted command and admin screen.
- Last-administrator and system-definition protections.
- Deployment order: migrate, seed catalogue, create/register operator, assign first administrator.
- Existing-user backfill policy: explicit, no automatic bulk assignment.

## 14. React / Inertia plan

### New page: `resources/js/pages/admin/users/index.tsx`

Props:

```ts
type RoleSummary = { id: number; slug: string; name: string };

type AdminUserRow = {
    id: number;
    name: string;
    email: string;
    roles: RoleSummary[];
};

type AdminUserIndexProps = {
    users: {
        data: AdminUserRow[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    filters: { search: string };
};
```

Behavior:

- Search form submits with `router.get(admin.users.index, { search }, { preserveState: true, preserveScroll: true, replace: true })`.
- Display role badges and “Manage roles” links.
- Render a semantic table with a caption, column headers, and a horizontally scrollable mobile wrapper.
- Render a shared `Pagination` component using `prev_page_url`, `next_page_url`, `current_page`, `last_page`, `total`, and `per_page`.
- Empty state for no users and for no search matches.
- No client-side filtering, pagination, or authorization.

### New page: `resources/js/pages/admin/users/roles.tsx`

Props:

```ts
type RoleOption = RoleSummary & {
    description: string | null;
    is_system: boolean;
};

type AdminUserRolesProps = {
    user: AdminUserRow;
    roles: RoleOption[];
};
```

Behavior:

- A real `<form onSubmit>` using `useForm<{ roles: number[] }>`.
- Checkboxes are controlled initial values from the user's current role IDs.
- Submit with `form.put(update.url(user.id), { preserveScroll: true })`.
- Disable submit while `processing`; show the existing `Spinner`.
- Display `errors.roles` inline and an `account` error in the existing delete-account dialog.
- Show a help note that a user with no roles cannot access permission-protected areas and that at least one platform administrator must remain.
- System roles show a small “System” badge; the form edits assignments only, not role definitions.
- Back link to the user list; success redirect shows a toast.
- No `useEffect` data fetching.

### Shared UI changes

- Add `resources/js/components/pagination.tsx` using existing `Link`/`Button` primitives and semantic navigation.
- Add `resources/js/types/authorization.ts`; export it from `resources/js/types/index.ts`.
- Reuse existing shadcn/ui `Button`, `Badge`, `Checkbox`, `Input`, `Label`, `InputError`, `Spinner`, `Heading`, and `Dialog` primitives where appropriate.
- No new component library, font, or dependency.
- Do not add an admin link to the sidebar; Feature 4 owns navigation.

### Accessibility and states

- Search input has a visible label and submit button; clear-search link is keyboard accessible.
- Table headers use `scope="col"`; table has a caption. The scroll wrapper has an accessible label.
- Role checkboxes are grouped with a `<fieldset>` and `<legend>`; each checkbox has a unique id generated with `useId`.
- Errors use `aria-invalid` and `aria-describedby`; `InputError` messages are associated.
- Pagination uses a labelled `<nav>`, disabled-looking non-links when no previous/next page, and an `aria-current` page indicator.
- Processing disables submit controls; toasts use the existing flash-toast channel.
- 403 and 404 use Laravel's default error handling; no custom error page is added.

## 15. Test matrix

Use the existing PHPUnit-style feature test convention under `tests/Feature/Authorization/` and update existing auth/settings tests. Seed `AuthorizationSeeder` only in tests that need catalogue data.

### Seeder and model tests

- Seeds the three roles, three permissions, admin mappings, and no users.
- Re-running the seeder is idempotent and preserves custom roles, permissions, and custom role mappings.
- Seeded definitions are `is_system`.
- Role/permission slug and pivot uniqueness are enforced.
- System role/permission cannot be deleted, have its slug changed, or have `is_system` cleared.
- Non-system role/permission can be deleted.

### Registration

- Registration assigns exactly `job_seeker`.
- A forged `role`, `role_id`, `is_admin`, `permissions`, or `account_status` payload has no effect.
- Missing job-seeker catalogue data fails closed and creates no user.
- Existing registration validation, session regeneration, and redirect tests still pass.

### Permission resolution and middleware

- A user with no roles has no permissions.
- A role grants its attached permissions; multiple roles union permissions.
- Removed roles stop granting permissions after cache invalidation.
- Guests are redirected from `/admin/users`.
- Authenticated users without `admin.access` or `users.view` receive 403.
- A job seeker with the role but no admin permission receives 403.
- A platform admin receives 200.
- Direct PUT requests without permission are denied regardless of UI.

### Admin user list

- Admin can search by name prefix and email prefix.
- Search wildcards and overlong terms do not cause unbounded behavior.
- Pagination returns 20 users, preserves the query string, and reports correct totals.
- Users with no roles render safely.
- Role summaries are eager-loaded; query count does not grow with page size.
- No password/2FA fields appear in Inertia props.

### Role update

- Admin can assign `employer` to a job seeker and replace prior roles.
- Empty roles are allowed for non-administrators.
- Unknown, non-integer, and duplicate role IDs fail validation.
- Forged permission IDs/fields have no effect.
- Non-admin cannot view or update roles.
- Last platform administrator cannot be demoted, including self-demotion.
- A platform administrator can be demoted when another administrator exists.
- Failed last-admin update persists no changes.
- Non-existent target returns 404.
- Success redirects and exposes a success toast.

### Account deletion

- Existing non-admin account deletion still works.
- Last platform administrator cannot delete their account; no logout or deletion occurs.
- Administrator can delete their account when another administrator exists.
- `UserPolicy::delete` denies deleting another user.
- Wrong password still returns a password validation error.

### Trusted command

- Assigns `platform_admin` to an existing user.
- Is idempotent.
- Unknown user and unknown role fail with actionable messages.
- Does not create a user or accept a password.

### Regression and verification

- All existing Feature 2 authentication, password reset, profile, dashboard, and homepage tests pass.
- Frontend types and production build pass.
- Add a query-count/no-N+1 test for the admin list.
- Browser/manual QA covers keyboard navigation, role checkbox grouping, search, pagination, last-admin error, 403, and mobile table scrolling.

## 16. Security and performance risks

| Risk                                         | Mitigation                                                                                                                  |
| -------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------- |
| Public registration privilege escalation     | `User` fillable remains name/email/password; registration assigns only `job_seeker`; regression tests for forged fields.    |
| Mass assignment through admin role form      | Form Request accepts only role IDs; permission/system flags are never mass-assigned from request input.                     |
| Last-admin lockout via role update           | Transaction, lock `platform_admin` role row, count other admins, dedicated exception, field error.                          |
| Last-admin lockout via account deletion      | Policy + service invariant inside the existing deletion path; test self-deletion.                                           |
| System catalogue deletion/rename             | `is_system` model guards on delete, slug update, and system-flag clearing; seeder restores required mappings.               |
| Privilege escalation through direct requests | Route permission middleware plus Policy checks; no frontend-only authorization.                                             |
| Sensitive data exposure in admin pages       | Explicit selected columns and typed Inertia projections; no password, 2FA, remember token, or permission catalogue payload. |
| N+1 role lookups                             | Eager-load `roles` on the list; memoized permission resolution; query-count regression test.                                |
| Search abuse/slow query                      | 100-character cap, escaped wildcards, prefix search, `users.name` index, fixed page size.                                   |
| Concurrent role updates                      | Role-row transaction lock for admin invariant; last-write-wins for unrelated simultaneous updates is documented.            |
| SQLite does not enforce row locking          | Tests cover sequential invariants; production concurrency relies on MySQL. Database target must be confirmed.               |
| Missing seeder in deployment                 | Registration fails closed and no orphan user is committed; deployment docs require catalogue seeding before launch.         |
| No audit trail                               | Role changes are not audited in Feature 3; Feature 31 must add append-only audit coverage.                                  |
| Existing users have no role                  | Pre-production assumption; explicit command assignment; Feature 4 must handle roleless users safely.                        |
| Admin screen not linked in navigation        | Accepted until Feature 4; direct URL is documented and protected server-side.                                               |
| Migration adds `users.name` index            | Review on MySQL for lock/write impact; current dataset is small. Do not run destructive fresh migrations on shared data.    |

## 17. Rollout and compatibility

1. Confirm the database target and run the migration on a disposable/local database first.
2. Apply migrations, then run `AuthorizationSeeder` before accepting new registrations.
3. Create/register the first operator, then run `auth:assign-role` from the console.
4. Existing users remain roleless unless explicitly assigned; this is intentional and must be communicated before Feature 4.
5. Rolling back drops the role tables and assignments. Back up role assignments before rollback on any shared environment.
6. No application code is changed as part of this architecture step.

## 18. Ordered implementation plan

1. Add the migration for roles, permissions, pivots, and the `users.name` index.
2. Add `RoleSlug`/`PermissionSlug` enums, `Role`/`Permission` models, relations, casts, and the `HasRolesAndPermissions` concern.
3. Add the idempotent `AuthorizationSeeder`; wire `DatabaseSeeder`; add factories/states for users with roles.
4. Add `RoleAssignmentService`, `LastAdministratorException`, and model-level system-definition guards.
5. Register the `permission` middleware alias and add `EnsureUserHasPermission`; add `AuthorizesRequests` to the base controller.
6. Update `CreateNewUser` to assign `job_seeker` transactionally and add registration regression coverage.
7. Add `UserPolicy`, the admin Form Request, controller, and `routes/admin.php`; apply route middleware, Policy calls, and last-admin translation.
8. Add the trusted `auth:assign-role` command and its tests.
9. Update `ProfileController@destroy` with Policy authorization and the account-deletion invariant; update the delete dialog error display.
10. Add admin Inertia pages, shared Pagination, typed props, search, role form, empty/error/processing states.
11. Add `docs/authorization.md` with catalogue, provisioning, extension, and deployment instructions.
12. Run focused authorization tests, the full backend suite, lint/static analysis, frontend typecheck/build, route inspection, and manual browser QA.

## 19. Verification commands

Run from the repository root; do not run destructive commands against shared data.

```text
php artisan migrate --pretend
php artisan migrate
php artisan db:seed --class=AuthorizationSeeder
php artisan auth:assign-role <existing-user-email> platform_admin
php artisan route:list -v
php artisan test --filter=Authorization
php artisan test
composer lint:check
composer types:check
npm run types:check
npm run build
npm run check
git diff --check
```

- `php artisan migrate --pretend` is the SQL review step; apply only to a disposable/local database until the target database is confirmed.
- `npm run check` currently has known pre-existing documentation formatting failures; report new failures separately rather than silently claiming a clean run.
- Manual checks: admin search/pagination, role assignment, last-admin rejection, last-admin account deletion rejection, guest/403 responses, keyboard-only use, and mobile table scrolling.
- Report the exact commands run and their actual outcomes. Do not claim a check passed without inspecting its result.
