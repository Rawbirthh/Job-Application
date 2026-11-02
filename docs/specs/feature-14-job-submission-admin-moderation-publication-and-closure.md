# Feature 14 — Job Submission, Admin Moderation, Publication, and Closure

**Metadata:**

- Feature ID: 14
- Title: Job submission, admin moderation, publication, and closure
- Spec Type: Feature specification
- Source: `project-overview.md` → “Feature 14 — Job submission, admin moderation, publication, and closure”
- Dependencies: Feature 13 (Employer job draft CRUD, implemented), Feature 3 (Custom roles/permissions/Policies, implemented), Feature 10 (Admin employer review/suspension, implemented)
- Downstream dependents: Feature 15 (Employer job dashboard and listing filters), Features 16/17 (Public job listing and detail), Feature 21 (Submit a job application), Feature 23 (Employer applicant list)
- Status: **APPROVED**
- Created: 2026-11-02
- Slug: `feature-14-job-submission-admin-moderation-publication-and-closure`

---

## Goal

Control the lifecycle of job postings from draft creation through employer submission, administrative moderation (approval with publication or rejection with reason), employer resubmission of corrected jobs, and employer/admin closure of published jobs. Ensure that unapproved, rejected, or closed jobs are never publicly visible, and that controlled status transitions are rigorously enforced on the server.

## Non-goals

- Public job indexing and searching (Feature 16).
- Public job detail view (Feature 17).
- Employer job dashboard listing, filtering, and summary statistics UI (Feature 15).
- Candidate applications and recruitment pipeline (Features 21–24).
- Email notifications or external webhooks (Feature 25 / future).
- Batch moderation or automated AI screening.

## Current repository evidence

- Feature 13 implemented: `job_postings` table, `Job` model, `JobPolicy`, `StoreJobRequest`, `UpdateJobRequest`, `JobController` (`employer.jobs.*`), Inertia create/edit pages, and Pest test suite (`tests/Feature/Employer/JobDraftTest.php`).
- Custom authorization system: roles (`platform_admin`, `employer`, `job_seeker`), permissions (`admin.access`, `employers.*`, etc.), and middleware (`role:platform_admin`, `role:employer`, `employer.approved`).
- Admin UI patterns established in Feature 3.1 and Feature 10 (paginated tables, search, action buttons, modals or form requests with explicit authorization).

## Assumptions and open questions

### Assumptions

- **A1:** Transition matrix is strict:
    - `draft` → `pending_review` (submitted by owner employer).
    - `rejected` → `pending_review` (resubmitted by owner employer after editing).
    - `pending_review` → `published` (approved by platform admin).
    - `pending_review` → `rejected` (rejected by platform admin, requires non-empty `rejection_reason`).
    - `published` → `closed` (closed by owner employer or platform admin).
    - `closed` → `pending_review` (re-opened/resubmitted by owner employer).
