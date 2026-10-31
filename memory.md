# Project Memory

> Maintained by `/remember save`. Concise project facts and latest work handoff; not a transcript or secret store.

## Project profile

- Stack: Laravel 13.38, React 19.3, TypeScript 5.9 strict, Inertia 3, Tailwind CSS 4, shadcn/ui vendored components.
- Product: job portal for candidates, employers, and platform administrators per `project-overview.md`.
- Database target: unresolved. `AGENTS.md` baseline is MySQL; local runtime and test suite use SQLite.
- Authentication: session-based Laravel Fortify. Registration, login/logout, password reset, 2FA, passkeys enabled; email verification disabled.
- Authorization: custom dynamic DB-backed role/permission system (Features 3, 3.1, 4, 5, 6, 7, 8). Roles: `platform_admin`, `employer`, `job_seeker`. Permissions (15): `admin.access`, `users.view`, `users.roles.manage`, `roles.view`, `roles.manage`, `permissions.view`, `permissions.manage`, `categories.view`, `categories.manage`, `employment_types.view`, `employment_types.manage`, `workplace_arrangements.view`, `workplace_arrangements.manage`, `experience_levels.view`, `experience_levels.manage`. Policies, middleware, services, and admin management UI in place.
- Tests: Pest 5 on PHPUnit 13; `php artisan test`. Latest full suite: 201 passed, 1288 assertions.
- Frontend conventions: Inertia page props, Wayfinder actions, named routes, vendored shadcn/ui, pagination component, split layout.

## Confirmed architecture decisions

- Follow `AGENTS.md`; keep Services/Repositories only when complexity, reuse, or query shape justifies them. Feature 3 added `RoleAssignmentService` for transactional role synchronization and last-admin invariant locking.
- `GET /` is named `home`, uses `web` only, and renders Inertia `home` through `PublicLayout`.
- Global palette is teal / deep emerald / soft fog (60-30-10).
- Public registration assigns `job_seeker` role transactionally and securely rejects forged role/admin payloads.
- Last-administrator protection is enforced on role updates and self-service account deletion.
- System roles and permissions (`is_system = true`) are protected against deletion, slug changes, and unsetting of the system flag via `ProtectsSystemDefinition` model concern.
- Feature 4: `EnsureUserHasRole` middleware, `DashboardController` role-based dashboard routing, shared auth roles/permissions via `HandleInertiaRequests`, role-aware navigation.
- Feature 3.1: Admin Role & Permission Management CRUD.
- Features 5–8 are admin configuration catalogues; all use thin controllers, dedicated Form Requests, permission-based Policies, 20-row paginated search, split-layout UI, and no Service/Repository.
- Feature 5: Job Categories (`JobCategory`) at `/admin/categories`; permissions `categories.view` / `categories.manage`.
- Feature 6: Employment Types (`EmploymentType`) at `/admin/employment-types`; permissions `employment_types.view` / `employment_types.manage`. Committed `a4f8a11` (+ `34b3313` formatting).
- Feature 7: Workplace Arrangements (`WorkplaceArrangement`) at `/admin/workplace-arrangements` with `sort_order` and ordering `sort_order → name → id`; permissions `workplace_arrangements.view` / `.manage`; no `display_label`/`is_remote` (name = label, slug = machine key). Committed `a158678` (+ `13aac0a` formatting).
- Feature 8: Experience Levels (`ExperienceLevel`) at `/admin/experience-levels`; mirrors Feature 7 including `sort_order`; permissions `experience_levels.view` / `.manage`; navigation icon `GraduationCap`. Spec `docs/specs/feature-08-experience-levels.md`.
- Wayfinder camel-cases hyphenated route-name segments: `admin.employment-types.*` → `admin.employmentTypes.*`, `admin.workplace-arrangements.*` → `admin.workplaceArrangements.*`, `admin.experience-levels.*` → `admin.experienceLevels.*`.
- Platform admin permissions are an explicit allowlist in `AuthorizationSeeder`; new permission enum cases must be added there deliberately. Deployed environments must rerun the seeder after permission changes or existing admins receive 403.

## Current task / latest handoff

- Status: Feature 8 fully implemented, tested, built, typechecked, linted, static-analysed, and reviewed — **NOT yet committed** on `main` (last commit `13aac0a`). Feature 7 is already committed.
- Summary: Added admin experience-level catalogue with migration/model/factory, permissions/enum/seeder, Policy, Store/Update Form Requests, thin controller, routes, typed Inertia page, navigation entry, and tests. View-only admins get a read-only panel; writes return 403.
- Key files:
    - Backend: `app/Models/ExperienceLevel.php`, `app/Http/Controllers/Admin/ExperienceLevelController.php`, `app/Http/Requests/Admin/Store/UpdateExperienceLevelRequest.php`, `app/Policies/ExperienceLevelPolicy.php`, migration `2026_11_01_000001_create_experience_levels_table.php`, factory, `AuthorizationSeeder`, `PermissionSlug`, `routes/admin.php`.
    - Frontend: `resources/js/pages/admin/experience-levels/index.tsx`, `resources/js/lib/navigation.ts`.
    - Tests: `tests/Feature/Admin/ExperienceLevelTest.php` (17 tests), `tests/Feature/Authorization/AuthorizationCatalogueTest.php` (counts 15/16).
    - Spec: `docs/specs/feature-08-experience-levels.md` (approved/implemented).
- Verification observed:
    - `php artisan test`: 201 passed, 1288 assertions.
    - `php artisan test tests/Feature/Admin/ExperienceLevelTest.php`: 17 passed, 119 assertions.
    - `npm run build`: passed.
    - `npm run types:check`: passed.
    - `npm run check`: passed (124 formatted, 83 linted).
    - `vendor/bin/pint --test`: passed.
    - `vendor/bin/phpstan analyse --no-progress`: 0 errors.
    - `php artisan route:list --path=admin/experience-levels -v`: 4 routes with expected middleware.
- Review (`code-reviewer`, independent): no Critical findings. Remaining warnings/suggestions: no isolated test for `admin.access` without `experience_levels.view`; no pagination/query-string test and `id` ordering tiebreaker untested; PUT omitting `is_active`/`sort_order` resets defaults (latent partial-update risk); `escapeLike()` lacks explicit `ESCAPE` (SQLite/MySQL portability); Policy uses string permission literals instead of enum (style). Browser/manual UI and MySQL-specific query behavior NOT verified.
- Next recommended step: commit Feature 8, then architect Feature 9 (`Admin manages salary filter bands and pay-period options`) — no spec exists yet.

## Durable notes

- Preserve custom authorization architecture and last-administrator safeguards.
- Keep route permission middleware and Policy checks authoritative on the server.
- Rebuild frontend assets (`npm run build`) after modifying controller actions or route definitions used by Wayfinder.
- Feature 6 deferred rule: "do not delete an employment type referenced by jobs" cannot be enforced until Feature 13 creates the `jobs` table; Feature 13's spec must add `employment_type_id` FK with restrict-on-delete plus admin-UI protection.
- Feature 7 deferred rule: same referenced-option delete protection for workplace arrangements, pending Feature 13.
- Feature 8 deferred rule: same referenced-option delete protection for experience levels, pending Feature 13; Feature 13 must add `experience_level_id` FK with restrict-on-delete.
- Never store `.env` values, credentials, tokens, private keys, or personal data here.
