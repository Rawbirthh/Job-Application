---
name: feature-24-application-status-transitions-and-history
description: Specification for Feature 24 — Application status transitions and history
metadata:
  type: project
  status: APPROVED
  created: 2026-11-07
  updated: 2026-11-07
---

# Feature 24 — Application status transitions and history

## Metadata

- **Feature ID**: Feature 24
- **Title**: Application status transitions and history
- **Spec Type**: New Feature
- **Source File**: `project-overview.md` (Feature 24)
- **Dependencies**: Feature 23 (Employer applicant list and application detail), Feature 22 (Candidate application history)
- **Status**: APPROVED
- **Created Date**: 2026-11-07
- **Updated Date**: 2026-11-07

---

## Goal

Provide authorized recruiters with the ability to advance or reject applications through a strictly controlled hiring pipeline, record a tamper-proof status history timeline, handle concurrent updates safely, and reflect updated statuses and history to candidates with candidate withdrawal capabilities.

## Non-Goals

- Arbitrary status jumps outside the agreed transition matrix.
- In-app notification creation/dispatch (Feature 25).
- Interview scheduling and calendar management (Feature 26).
- Automated AI screening or candidate ranking.
- Real-time WebSocket broadcasting (Reverb deferred to keep launch infrastructure simple; updates occur via standard Inertia form submissions and page reloads).

---

## Actors and Flows

### 1. Recruiter / Approved Employer
- Navigates to `/employer/applications/{application}`.
- Views the current application status, allowed next actions, and the status history timeline.
- Selects a valid next status from allowed transitions (e.g., from `submitted` to `reviewing` or `rejected`).
- Optionally provides a reason (up to 500 characters).
- Submits the transition via `POST /employer/applications/{application}/status`.
- If another user modified the application concurrently, receives a 409 Conflict error with instructions to refresh.
- On success, the application status updates, a new `job_application_status_histories` entry is created, and the updated detail view renders with a flash success message.

### 2. Candidate (Job Seeker)
- Navigates to `/candidate/applications/{application}`.
- Views the updated status badge and visible status history timeline (date, status change, and optional reason).
- For any non-terminal application (`submitted`, `reviewing`, `shortlisted`, `interview_scheduled`, `offer_sent`), candidate can click "Withdraw Application".
- Enters an optional withdrawal reason and confirms.
- Submits via `POST /candidate/applications/{application}/withdraw`.
- Application status transitions to `withdrawn`, recording the candidate as the actor in `job_application_status_histories`.

---

## Transition Matrix & Business Rules

### Defined Statuses
- `submitted` (Initial state)
- `reviewing`
- `shortlisted`
- `interview_scheduled`
- `offer_sent`
- `hired` (Terminal state)
- `rejected` (Terminal state)
- `withdrawn` (Terminal state)

### Allowed Employer Transitions
- `submitted` → `reviewing`, `rejected`
- `reviewing` → `shortlisted`, `rejected`
- `shortlisted` → `interview_scheduled`, `rejected`
- `interview_scheduled` → `offer_sent`, `rejected`
- `offer_sent` → `hired`, `rejected`
- `hired` → None (terminal)
- `rejected` → None (terminal)
- `withdrawn` → None (terminal)

### Allowed Candidate Transitions
- Any non-terminal state (`submitted`, `reviewing`, `shortlisted`, `interview_scheduled`, `offer_sent`) → `withdrawn`
- Terminal states (`hired`, `rejected`, `withdrawn`) → None (cannot withdraw already finalized applications)

### Concurrency & Stale Update Protection
- The employer transition request includes `current_status`.
- The update operation acquires a pessimistic row lock (`lockForUpdate()`) within a database transaction.
- If `$application->status !== $request->current_status`, the transaction aborts with HTTP 409 Conflict ("The application status has changed since you loaded the page. Please refresh and try again.").

---

## User-Visible Behavior and Acceptance Criteria

- [ ] **Authorized Employer Action**: Only approved employers owning the job's company can transition applications for that job. Unrelated employers receive 403 Forbidden.
- [ ] **Candidate Withdrawal**: The applying candidate can withdraw their own non-terminal application. Other candidates receive 403 Forbidden.
- [ ] **Controlled Transitions**: Attempting any transition outside the matrix (e.g. `submitted` → `hired`, or transitioning from `hired`/`rejected`/`withdrawn`) returns 422 Unprocessable Entity.
- [ ] **Status History Persistence**: Every transition creates an immutable record in `job_application_status_histories` with:
  - `job_application_id`
  - `actor_id` (the authenticated user)
  - `from_status`
  - `to_status`
  - `reason` (nullable, sanitized string <= 500 chars)
  - `created_at` timestamp
- [ ] **Concurrency**: Sending a transition with a stale `current_status` returns 409 Conflict.
- [ ] **Employer UI**: `/employer/applications/{application}` displays:
  - Current status badge.
  - Transition form with dropdown populated only with valid next transitions.
  - Optional reason input.
  - Chronological status history timeline showing actor name, transition, date, and reason.
