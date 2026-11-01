# Project Memory

> Maintained by `/remember save`. Concise project facts and latest work handoff; not a transcript or secret store.

## Project profile

- Stack: Laravel 13.38, React 19.3, TypeScript 5.9 strict, Inertia 3, Tailwind CSS 4, shadcn/ui vendored components.
- Product: job portal for candidates, employers, and platform administrators per `project-overview.md`.
- Database target: unresolved. `AGENTS.md` baseline is MySQL; local runtime and test suite use SQLite.
- Authentication: session-based Laravel Fortify. Registration, login/logout, password reset, 2FA, passkeys enabled; email verification disabled.
- Authorization: custom dynamic DB-backed role/permission system (Features 3–10). Roles: `platform_admin`, `employer`, `job_seeker`. Permissions include `admin.access`, `users.*`, `roles.*`, `permissions.*`, catalogue permissions, and `employers.view`/`employers.manage`. Policies, middleware, services, and admin management UI in place.
- Tests: Pest 5 on PHPUnit 13; `php artisan test`. Latest full suite: **266 passed, 1911 assertions** (Feature 12 added `tests/Feature/Employer/CompanyTest.php`; admin company payload changes included).
- Frontend conventions: Inertia page props, Wayfinder actions/routes, named routes, vendored shadcn/ui, pagination component, split layout.

## Confirmed architecture decisions

- Follow `AGENTS.md`; keep Services/Repositories only when complexity, reuse, or query shape justifies them. `RoleAssignmentService` handles transactional role sync and last-admin invariant locking.
- `GET /` is named `home`, uses `web` only, and renders Inertia `home` through `PublicLayout`.
- Global palette is teal / deep emerald / soft fog (60-30-10).
- Public registration assigns `job_seeker` role transactionally and securely rejects forged role/admin payloads; employer status remains `null`.
- Last-administrator protection is enforced on role updates and self-service account deletion.
- System roles and permissions (`is_system = true`) are protected against deletion, slug changes, and unsetting of the system flag via `ProtectsSystemDefinition`.
- Feature 4: `EnsureUserHasRole` middleware, `DashboardController` role-based dashboard routing, shared auth roles/permissions via `HandleInertiaRequests`, role-aware navigation.
- Feature 3.1: Admin Role & Permission Management CRUD.
- Features 5–9 are admin configuration/catalogues; thin controllers, dedicated Form Requests, permission-based Policies, paginated search, split-layout UI, no Service/Repository.
- Wayfinder camel-cases hyphenated route-name segments (e.g. `admin.employment-types.*` → `admin.employmentTypes.*`).
- Platform admin permissions are an explicit allowlist in `AuthorizationSeeder`; new permission enum cases must be added there deliberately. Deployed environments must rerun the seeder after permission changes.
- Employer capability requires **role `employer` + status `approved`**. Status is separate from role/permission and lives on `users.status` (nullable enum-backed via accessor; empty/invalid values normalize to `null`). Suspension preserves data and blocks employer routes via `employer.approved` middleware but does not ban login.
- Feature 10 admin employer review/suspension is implemented; `/admin/employers` scopes to applicants/employers and uses explicit approve/reject/suspend/reactivate actions.
- Feature 11 onboarding persists one `employer_onboarding_requests` row per user (unique `user_id`), with reviewer metadata and `review_note` (rejection reason). Approval adds `employer` additively while retaining `job_seeker`.
- Feature 12 implemented: approved employers create/manage company profiles via new `companies` table (`owner_user_id` FK→users, unique `slug`, `is_active` boolean default true, free-text `industry`/`size_band`/`location`, optional public logo on `public` disk). No new permissions; owner Policy-based. No Service/Repository for company CRUD. Admin employer index/show now surface the owner’s `company` summary. Company deletion deferred to Feature 13 (restrict-on-delete).

## Current task / latest handoff

- Status: **Feature 12 implemented, tested, built, typechecked, linted, formatted, migrated, and verified via follow-up fixes** (this session). Not committed/pushed.
- Summary: Employer company profiles added (`companies` table + `Company` model + `CompanyPolicy` + `Store/UpdateCompanyRequest` + `CompanyController` + employer company routes under `role:employer` + `employer.approved` + `throttle:20,1`; Inertia pages `employer/companies/{index,create,edit,show}`; employer dashboard card now links to company list; admin employer show/index expose owner `company` summary).
- Key files (Feature 12):
    - Backend: `database/migrations/2026_11_01_000007_create_companies_table.php`, `app/Models/Company.php`, `database/factories/CompanyFactory.php`, `app/Policies/CompanyPolicy.php`, `app/Http/Requests/Employer/StoreCompanyRequest.php`, `app/Http/Requests/Employer/UpdateCompanyRequest.php`, `app/Http/Controllers/Employer/CompanyController.php`, `app/Models/User.php` (companies relation), `app/Http/Controllers/Admin/EmployerController.php` (company payload), `routes/web.php`, migrations `2026_11_01_000005_*`, `2026_11_01_000006_*`.
    - Frontend: `resources/js/types/company.ts`, `resources/js/types/index.ts`, `resources/js/pages/employer/companies/*`, `resources/js/pages/admin/employers/{index,show}.tsx`, `resources/js/pages/employer/dashboard.tsx`, `resources/js/types/authorization.ts`.
    - Tests/spec: `tests/Feature/Employer/CompanyTest.php`, `docs/specs/feature-12-employer-company-profile.md`.
- Verification observed (latest run):
    - `php artisan test`: **266 passed, 1911 assertions**
    - `npm run types:check`: no errors
    - `npm run check`: 138 files formatted, no lint errors
    - `npm run build`: built successfully (Wayfinder regenerated)
    - `vendor/bin/pint --test`: passed
    - `vendor/bin/phpstan analyse --memory-limit=512M`: 0 errors
    - `php artisan migrate`: applied through `2026_11_01_000007`
- Behavior notes:
    - Company status = `is_active` boolean (default true); no new permission; owner-only Policy checks.
    - Optional public logo (jpeg/png/webp, ≤2MB) on `public` disk; fixed missing `public/storage` symlink via `php artisan storage:link` (root cause of invisible logo).
    - Admin employer review now shows employer-created `company` (falls back to onboarding request company name in list).
    - Company deletion deferred to Feature 13 (restrict-on-delete when jobs exist).
- Next recommended step: start Feature 13 (employer job draft CRUD) via `/architect feature 13`.

## Durable notes

- Preserve custom authorization architecture and last-administrator safeguards.
- Keep route permission middleware and Policy checks authoritative on the server.
- Rebuild frontend assets (`npm run build`) after modifying controller actions or route definitions used by Wayfinder.
- Feature 6 deferred rule: "do not delete an employment type referenced by jobs" cannot be enforced until Feature 13 creates the `jobs` table; Feature 13's spec must add `employment_type_id` FK with restrict-on-delete plus admin-UI protection.
- Feature 7 deferred rule: same referenced-option delete protection for workplace arrangements, pending Feature 13.
- Feature 8 deferred rule: same referenced-option delete protection for experience levels, pending Feature 13; Feature 13 must add `experience_level_id` FK with restrict-on-delete.
- Feature 9 deferred rules: job `pay_period_id` FK (restrict-on-delete) + active-period enforcement in Feature 13; band filtering by currency+period/half-open semantics in Feature 16/17; saved-search band references in Feature 29.
- Never store `.env` values, credentials, tokens, private keys, or personal data here.
