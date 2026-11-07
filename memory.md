# Project Memory

> Maintained by `/remember save`. Concise project facts and latest work handoff; not a transcript or secret store.

## Project profile

- Stack: Laravel 13.38, React 19.3, TypeScript 5.9 strict, Inertia 3, Tailwind CSS 4, shadcn/ui vendored components.
- Product: job portal for candidates, employers, and platform administrators per `project-overview.md`.
- Database target: unresolved. `AGENTS.md` baseline is MySQL; local runtime and test suite use SQLite.
- Authentication: session-based Laravel Fortify. Registration, login/logout, password reset, 2FA, passkeys enabled; email verification disabled.
- Authorization: custom dynamic DB-backed role/permission system (Features 3–14). Roles: `platform_admin`, `employer`, `job_seeker`. Permissions include `admin.access`, `users.*`, `roles.*`, `permissions.*`, catalogue permissions, `employers.view`/`employers.manage`, and `jobs.moderate`. Policies, middleware, services, and admin management UI in place.
- Tests: Pest 5 on PHPUnit 13; `php artisan test`. Latest full suite: **389 passed, 2791 assertions** across all features.
- Frontend conventions: Inertia page props, Wayfinder actions/routes, named routes, vendored shadcn/ui, pagination component, split/admin layouts.
- Feature 21 implemented: Submit a job application. Eligible job seekers can submit applications to published jobs with their selected resume and optional cover letter, enforcing duplicate prevention via DB unique constraint + Form Request closure validation, deadline validation, and server-side authorization.
- Feature 22 implemented: Candidate application history and status view.
- Feature 23 implemented: Employer applicant list and application detail. Approved employers can view paginated applicants for their company's jobs at `/employer/jobs/{job}/applicants`, view candidate profile details at `/employer/applications/{application}`, and download submitted resumes at `/employer/applications/{application}/resume`. Strict company tenant authorization via `JobApplicationPolicy` and `CandidateResumePolicy` prevents IDOR/cross-company data leakage.
- Feature 25 implemented: In-app notification center at `/notifications` using Laravel database notifications, with read/unread state, pagination, mark-one/mark-all-read, shared unread badge, and owner-only policy. Events covered: candidate application submitted, employer new application received, job submitted for review, candidate status changed, candidate withdrawal, job moderation outcome, new user registration, and employer onboarding submission.
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
- Feature 24 implemented: Application status transitions and history — employers can transition applications through a controlled pipeline with status history timeline; candidates can withdraw non-terminal applications. Includes server-side validation, concurrency protection, and UI components.
- Feature 25 decisions: Laravel database notifications via the existing `User` `Notifiable` trait and standard `notifications` table (UUID primary key, polymorphic notifiable, nullable `read_at`, composite `(notifiable_id, read_at)` index). `NotificationService` centralizes creation and isolates failures with try/catch + `Log::warning`; notifications are created after primary writes/outside transactions. `NotificationPolicy` enforces recipient-only access via `view`. Routes `GET /notifications`, `PATCH /notifications/{notification}/read`, `POST /notifications/read-all` live under `auth` with throttled writes. No new permissions. Unread count is shared through Inertia. Admin-targeted events resolve recipients from the custom role/permission schema; job-review notifications target users with both `admin.access` and `jobs.moderate`.

## Current task / latest handoff

- Status: **Feature 25 fully implemented, tested, and verified; changes are uncommitted in the working tree on `main`.**
- Summary: Feature 25 adds an authenticated notification center backed by Laravel database notifications. Users can view paginated notifications newest-first, mark one or all as read, and see an unread badge in the shared header/sidebar shell. Notifications are owner-scoped by `NotificationPolicy`; no deletion or preferences are supported.
- Notification events: candidate application submitted; employer receives a new-application notification; admins/moderators receive a job-submitted-for-review notification; candidate receives status-change notifications; company owner receives withdrawal notifications; company owner receives job moderation outcomes; platform admins receive new-user and employer-onboarding notifications.
- Branch: `main` (working tree contains Feature 25 changes; verify before committing).
- Approved spec: `docs/specs/feature-25-in-app-notifications.md`.
- Key files (Feature 25):
    - Backend: `database/migrations/2026_11_07_130000_create_notifications_table.php`, `app/Notifications/*` (8 classes), `app/Services/NotificationService.php`, `app/Policies/NotificationPolicy.php`, `app/Http/Controllers/NotificationController.php`, `app/Http/Middleware/HandleInertiaRequests.php`, `app/Providers/AppServiceProvider.php`, `routes/web.php`.
    - Call sites: `CreateNewUser`, `JobApplicationController`, `EmployerJobApplicationController`, `ApplicationHistoryController`, `JobController` (submit for review), `JobModerationController`, `EmployerOnboardingController`.
    - Frontend: `resources/js/pages/notifications/index.tsx`, `resources/js/types/notification.ts`, `resources/js/types/global.d.ts`, `resources/js/lib/navigation.ts`, `resources/js/components/app-header.tsx`, `resources/js/components/app-sidebar-header.tsx`.
    - Tests: `tests/Feature/Notifications/NotificationCenterTest.php`, `tests/Feature/Notifications/NotificationEventsTest.php`.
- Tests/checks actually run for Feature 25:
    - `php artisan test` — **389 passed, 2791 assertions**.
    - `php artisan test --filter=Notifications` — **18 passed, 92 assertions**.
    - `./vendor/bin/pint --test` — passed.
    - `vendor/bin/phpstan analyse` — passed, 0 errors.
    - `npm run types:check` — passed.
    - `npm run build` — passed.
    - `php artisan migrate` — succeeded locally.
- Next recommended step: review/commit the Feature 25 working-tree changes, then implement Feature 26 (Interview scheduling) per `project-overview.md`.

## Durable notes

- Preserve custom authorization architecture and last-administrator safeguards.
- Keep route permission middleware and Policy checks authoritative on the server.
- Rebuild frontend assets (`npm run build`) after modifying controller actions or route definitions used by Wayfinder.
- Feature 6–9 deferred delete protections (employment types, workplace arrangements, experience levels, pay periods, categories) are fully active via `restrictOnDelete` foreign key constraints on `job_postings`.
- Never store `.env` values, credentials, tokens, private keys, or personal data here.