- [ ] **Candidate UI**: `/candidate/applications/{application}` displays:
  - Current status badge.
  - Status history timeline.
  - "Withdraw Application" action if status is non-terminal.
  - Once withdrawn, displays terminal "Withdrawn" status.
- [ ] **Index View Accuracy**: Both `/employer/jobs/{job}/applicants` and `/candidate/applications` reflect the latest status accurately.

---

## Technical Design

### Database Migration

Table: `job_application_status_histories`
```php
Schema::create('job_application_status_histories', function (Blueprint $table): void {
    $table->id();
    $table->foreignId('job_application_id')->constrained('job_applications')->cascadeOnDelete();
    $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
    $table->string('from_status');
    $table->string('to_status');
    $table->text('reason')->nullable();
    $table->timestamps();

    $table->index(['job_application_id', 'created_at']);
});
```

### Enum: `App\Enums\JobApplicationStatus`
Cases:
- `Submitted = 'submitted'`
- `Reviewing = 'reviewing'`
- `Shortlisted = 'shortlisted'`
- `InterviewScheduled = 'interview_scheduled'`
- `OfferSent = 'offer_sent'`
- `Hired = 'hired'`
- `Rejected = 'rejected'`
- `Withdrawn = 'withdrawn'`

Methods:
- `label(): string`
- `allowedEmployerTransitions(): array`
- `canTransitionTo(JobApplicationStatus $target, bool $isCandidate = false): bool`
- `isTerminal(): bool`

### Model: `App\Models\JobApplicationStatusHistory`
- Fillable: `job_application_id`, `actor_id`, `from_status`, `to_status`, `reason`
- Relationships:
  - `belongsTo(JobApplication::class, 'job_application_id')`
  - `belongsTo(User::class, 'actor_id')`

### Model: `App\Models\JobApplication`
- Casts: `status` => `JobApplicationStatus::class`
- Relationship: `hasMany(JobApplicationStatusHistory::class)->orderBy('created_at', 'asc')`

### Service: `App\Services\ApplicationStatusTransitionService`
Handles the transactional state transition:
```php
public function transitionAsEmployer(
    JobApplication $application,
    JobApplicationStatus $newStatus,
    string $expectedCurrentStatus,
    User $actor,
    ?string $reason = null
): JobApplication;

public function withdrawAsCandidate(
    JobApplication $application,
    User $candidate,
    ?string $reason = null
): JobApplication;
```

### Authorization (`JobApplicationPolicy`)
Add:
- `transitionStatus(User $actor, JobApplication $application)`:
  `$actor->isApprovedEmployer() && $application->jobPosting->company !== null && $application->jobPosting->company->owner_user_id === $actor->id`
- `withdraw(User $actor, JobApplication $application)`:
  `$actor->is($application->user)`

### Form Requests
1. `App\Http\Requests\Employer\TransitionApplicationStatusRequest`:
   - `current_status`: `['required', 'string', Rule::enum(JobApplicationStatus::class)]`
   - `new_status`: `['required', 'string', Rule::enum(JobApplicationStatus::class)]`
   - `reason`: `['nullable', 'string', 'max:500']`
2. `App\Http\Requests\Candidate\WithdrawApplicationRequest`:
   - `reason`: `['nullable', 'string', 'max:500']`

### Routes (`routes/web.php`)
```php
// Employer group
Route::middleware(['auth', 'role:employer', 'employer.approved'])->prefix('employer')->name('employer.')->group(function () {
    // ...
    Route::post('applications/{application}/status', [EmployerJobApplicationController::class, 'updateStatus'])
        ->name('applications.update-status');
});

// Candidate group
Route::middleware(['auth', 'role:job_seeker'])->prefix('candidate')->name('candidate.')->group(function () {
    // ...
    Route::post('applications/{application}/withdraw', [ApplicationHistoryController::class, 'withdraw'])
        ->name('applications.withdraw');
});
```

---

## Tests

1. **Employer Status Transitions**:
   - Authorized employer can move application through allowed pipeline stages (`submitted` → `reviewing` → `shortlisted` → `interview_scheduled` → `offer_sent` → `hired`).
   - Authorized employer can reject an application from any non-terminal stage.
   - Employer cannot transition an application with an invalid next status (422).
   - Employer cannot transition a terminal application (`hired`, `rejected`, `withdrawn`) (422).
   - Unrelated employer cannot transition another company's application (403).
   - Stale update returns 409 Conflict when `current_status` does not match database state.
   - History entry is created with actor, old status, new status, and optional reason.

2. **Candidate Withdrawal**:
   - Candidate can withdraw their own non-terminal application.
   - Candidate cannot withdraw another candidate's application (403).
   - Candidate cannot withdraw a terminal application (`hired`, `rejected`, `withdrawn`) (422).
   - History entry records candidate as actor and stores withdrawal reason.

3. **Frontend / Integration**:
   - `npm run types:check`
   - `npm run build`
   - `./vendor/bin/pint --test`
