# Project Memory

> Maintained by `/remember save`. Concise project facts and latest work handoff; not a transcript or secret store.

## Project profile

- Stack: Laravel 13.38, React 19.3, TypeScript 5.9 strict, Inertia 3, Tailwind CSS 4, shadcn/ui vendored components.
- Product: job portal for candidates, employers, and platform administrators per `project-overview.md`.
- Database target: unresolved. `AGENTS.md` baseline is MySQL; local runtime and test suite use SQLite.
- Authentication: session-based Laravel Fortify. Registration, login/logout, password reset, 2FA, passkeys enabled; email verification disabled.
- Authorization: custom and dynamic database-backed role and permission system fully implemented (Feature 3, Feature 3.1, Feature 4). Roles: `platform_admin`, `employer`, `job_seeker`. Permissions: `admin.access`, `users.view`, `users.roles.manage`, `roles.view`, `roles.manage`, `permissions.view`, `permissions.manage`. Policies, middleware (`permission`, `role`), service layer (`RoleAssignmentService`), CLI assignment command (`auth:assign-role`), and admin user/role/permission management UI in place.
- Tests: Pest 5 runner with PHPUnit 13; `php artisan test`. Latest full suite: 152 passed, 944 assertions.
- Frontend conventions: Inertia page props, Wayfinder actions, named routes, vendored shadcn/ui, pagination component, split layout.

## Confirmed architecture decisions

- Follow `AGENTS.md`; keep Services/Repositories only when complexity, reuse, or query shape justifies them. Feature 3 added `RoleAssignmentService` for transactional role synchronization and last-admin invariant locking.
- `GET /` is named `home`, uses `web` only, and renders Inertia `home` through `PublicLayout`.
- Global palette is teal / deep emerald / soft fog (60-30-10).
- Public registration assigns `job_seeker` role transactionally and securely rejects forged role/admin payloads.
- Last-administrator protection is enforced on role updates (via `RoleAssignmentService`) and self-service account deletion (via `ProfileController` and `UserPolicy`).
- System roles and permissions (`is_system = true`) are protected against deletion, slug changes, and unsetting of the system flag via `ProtectsSystemDefinition` model concern.
- Feature 4: Implemented `EnsureUserHasRole` middleware (aliased as `role`), `DashboardController` for role-based dashboard routing (`/admin/dashboard`, `/employer/dashboard`, `/candidate/dashboard`), extended `HandleInertiaRequests` to share `auth.roles` and `auth.permissions`, and built role-aware sidebar/header navigation with Wayfinder actions.
- Feature 3.1: Implemented Admin Role & Permission Management CRUD (`RoleController`, `PermissionController`, Form Requests, Policies, split-layout UI, pagination, search, and preloaded `permission_ids`).

## Current task / latest handoff

- Status: Feature 3, Feature 3.1, and Feature 4 fully implemented, tested, built, and verified.
- Summary: Completed Feature 4 shared application shell & role-aware navigation, Feature 3.1 admin role & permission management CRUD, and resolved Wayfinder action / asset build updates (`npm run build`).
- Key Files:
    - Backend: `AdminDashboardController`, `RoleController`, `PermissionController`, `EnsureUserHasRole`, `RolePolicy`, `PermissionPolicy`, Form Requests, seeders, routes (`admin.php`, `web.php`).
    - Frontend: `resources/js/components/admin/split-layout.tsx`, `resources/js/pages/admin/roles/index.tsx`, `resources/js/pages/admin/permissions/index.tsx`, `resources/js/lib/navigation.ts`, role-specific dashboards.
    - Tests: `tests/Feature/Authorization/*`, `tests/Feature/DashboardRoutingTest.php`.
- Verification observed:
    - `php artisan test`: 152 passed, 944 assertions.
    - `composer test`: Passed (Pint, PHPStan 0 errors, Pest 152 passed).
    - `npm run build`: Passed (Vite + Wayfinder action bundle generation).
- Next recommended step: Proceed to the next roadmap feature in `project-overview.md` (e.g., Feature 5 or subsequent features) per user instructions.

## Durable notes

- Preserve custom authorization architecture and last-administrator safeguards.
- Keep route permission middleware and Policy checks authoritative on the server.
- Maintain transactional integrity when syncing roles or checking administrator counts.
- Rebuild frontend assets (`npm run build`) after modifying controller actions or route definitions used by Wayfinder.
- Never store `.env` values, credentials, tokens, private keys, or personal data here.
