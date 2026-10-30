# Project Memory

> Maintained by `/remember save`. Concise project facts and latest work handoff; not a transcript or secret store.

## Project profile

- Stack: Laravel 13.38, React 19.3, TypeScript 5.9 strict, Inertia 3, Tailwind CSS 4, shadcn/ui vendored components.
- Product: job portal for candidates, employers, and platform administrators per `project-overview.md`.
- Database target: unresolved. `AGENTS.md` baseline is MySQL; local runtime and test suite use SQLite.
- Authentication: session-based Laravel Fortify. Registration, login/logout, password reset, 2FA, passkeys enabled; email verification disabled.
- Authorization: custom and dynamic database-backed role and permission system fully implemented (Feature 3, Feature 3.1, Feature 4, Feature 5). Roles: `platform_admin`, `employer`, `job_seeker`. Permissions: `admin.access`, `users.view`, `users.roles.manage`, `roles.view`, `roles.manage`, `permissions.view`, `permissions.manage`, `categories.view`, `categories.manage`. Policies, middleware, services, and admin management UI in place.
- Tests: Pest 5 runner with PHPUnit 13; `php artisan test`. Latest full suite: 158 passed, 981 assertions.
- Frontend conventions: Inertia page props, Wayfinder actions, named routes, vendored shadcn/ui, pagination component, split layout.

## Confirmed architecture decisions

- Follow `AGENTS.md`; keep Services/Repositories only when complexity, reuse, or query shape justifies them. Feature 3 added `RoleAssignmentService` for transactional role synchronization and last-admin invariant locking.
- `GET /` is named `home`, uses `web` only, and renders Inertia `home` through `PublicLayout`.
- Global palette is teal / deep emerald / soft fog (60-30-10).
- Public registration assigns `job_seeker` role transactionally and securely rejects forged role/admin payloads.
- Last-administrator protection is enforced on role updates and self-service account deletion.
- System roles and permissions (`is_system = true`) are protected against deletion, slug changes, and unsetting of the system flag via `ProtectsSystemDefinition` model concern.
- Feature 4: Implemented `EnsureUserHasRole` middleware, `DashboardController` for role-based dashboard routing, extended `HandleInertiaRequests` to share auth roles/permissions, and built role-aware navigation.
- Feature 3.1: Admin Role & Permission Management CRUD.
- Feature 5: Admin Job Categories Management CRUD (`JobCategory` model, migration, factory, `JobCategoryController`, Form Requests, `JobCategoryPolicy`, `categories.view` / `categories.manage` permissions, split-layout UI at `/admin/categories`, navigation, and Pest tests).

## Current task / latest handoff

- Status: Feature 5 fully implemented, tested (`php artisan test` 158 passed, 981 assertions), built (`npm run build`), and verified.
- Summary: Completed Feature 5 (Admin manages job categories) spec (`docs/specs/feature-05-job-categories.md`), backend persistence/routing/policy/controller/requests/seeders, frontend Inertia React split-layout UI, and comprehensive feature tests.
- Key Files:
    - Backend: `JobCategory`, `JobCategoryController`, `StoreJobCategoryRequest`, `UpdateJobCategoryRequest`, `JobCategoryPolicy`, migration `2026_10_30_000000_create_job_categories_table.php`, factory, `AuthorizationSeeder`, routes in `routes/admin.php`.
    - Frontend: `resources/js/pages/admin/categories/index.tsx`, `resources/js/lib/navigation.ts`.
    - Tests: `tests/Feature/Admin/JobCategoryTest.php`, `tests/Feature/Authorization/AuthorizationCatalogueTest.php`.
- Verification observed:
    - `php artisan test`: 158 passed, 981 assertions.
    - `npm run build`: Passed successfully.
- Next recommended step: Proceed to Feature 6 (`Admin manages employment types`) per `project-overview.md`.

## Durable notes

- Preserve custom authorization architecture and last-administrator safeguards.
- Keep route permission middleware and Policy checks authoritative on the server.
- Rebuild frontend assets (`npm run build`) after modifying controller actions or route definitions used by Wayfinder.
- Never store `.env` values, credentials, tokens, private keys, or personal data here.
