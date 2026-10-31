# Project Memory

> Maintained by `/remember save`. Concise project facts and latest work handoff; not a transcript or secret store.

## Project profile

- Stack: Laravel 13.38, React 19.3, TypeScript 5.9 strict, Inertia 3, Tailwind CSS 4, shadcn/ui vendored components.
- Product: job portal for candidates, employers, and platform administrators per `project-overview.md`.
- Database target: unresolved. `AGENTS.md` baseline is MySQL; local runtime and test suite use SQLite.
- Authentication: session-based Laravel Fortify. Registration, login/logout, password reset, 2FA, passkeys enabled; email verification disabled.
- Authorization: custom dynamic DB-backed role/permission system (Features 3, 3.1, 4, 5, 6, 7). Roles: `platform_admin`, `employer`, `job_seeker`. Permissions (13): `admin.access`, `users.view`, `users.roles.manage`, `roles.view`, `roles.manage`, `permissions.view`, `permissions.manage`, `categories.view`, `categories.manage`, `employment_types.view`, `employment_types.manage`, `workplace_arrangements.view`, `workplace_arrangements.manage`. Policies, middleware, services, and admin management UI in place.
- Tests: Pest 5 on PHPUnit 13; `php artisan test`. Latest full suite: 184 passed, 1163 assertions.
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
- Feature 5: Admin Job Categories Management CRUD (`JobCategory` model, migration, factory, `JobCategoryController`, Form Requests, `JobCategoryPolicy`, `categories.view` / `categories.manage` permissions, split-layout UI at `/admin/categories`, navigation, Pest tests).
- Feature 6: Admin Employment Types Management CRUD — exact structural mirror of Feature 5 (`EmploymentType` model, migration `2026_10_30_000001_create_employment_types_table.php`, factory, `EmploymentTypeController`, Form Requests, `EmploymentTypePolicy`, `employment_types.view` / `employment_types.manage` permissions, split-layout UI at `/admin/employment-types`, navigation entry with Briefcase icon, Pest tests). Committed as `a4f8a11` (plus `34b3313` spec formatting fix).
- Feature 7: Admin Workplace Arrangements Management CRUD — mirror of Feature 6 with added `sort_order` (integer, default 0, validated 0–9999) and ordering `sort_order → name → id`. `WorkplaceArrangement` model, migration `2026_10_31_000001_create_workplace_arrangements_table.php`, factory, `WorkplaceArrangementController`, Form Requests, `WorkplaceArrangementPolicy`, `workplace_arrangements.view` / `workplace_arrangements.manage` permissions, split-layout UI at `/admin/workplace-arrangements`, navigation entry with MapPin icon, Pest tests. No `display_label` or `is_remote` column — `name` is display label, `slug` is machine key, remote-ness encoded in slug. Spec: `docs/specs/feature-07-workplace-arrangements.md`.
- Wayfinder camel-cases hyphenated route-name segments: `admin.employment-types.*` → `admin.employmentTypes.*`, `admin.workplace-arrangements.*` → `admin.workplaceArrangements.*`.

## Current task / latest handoff

- Status: Feature 7 fully implemented, tested (`php artisan test` 184 passed, 1163 assertions), built (`npm run build`), typechecked (`npm run types:check`), reviewed — **NOT yet committed** (all files untracked/modified on `main`; last commit `34b3313`).
- Summary: Completed Feature 7 (Admin manages workplace arrangements) per approved spec, backend persistence/routing/policy/controller/requests/seeders, frontend Inertia React split-layout UI with display-order input, and comprehensive feature tests.
- Review (`/review Feature 7`): no critical findings; 4 warnings (redundant `->index()` after `->unique()` on slug; missing 403 tests for update/destroy + view-only/guest paths; implicit `SELECT *` in list query justified by spec; signed `integer` for `sort_order`), 2 suggestions (icon-only button `aria-label`; `escapeLike` portability). Independent `code-reviewer` subagent succeeded. Security checklist pass/N-A with evidence. Browser behavior NOT verified — manual checkpoint for user.
- Key files:
    - Backend: `WorkplaceArrangement`, `WorkplaceArrangementController`, `Store/UpdateWorkplaceArrangementRequest`, `WorkplaceArrangementPolicy`, migration `2026_10_31_000001_create_workplace_arrangements_table.php`, factory, `AuthorizationSeeder`, routes in `routes/admin.php`, `PermissionSlug`.
    - Frontend: `resources/js/pages/admin/workplace-arrangements/index.tsx`, `resources/js/lib/navigation.ts`.
    - Tests: `tests/Feature/Admin/WorkplaceArrangementTest.php` (14 tests), `tests/Feature/Authorization/AuthorizationCatalogueTest.php` (counts updated to 13/14).
- Verification observed:
    - `php artisan test`: 184 passed, 1163 assertions.
    - `php artisan test tests/Feature/Admin/WorkplaceArrangementTest.php`: 14 passed, 96 assertions.
    - `npm run build`: passed.
    - `npm run types:check`: passed with no errors.
    - `vendor/bin/pint --test`: passed.
- Next recommended step: commit Feature 7, then proceed to Feature 8 (`Admin manages experience levels`) per `project-overview.md`; Feature 8 spec does not yet exist (likely needs `/architect` first). Deferred referenced-option delete protection for workplace arrangements still pending until Feature 13.

## Durable notes

- Preserve custom authorization architecture and last-administrator safeguards.
- Keep route permission middleware and Policy checks authoritative on the server.
- Rebuild frontend assets (`npm run build`) after modifying controller actions or route definitions used by Wayfinder.
- Feature 6 deferred rule: "do not delete an employment type referenced by jobs" cannot be enforced until Feature 13 creates the `jobs` table; Feature 13's spec must add the `employment_type_id` FK with restrict-on-delete plus admin-UI protection.
- Feature 7 deferred rule: same referenced-option delete protection for workplace arrangements, pending Feature 13; plus `sort_order` optional/redundant-index/index-type refinements are low priority.
- Never store `.env` values, credentials, tokens, private keys, or personal data here.
