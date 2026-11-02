# Project Memory

> Maintained by `/remember save`. Concise project facts and latest work handoff; not a transcript or secret store.

## Project profile

- Stack: Laravel 13.38, React 19.3, TypeScript 5.9 strict, Inertia 3, Tailwind CSS 4, shadcn/ui vendored components.
- Product: job portal for candidates, employers, and platform administrators per `project-overview.md`.
- Database target: unresolved. `AGENTS.md` baseline is MySQL; local runtime and test suite use SQLite.
- Authentication: session-based Laravel Fortify. Registration, login/logout, password reset, 2FA, passkeys enabled; email verification disabled.
- Authorization: custom dynamic DB-backed role/permission system (Features 3–14). Roles: `platform_admin`, `employer`, `job_seeker`. Permissions include `admin.access`, `users.*`, `roles.*`, `permissions.*`, catalogue permissions, `employers.view`/`employers.manage`, and `jobs.moderate`. Policies, middleware, services, and admin management UI in place.
- Tests: Pest 5 on PHPUnit 13; `php artisan test`. Latest full suite: **287 passed, 1995 assertions** (Feature 14 added `tests/Feature/Employer/JobSubmissionTest.php` and `tests/Feature/Admin/JobModerationTest.php`).
- Frontend conventions: Inertia page props, Wayfinder actions/routes, named routes, vendored shadcn/ui, pagination component, split/admin layouts.

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
- Feature 12 implemented: approved employers create/manage company profiles via new `companies` table (`owner_user_id` FK→users, unique `slug`, `is_active` boolean default true, free-text `industry`/`size_band`/`location`, optional public logo on `public` disk). No new permissions; owner Policy-based. No Service/Repository for company CRUD. Admin employer index/show now surface the owner’s `company` summary. Company deletion restricted when jobs exist.
- Feature 13 implemented: approved employers can create and edit job drafts (`job_postings` table) associated with their owned companies. Includes migration with restrict-on-delete constraints on catalogues, `Job` model & factory, `JobPolicy`, `StoreJobRequest` & `UpdateJobRequest` with salary validation, `JobController` with owned-scope IDOR protection, routes (`employer.jobs.*`), Inertia React pages (`employer/jobs/create.tsx`, `edit.tsx`), and Pest test suite (`tests/Feature/Employer/JobDraftTest.php`).
- Feature 14 implemented: Job submission (`draft`/`rejected` → `pending_review`), admin moderation queue & approval (`pending_review` → `published`), rejection with required reason (`pending_review` → `rejected`), closure (`published`/`pending_review` → `closed`), and re-review rule (editing a published job resets status to `draft`). Includes migration for `rejection_reason`, `jobs.moderate` permission in `AuthorizationSeeder`, `JobPolicy` moderation/submission/closure rules, `JobModerationController` (`admin/jobs/*`), admin React pages (`admin/jobs/index.tsx`, `show.tsx`), employer submission/closure controller actions, and comprehensive Pest test suites (`JobSubmissionTest.php`, `JobModerationTest.php`).
- Feature 15 implemented: Employer job dashboard and listing filters (`employer/jobs`). Includes company selection, status filtering, title search (`search`), application counts (`withCount('applications')`), pagination preserving query strings, and context-aware action links. Backed by Pest feature test suite (`tests/Feature/Employer/JobDashboardTest.php`).

## Current task / latest handoff

- Status: **Feature 15 implemented, tested, built, typechecked, linted, formatted, and verified.**
- Summary: Approved employer job dashboard (`employer/jobs`) fully implemented with company selection, status filtering, title search (`search`), pagination preserving query strings, dates, application count indicators, and context-aware action links.
- Key files (Feature 15):
    - Backend: `app/Http/Controllers/Employer/JobController.php` (search & payload support).
    - Frontend: `resources/js/pages/employer/jobs/index.tsx`, `resources/js/types/job.ts`.
    - Tests/spec: `tests/Feature/Employer/JobDashboardTest.php`, `docs/specs/feature-15-employer-job-dashboard-and-listing-filters.md`.
- Verification observed (latest run):
    - `php artisan test`: **294 passed, 2093 assertions**
    - `npm run types:check`: 0 errors (`tsc --noEmit`)
    - `npm run build`: built successfully
    - `vendor/bin/pint`: passed formatted
    - `vendor/bin/phpstan analyse --memory-limit=512M`: 0 errors
- Next recommended step: Await further instructions from the user on next features (e.g. Feature 16 public job listing).

## Durable notes

- Preserve custom authorization architecture and last-administrator safeguards.
- Keep route permission middleware and Policy checks authoritative on the server.
- Rebuild frontend assets (`npm run build`) after modifying controller actions or route definitions used by Wayfinder.
- Feature 6–9 deferred delete protections (employment types, workplace arrangements, experience levels, pay periods, categories) are fully active via `restrictOnDelete` foreign key constraints on `job_postings`.
- Never store `.env` values, credentials, tokens, private keys, or personal data here.
