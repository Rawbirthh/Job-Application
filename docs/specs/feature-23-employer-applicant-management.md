# Feature 23 — Employer applicant list and application detail

## Metadata

- **Feature ID**: Feature 23
- **Title**: Employer applicant list and application detail
- **Spec Type**: New Feature
- **Source File**: `project-overview.md` (Feature 23)
- **Dependencies**: Feature 21 (Submit Application), Feature 12 (Company Profile), Feature 14 (Job Moderation/Publication)
- **Status**: IMPLEMENTED — completed and verified on 2026-11-07
- **Created Date**: 2026-11-07

---

## Goal

Allow authorized employers to review candidates who have applied to their jobs. This includes a listing of all applicants for a specific job and a detailed view of an individual application, including the candidate's professional profile and secure resume access.

## Non-Goals

- Changing application statuses (Feature 24).
- Internal recruiter notes (Feature 24).
- In-app notifications (Feature 25).
- Interview scheduling (Feature 26).

---

## Actors and Flows

### Employer

1. **View Applicants List**: From the Employer Job Dashboard, the employer clicks "View Applicants" for a job. They see a paginated list of all candidates who applied.
2. **Review Application Detail**: The employer clicks on a specific applicant to see their full profile (summary, education, experience, skills), cover letter, and a link to download their resume.
3. **Secure Resume Download**: The employer clicks "Download Resume" to retrieve the private file.

---

## User-Visible Behavior and Acceptance Criteria

- [ ] A "View Applicants" link is available for each job in the Employer Job Dashboard (`/employer/jobs`).
- [ ] The applicant list for a job (`/employer/jobs/{job}/applicants`) is paginated (10 per page).
- [ ] The list shows: Candidate Name, Application Date, and Current Status.
- [ ] The application detail page (`/employer/applications/{application}`) displays:
    - Candidate summary/headline.
    - Full list of Skills.
    - Professional Experience (ordered by date).
    - Education History (ordered by date).
    - Cover Letter (if provided).
    - Action to download the resume.
- [ ] Authorization:
    - Only the owner of the company that posted the job can see the applicants and their details.
    - Attempting to view applicants for another company's job returns a 403.
    - Attempting to view an application for another company's job returns a 403.
- [ ] Empty states: Clear messaging when a job has zero applications.

---

## Existing Code and Conventions Inspected

### Paths

- **Models**: `App\Models\JobApplication`, `App\Models\Job`, `App\Models\CandidateProfile`.
- **Controllers**: `App\Http\Controllers\Employer\JobController` (for dashboard link), `App\Http\Controllers\Candidate\ApplicationHistoryController` (reference for profile display).
- **Policies**: `App\Policies\JobApplicationPolicy`.
- **Routes**: `routes/web.php` (employer prefix).

### Conventions

- Use `Inertia::render` with typed props.
- Secure private storage via `Storage::disk('local')`.
- Eager load relationships in controllers to avoid N+1.
- Use Form Requests for any future status changes (though not in this feature's scope).

---

## Technical Design

### Routes (`routes/web.php`)

```php
Route::middleware(['auth', 'role:employer', 'employer.approved'])->prefix('employer')->name('employer.')->group(function () {
    // ... existing routes
    Route::get('jobs/{job}/applicants', [EmployerJobApplicationController::class, 'index'])
        ->name('jobs.applicants');
    Route::get('applications/{application}', [EmployerJobApplicationController::class, 'show'])
        ->name('applications.show');
});
```

### Models & Relationships

- `Job` hasMany `JobApplication`.
- `JobApplication` belongsTo `User` (as applicant).
- `User` hasOne `CandidateProfile`.
- `CandidateProfile` hasMany `CandidateExperience`, `CandidateEducation`.

### Authorization (`JobApplicationPolicy`)

Add the following methods:

- `viewAnyForJob(User $user, Job $job)`: Checks if user owns the job's company.
- `viewAsEmployer(User $user, JobApplication $application)`: Checks if user owns the company that the application's job belongs to.

### Controller (`EmployerJobApplicationController`)

- **index**:
    - `authorize('viewAnyForJob', [JobApplication::class, $job])`
    - Load applications with `user:id,name` and `user.candidateProfile`.
    - Paginate.
- **show**:
    - `authorize('viewAsEmployer', $application)`
    - Load full applicant profile: `user.candidateProfile`, `user.candidateProfile.experience`, `user.candidateProfile.education`, `candidateResume`.
    - Note: Reuse the existing secure resume download route or ensure the policy allows employer access.

---

## UI/UX Design

### Applicant List (`employer/jobs/applicants/index`)

- Title: "Applicants for [Job Title]"
- Table with columns: Name, Applied, Status, Actions.
- Empty state: "No applications yet."

### Application Detail (`employer/applications/show`)

- Sidebar layout:
    - Left/Main: Profile Summary, Skills, Experience, Education.
    - Right/Side: Application Metadata (Status, Date), Cover Letter, Resume Download button.
- Back button to the applicant list.

---

## Tests

- `tests/Feature/Employer/JobApplicationTest.php`
- `test_employer_can_view_applicants_for_their_job`
- `test_employer_cannot_view_applicants_for_another_companys_job`
- `test_employer_can_view_application_detail`
- `test_employer_cannot_view_application_detail_for_another_companys_job`
- `test_unauthorized_users_cannot_access_employer_application_routes`

---

## Risks, Dependencies, and Assumptions

- **Dependency**: Feature 21 must be stable (it is).
- **Privacy**: We are assuming the Employer is authorized to see the full candidate profile once an application is submitted.
- **Resume Access**: Must ensure the existing `CandidateResumePolicy` or a new check allows the authorized employer to download the file.

---

## Implementation Steps

1. **Spec Approval**: Stop for user approval.
2. **Policy**: Update `JobApplicationPolicy` with employer checks.
3. **Controller**: Create `EmployerJobApplicationController`.
4. **Routes**: Add routes to `web.php`.
5. **Dashboard Link**: Add "View Applicants" link to `employer/jobs/index.tsx`.
6. **Views**: Create `index.tsx` and `show.tsx` for employer applications.
7. **Verification**: Run tests and verify manually.
