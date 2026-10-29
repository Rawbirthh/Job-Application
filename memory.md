# Project Memory

> Maintained by `/remember save`. Concise project facts and latest work handoff; not a transcript or secret store.

## Project profile

- Stack: Laravel 13.38, React 19.3, TypeScript 5.9 strict, Inertia 3, Tailwind CSS 4, shadcn/ui vendored components.
- Product: job portal for candidates, employers, and platform administrators per `project-overview.md`.
- Database target: unresolved. `AGENTS.md` baseline is MySQL; local runtime and test suite use SQLite.
- Authentication: session-based Laravel Fortify. Registration, login/logout, password reset, 2FA, passkeys enabled; email verification disabled.
- Authorization: custom and dynamic database-backed role and permission system fully implemented (Feature 3). Roles: `platform_admin`, `employer`, `job_seeker`. Permissions: `admin.access`, `users.view`, `users.roles.manage`. Policies, middleware (`permission`), service layer (`RoleAssignmentService`), CLI assignment command (`auth:assign-role`), and admin user/role management UI in place.
- Tests: Pest 5 runner with PHPUnit 13; `php artisan test`. Latest full suite: 127 passed, 778 assertions (66 authorization tests).
- Frontend conventions: Inertia page props, Wayfinder actions, named routes, vendored shadcn/ui, pagination component.

## Confirmed architecture decisions

- Follow `AGENTS.md`; keep Services/Repositories only when complexity, reuse, or query shape justifies them. Feature 3 added `RoleAssignmentService` for transactional role synchronization and last-admin invariant locking.
- `GET /` is named `home`, uses `web` only, and renders Inertia `home` through `PublicLayout`.
- Global palette is teal / deep emerald / soft fog (60-30-10).
- Public registration assigns `job_seeker` role transactionally and securely rejects forged role/admin payloads.
- Last-administrator protection is enforced on role updates (via `RoleAssignmentService`) and self-service account deletion (via `ProfileController` and `UserPolicy`).
- System roles and permissions (`is_system = true`) are protected against deletion, slug changes, and unsetting of the system flag via `ProtectsSystemDefinition` model concern.
- Admin user listing (`/admin/users`) and role management (`/admin/users/{user}/roles`) are fully implemented, paginated (20 per page), searchable (prefix search on name/email), and secured by `admin.access` and specific permission middleware.

## Current task / latest handoff

- Status: Feature 3 (Custom roles, permissions, middleware, and Policies) fully implemented, tested, and verified. All changes uncommitted.
- Summary: Implemented authorization migration, enums, models, system definition protection, `RoleAssignmentService`, `EnsureUserHasPermission` middleware, `UserPolicy`, `UpdateUserRolesRequest`, admin controller & routes (`routes/admin.php`), `auth:assign-role` artisan command, `AuthorizationSeeder`, admin Inertia pages (`admin/users/index.tsx`, `admin/users/roles.tsx`), shared `Pagination` component, types, and comprehensive feature test suite (10 test files/traits, 66 tests). Updated Fortify `CreateNewUser` and `ProfileController` with last-admin safeguards.
- Key Feature 3 files:
    - Backend: `database/migrations/2026_10_28_000000_create_authorization_tables.php`, `app/Enums/RoleSlug.php`, `app/Enums/PermissionSlug.php`, `app/Models/Role.php`, `app/Models/Permission.php`, `app/Models/Concerns/HasRolesAndPermissions.php`, `app/Models/Concerns/ProtectsSystemDefinition.php`, `app/Services/Authorization/RoleAssignmentService.php`, `app/Exceptions/LastAdministratorException.php`, `app/Exceptions/ProtectedSystemDefinitionException.php`, `app/Http/Middleware/EnsureUserHasPermission.php`, `app/Policies/UserPolicy.php`, `app/Http/Requests/Admin/UpdateUserRolesRequest.php`, `app/Http/Controllers/Admin/UserRoleController.php`, `app/Console/Commands/AssignRoleCommand.php`, `database/seeders/AuthorizationSeeder.php`, `routes/admin.php`, `app/Actions/Fortify/CreateNewUser.php`, `app/Http/Controllers/Settings/ProfileController.php`.
    - Frontend: `resources/js/types/authorization.ts`, `resources/js/components/pagination.tsx`, `resources/js/pages/admin/users/index.tsx`, `resources/js/pages/admin/users/roles.tsx`, `resources/js/components/delete-user.tsx`.
    - Tests: `tests/Feature/Authorization/*` (10 files).
    - Docs: `docs/authorization.md`, `docs/specs/custom-authorization-foundation.md`.
- Verification observed:
    - `php artisan test`: 127 passed, 778 assertions (66 authorization tests).
    - `composer lint:check`: passed (Pint).
    - `composer types:check`: passed (PHPStan).
    - `npm run types:check`: passed (`tsc --noEmit`).
    - `npm run build`: passed (Vite).
    - `git diff --check`: clean.
- Next recommended step: Review Feature 3 status, then proceed to Feature 4 (Shared application shell and role-aware dashboard routing) or user's next requested task.

## Durable notes

- Preserve custom authorization architecture and last-administrator safeguards.
- Keep route permission middleware and Policy checks authoritative on the server.
- Maintain transactional integrity when syncing roles or checking administrator counts.
- Never store `.env` values, credentials, tokens, private keys, or personal data here.
