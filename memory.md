# Project Memory

> Maintained by `/remember save`. Concise project facts and latest work handoff; not a transcript or secret store.

## Project profile

- Stack: Laravel 13.38, React 19.3, TypeScript 5.9 strict, Inertia 3, Tailwind CSS 4, shadcn/ui vendored components.
- Product: job portal for candidates, employers, and platform administrators per `project-overview.md`.
- Database target: unresolved. `AGENTS.md` baseline is MySQL; local runtime and test suite use SQLite.
- Authentication: session-based Laravel Fortify. Registration, login/logout, password reset, 2FA, passkeys enabled; email verification disabled.
- Authorization: custom dynamic DB-backed role/permission system (Features 3–14). Roles: `platform_admin`, `employer`, `job_seeker`. Permissions include `admin.access`, `users.*`, `roles.*`, `permissions.*`, catalogue permissions, `employers.view`/`employers.manage`, and `jobs.moderate`. Policies, middleware, services, and admin management UI in place.
- Tests: Pest 5 on PHPUnit 13; `php artisan test`. Latest full suite: **496 passed, 3492 assertions** (includes Feature 28 tests).
- Frontend conventions: Inertia page props, Wayfinder actions/routes, named routes, vendored shadcn/ui, pagination component, split/admin layouts.
- Feature 21 implemented: Submit a job application. Eligible job seekers can submit applications to published jobs with their selected resume and optional cover letter, enforcing duplicate prevention via DB unique constraint + Form Request closure validation, deadline validation, and server-side authorization.
- Feature 22 implemented: Candidate application history and status view.
- Feature 23 implemented: Employer applicant list and application detail. Approved employers can view paginated applicants for their company's jobs at `/employer/jobs/{job}/applicants`, view candidate profile details at `/employer/applications/{application}`, and download submitted resumes at `/employer/applications/{application}/resume`. Strict company tenant authorization via `JobApplicationPolicy` and `CandidateResumePolicy` prevents IDOR/cross-company data leakage.
- Feature 24 implemented: Application status transitions and history — employers can transition applications through a controlled pipeline with status history timeline; candidates can withdraw non-terminal applications. Includes server-side validation, concurrency protection, and UI components.
- Feature 25 implemented: In-app notification center at `/notifications` using Laravel database notifications, with read/unread state, pagination, mark-one/mark-all-read, shared unread badge, and owner-only policy. Events covered: candidate application submitted, employer new application received, job submitted for review, candidate status changed, candidate withdrawal, job moderation outcome, new user registration, and employer onboarding submission.
- Feature 26 implemented: Interview scheduling. Approved employers can schedule multiple interview rounds per application (shortlisted → interview_scheduled), reschedule/cancel rounds, and candidates can accept, decline, or request a new time. Cancelling the last active round reverts the application to `shortlisted` (system-only transition, not in employer dropdown). Declining the last round does not revert. Application transitions out of `interview_scheduled` (offer/rejected/withdrawn) auto-cancel all active rounds. Includes overlap guard, UTC time handling, candidate-safe props, and interview notifications.
- Feature 27 implemented: Job-specific screening questions with reusable question bank, named templates, and job-level application. Bank questions (`short_text`, `long_text`, `yes_no`, `single_choice`) are owned per employer user, grouped into templates, and copied onto jobs (snapshot). Candidates answer active questions on the apply form; answers persist immutably and display on candidate/employer application detail pages. Includes deactivation-vs-delete protection for answered questions, duplicate-option UX, and template/question bank navigation.
- Feature 28 implemented: Company hiring-team members and scoped permissions. Company owner can add/revoke/approve-employer members with company-scoped roles `owner`/`recruiter`/`reviewer`; membership-based access for jobs, applicants, interviews, screening questions, resumes, and company profile; last-owner protection; reviewer read-only gating on application status/interview scheduling; notifications on add/role-change/revoke. Table `company_members`; no platform role/permission changes; primary `owner_user_id` immutable.
- Closed job status display on candidate application views: `/candidate/applications` and `/candidate/applications/{application}` surface `job_status`/`job.status` with a distinct "Job Closed" badge when an employer closes a job posting.
- Combined employer navigation: "Job Drafts" and "Applicant Review" merged into a single "Jobs & Applicants" sidebar item pointing to `employer.jobs.index()` to eliminate dual-highlighting.

## Confirmed architecture decisions

