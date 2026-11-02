# Feature 15 — Employer Job Dashboard and Listing Filters

**Metadata:**

- Feature ID: 15
- Title: Employer job dashboard and listing filters
- Spec Type: Feature specification
- Source: `project-overview.md` → “Feature 15 — Employer job dashboard and listing filters”
- Dependencies: Feature 13 (Employer job draft CRUD, implemented), Feature 14 (Job submission, moderation, and closure, implemented)
- Downstream dependents: Feature 23 (Employer applicant list and application detail), Feature 27 (Job-specific screening questions)
- Status: **APPROVED**
- Created: 2026-11-02
- Slug: `feature-15-employer-job-dashboard-and-listing-filters`

---

## Goal

Provide approved employers with a dedicated, paginated, and filterable dashboard (`employer/jobs`) to manage the complete lifecycle of job postings belonging to their authorized companies. Enable searching by title, filtering by company and status, viewing key metadata (created, updated, published dates, and application counts where available), and accessing quick action links (edit, preview, submit for review, close) contingent upon current job status and server-side authorization.

## Non-goals

- Public job indexing and candidate discovery (Feature 16).
- Candidate application submission and history (Features 21–22).
- Recruiter applicant review and candidate pipeline management (Feature 23).
- Platform-wide admin moderation queues (Feature 14).

## Current codebase evidence

- Feature 13 & 14 established:
    - `Job` model with `forOwner()` scoping, relations (`company`, `category`, `employmentType`, `applications`), and lifecycle states (`draft`, `pending_review`, `published`, `rejected`, `closed`).
    - `JobController@index` in `app/Http/Controllers/Employer/JobController.php` with company selection, status filtering, pagination, and `jobPayload` formatting.
    - `resources/js/pages/employer/jobs/index.tsx` rendering the employer job index with company/status filters, pagination, status badges, and action buttons.
    - `JobPolicy` enforcing strict owner-company scoping.

## Assumptions and open questions

### Assumptions

- **A1:** Employers can manage jobs across multiple owned companies (if they own more than one company profile). The dashboard provides a company selector ("All companies" or a specific company) and a status filter (`all`, `draft`, `pending_review`, `published`, `rejected`, `closed`).
- **A2:** Application counts for each job are eager-loaded/counted efficiently in SQL (`withCount('applications')`) without N+1 queries.
- **A3:** Action buttons dynamically adapt to job status (e.g., Edit/Submit for Review for drafts/rejected jobs; View/Close for published jobs; Edit/Resubmit for rejected jobs).
- **A4:** All queries are strictly scoped via `Job::forOwner($user)` and validated against company ownership.

### Open Questions

- **Q1:** Should search by job title be added to the employer job index filters alongside company and status? _(Decision: Yes, include a title search input to fully satisfy the requirement: "search by title; filter by status/category")._

---

## User flows and acceptance criteria

### F1 — Employer Views and Filters Company Jobs

An approved employer navigates to the employer job dashboard (`/employer/jobs`).

- **Acceptance criteria:**
    - The table displays jobs belonging only to companies owned by the authenticated user.
    - Filters for company, status, and title search are server-driven, preserve query strings across pagination, and update via Inertia router.
    - Application counts are correctly displayed per job without N+1 queries.
    - Empty states are shown clearly when no jobs match the criteria.

### F2 — Contextual Job Actions

- **Acceptance criteria:**
    - Action buttons reflect permitted transitions based on job status:
        - `draft`: Edit, Submit for Review, Delete.
        - `pending_review`: View/Preview.
        - `published`: View, Close.
        - `rejected`: Edit (with rejection reason banner visible), Resubmit for Review.
        - `closed`: View, Re-submit for Review.

---

## Technical design & specifications

### 1. Routes & Controller

- **Route:** `GET /employer/jobs` (`employer.jobs.index`) handled by `App\Http\Controllers\Employer\JobController@index`.
- **Query Parameters:** `company_id`, `status`, `search`.
- **Scoping:** `Job::query()->forOwner($user)->with([...])->withCount('applications')...`

### 2. Frontend Component

- **Page:** `resources/js/pages/employer/jobs/index.tsx`.
- **Props:** `jobs` (paginated list with `id`, `title`, `status`, `rejection_reason`, `created_at`, `updated_at`, `published_at`, `applications_count`, `company`, `category`, `employment_type`), `companies`, `filters`.

### 3. Verification Plan

- Pest feature tests in `tests/Feature/Employer/JobDashboardTest.php` (or extending `JobDraftTest.php` / `JobSubmissionTest.php`) verifying:
    - Company scoping (employers cannot see other employers' jobs).
    - Status and title filtering.
    - Application counts and date formatting.
    - Response structure and Inertia props.
