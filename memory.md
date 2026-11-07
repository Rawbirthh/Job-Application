# Project Memory

> Maintained by `/remember save`. Concise project facts and latest work handoff; not a transcript or secret store.

## Project profile

- Stack: Laravel 13.38, React 19.3, TypeScript 5.9 strict, Inertia 3, Tailwind CSS 4, shadcn/ui vendored components.
- Product: job portal for candidates, employers, and platform administrators per `project-overview.md`.
- Database target: unresolved. `AGENTS.md` baseline is MySQL; local runtime and test suite use SQLite.
- Authentication: session-based Laravel Fortify. Registration, login/logout, password reset, 2FA, passkeys enabled; email verification disabled.
- Authorization: custom dynamic DB-backed role/permission system (Features 3–14). Roles: `platform_admin`, `employer`, `job_seeker`. Permissions include `admin.access`, `users.*`, `roles.*`, `permissions.*`, catalogue permissions, `employers.view`/`employers.manage`, and `jobs.moderate`. Policies, middleware, services, and admin management UI in place.
- Tests: Pest 5 on PHPUnit 13; `php artisan test`. Latest full suite: **364 passed, 2683 assertions** across all features.
- Frontend conventions: Inertia page props, Wayfinder actions/routes, named routes, vendored shadcn/ui, pagination component, split/admin layouts.
- Feature 21 implemented: Submit a job application. Eligible job seekers can submit applications to published jobs with their selected resume and optional cover letter, enforcing duplicate prevention via DB unique constraint + Form Request closure validation, deadline validation, and server-side authorization.
- Feature 22 implemented: Candidate application history and status view.
- Feature 23 implemented: Employer applicant list and application detail. Approved employers can view paginated applicants for their company's jobs at `/employer/jobs/{job}/applicants`, view comprehensive candidate profile details (headline, summary, skills, experience, education, cover letter) at `/employer/applications/{application}`, and download submitted resumes at `/employer/applications/{application}/resume`. Strict company tenant authorization via `JobApplicationPolicy` and `CandidateResumePolicy` prevents IDOR/cross-company data leakage.
- Closed job status display on candidate application views: `/candidate/applications` and `/candidate/applications/{application}` surface `job_status`/`job.status` with a distinct "Job Closed" badge when an employer closes a job posting.
- Combined employer navigation: "Job Drafts" and "Applicant Review" merged into a single "Jobs & Applicants" sidebar item pointing to `employer.jobs.index()` to eliminate dual-highlighting.

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
- Feature 20 implemented: Saved jobs. Job seekers can bookmark published jobs, view a private paginated saved jobs list at `/candidate/saved-jobs`, remove saved jobs, and toggle bookmarks directly on public job listings and detail pages.
- Feature 22 implemented: Candidate application history and status view.
- Feature 23 implemented: Employer applicant list and application detail (`/employer/jobs/{job}/applicants`, `/employer/applications/{application}`, `/employer/applications/{application}/resume`).
- Feature 24 implemented: Application status transitions and history — employers can transition applications through a controlled pipeline with status history timeline; candidates can withdraw non-terminal applications. Includes server-side validation, concurrency protection, and UI components.

## Current task / latest handoff

- Status: **Feature 24 fully implemented, tested, and verified.**
- Summary: Feature 24 adds controlled application status transitions (Submitted → Reviewing → Shortlisted → Interview Scheduled → Offer Sent → Hired/Rejected) with history tracking, concurrent update protection, and candidate withdrawal capability. Employers see status history timeline; candidates can withdraw non-terminal applications.
- Branch: `feature/employer-applicant-management` (feature-24 changes on top).
- Approved spec: `docs/specs/feature-24-application-status-transitions-and-history.md`.
- Key files (Feature 24):
    - Backend: `ApplicationStatusTransitionService`, `JobApplicationStatus` enum, `JobApplicationStatusHistory` model, `TransitionApplicationStatusRequest`, `WithdrawApplicationRequest`, `JobApplication` model (statusHistories relationship).
    - Frontend: `employer/applications/show.tsx` (transition form, history timeline), `candidate/applications/show.tsx` (withdraw dialog, history timeline), `status-history-timeline.tsx` component.
- Tests: 371 tests passing, 2699 assertions (7 new Feature 24 tests).
- Next recommended step: Feature 25 (Notifications) per `project-overview.md` roadmap.

## Durable notes

- Preserve custom authorization architecture and last-administrator safeguards.
- Keep route permission middleware and Policy checks authoritative on the server.
- Rebuild frontend assets (`npm run build`) after modifying controller actions or route definitions used by Wayfinder.
- Feature 6–9 deferred delete protections (employment types, workplace arrangements, experience levels, pay periods, categories) are fully active via `restrictOnDelete` foreign key constraints on `job_postings`.
- Never store `.env` values, credentials, tokens, private keys, or personal data here.
