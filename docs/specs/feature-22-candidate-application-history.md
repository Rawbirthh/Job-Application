---
Feature ID: 22
Title: Candidate application history and status view
Status: APPROVED
Type: Feature Implementation
Source: project-overview.md (Phase 3 — Public discovery and candidate profile)
Dependencies: Feature 21 (Submit a job application)
Created: 2026-11-06
Updated: 2026-11-06
---

# Feature 22 Specification: Candidate application history and status view

## Objective

Provide job seekers with a paginated overview of their submitted job applications and allow them to view the status and details of individual applications, ensuring they can track their recruitment progress.

## Goals & Non-Goals

### Goals

- Allow candidates to view a paginated list of all their submitted applications.
- Show key information in the list: job title, company name, submission date, and current application status.
- Provide a detail view for an application, showing additional information (like cover letter used, if applicable) and the current status.
- Ensure candidates can only view their own applications.
- Provide links from the application list/detail back to the relevant public job posting (if still available).

### Non-Goals

- Employer management of applicants (Feature 23).
- Application status history timeline (Feature 24).
- In-app notifications for status changes (Feature 25).
- Interview scheduling (Feature 26).

## Dependencies Verified

- **Feature 21**: The `job_applications` table exists, `JobApplication` model is in place, and `JobApplicationPolicy` is configured.

## Routes & Access Boundary

Add to `routes/web.php` under the `candidate` prefix group:

```php
Route::middleware(['auth', 'role:job_seeker'])->group(function (): void {
    // ... existing routes
    Route::get('applications', [ApplicationHistoryController::class, 'index'])->name('applications.index');
    Route::get('applications/{application}', [ApplicationHistoryController::class, 'show'])->name('applications.show');
});
```

## Policy Actions & Authorization

Verify `App\Policies\JobApplicationPolicy.php`:

- `viewAny(User $actor)`: `return $actor->hasRole(RoleSlug::JobSeeker);` (Existing)
- `view(User $actor, JobApplication $application)`: `return $actor->is($application->user);` (Existing)

## Controllers & Query Shape

`App\Http\Controllers\Candidate\ApplicationHistoryController`:

- `index()`:
    - `JobApplication::where('user_id', auth()->id())->with(['jobPosting.company'])->latest()->paginate(10);`
    - Render Inertia page `candidate/applications/index`.
- `show(JobApplication $application)`:
    - Authorize view.
    - Load `jobPosting` and `jobPosting.company`.
    - Render Inertia page `candidate/applications/show`.

## React / Inertia UI

- Page: `resources/js/pages/candidate/applications/index.tsx`
    - Paginated table/list showing: Job Title, Company, Date Applied, Status.
- Page: `resources/js/pages/candidate/applications/show.tsx`
    - Details of the application, including the cover letter, status, and job posting details.

## Tests

`tests/Feature/Candidate/ApplicationHistoryTest.php`:

- Candidate can view their application history.
- Candidate can view details of a specific application.
- Candidate cannot view another candidate's application.
- List is paginated and scoped to the user.
- Applications for closed/deleted jobs handle gracefully.

## Verification Commands

- `php artisan test --filter ApplicationHistoryTest`
- `php artisan test`
- `npm run build`
