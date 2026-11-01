# Project Memory

> Maintained by `/remember save`. Concise project facts and latest work handoff; not a transcript or secret store.

## Project profile

- Stack: Laravel 13.38, React 19.3, TypeScript 5.9 strict, Inertia 3, Tailwind CSS 4, shadcn/ui vendored components.
- Product: job portal for candidates, employers, and platform administrators per `project-overview.md`.
- Database target: unresolved. `AGENTS.md` baseline is MySQL; local runtime and test suite use SQLite.
- Authentication: session-based Laravel Fortify. Registration, login/logout, password reset, 2FA, passkeys enabled; email verification disabled.
- Authorization: custom dynamic DB-backed role/permission system (Features 3, 3.1, 4, 5, 6, 7, 8, 9, 10). Roles: `platform_admin`, `employer`, `job_seeker`. Permissions include `admin.access`, `users.view`, `users.roles.manage`, `roles.view`, `roles.manage`, `permissions.view`, `permissions.manage`, `categories.view`, `categories.manage`, `employment_types.view`, `employment_types.manage`, `workplace_arrangements.view`, `workplace_arrangements.manage`, `experience_levels.view`, `experience_levels.manage`, `pay_periods.view`, `pay_periods.manage`, `salary_bands.view`, `salary_bands.manage`, `employers.view`, `employers.manage`. Policies, middleware, services, and admin management UI in place.
- Tests: Pest 5 on PHPUnit 13; `php artisan test`. Latest full suite: **212 passed, 1437 assertions** (includes `tests/Feature/Admin/EmployerTest.php`).
- Frontend conventions: Inertia page props, Wayfinder actions/routes, named routes, vendored shadcn/ui, pagination component, split layout.
- Feature 11 (employer onboarding + eligibility) not yet specified/implemented; status lives on `users` per Feature 10 design.

## Confirmed architecture decisions

- Follow `AGENTS.md`; keep Services/Repositories only when complexity, reuse, or query shape justifies them. Feature 3 added `RoleAssignmentService` for transactional role synchronization and last-admin invariant locking.
- `GET /` is named `home`, uses `web` only, and renders Inertia `home` through `PublicLayout`.
- Global palette is teal / deep emerald / soft fog (60-30-10).
- Public registration assigns `job_seeker` role transactionally and securely rejects forged role/admin payloads.
- Last-administrator protection is enforced on role updates and self-service account deletion.
- System roles and permissions (`is_system = true`) are protected against deletion, slug changes, and unsetting of the system flag via `ProtectsSystemDefinition` model concern.
- Feature 4: `EnsureUserHasRole` middleware, `DashboardController` role-based dashboard routing, shared auth roles/permissions via `HandleInertiaRequests`, role-aware navigation.
- Feature 3.1: Admin Role & Permission Management CRUD.
- Features 5–9 are admin configuration/catalogues; thin controllers, dedicated Form Requests, permission-based Policies, paginated search, split-layout UI, no Service/Repository.
- Wayfinder camel-cases hyphenated route-name segments (e.g. `admin.employment-types.*` → `admin.employmentTypes.*`).
- Platform admin permissions are an explicit allowlist in `AuthorizationSeeder`; new permission enum cases must be added there deliberately. Deployed environments must rerun the seeder after permission changes or existing admins receive 403.
- Employer capability requires **role `employer` + status `approved` + ownership**; status is separate from role/permission. Suspension preserves data and blocks employer actions but does not imply login ban by default (enforcement belongs to employer/company/job features).
- Feature 11 approval should add `employer` role (and can keep `job_seeker`) while setting `users.status = approved`; `/admin/employers` should scope to employer-eligible/pending accounts once onboarding exists.

## Current task / latest handoff

- Status: **Feature 10 implemented, tested, built, typechecked, linted, formatted, and verified — NOT yet committed** (HEAD `60ac586`). All Feature 10 changes are uncommitted/untracked.
- Summary: Implemented `/admin/employers` (review/suspend/reactivate). Fixed multiple issues discovered during recovery: removed dependency on non-existent `companies` table (set `company_count` null, dropped `User::companies()`/`Company` model), aligned Inertia props (`employers` not `users`), wired `EmployerPolicy` via explicit gates in `AppServiceProvider`, fixed route-model binding in `store()` (implicit `User $user`), switched frontend to generated route helpers and added nullable narrowing, fixed badge colors in both light/dark. Added regression tests.
- Key files (uncommitted/untracked):
  - Backend: `app/Http/Controllers/Admin/EmployerController.php`, `app/Http/Requests/Admin/StoreEmployerRequest.php`, `app/Http/Requests/Admin/UpdateEmployerRequest.php`, `app/Policies/EmployerPolicy.php`, `app/Providers/AppServiceProvider.php`, `app/Enums/PermissionSlug.php`, `database/seeders/AuthorizationSeeder.php`, `routes/admin.php`, `database/migrations/2026_10_31_062635_add_status_nullable_to_users_table.php`, `database/migrations/2026_11_01_000004_add_status_and_suspension_reason_to_users_table.php`
  - Frontend: `resources/js/pages/admin/employers/index.tsx`, `resources/js/lib/navigation.ts`, `resources/js/types/authorization.ts`
  - Tests/spec: `tests/Feature/Admin/EmployerTest.php`, `docs/specs/feature-10-employer-account-review-and-suspension.md`, `tests/Feature/Authorization/AuthorizationCatalogueTest.php` (updated)
- Verification observed:
  - `php artisan test`: **212 passed, 1437 assertions**
  - `npm run types:check`: no errors
  - `npm run check`: 129 files formatted, 86 files no warnings/lint errors
  - `vendor/bin/pint --test`: passed
  - `npm run build`: built successfully (Wayfinder regenerated actions/routes)
  - `php artisan route:list --path=admin/employers -v`: 4 routes with `auth`, `permission:admin.access`, `employers.view`/`employers.manage`
- Behavior notes: `company_count` is `null` (no `companies` table; Feature 11/12 will introduce). `rejected` not accepted by `StoreEmployerRequest` (spec inconsistency). Suspension today is a flag+reason only; enforcement in employer routes/policies not yet implemented.
- Next recommended step: commit Feature 10 changes; decide whether to support `rejected` as a transition; then begin Feature 11 (employer onboarding + eligibility) — `/architect feature 11` recommended before implementation.

## Durable notes

- Preserve custom authorization architecture and last-administrator safeguards.
- Keep route permission middleware and Policy checks authoritative on the server.
- Rebuild frontend assets (`npm run build`) after modifying controller actions or route definitions used by Wayfinder.
- Feature 6 deferred rule: "do not delete an employment type referenced by jobs" cannot be enforced until Feature 13 creates the `jobs` table; Feature 13's spec must add `employment_type_id` FK with restrict-on-delete plus admin-UI protection.
- Feature 7 deferred rule: same referenced-option delete protection for workplace arrangements, pending Feature 13.
- Feature 8 deferred rule: same referenced-option delete protection for experience levels, pending Feature 13; Feature 13 must add `experience_level_id` FK with restrict-on-delete.
- Feature 9 deferred rules: job `pay_period_id` FK (restrict-on-delete) + active-period enforcement in Feature 13; band filtering by currency+period/half-open semantics in Feature 16/17; saved-search band references in Feature 29.
- Never store `.env` values, credentials, tokens, private keys, or personal data here.
