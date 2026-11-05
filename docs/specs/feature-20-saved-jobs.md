---
Feature ID: 20
Title: Saved jobs
Status: IMPLEMENTED
Type: Feature Implementation
Source: project-overview.md (Phase 3 — Public discovery and candidate profile)
Dependencies: Feature 3 (Custom roles/permissions), Feature 16 (Public job listing)
Created: 2026-11-05
Updated: 2026-11-05
---

# Feature 20 Specification: Saved jobs

## Objective

Allow authenticated job seekers to bookmark published jobs, view their saved jobs in a private, paginated list, and remove (unsave) jobs from their bookmarks.

## Goals & Non-Goals

### Goals

- Provide a secure bookmarking mechanism for users with the `job_seeker` role to save and unsave published job listings.
- Ensure unique user-job relationships (preventing duplicate saves for the same job by the same candidate at the database and application levels).
- Provide a private, server-paginated listing of saved jobs at `/candidate/saved-jobs` with essential job summary details (title, company name, workplace arrangement, employment type, location, salary summary, and saved date).
- Provide save/unsave interaction buttons on public job views or candidate dashboards.
- Safely handle closed or deleted jobs in saved history (retain the record in history or indicate closed status gracefully without breaking the listing).

### Non-Goals

- Email notifications or alerts for saved jobs (handled later in Feature 29).
- Folders, tags, or custom organization of saved jobs.
- Public sharing of saved job lists.

## Dependencies Verified

- **Feature 3**: Custom roles/permissions, `role:job_seeker` middleware, Policies.
- **Feature 16**: Public job listing and `JobPosting` model with published scopes and relationships.

**Existing conventions inspected**:

- Candidate routes group in `routes/web.php` under `middleware(['auth', 'role:job_seeker'])`.
- Form Requests validate write inputs (`authorize()` returning `true`, controller calling Policy authorization).
- Inertia pages render React components with typed props.
- Toast notifications via `Inertia::flash('toast', ...)`.

## Assumptions (labelled)

- **A1**: Saved jobs are stored in a dedicated `saved_jobs` pivot table connecting `users` (job seekers) and `job_postings`.
- **A2**: Only users with the `job_seeker` role can save jobs. Employers and administrators do not need saved job bookmarks.
- **A3**: When a job is closed by an employer, it remains in the candidate's saved history but displays a clear "Closed" badge or status indicator.
- **A4**: Duplicate save requests (e.g., rapid double-clicks) return a success response idempotently without throwing database unique constraint violation errors to the user.

## Routes & Access Boundary

Add to the existing candidate group in `routes/web.php`:

```php
Route::prefix('candidate')->name('candidate.')->middleware(['role:job_seeker'])->group(function (): void {
    // ... existing candidate routes ...
    Route::get('saved-jobs', [SavedJobController::class, 'index'])->name('saved-jobs.index');
    Route::post('saved-jobs', [SavedJobController::class, 'store'])
        ->middleware(['throttle:30,1'])
        ->name('saved-jobs.store');
    Route::delete('saved-jobs/{jobPosting}', [SavedJobController::class, 'destroy'])
        ->name('saved-jobs.destroy');
});
```

- **Middleware**: `auth`, `role:job_seeker`.
- **Named routes**: `candidate.saved-jobs.index`, `candidate.saved-jobs.store`, `candidate.saved-jobs.destroy`.

## Policy Actions & Dynamic Role/Permission Resolution

New `app/Policies/SavedJobPolicy.php`:

- `viewAny(User $actor): bool` → `$actor->hasRole('job_seeker')`.
- `create(User $actor): bool` → `$actor->hasRole('job_seeker')`.
- `delete(User $actor, SavedJob $savedJob): bool` → `$actor->is($savedJob->user);` (owner only).

## Database Schema & Migrations

New migration `database/migrations/2026_11_05_000000_create_saved_jobs_table.php`:

- Table name: `saved_jobs`
- Columns:
    - `id` (bigint, primary)
    - `user_id` (bigint, foreign key referencing `users.id`, onDelete cascade)
    - `job_posting_id` (bigint, foreign key referencing `job_postings.id`, onDelete cascade)
    - `timestamps()`
- Indexes:
    - Unique composite index on `['user_id', 'job_posting_id']` to prevent duplicates.
    - Index on `user_id`.

## Form Requests & Validation

- `App\Http\Requests\Candidate\SaveJobRequest`:
    - `authorize()`: returns `true` (policy checked in controller or via `$this->authorize('create', SavedJob::class)`).
    - `rules()`:
        - `job_posting_id` => `['required', 'integer', 'exists:job_postings,id']`.
    - Custom validation or query check: verify that the target job is in a valid visible/published state (or allow saving any existing job posting that isn't deleted).

## Controllers, Services & Query Shape

- `App\Http\Controllers\Candidate\SavedJobController`:
    - `index()`: Paginate saved jobs for authenticated user. Eager load `jobPosting.company`, `jobPosting.category`, `jobPosting.employmentType`, `jobPosting.workplaceArrangement`. Select required columns to avoid N+1 and unbounded data.
    - `store(SaveJobRequest $request)`: First check if already saved (idempotent firstOrNew / firstOrCreate), create if not. Return redirect with success toast.
    - `destroy(JobPosting $jobPosting)`: Find saved record for authenticated user and given job posting, delete it. Return redirect with success toast.

## React / Inertia UI

- Page: `resources/js/pages/candidate/saved-jobs/index.tsx`
    - Uses candidate layout (`resources/js/layouts/candidate-layout.tsx` or similar app layout).
    - Displays paginated list of saved jobs with card/table layout.
    - Shows job title, company name, location, salary range, employment type, workplace arrangement, saved date, and status ("Published" vs "Closed").
    - Action buttons: View job detail link (`/jobs/{job}`), Unsave button (DELETE request via Inertia router).
- Public job listing / detail pages:
    - Add a "Save" / "Saved" bookmark toggle button for authenticated job seekers.

## Tests

- `tests/Feature/Candidate/SavedJobTest.php`:
    - Candidate can save a published job.
    - Duplicate save requests do not create duplicate rows (idempotent).
    - Candidate can remove a saved job (unsave).
    - Unauthenticated users and employers cannot access or modify saved jobs (403/redirect).
    - Saved jobs list is paginated and scoped strictly to the authenticated candidate.
    - Closed jobs display correctly in the saved list.

## Verification Commands

- `php artisan test --filter SavedJobTest`
- `php artisan test`