- Follow `AGENTS.md`; keep Services/Repositories only when complexity, reuse, or query shape justifies them. `RoleAssignmentService` handles transactional role sync and last-admin invariant locking. `ScreeningTemplateService::applyToJob` wraps the transactional multi-row copy when applying a template. Company membership CRUD stays thin-controller (no Service) with transactional last-owner protection.
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
- Feature 24 decisions: Application status transitions and history — employers can transition applications through a controlled pipeline with status history timeline; candidates can withdraw non-terminal applications. Includes server-side validation, concurrency protection, and UI components.
- Feature 25 decisions: Laravel database notifications via the existing `User` `Notifiable` trait and standard `notifications` table (UUID primary key, polymorphic notifiable, nullable `read_at`, composite `(notifiable_id, read_at)` index). `NotificationService` centralizes creation and isolates failures with try/catch + `Log::warning`; notifications are created after primary writes/outside transactions. `NotificationPolicy` enforces recipient-only access via `view`. Routes `GET /notifications`, `PATCH /notifications/{notification}/read`, `POST /notifications/read-all` live under `auth` with throttled writes. No new permissions. Unread count is shared through Inertia. Admin-targeted events resolve recipients from the custom role/permission schema; job-review notifications target users with both `admin.access` and `jobs.moderate`.
- Feature 26 decisions: multiple interview rounds per application (no unique constraint on `job_application_id`); active statuses `scheduled`/`accepted`/`reschedule_requested`; candidate may accept, decline, or request a new time with one proposed UTC instant + IANA zone + optional note; cancelling the last active round reverts the application to `shortlisted` via a guarded system-only transition with status history; declining the last round does not revert. Overlap guard rejects overlapping active intervals (half-open `[start, start+duration)`); cancelled/declined rounds do not block. UTC storage via `dateTime` with server-side conversion from submitted local wall time; IANA validation. `internal_notes` employer-only and excluded from candidate props and notification text. `InterviewService` added; no Repository. Application-first lock order; stale writes return 409. Application leaving `interview_scheduled` (offer/rejected/withdrawn) auto-cancels active rounds without a separate interview notification. No new permission slugs; `InterviewPolicy` reuses approved-employer/company-owner and candidate-owner checks.
- Feature 27 decisions: reusable question bank + named templates + job-level copy (snapshot) of questions; question types `short_text`, `long_text`, `yes_no`, `single_choice`; questions owned per approved employer user; templates group bank questions; applying a template copies questions into `job_screening_questions` with `source_question_id`; questions with submitted answers are protected from edits/deletion (deactivate allowed); answers stored in `job_application_answers` with separate columns and are immutable; no new permission slugs; cap 50 questions per job / 100 per bank; closed jobs are read-only for job questions.
- Feature 28 decisions: company hiring-team membership via `company_members` table (`company_id`, `user_id`, `company_role` ∈ owner/recruiter/reviewer, `status` ∈ active/revoked, invited metadata); members must already be approved employers; primary `owner_user_id` stays immutable and is treated as implicit `owner` role (no backfill); company-scoped permission matrix (owner all; recruiter all except `company.update`/`members.manage`; reviewer view + resume download); `Company::forManagingUser` / `Job::forManagingUser` scopes; `User::companyRole()` / `hasCompanyPermission()` resolve access without platform roles; last active owner membership cannot be removed/demoted; revoked members lose access immediately; notifications on add/role-change/revoke; no new platform permission slugs; reviewer is read-only for application status transitions and interview scheduling.

## Current task / latest handoff

- Status: **Feature 28 implemented, tested, and verified; changes are uncommitted in the working tree on `main`.** Feature 27 is committed.
- Summary: Feature 28 adds company hiring-team members with scoped permissions. Owner can add/revoke approved-employer members with roles owner/recruiter/reviewer; membership-based access for companies, jobs, applicants, interviews, screening questions, resumes; reviewer read-only gating; notifications; last-owner protection; two-column members UI. Backend security hardened (`JobPolicy::create` requires `jobs.create` permission). Spec: `docs/specs/feature-28-company-hiring-team-members.md` (status IMPLEMENTED).
- Branch: `main` (working tree contains Feature 28 changes; verify before committing).
- Key files (Feature 28):
    - Backend: `database/migrations/2026_11_10_000000_create_company_members_table.php`, `app/Enums/CompanyMemberRole.php`, `app/Enums/CompanyMemberStatus.php`, `app/Models/CompanyMember.php`, updates to `Company`, `Job`, `User`, `CompanyPolicy`, `JobPolicy`, `JobApplicationPolicy`, `InterviewPolicy`, `JobScreeningQuestionPolicy`, `CandidateResumePolicy`, `app/Http/Controllers/Employer/CompanyMemberController.php`, `StoreCompanyMemberRequest`, `UpdateCompanyMemberRequest`, `CompanyController`, `JobController`, `EmployerJobApplicationController`, `app/Services/NotificationService.php`, `app/Notifications/CompanyMember*Notification.php`, `routes/web.php`.
    - Frontend: `resources/js/pages/employer/companies/members.tsx`, updates to `employer/companies/show.tsx`, `resources/js/types/company.ts`, `employer/applications/show.tsx`, `components/interview/employer-interviews.tsx`.
    - Tests: `tests/Feature/Employer/CompanyMemberTest.php` (17 tests); existing suite updated (InterviewSchedulingTest still green).
- Tests/checks actually run for Feature 28:
    - `php artisan test` — **496 passed, 3492 assertions**.
    - `php artisan test --filter=CompanyMemberTest` — **17 passed, 109 assertions**.
    - `vendor\bin\pint --test` — passed.
    - `php vendor/bin/phpstan analyse --memory-limit=512M --no-progress` — passed, 0 errors.
    - `npm run types:check` — passed.
    - `npm run check` — passed.
    - `npm run build` — passed.
- Defects found and fixed during implementation:
    - Reviewers could create jobs because creation only checked approved-employer status; added `JobPolicy::create(User, Company)` requiring `jobs.create`.
    - `EmployerJobApplicationController` eager-loaded `company` without `owner_user_id`, breaking `companyRole()` for owners; added `owner_user_id` to select.
    - Revoked-member role changes and revoke errors now surface in UI instead of silent refresh.
- Next recommended step: review/commit the Feature 28 working-tree changes, then implement the next feature (Feature 29 — Saved searches and job alerts) per `project-overview.md`.

## Durable notes

- Preserve custom authorization architecture and last-administrator safeguards.
- Keep route permission middleware and Policy checks authoritative on the server.
- Rebuild frontend assets (`npm run build`) after modifying controller actions or route definitions used by Wayfinder.
- Feature 6–9 deferred delete protections (employment types, workplace arrangements, experience levels, pay periods, categories) are fully active via `restrictOnDelete` foreign key constraints on `job_postings`.
- Screening bank questions and templates are owned per employer user; bank question deletion is blocked when referenced by any template or job (deactivate instead).
- Company members are scoped per company; company-scoped roles never grant platform-admin permissions. Revoked members lose access immediately; last active owner cannot be removed/demoted.
- Never store `.env` values, credentials, tokens, private keys, or personal data here.