# Project Memory

> Maintained by `/remember save`. Concise project facts and latest work handoff; not a transcript or secret store.

## Project profile

- Stack: Laravel 13.38, React 19.3, TypeScript 5.9 strict, Inertia 3, Tailwind CSS 4, shadcn/ui vendored components.
- Product: job portal for candidates, employers, and platform administrators per `project-overview.md`.
- Database target: unresolved. `AGENTS.md` baseline is MySQL; local runtime and test suite use SQLite.
- Authentication: session-based Laravel Fortify. Registration, login/logout, password reset, 2FA, passkeys enabled; email verification disabled.
- Authorization: custom dynamic DB-backed role/permission system (Features 3, 3.1, 4, 5, 6, 7, 8, 9). Roles: `platform_admin`, `employer`, `job_seeker`. Permissions (19 system; 20 in reseed-with-custom test): `admin.access`, `users.view`, `users.roles.manage`, `roles.view`, `roles.manage`, `permissions.view`, `permissions.manage`, `categories.view`, `categories.manage`, `employment_types.view`, `employment_types.manage`, `workplace_arrangements.view`, `workplace_arrangements.manage`, `experience_levels.view`, `experience_levels.manage`, `pay_periods.view`, `pay_periods.manage`, `salary_bands.view`, `salary_bands.manage`. Policies, middleware, services, and admin management UI in place.
- Tests: Pest 5 on PHPUnit 13; `php artisan test`. Latest full suite: **202 passed, 1304 assertions** (includes new `SalaryBandTest` create regression; prior baseline 201/1288).
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
- Feature 6: Employment Types (`EmploymentType`) at `/admin/employment-types`; permissions `employment_types.view` / `.manage`.
- Feature 7: Workplace Arrangements (`WorkplaceArrangement`) at `/admin/workplace-arrangements` with `sort_order`; ordering `sort_order → name → id`; permissions `workplace_arrangements.view` / `.manage`.
- Feature 8: Experience Levels (`ExperienceLevel`) at `/admin/experience-levels`; mirrors Feature 7 including `sort_order`; permissions `experience_levels.view` / `.manage`; navigation icon `GraduationCap`.
- Feature 9: Pay Periods (`PayPeriod`) at `/admin/pay-periods` and Salary Bands (`SalaryBand`) at `/admin/salary-bands`; bands are currency + pay-period scoped, half-open `[lower, upper)`, overlaps rejected, both bounds required, no seed data; two admin pages with separate permission pairs.
- Wayfinder camel-cases hyphenated route-name segments: `admin.employment-types.*` → `admin.employmentTypes.*`, `admin.workplace-arrangements.*` → `admin.workplaceArrangements.*`, `admin.experience-levels.*` → `admin.experienceLevels.*`, `admin.pay-periods.*` → `admin.payPeriods.*`, `admin.salary-bands.*` → `admin.salaryBands.*`.
- Platform admin permissions are an explicit allowlist in `AuthorizationSeeder`; new permission enum cases must be added there deliberately. Deployed environments must rerun the seeder after permission changes or existing admins receive 403.

## Current task / latest handoff