- **A2:** Editing a `published` job automatically resets its status to `draft` (or `pending_review`) to prevent unreviewed content changes from remaining live. (Per product overview: "An edit to a published job that materially changes its content may require re-review"). For simplicity and safety, any edit to a `published` job transitions it back to `draft`.
- **A3:** Employers cannot submit a job if their employer status is not approved, if their company is inactive, or if required job fields (title, description, category, employment type, salary where required, etc.) are missing.
- **A4:** Closing a job preserves all associated records (applications remain intact; restrict-on-delete and historical retention apply).
- **A5:** Admin moderation requires the `admin.access` permission and a dedicated admin permission (e.g., `jobs.moderate` or reusing admin access; let's establish `jobs.moderate` or `admin.access` combined with admin role).

### Open Questions

- **Q1:** Should admin moderation have a dedicated permission slug (e.g., `jobs.moderate`) added to `AuthorizationSeeder`? _(Decision: Yes, add `jobs.moderate` to `PermissionSlug` and `AuthorizationSeeder`, assigned by default to `platform_admin`)._
- **Q2:** Should closing a job allow reopening directly to published or require re-review? _(Decision: Reopening/resubmitting a closed job goes to `pending_review` for safety)._

---

## User flows and acceptance criteria

### F1 — Employer Submits Job for Review

An approved employer with an active company views their job draft (or rejected job) and clicks "Submit for Review".

- **Acceptance criteria:**
    - Job status changes from `draft` (or `rejected`) to `pending_review`.
    - Server validates that the employer is approved, company is active, and all required job fields are present.
    - Rejection reason (if any) is cleared upon resubmission.
    - Unauthorized users or owners of suspended employers/companies receive 403.

### F2 — Admin Moderation Queue and Review

An authorized platform admin (`platform_admin` role / `jobs.moderate` permission) accesses the admin job moderation queue (`/admin/jobs`).

- **Acceptance criteria:**
    - Paginated list of jobs pending review (filterable by status: `pending_review`, `published`, `rejected`, `closed`).
    - Admin views full job details.
    - Admin approves a `pending_review` job → status becomes `published`.
    - Admin rejects a `pending_review` job → status becomes `rejected`, and a non-empty `rejection_reason` is required and saved.
    - Action is authorized and logged.

### F3 — Employer Corrects and Resubmits Rejected Job

An employer whose job was rejected views the rejection reason, edits the draft to correct issues, and resubmits.

- **Acceptance criteria:**
    - Employer sees the `rejection_reason` on their job dashboard/edit view.
    - Editing a rejected job keeps it as `draft` or `rejected` until the employer explicitly clicks "Submit for Review".
    - Resubmission transitions status back to `pending_review` and clears the rejection reason.

### F4 — Employer or Admin Closes a Published Job

An authorized employer (or admin) closes a published job.

- **Acceptance criteria:**
    - Job status transitions from `published` to `closed`.
    - Closed jobs are no longer active/publicly searchable.
    - Existing applications are preserved.

---

## Routes, middleware, and authorization

### Employer Routes (prefix: `/employer/jobs`, middleware: `auth`, `role:employer`, `employer.approved`)

- `POST /employer/jobs/{job}/submit` — `employer.jobs.submit` (submit draft/rejected job for review)
- `POST /employer/jobs/{job}/close` — `employer.jobs.close` (close published job)

### Admin Routes (prefix: `/admin/jobs`, middleware: `auth`, `role:platform_admin`, `can:jobs.moderate`)

- `GET /admin/jobs` — `admin.jobs.index` (moderation queue / list with status filter)
- `GET /admin/jobs/{job}` — `admin.jobs.show` (admin job review detail)
- `POST /admin/jobs/{job}/approve` — `admin.jobs.approve` (approve job → published)
- `POST /admin/jobs/{job}/reject` — `admin.jobs.reject` (reject job with reason)
- `POST /admin/jobs/{job}/close` — `admin.jobs.close` (admin close job)

---

## Policy actions

### `JobPolicy` updates:

- `submit(User $user, Job $job): bool` — true if employer is approved, owns the job's company, and status is in `['draft', 'rejected', 'closed']`.
- `close(User $user, Job $job): bool` — true if (employer owns the job's company AND status is `published`) OR user has `admin.access`/`jobs.moderate`.
- `moderate(User $user, Job $job): bool` — true if user has `jobs.moderate` permission.

---

## Form Requests and validation

- `SubmitJobRequest` / `StoreJobSubmissionRequest`: empty or validates submission eligibility.
- `RejectJobRequest`: `rejection_reason` required, string, max:1000.
- `UpdateJobRequest` modification: If job status is `published`, updating its content automatically resets status to `draft` (or triggers re-review).

---

## Data model and migration

New migration `2026_11_02_000010_add_moderation_fields_to_job_postings_table.php`:

- `rejection_reason` (text, nullable)
- Add indexes on `status` and `company_id`.

Permission seeder update:

- Add `jobs.moderate` to `PermissionSlug` and `AuthorizationSeeder` (assigned to `platform_admin`).

---

## Test matrix and regression

- `tests/Feature/Employer/JobSubmissionTest.php`:
    - Employer successfully submits draft to `pending_review`.
    - Employer resubmits rejected job.
    - Employer cannot submit with missing fields or if suspended/unapproved.
    - Employer closes published job.
    - Editing published job resets status to `draft`.
- `tests/Feature/Admin/JobModerationTest.php`:
    - Admin views moderation queue.
    - Admin approves job (`pending_review` → `published`).
    - Admin rejects job with reason (`pending_review` → `rejected`).
    - Non-admin denied moderation access.

---

## Approval gate

Status: **PROPOSED**. Stop here; do not implement code or migrations until the user explicitly approves this specification.
