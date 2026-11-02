# Project Memory

> Maintained by `/remember save`. Concise project facts and latest work handoff; not a transcript or secret store.

## Project profile

- Stack: Laravel 13.38, React 19.3, TypeScript 5.9 strict, Inertia 3, Tailwind CSS 4, shadcn/ui vendored components.
- Product: job portal for candidates, employers, and platform administrators per `project-overview.md`.
- Database target: unresolved. `AGENTS.md` baseline is MySQL; local runtime and test suite use SQLite.
- Authentication: session-based Laravel Fortify. Registration, login/logout, password reset, 2FA, passkeys enabled; email verification disabled.
- Authorization: custom dynamic DB-backed role/permission system (Features 3–14). Roles: `platform_admin`, `employer`, `job_seeker`. Permissions include `admin.access`, `users.*`, `roles.*`, `permissions.*`, catalogue permissions, `employers.view`/`employers.manage`, and `jobs.moderate`. Policies, middleware, services, and admin management UI in place.
- Tests: Pest 5 on PHPUnit 13; `php artisan test`. Latest full suite: **297 passed, 2151 assertions** (Feature 16 added `tests/Feature/PublicJobListTest.php`).
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
- Feature 16 implemented: Public job listing with server-side pagination (`docs/specs/feature-16-public-job-listing.md`). Includes `PublicJobController`, public `GET /jobs` route, `PublicLayout` mapping for `jobs/*`, React Inertia index page (`resources/js/pages/jobs/index.tsx`) with search and filter dropdowns (category, employment type, workplace arrangement, experience level), homepage/header navigation links, and Pest test suite (`tests/Feature/PublicJobListTest.php`).

## Current task / latest handoff

- Status: **Feature 16 implemented, tested, built, and verified.**
- Summary: Public job listing (`/jobs`) fully implemented allowing anyone (guests and users) to search published jobs from approved active employers and filter by category, employment type, workplace arrangement, and experience level with server-side pagination.
- Key files (Feature 16):
    - Backend: `app/Http/Controllers/PublicJobController.php`, `routes/web.php`.
    - Frontend: `resources/js/pages/jobs/index.tsx`, `resources/js/app.tsx`, `resources/js/components/public/site-header.tsx`, `resources/js/pages/home.tsx`.
    - Tests/spec: `tests/Feature/PublicJobListTest.php`, `docs/specs/feature-16-public-job-listing.md`.
- Verification observed (latest run):
    - `php artisan test`: **297 passed, 2151 assertions**
    - `npm run build`: built successfully
- Next recommended step: Await further instructions from the user on next features (e.g., Feature 17 detailed job view page).

## Durable notes

- Preserve custom authorization architecture and last-administrator safeguards.
- Keep route permission middleware and Policy checks authoritative on the server.
- Rebuild frontend assets (`npm run build`) after modifying controller actions or route definitions used by Wayfinder.
- Feature 6–9 deferred delete protections (employment types, workplace arrangements, experience levels, pay periods, categories) are fully active via `restrictOnDelete` foreign key constraints on `job_postings`.
- Never store `.env` values, credentials, tokens, private keys, or personal data here.