- Status: **Feature 9 implemented, tested, built, typechecked, linted, static-analysed, and reviewed — NOT yet committed** (uncommitted working-tree changes from this session; HEAD still `bc0a37e`).
- Summary: Added admin pay-period + salary-band catalogues (migrations/models/factories/policies/FormRequests/controllers/routes/permissions/frontend pages/nav), plus regression fix: `StoreSalaryBandRequest`/`UpdateSalaryBandRequest` now place overlap rule in `rules()` and read data via `$this->all()` (not `$this->safe()` in `after`); added `tests/Feature/Admin/SalaryBandTest.php::test_admin_can_create_a_salary_band`.
- Key files:
    - Backend: `app/Models/PayPeriod.php`, `app/Models/SalaryBand.php`, `app/Http/Controllers/Admin/PayPeriodController.php`, `app/Http/Controllers/Admin/SalaryBandController.php`, `app/Http/Requests/Admin/Store/UpdatePayPeriodRequest.php`, `app/Http/Requests/Admin/Store/UpdateSalaryBandRequest.php`, `app/Policies/PayPeriodPolicy.php`, `app/Policies/SalaryBandPolicy.php`, `app/Rules/SalaryBandRangeDoesNotOverlap.php`, migrations `2026_11_01_000002_create_pay_periods_table.php`, `2026_11_01_000003_create_salary_bands_table.php`, factories, `app/Enums/PermissionSlug.php`, `database/seeders/AuthorizationSeeder.php`, `routes/admin.php`.
    - Frontend: `resources/js/pages/admin/pay-periods/index.tsx`, `resources/js/pages/admin/salary-bands/index.tsx`, `resources/js/lib/navigation.ts`.
    - Tests: `tests/Feature/Admin/SalaryBandTest.php` (create regression), `tests/Feature/Authorization/AuthorizationCatalogueTest.php` (permission counts 19/20).
    - Spec: `docs/specs/feature-09-salary-filter-bands-and-pay-periods.md` (status APPROVED — implemented and verified (2026-10-31); approval gate updated).
- Verification observed:
    - `php artisan migrate --force`: ✅ created `pay_periods` + `salary_bands`.
    - `php artisan db:seed --class=AuthorizationSeeder`: ✅ succeeded.
    - `php artisan route:list --path=admin/pay-periods -v` and `--path=admin/salary-bands -v`: ✅ 4 routes each with expected middleware.
    - `php artisan test tests/Feature/Admin/SalaryBandTest.php`: ✅ passed (1 test, 4 assertions).
    - `php artisan test tests/Feature/Authorization/AuthorizationCatalogueTest.php tests/Feature/Admin/ExperienceLevelTest.php`: ✅ passed (20 tests, 138 assertions).
    - `php artisan test`: ✅ full suite passed (202 tests, 1304 assertions).
    - `vendor/bin/pint --test`: ✅ passed. `vendor/bin/phpstan analyse --no-progress`: ✅ 0 errors. `npm run types:check`: ✅. `npm run check`: ✅. `npm run build`: ✅.
- Review (`code-reviewer`, independent): no 🔴 Critical. 🟡: missing new-endpoint negative/behavioral coverage; duplicate `upper_bound` InputError in salary-bands form (lines 259/266). 🔵: partial-update defaults reset optional fields; delete FK-catch not present; no-pay-periods create state missing.
- Recovery (this session): fixed FormRequest `after` → `$this->all()` + moved overlap rule into `rules()`; regression test added and passing.
- Next recommended step: commit the uncommitted Feature 9 implementation changes; then add the full Feature 9 test matrix (`PayPeriodTest`, extended `SalaryBandTest`) via test-engineer; then commit.

## Durable notes

- Preserve custom authorization architecture and last-administrator safeguards.
- Keep route permission middleware and Policy checks authoritative on the server.
- Rebuild frontend assets (`npm run build`) after modifying controller actions or route definitions used by Wayfinder.
- Feature 6 deferred rule: "do not delete an employment type referenced by jobs" cannot be enforced until Feature 13 creates the `jobs` table; Feature 13's spec must add `employment_type_id` FK with restrict-on-delete plus admin-UI protection.
- Feature 7 deferred rule: same referenced-option delete protection for workplace arrangements, pending Feature 13.
- Feature 8 deferred rule: same referenced-option delete protection for experience levels, pending Feature 13; Feature 13 must add `experience_level_id` FK with restrict-on-delete.
- Feature 9 deferred rules: job `pay_period_id` FK (restrict-on-delete) + active-period enforcement in Feature 13; band filtering by currency+period/half-open semantics in Feature 16/17; saved-search band references in Feature 29.
- Never store `.env` values, credentials, tokens, private keys, or personal data here.
