# Project Memory

> Maintained by `/remember save`. Concise project facts and latest work handoff; not a transcript or secret store.

## Project profile

- Stack: Laravel 13.38, React 19.3, TypeScript 5.9 strict, Inertia 3, Tailwind CSS 4, shadcn/ui vendored components.
- Product: job portal for candidates, employers, and platform administrators per `project-overview.md`.
- Database target: unresolved. `AGENTS.md` baseline is MySQL; local runtime and test suite use SQLite.
- Authentication: session-based Laravel Fortify. Registration, login/logout, password reset, 2FA, passkeys enabled; email verification disabled.
- Authorization: custom and dynamic database-backed role and permission system fully implemented (Feature 3, Feature 3.1, Feature 4, Feature 5, Feature 6). Roles: `platform_admin`, `employer`, `job_seeker`. Permissions: `admin.access`, `users.view`, `users.roles.manage`, `roles.view`, `roles.manage`, `permissions.view`, `permissions.manage`, `categories.view`, `categories.manage`, `employment_types.view`, `employment_types.manage`. Policies, middleware, services, and admin management UI in place.
- Tests: Pest 5 runner with PHPUnit 13; `php artisan test`. Latest full suite: 170 passed, 1061 assertions.
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
- Feature 6: Admin Employment Types Management CRUD — exact structural mirror of Feature 5 (`EmploymentType` model, migration `2026_10_30_000001_create_employment_types_table.php`, factory, `EmploymentTypeController`, Form Requests, `EmploymentTypePolicy`, `employment_types.view` / `employment_types.manage` permissions, split-layout UI at `/admin/employment-types`, navigation entry with Briefcase icon, and Pest tests).
- Wayfinder camel-cases hyphenated route-name segments: route name `admin.employment-types.*` is exposed to JS as `admin.employmentTypes.*`. The same applies to any future hyphenated route name.

## Current task / latest handoff

- Status: Feature 6 fully implemented, tested (`php artisan test` 170 passed, 1061 assertions), built (`npm run build`), typechecked (`npm run types:check`), reviewed — **NOT yet committed** (all files still untracked/modified on `main`, last commit `e3b09f7`).
- Summary: Completed Feature 6 (Admin manages employment types) per approved spec (`docs/specs/feature-06-employment-types.md`), backend persistence/routing/policy/controller/requests/seeders, frontend Inertia React split-layout UI, and comprehensive feature tests.
- Review (`/review Feature 6`): no critical or warning findings; 4 blue suggestions (redundant `->index()` after `->unique()` on slug mirroring Feature 5 convention; concurrent duplicate submit hits DB constraint → 500 instead of validation error — pre-existing convention from Feature 5; missing 403 tests for update/destroy verbs, optional hardening; SQLite LIKE backslash escape nuance). Security checklist all pass/N-A with evidence. Independent `code-reviewer` subagent failed with a gateway 503, so review was performed in-session read-only. Browser behavior NOT verified — manual checkpoint for user.
- Key Files:
    - Backend: `EmploymentType`, `EmploymentTypeController`, `StoreEmploymentTypeRequest`, `UpdateEmploymentTypeRequest`, `EmploymentTypePolicy`, migration `2026_10_30_000001_create_employment_types_table.php`, factory, `AuthorizationSeeder`, routes in `routes/admin.php`.
    - Frontend: `resources/js/pages/admin/employment-types/index.tsx`, `resources/js/lib/navigation.ts`.
    - Tests: `tests/Feature/Admin/EmploymentTypeTest.php` (12 tests), `tests/Feature/Authorization/AuthorizationCatalogueTest.php` (permission counts updated to 11).
- Verification observed:
    - `php artisan test`: 170 passed, 1061 assertions.
    - `npm run build`: Passed successfully.
    - `npm run types:check`: Passed with no errors.
    - `vendor/bin/pint --test` on touched PHP files: passed (backend agent).
- Next recommended step: Proceed to Feature 7 (`Admin manages workplace arrangements`) per `project-overview.md`; note Feature 7's spec may add ordering/display-sort which Feature 6 deliberately omitted.

## Durable notes

- Preserve custom authorization architecture and last-administrator safeguards.
- Keep route permission middleware and Policy checks authoritative on the server.
- Rebuild frontend assets (`npm run build`) after modifying controller actions or route definitions used by Wayfinder.
- Feature 6 deferred rule: "do not delete an employment type referenced by jobs" cannot be enforced until Feature 13 creates the `jobs` table; Feature 13's spec must add the `employment_type_id` FK with restrict-on-delete plus admin-UI protection.
- Never store `.env` values, credentials, tokens, private keys, or personal data here.
