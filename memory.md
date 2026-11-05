# Project Memory

> Maintained by `/remember save`. Concise project facts and latest work handoff; not a transcript or secret store.

## Project profile

- Stack: Laravel 13.38, React 19.3, TypeScript 5.9 strict, Inertia 3, Tailwind CSS 4, shadcn/ui vendored components.
- Product: job portal for candidates, employers, and platform administrators per `project-overview.md`.
- Database target: unresolved. `AGENTS.md` baseline is MySQL; local runtime and test suite use SQLite.
- Authentication: session-based Laravel Fortify. Registration, login/logout, password reset, 2FA, passkeys enabled; email verification disabled.
- Authorization: custom dynamic DB-backed role/permission system (Features 3–14). Roles: `platform_admin`, `employer`, `job_seeker`. Permissions include `admin.access`, `users.*`, `roles.*`, `permissions.*`, catalogue permissions, `employers.view`/`employers.manage`, and `jobs.moderate`. Policies, middleware, services, and admin management UI in place.
- Tests: Pest 5 on PHPUnit 13; `php artisan test`. Latest full suite: **327 passed, 2457 assertions** (Features 16-19).
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
- Feature 12 implemented: approved employers create/manage company profiles via new `companies` table.
- Feature 13 implemented: Employer job drafts (`job_postings`).
- Feature 14 implemented: Job submission (`pending_review`), admin moderation, rejection, and closure.
- Feature 15 implemented: Employer job dashboard.
- Feature 16 implemented: Public job listing.
- Feature 17 implemented: Public job detail.
- Feature 18 implemented: Candidate profile management (`candidate_profiles`, `candidate_educations`, `candidate_experiences`).
- Feature 19 implemented: Private candidate resume management (upload, replace, delete, download). Uses `Storage::disk('local')` for secure private storage; `CandidateResumePolicy` enforces owner-only access.

## Current task / latest handoff

- Status: **Feature 19 implemented, tested, and verified.**
- Summary: Private resume upload/management implemented. Job seekers can upload, replace, delete, and download their single PDF resume at `/candidate/resumes`. Atomic file replacement, soft deletes, secure streaming download via `CandidateResumeController::download` and `CandidateResumePolicy`. Fixed oversized file validation bug (`max:5120` KB). UI polished for consistency and accessibility.
- Key files (Feature 19):
    - Backend: `database/migrations/2026_11_04_000000_create_candidate_resumes.php`, `CandidateResume`, `CandidateResumePolicy`, `UploadResumeRequest`, `CandidateResumeService`, `CandidateResumeController`, routes (`candidate.resumes.*`, `candidate.resume.download`).
    - Frontend: `resources/js/pages/candidate/resumes/index.tsx`, `resources/js/pages/candidate/dashboard.tsx` (updated UI), `resources/js/types/candidate.ts`.
    - Tests: `tests/Feature/Candidate/CandidateResumeTest.php` (10 tests).
    - Spec: `docs/specs/feature-19-private-resume-upload-and-management.md` (status IMPLEMENTED).
- Verification observed (2026-11-05):
    - `php artisan test --filter CandidateResumeTest`: **10 passed**
    - `php artisan test`: All passed.
- Next recommended step: Feature 20 (Saved jobs) or Feature 21 (Submit a job application) per `project-overview.md` roadmap.

## Durable notes

- Preserve custom authorization architecture and last-administrator safeguards.
- Keep route permission middleware and Policy checks authoritative on the server.
- Rebuild frontend assets (`npm run build`) after modifying controller actions or route definitions used by Wayfinder.
- Feature 6–9 deferred delete protections (employment types, workplace arrangements, experience levels, pay periods, categories) are fully active via `restrictOnDelete` foreign key constraints on `job_postings`.
- Never store `.env` values, credentials, tokens, private keys, or personal data here.
