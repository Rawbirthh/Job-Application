---
Feature ID: 21
Title: Submit a job application
Status: APPROVED
Type: Feature Implementation
Source: project-overview.md (Phase 3 — Public discovery and candidate profile)
Dependencies: Feature 3 (Custom roles/permissions), Feature 17 (Public job detail page), Feature 18 (Candidate profile management), Feature 19 (Private resume upload and management)
Created: 2026-11-05
Updated: 2026-11-05
---

# Feature 21 Specification: Submit a job application

## Objective

Allow eligible job seekers to apply to published, active job postings by submitting an application form containing their selected candidate resume and an optional cover letter, enforcing duplicate prevention, status/deadline validation, and server-side authorization.

## Goals & Non-Goals

### Goals

- Provide a secure application submission flow accessible from the public job detail page (`/jobs/{job}`).
- Validate on the server that the target job is published and not past its application deadline.
- Ensure the candidate has an eligible profile and at least one uploaded resume (or allow selecting from their uploaded resumes).
- Enforce a unique constraint and database-level protection against duplicate applications for the same job by the same candidate.
- Persist the application record (`job_applications`) with the applicant user ID, job posting ID, resume ID, optional cover letter, initial status (`submitted`), and submission timestamp.
- Prevent ID forgery: candidate ID, company ID, job status, and application status are strictly determined server-side and never trusted from client input.
- Provide clear validation feedback, loading states, and success confirmation (with redirection to candidate application history or success message).

### Non-Goals

- Employer applicant management and review (Feature 23).
- Application status transitions and history pipeline (Feature 24).
- In-app notifications for application submission (Feature 25, though hooks can be added or integrated when Feature 25 is built).
- Interview scheduling (Feature 26).
- Job-specific screening questions (Feature 27).

## Dependencies Verified

- **Feature 3**: Custom roles, permissions, middleware (`role:job_seeker`).
- **Feature 17**: Public job detail page (`/jobs/{job}`).
- **Feature 18**: Candidate profile management (`candidate_profiles`).
- **Feature 19**: Private resume upload and management (`candidate_resumes`).

**Existing conventions inspected**:

- Controllers are thin; Form Requests handle validation; Policies enforce resource authorization.
- Inertia pages render React components with typed props and toast notifications.
- Routes use explicit middleware grouping (`auth`, `role:job_seeker`).

## Assumptions & Business Rules (labelled)

- **A1**: Applications are stored in a new `job_applications` table.
- **A2**: A candidate must have the `job_seeker` role and an uploaded resume to submit an application. If multiple resumes exist, the candidate selects which resume to attach; if only one exists, it defaults or is selected.
- **A3**: An application status begins at `submitted` (matching the planned application lifecycle in `project-overview.md`: `submitted → reviewing → shortlisted → ...`).
- **A4**: Duplicate applications are prevented via a unique composite index on `['user_id', 'job_posting_id']` and a Form Request validation rule checking existing submissions.
- **A5**: Closed, draft, or expired jobs (past `application_deadline`) cannot receive new applications.

## Routes & Access Boundary

Add to `routes/web.php` under the candidate or public/candidate group:

```php
Route::middleware(['auth', 'role:job_seeker'])->group(function (): void {
    Route::get('jobs/{job}/apply', [JobApplicationController::class, 'create'])->name('candidate.applications.create');
    Route::post('jobs/{job}/apply', [JobApplicationController::class, 'store'])
        ->middleware(['throttle:20,1'])
        ->name('candidate.applications.store');
});
```

- **Middleware**: `auth`, `role:job_seeker`.
- **Named routes**: `candidate.applications.create`, `candidate.applications.store`.

## Policy Actions & Authorization

New `app/Policies/JobApplicationPolicy.php`:

- `viewAny(User $actor): bool` → `$actor->hasRole('job_seeker')`.
- `create(User $actor): bool` → `$actor->hasRole('job_seeker')`.
- `view(User $actor, JobApplication $application): bool` → `$actor->is($actor->id === $application->user_id)`.

## Database Schema & Migration

New migration `database_applications_table` (e.g. `2026_11_05_120000_create_job_applications_table.php`):

- Table name: `job_applications`
- Columns:
    - `id` (bigint, primary)
    - `job_posting_id` (bigint, foreign key referencing `job_postings.id`, onDelete cascade)
    - `user_id` (bigint, foreign key referencing `users.id`, onDelete cascade) -- candidate user ID
    - `candidate_resume_id` (bigint, foreign key referencing `candidate_resumes.id`, onDelete cascade)
    - `cover_letter` (text, nullable)
    - `status` (string, default `'submitted'`)
    - `timestamps()`
- Indexes:
    - Unique composite index on `['user_id', 'job_posting_id']`.
    - Index on `job_posting_id`.
    - Index on `user_id`.

## Form Requests & Validation

`App\Http\Requests\Candidate\StoreJobApplicationRequest`:

- `authorize()`: returns `true` (policy/role middleware enforced).
- `rules()`:
    - `candidate_resume_id` => `['required', 'integer', 'exists:candidate_resumes,id']` (must belong to authenticated user).
    - `cover_letter` => `['nullable', 'string', 'max:5000']`.
- Custom validation closures:
    - Verify target job exists and status is `published`.
    - Verify target job `application_deadline` is either null or not in the past (`>= today`).
    - Verify candidate has not already applied to this job (unique check).
    - Verify the selected resume belongs to the authenticated user.

## Controllers & Query Shape

`App\Http\Controllers\Candidate\JobApplicationController`:

- `create(JobPosting $job)`:
    - Authorize create.
    - Check if job is published and not expired, and candidate has not already applied.
    - Load candidate resumes (`user->candidateResumes` or `CandidateResume::where('user_id', user->id)->get()`) and candidate profile.
    - Render Inertia page `candidate/applications/create` (or modal/page data).
- `store(StoreJobApplicationRequest $request, JobPosting $job)`:
    - Authorize create.
    - DB transaction: create `JobApplication` with validated resume ID, cover letter, authenticated user ID (`user_id`), and job posting ID.
    - Flash success toast and redirect to candidate applications or job page.

## React / Inertia UI

- Page: `resources/js/pages/candidate/applications/create.tsx` (or integrated into job detail / application form component).
- Form fields:
    - Resume selector (dropdown or radio group of candidate's uploaded resumes, with a link to upload a new resume if none exist).
    - Cover letter textarea (optional).
    - Submit button with loading/processing state and visible validation errors.
- Public Job Detail page (`resources/js/pages/jobs/show.tsx`):
    - Show "Apply Now" button for authenticated job seekers (or "Already Applied" if an application exists, or "Job Closed").

## Tests

`tests/Feature/Candidate/JobApplicationTest.php`:

- Job seeker can view application form for a published job.
- Job seeker can successfully submit an application with a valid resume and optional cover letter.
- Cannot apply to a closed, draft, or expired job.
- Duplicate applications are prevented (unique constraint / validation).
- Cannot apply without an eligible resume or using another user's resume.
- Guests and employers cannot access application form or submit applications.
- Candidate identity and job association cannot be forged via request input.

## Verification Commands

- `php artisan test --filter JobApplicationTest`
- `php artisan test`
- `npm run build`
