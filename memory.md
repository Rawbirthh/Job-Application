# Project Memory

> Maintained by `/remember save`. Concise project facts and latest work handoff; not a transcript or secret store.

## Project profile

- Stack: Laravel 13.38, React 19.3, TypeScript 5.9 strict, Inertia 3, Tailwind CSS 4, shadcn/ui vendored components.
- Product: job portal for candidates, employers, and platform administrators per `project-overview.md`.
- Database target: unresolved. `AGENTS.md` baseline is MySQL; local runtime and test suite use SQLite.
- Authentication: session-based Laravel Fortify. Registration, login/logout, password reset, 2FA, passkeys enabled; email verification disabled.
- Authorization: custom dynamic DB-backed role/permission system (Features 3–10). Roles: `platform_admin`, `employer`, `job_seeker`. Permissions include `admin.access`, `users.*`, `roles.*`, `permissions.*`, catalogue permissions, and `employers.view`/`employers.manage`. Policies, middleware, services, and admin management UI in place.
- Tests: Pest 5 on PHPUnit 13; `php artisan test`. Latest full suite: **239 passed, 1732 assertions**.
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

## Current task / latest handoff

- Status: **Feature 11 implemented, tested, built, typechecked, linted, formatted, migrated, and committed locally** as `6d2ee64` on `main`. Not pushed (push declined).
- Summary: Added employer onboarding flow (`/employer/onboarding`), eligibility enforcement (`role:employer` + `employer.approved`), admin review page, candidate dashboard employer-access card, shared `auth.employer_status`. Resolved Feature 10 contradictions: `rejected` is now a real status, admin actions are explicit, directory scoped to applicants/employers. Hardened `users.status` cast against legacy empty strings.
- Key files:
    - Backend: `app/Enums/EmployerStatus.php`, `app/Models/User.php`, `app/Models/EmployerOnboardingRequest.php`, `app/Services/Employer/EmployerOnboardingService.php`, `app/Http/Controllers/Employer/EmployerOnboardingController.php`, `app/Http/Controllers/Admin/EmployerController.php`, `app/Http/Middleware/EnsureEmployerIsApproved.php`, `app/Policies/EmployerOnboardingRequestPolicy.php`, `app/Policies/EmployerPolicy.php`, `app/Providers/AppServiceProvider.php`, `bootstrap/app.php`, `routes/web.php`, `routes/admin.php`, migrations `2026_11_01_000005_*` and `2026_11_01_000006_*`.
    - Frontend: `resources/js/pages/employer/onboarding.tsx`, `resources/js/pages/admin/employers/index.tsx`, `resources/js/pages/admin/employers/show.tsx`, `resources/js/pages/candidate/dashboard.tsx`, `resources/js/types/authorization.ts`, `resources/js/types/auth.ts`.
    - Tests/spec: `tests/Feature/Employer/EmployerOnboardingTest.php`, `tests/Feature/Admin/EmployerTest.php`, `tests/Feature/DashboardRoutingTest.php`, `tests/Feature/Authorization/RegistrationAuthorizationTest.php`, `docs/specs/feature-11-employer-onboarding-and-eligibility-state.md`.
- Verification observed:
    - `php artisan test`: **239 passed, 1732 assertions**
    - `npm run types:check`: no errors
    - `npm run check`: 132 files formatted, 88 files no warnings/lint errors
    - `npm run build`: built successfully (Wayfinder regenerated)
    - `vendor/bin/pint --test`: passed
    - `vendor/bin/phpstan analyse --memory-limit=512M`: 0 errors
    - `php artisan migrate`: applied through `2026_11_01_000006`
    - `php artisan route:list`: onboarding 2 routes; admin employers 6 routes; employer dashboard has `role:employer` + `employer.approved`
- Behavior notes: `company_count` remains `null` (no `companies` table; Feature 12 will introduce). Onboarding collects minimal review context only. No notifications, audit logging, or document uploads in this increment. Suspended users retain the employer role and can view onboarding status but receive 403 on employer business routes.
- Next recommended step: push `main` when ready; then start Feature 12 (company profile creation/editing) via `/architect feature 12`.

## Durable notes

- Preserve custom authorization architecture and last-administrator safeguards.
- Keep route permission middleware and Policy checks authoritative on the server.
- Rebuild frontend assets (`npm run build`) after modifying controller actions or route definitions used by Wayfinder.
- Feature 6 deferred rule: "do not delete an employment type referenced by jobs" cannot be enforced until Feature 13 creates the `jobs` table; Feature 13's spec must add `employment_type_id` FK with restrict-on-delete plus admin-UI protection.
- Feature 7 deferred rule: same referenced-option delete protection for workplace arrangements, pending Feature 13.
- Feature 8 deferred rule: same referenced-option delete protection for experience levels, pending Feature 13; Feature 13 must add `experience_level_id` FK with restrict-on-delete.
- Feature 9 deferred rules: job `pay_period_id` FK (restrict-on-delete) + active-period enforcement in Feature 13; band filtering by currency+period/half-open semantics in Feature 16/17; saved-search band references in Feature 29.
- Never store `.env` values, credentials, tokens, private keys, or personal data here.
