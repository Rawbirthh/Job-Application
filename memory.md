# Project Memory

> Maintained by `/remember save`. Concise project facts and latest work handoff; not a transcript or secret store.

## Project profile

- Stack: Laravel 13.38, React 19.3, TypeScript 5.9 strict, Inertia 3, Tailwind CSS 4, shadcn/ui vendored components.
- Product: job portal for candidates, employers, and platform administrators per `project-overview.md`.
- Database target: unresolved. `AGENTS.md` baseline is MySQL; local runtime and test suite use SQLite.
- Authentication: session-based Laravel Fortify. Registration, login/logout, password reset, 2FA, passkeys enabled; email verification disabled.
- Authorization: custom dynamic DB-backed role/permission system (Features 3–14). Roles: `platform_admin`, `employer`, `job_seeker`. Permissions include `admin.access`, `users.*`, `roles.*`, `permissions.*`, catalogue permissions, `employers.view`/`employers.manage`, and `jobs.moderate`. Policies, middleware, services, and admin management UI in place.
- Tests: Pest 5 on PHPUnit 13; `php artisan test`. Latest full suite: **327 passed, 2457 assertions** (Features 16-18).
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
- Feature 17 implemented: Public job detail page (`docs/specs/feature-17-public-job-detail.md`). Includes `PublicJobController::show` with server-side visibility enforcement (only `published` jobs from approved active companies viewable, all others 404), eager loading to prevent N+1, `GET /jobs/{job}` route (`jobs.show`), responsive React Inertia detail page (`resources/js/pages/jobs/show.tsx`) with two-column layout, contextual CTA (guest: sign-in link, job seeker: apply button, employer/admin: role note), company sidebar, salary formatting, deadline handling, accessible markup, and Pest test suite (`tests/Feature/PublicJobDetailTest.php` with 14 tests covering visibility rules, field exclusion, optional fields).
- Feature 18 implemented: Candidate profile management (`docs/specs/feature-18-candidate-profile-management.md`). Includes `candidate_profiles`, `candidate_educations`, `candidate_experiences` tables; `CandidateProfile`/`CandidateEducation`/`CandidateExperience` models; `CandidateProfilePolicy` (owner-only); `UpdateCandidateProfileRequest`; `CandidateProfileService` (transactional sync); `CandidateProfileController` (`candidate.profile.edit`/`update`); Inertia page `resources/js/pages/candidate/profile/edit.tsx`; types `resources/js/types/candidate.ts`; dashboard link. Privacy: owner-only, no ID-based access, forged IDs ignored.

## Current task / latest handoff

- Status: **Feature 18 implemented, tested, and verified.**
- Summary: Candidate profile management implemented. Authenticated job seekers can create/edit private professional profile (headline, summary, location, skills, education entries, work experience entries) at `/candidate/profile`. Owner-only access; no ID-based route; forged IDs ignored. Data persisted across `candidate_profiles`, `candidate_educations`, `candidate_experiences`. Validation via `UpdateCandidateProfileRequest`; transactional sync via `CandidateProfileService`. Inertia page with dynamic rows, skills chips, empty states, nested errors.
- Key files (Feature 18):
    - Backend: migration `database/migrations/2026_11_03_000000_create_candidate_profile_tables.php`; models `CandidateProfile`, `CandidateEducation`, `CandidateExperience`, `User::candidateProfile`; `CandidateProfilePolicy`; `UpdateCandidateProfileRequest`; `CandidateProfileService`; `CandidateProfileController`; routes `candidate.profile.edit`/`update`.
    - Frontend: `resources/js/types/candidate.ts`, `resources/js/pages/candidate/profile/edit.tsx`, dashboard link.
    - Tests: `tests/Feature/Candidate/CandidateProfileTest.php` (12 tests, 156 assertions).
    - Spec: `docs/specs/feature-18-candidate-profile-management.md` (status IMPLEMENTED).
- Verification observed (2026-11-03):
    - `php artisan test --filter=CandidateProfileTest`: **12 passed, 156 assertions**
    - `php artisan test`: **327 passed, 2457 assertions**
    - `npx tsc --noEmit`: **0 errors**
    - `npm run build`: success
    - `vendor/bin/pint`: fixed formatting on 2 files; rerun tests passed
- Next recommended step: Feature 19 (Private resume upload) or Feature 20 (Saved jobs) per `project-overview.md` roadmap.

## Durable notes

- Preserve custom authorization architecture and last-administrator safeguards.
- Keep route permission middleware and Policy checks authoritative on the server.
- Rebuild frontend assets (`npm run build`) after modifying controller actions or route definitions used by Wayfinder.
- Feature 6–9 deferred delete protections (employment types, workplace arrangements, experience levels, pay periods, categories) are fully active via `restrictOnDelete` foreign key constraints on `job_postings`.
- Never store `.env` values, credentials, tokens, private keys, or personal data here.
