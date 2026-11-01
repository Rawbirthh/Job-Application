# Feature 11 — Employer Onboarding and Eligibility State

**Metadata:**

- Feature ID: 11
- Title: Employer onboarding and eligibility state
- Spec Type: Feature specification
- Source: `project-overview.md` → “Feature 11 — Employer onboarding and eligibility state”
- Dependencies: Feature 2 (authentication), Feature 3 (custom authorization), Feature 4 (shared shell/dashboard routing) — all implemented
- Integration dependency: Feature 10 (employer-account review and suspension) — implemented, with gaps this feature must close
- Status: **APPROVE**
- Created: 2026-11-01
- Slug: `feature-11-employer-onboarding-and-eligibility-state`

---

## Goal

Allow an authenticated job seeker to request employer capability through a controlled, server-validated onboarding flow, persist the application and its eligibility state, give the applicant clear status/next-step guidance, and integrate the request into the existing admin employer-review screen.

A user becomes an eligible employer only when all of the following are true:

1. The account has the `employer` role.
2. The account’s employer eligibility status is `approved`.

The role is assigned by the server on admin approval; the browser never supplies role, permission, status, or applicant identity.

## Non-goals

- Company profile creation or editing (Feature 12).
- Job drafts, publishing, moderation, or closure (Features 13–15).
- In-app or email notifications (Feature 25). The onboarding status page is the source of truth for this increment.
- Audit logging (Feature 31). `reviewed_at` / `reviewed_by_id` are retained for later audit integration.
- Uploading business documents, identity documents, or other sensitive files.
- Company hiring-team membership or company-scoped roles (Feature 28).
- Re-enabling email verification. The current project intentionally has email verification disabled.
- Building a generic employer directory separate from the existing `/admin/employers` review screen.
- Automatic employer-role assignment at registration.

---

## Current repository evidence

- `users.status` and `users.suspension_reason` already exist from Feature 10; `users.status` is indexed and nullable. The current admin controller also writes them.
- `User` does not cast `status`, does not expose employer status in shared Inertia props, and has no onboarding relationship.
- Registration assigns only `job_seeker`; this must remain true.
- `role_user` allows multiple roles. Approval should add `employer` without removing `job_seeker`.
- `/employer/dashboard` currently uses `role:employer` only. It does not reject a suspended employer, and a pending applicant has no route to check status.
- `DashboardController` redirects any user with the employer role to the employer dashboard, regardless of status.
- `/admin/employers` currently lists every user, not only employer applicants/employers. Its `show` route renders a nonexistent `admin/employers/show` page.
- `StoreEmployerRequest` / `UpdateEmployerRequest` allow only `pending`, `approved`, and `suspended`; the controller/UI contain unreachable `rejected` branches. Feature 11 must resolve this contradiction rather than preserve it.
- The admin employer page currently relies on generated Wayfinder routes and a selected-row action form. The action model is ambiguous because both suspend and reactivate post to the same `store` method.
- There is no `companies` table yet; `company_count` is currently `null`.
- Current verified baseline: `php artisan test` → 212 passed, 1437 assertions. This is evidence only; implementation must re-run the suite.

---

## Assumptions and focused questions

### Assumptions (defaults for approval)

- **A1 — Canonical status lives on `users`.** `users.status` is the single source of truth for employer eligibility. Do not add a second status column to the onboarding table.
- **A2 — One current onboarding request per user.** A unique `user_id` prevents duplicate rows. A rejected applicant resubmits by updating the same request; request history is deferred to Feature 31.
- **A3 — Four statuses.** `pending`, `approved`, `rejected`, `suspended`. `null` means “never applied / not an employer applicant.” `rejected` is a real state, not an alias for suspended.
- **A4 — Approval adds the employer role.** The user keeps `job_seeker`. Rejection does not grant `employer`. Suspension preserves the role but blocks employer routes because status is not `approved`.
- **A5 — Rejection is re-applicable.** A rejected applicant can submit a revised request immediately. Suspended applicants cannot reapply; only an admin reactivation restores access.
- **A6 — Minimal onboarding fields.** The request captures intended company name, optional company website, applicant’s role/job title at the company, and an optional message. No sensitive documents. Feature 12 owns the authoritative company profile.
- **A7 — Email verification is not required.** The existing application has verification disabled; this feature does not re-enable it.
- **A8 — Reuse existing permissions.** No new permission slugs are needed. Admin reads use `employers.view`; review/status writes use `employers.manage`.
- **A9 — Admin list scope changes.** `/admin/employers` must show only users with an onboarding request, a non-null employer status, or the `employer` role. It must not list every job seeker.
- **A10 — Explicit admin actions replace the ambiguous status form.** Feature 11 may refine the Feature 10 UI/routes; it must preserve Feature 10’s permission boundary and status fields.
- **A11 — No new notifications.** The applicant sees status/rejection/suspension reasons on the onboarding page and candidate dashboard CTA.
- **A12 — Pending/rejected applicants remain candidates.** They keep access to `/candidate/dashboard`; employer-only routes stay blocked until approval.

### Non-blocking questions (defaults above apply)

1. Should a pending applicant be allowed to withdraw/cancel their request? Default: no, not in Feature 11.
2. Should rejection impose a cooldown before reapplication? Default: no; reapplication is immediate.
3. Should the onboarding request ask for company details now, or only the applicant’s intent? Default: collect the minimum review context in A6; Feature 12 remains authoritative for the company profile.

---

## Status model and transition matrix

`App\Enums\EmployerStatus`:

```text
pending | approved | rejected | suspended
```

`users.status` is nullable. `null` is not an employer state; it means the user has not submitted an onboarding request.

| Actor                         | Current status | Action         | New status  | Required/cleared data                             | Side effects                                                                                      |
| ----------------------------- | -------------- | -------------- | ----------- | ------------------------------------------------- | ------------------------------------------------------------------------------------------------- |
| Authenticated applicant       | `null`         | Submit request | `pending`   | Company name, job title; website/message optional | Create onboarding request; set `submitted_at`; no role change                                     |
| Authenticated applicant       | `rejected`     | Resubmit       | `pending`   | Same fields                                       | Update same request; reset `reviewed_at`, `reviewed_by_id`, `review_note`; set new `submitted_at` |
| Admin with `employers.manage` | `pending`      | Approve        | `approved`  | None                                              | Assign `employer` role; set request review metadata; clear suspension reason                      |
| Admin with `employers.manage` | `pending`      | Reject         | `rejected`  | Rejection reason required                         | Store reason in `review_note`; set review metadata; do not add employer role                      |
| Admin with `employers.manage` | `approved`     | Suspend        | `suspended` | Suspension reason required                        | Store `suspension_reason`; preserve role and records                                              |
| Admin with `employers.manage` | `suspended`    | Reactivate     | `approved`  | None                                              | Clear `suspension_reason`; ensure employer role exists                                            |

Invalid transitions must fail server-side with a `422` field error on `status` (or a clear validation message) and must not partially update records. Examples: approving an approved user, rejecting a suspended user, resubmitting while pending, or reactivating a rejected user.

---

## User flows and acceptance criteria

### F1 — Submit an employer request

A signed-in user with no employer status opens `/employer/onboarding`, completes the form, and submits.

Acceptance criteria:

- The form collects company name, optional company website, job title/role, and an optional message.
- The server sets `users.status = pending`, creates exactly one onboarding request, and records `submitted_at`.
- The server does **not** assign `employer` or any additional permission.
- The user is redirected back to the onboarding page with a success message and sees a pending status panel.
- A forged `status`, `role`, `role_id`, `permissions`, `user_id`, or `reviewed_at` field has no effect.
- Invalid input returns field-level errors and creates no request.

### F2 — View onboarding status and guidance

The onboarding page renders the current user’s own state.

Acceptance criteria:

- No request: form and explanation of the review process.
- `pending`: “Under review,” submission date, and submitted request summary; no duplicate form.
- `approved`: approval confirmation and a link to `/employer/dashboard`.
- `rejected`: rejection reason and a pre-filled reapplication form.
- `suspended`: suspension reason and guidance that employer actions are unavailable.
- The page only ever reads the authenticated user’s request; there is no user ID route parameter.
- The candidate dashboard shows a contextual employer-access card: apply, view pending status, view rejection/reapply, or employer dashboard link.

### F3 — Admin reviews a request

An admin with `admin.access` and `employers.view` opens `/admin/employers`, filters to pending requests, and opens the review page.

Acceptance criteria:

- The directory contains only employer applicants/employers, not unrelated job seekers.
- The list supports search by user name/email prefix, status filtering, pagination at 25, and a stable order.
- Each row exposes the applicant, intended company, status, submission date, and a review link.
- The review page shows applicant identity, request details, status, rejection/suspension reasons, reviewer metadata, and action controls only when `employers.manage` is held.
- A view-only admin can read but receives `403` on every write action.

### F4 — Admin approves

- Approval is allowed only from `pending`.
- The transaction sets `status = approved`, clears `suspension_reason`, assigns the `employer` role, and records `reviewed_at` / `reviewed_by_id`.
- If role assignment fails, the status change rolls back.
- The applicant is immediately eligible for employer routes.
- The applicant keeps `job_seeker`; the employer role is additive.

### F5 — Admin rejects

- Rejection is allowed only from `pending`.
- A non-empty reason is required and stored as `review_note`.
- `status = rejected`; no employer role is granted; `suspension_reason` is cleared.
- The applicant sees the rejection reason and may resubmit.

### F6 — Admin suspends and reactivates

- Suspension is allowed only from `approved`; a reason is required and stored in `suspension_reason`.
- Suspension preserves the employer role, request, and future company/job records; it only blocks employer-route access.
- Reactivation is allowed only from `suspended`; it restores `approved`, clears the suspension reason, and ensures the employer role exists.
- Suspended users see the reason on the onboarding page and receive `403` on employer routes.

### F7 — Eligibility enforcement

- Every `/employer/*` business route other than onboarding must require both `role:employer` and `employer.approved`.
- A pending, rejected, or suspended user must not reach `/employer/dashboard` or any future employer company/job route, even by direct request.
- `/dashboard` routes an approved employer to the employer dashboard; an employer-role user who is not approved is sent to the onboarding status page.
- A job seeker remains routed to the candidate dashboard, where the onboarding status card is visible.

---

## Routes, middleware, and authorization

### Applicant routes

| Method | URI                    | Name                        | Middleware              |
| ------ | ---------------------- | --------------------------- | ----------------------- |
| GET    | `/employer/onboarding` | `employer.onboarding.index` | `auth`                  |
| POST   | `/employer/onboarding` | `employer.onboarding.store` | `auth`, `throttle:10,1` |

These routes intentionally do not use `role:employer`, because pending and rejected applicants normally hold only `job_seeker`; suspended users hold `employer` but must still be able to view their status.

### Employer business routes

| Method | URI                   | Name                 | Middleware                                   |
| ------ | --------------------- | -------------------- | -------------------------------------------- |
| GET    | `/employer/dashboard` | `employer.dashboard` | `auth`, `role:employer`, `employer.approved` |

Future employer company/job routes must join the same `role:employer` + `employer.approved` group. The middleware alias `employer.approved` maps to a new `EnsureEmployerIsApproved` middleware; it aborts `403` unless the authenticated user has `status === EmployerStatus::Approved`.

### Admin routes

| Method | URI                                  | Name                         | Middleware                                                       |
| ------ | ------------------------------------ | ---------------------------- | ---------------------------------------------------------------- |
| GET    | `/admin/employers`                   | `admin.employers.index`      | `auth`, `permission:admin.access`, `permission:employers.view`   |
| GET    | `/admin/employers/{user}`            | `admin.employers.show`       | `auth`, `permission:admin.access`, `permission:employers.view`   |
| POST   | `/admin/employers/{user}/approve`    | `admin.employers.approve`    | `auth`, `permission:admin.access`, `permission:employers.manage` |
| POST   | `/admin/employers/{user}/reject`     | `admin.employers.reject`     | `auth`, `permission:admin.access`, `permission:employers.manage` |
| POST   | `/admin/employers/{user}/suspend`    | `admin.employers.suspend`    | `auth`, `permission:admin.access`, `permission:employers.manage` |
| POST   | `/admin/employers/{user}/reactivate` | `admin.employers.reactivate` | `auth`, `permission:admin.access`, `permission:employers.manage` |

Route-model binding supplies a `User`, but the controller/service must resolve the target through the employer-applicant scope. A user with no onboarding request, no employer status, and no employer role must produce `404`, not appear reviewable.

The ambiguous `POST /admin/employers/{user}/suspend` and `/reactivate` routes currently mapping to one `store` method are replaced by the explicit actions above. The unused generic employer store/update routes must not remain.

### Policy actions

`EmployerOnboardingRequestPolicy`:

| Action   | Rule                                                                              |
| -------- | --------------------------------------------------------------------------------- |
| `view`   | Actor is the request owner.                                                       |
| `create` | Actor’s status is `null` or `rejected`; pending/approved/suspended cannot submit. |

`EmployerPolicy` (target is a `User`):

| Action       | Permission         | Notes                 |
| ------------ | ------------------ | --------------------- |
| `viewAny`    | `employers.view`   | Admin directory.      |
| `view`       | `employers.view`   | Admin review/detail.  |
| `review`     | `employers.manage` | Approve/reject.       |
| `suspend`    | `employers.manage` | Approved → suspended. |
| `reactivate` | `employers.manage` | Suspended → approved. |

Register explicit gates in `AppServiceProvider` for the `User` target actions (`employers.viewAny`, `employers.view`, `employers.review`, `employers.suspend`, `employers.reactivate`) because auto-discovery would otherwise select `UserPolicy` for a `User` target. Controllers must still call `$this->authorize(...)`; route middleware is not the only guard.

No new `PermissionSlug` cases or seeder changes are required. Existing permission catalogue counts remain unchanged.

---

## Form Requests and validation

### `StoreEmployerOnboardingRequest`

`authorize()` returns `true`; the controller authorizes `create` against `EmployerOnboardingRequest` before invoking the service.

| Field             | Rules                            | Normalization      |
| ----------------- | -------------------------------- | ------------------ |
| `company_name`    | required, string, min:2, max:150 | trim               |
| `company_website` | nullable, string, URL, max:255   | trim; blank → null |
| `job_title`       | required, string, min:2, max:100 | trim               |
| `message`         | nullable, string, max:1000       | trim; blank → null |

Do not accept applicant identity, status, role, permissions, review fields, or company ownership fields.

### `RejectEmployerRequest`

| Field    | Rules                            |
| -------- | -------------------------------- |
| `reason` | required, string, min:3, max:500 |

### `SuspendEmployerRequest`

| Field    | Rules                            |
| -------- | -------------------------------- |
| `reason` | required, string, min:3, max:500 |

Approve and reactivate accept no user input; use a plain `Request` plus Policy/Service authorization rather than a ceremonial empty Form Request.

The admin index search/status filter is a GET query, so it may use the existing controller-level `$request->validate()` pattern. Validate `status` with `Rule::enum(EmployerStatus::class)` and normalize an empty string to `null`.

All user-facing text is plain text; React renders it escaped. Do not render rejection/suspension reasons as HTML.

---

## Models, relationships, migration, constraints, and indexes

### `App\Enums\EmployerStatus`

Add a backed string enum with `Pending`, `Approved`, `Rejected`, and `Suspended`. Use it in casts, middleware, policies, services, and payload mapping.

### `User` model changes

- Cast `status` to `EmployerStatus::class` (nullable).
- Add `employerOnboardingRequest(): HasOne` keyed by `user_id`.
- Keep `status` and `suspension_reason` out of `$fillable`; the service sets them explicitly after validated input. This prevents mass-assignment escalation.
- Add a small helper such as `isApprovedEmployer(): bool` used by middleware and routing.
- Add a query scope for “employer applicant/employer” users used by the admin directory: non-null `status` OR `employer` role.
- Do not add employer-request fields to `users`; keep the request record separate from identity data.

### New table: `employer_onboarding_requests`

New migration only. Do not edit the already-applied Feature 10 migrations.

| Column                      | Type                               | Constraints/notes                         |
| --------------------------- | ---------------------------------- | ----------------------------------------- |
| `id`                        | bigint PK                          |                                           |
| `user_id`                   | foreign key → `users.id`           | **unique**, `cascadeOnDelete`             |
| `company_name`              | string(150)                        | required at validation                    |
| `company_website`           | string(255), nullable              | URL-normalized only as validated          |
| `job_title`                 | string(100)                        | applicant’s role at the company           |
| `message`                   | text, nullable                     | max 1000 at validation                    |
| `submitted_at`              | timestamp                          | set by service on initial submit/resubmit |
| `reviewed_at`               | timestamp, nullable                | approve/reject                            |
| `reviewed_by_id`            | foreign key → `users.id`, nullable | `nullOnDelete`; indexed                   |
| `review_note`               | text, nullable                     | rejection reason; cleared on resubmit     |
| `created_at` / `updated_at` | timestamps                         |                                           |

Indexes:

- Unique index on `user_id` (prevents duplicate current requests; also supports the relationship).
- Index on `reviewed_by_id` for the foreign key / later review queries.
- No extra composite index is justified yet. Admin filtering uses the existing `users.status` index; search uses name/email prefix and remains an admin-only paginated query.

Do not backfill `users.status` for existing accounts. A regular job seeker remains `null`. Existing Feature 10 rows with `pending`/`approved`/`suspended` remain valid. `rejected` is now a supported value.

### `EmployerOnboardingRequest` model

- `$fillable`: `company_name`, `company_website`, `job_title`, `message`, `submitted_at`, `reviewed_at`, `reviewed_by_id`, `review_note`.
- Casts: `submitted_at` and `reviewed_at` to `datetime`.
- Relationships: `user(): BelongsTo`; `reviewer(): BelongsTo` (`reviewed_by_id`).
- No `SoftDeletes`; deleting the account cascades the request. Historical retention belongs to later audit/retention work.

---

## Query, payload, pagination, and eager-loading plan

### Admin employer directory

- Authorize `viewAny`.
- Validate `search` (`nullable|string|max:100`) and `status` (`nullable|Rule::enum(EmployerStatus::class)`); trim search.
- Escape LIKE wildcards and use prefix matching (`name LIKE 'term%' OR email LIKE 'term%'`), consistent with `/admin/users`.
- Scope: `whereNotNull('users.status')` OR `whereHas('roles', slug = employer)`. Never list all users.
- Select only `users.id`, `users.name`, `users.email`, `users.status`.
- Eager-load the onboarding request with only the fields needed for the row: `id`, `user_id`, `company_name`, `submitted_at`, `reviewed_at`, `review_note`.
- Filter by status when supplied.
- Stable order: status priority (`pending`, `approved`, `rejected`, `suspended`, other) → `users.name` → `users.id`. Use a portable `CASE` expression for status priority.
- `paginate(25)->withQueryString()`.
- Payload projection includes `status_badge`, `suspension_reason`, `onboarding_request`, and `can_manage`. Do not include `company_count` until Feature 12 exists; remove the placeholder company-count column from the UI.
- No `companies` relationship query, no N+1, no unbounded `get()`.

### Admin review page

- Authorize `view` on the resolved target.
- Resolve target through the same employer-applicant scope; return `404` for unrelated users.
- Eager-load `employerOnboardingRequest.reviewer:id,name` and `roles:id,slug,name`.
- Select only required user fields plus `status`, `suspension_reason`, timestamps.
- Return server-computed `actions` booleans (`approve`, `reject`, `suspend`, `reactivate`) from the current status so the UI does not invent transition rules.

### Applicant onboarding page

- Resolve the authenticated user only; no route parameter.
- Load the single onboarding request and only the fields displayed.
- Return `status`, request details, `suspension_reason`, `can_apply`, and `can_access_employer_dashboard`.
- One query for the user/request; no pagination because the result is a single current record.

### Shared Inertia props

Add `auth.employer_status` (`EmployerStatus | null` serialized as a string) to `HandleInertiaRequests`. This lets the candidate dashboard render the correct CTA without a client fetch. Keep `auth.user` limited to `id`, `name`, `email`; do not expose review notes or internal metadata globally.

---

## Service / Repository decision

### Use one `EmployerOnboardingService`

Justification:

- Submission is multi-write (create/update request + set user status), and approval is multi-write plus role assignment. These must commit or roll back together.
- The same transition rules are used by applicant submission and four admin actions. Centralizing them prevents contradictory controller logic.
- The service wraps the existing `RoleAssignmentService` for approval/reactivation and clears the user permission cache.
- Locking the user row plus the unique `user_id` constraint addresses duplicate submissions and concurrent admin actions.

Suggested methods:

- `submit(User $user, array $data): EmployerOnboardingRequest`
- `approve(User $admin, User $applicant): void`
- `reject(User $admin, User $applicant, string $reason): void`
- `suspend(User $admin, User $applicant, string $reason): void`
- `reactivate(User $admin, User $applicant): void`

Each method:

1. Opens a DB transaction and re-reads/locks the user row.
2. Asserts the current status is valid for the action.
3. Updates the request and/or user status with explicit fields.
4. Assigns/ensures the employer role only where specified.
5. Returns normally or throws `ValidationException` with a `status` field message for an invalid transition.

Nested transactions with `RoleAssignmentService` are acceptable; Laravel uses savepoints where supported. Do not dispatch notifications or external side effects inside the transaction.

### No Repository

The index query is a single paginated Eloquent query with one eager-loaded relation; the status page and review page are single-record reads. There is no complex multi-join/aggregate query or reused query object that justifies a Repository yet.

---

## React / Inertia page and component plan

### Applicant page: `resources/js/pages/employer/onboarding.tsx`

- Uses `Head`, `Link`, `useForm`, `Button`, `Input`, `Textarea`, `Label`, `InputError`, `Spinner`, `Badge`, `Alert`, `Card`.
- State-specific content:
    - `null`: application form with explanation and expected review steps.
    - `pending`: status panel with submission date and request summary.
    - `approved`: confirmation, link to employer dashboard.
    - `rejected`: rejection note from `review_note`, plus the same form pre-filled from the existing request.
    - `suspended`: destructive-style status panel with `suspension_reason` and guidance.
- Actual `<form onSubmit>`; submit disabled while `processing`; show spinner; call `form.post(employerOnboarding.store.url(), { preserveScroll: true })`.
- Field errors use `InputError` with stable IDs, `aria-invalid`, and `aria-describedby`. Website helper text explains `https://` format.
- Approved users must not see an editable form.
- Add breadcrumbs via the page `layout` export.

### Admin directory: update `resources/js/pages/admin/employers/index.tsx`

- Keep `Head`, search form, status filter, `Pagination`, and the established visual language.
- Status filter options: all, pending, approved, rejected, suspended.
- Columns: employer (name + email), intended company, status badge, submitted date, actions (“Review”).
- Use `router.get(index.url(), { search, status }, { preserveState, preserveScroll, replace })`; preserve query string across pagination.
- Empty states distinguish “no applications yet” from “no matches.” Include a clear-filters action.
- View-only admins still see detail links; write controls are hidden.

### Admin review page: new `resources/js/pages/admin/employers/show.tsx`

- Show applicant summary, request details, status, submitted/reviewed metadata, reviewer name, review note, and suspension reason.
- Server-provided action flags control which buttons appear:
    - `pending`: Approve, Reject.
    - `approved`: Suspend.
    - `suspended`: Reactivate.
    - `rejected`: no write action; explain that the applicant must resubmit.
- Reject and suspend require a reason textarea with inline errors; use a real form and `router.post`/`useForm` with processing state.
- Approve/reactivate use confirmation; no reason field.
- Show success/error feedback via the existing flash toast hook; preserve scroll.
- Read-only view shows “View only” and no action form.

### Candidate dashboard

- Read `auth.employer_status` from shared props.
- Add an “Employer access” card:
    - `null` / `rejected`: “Become an employer” / “Reapply” with link to onboarding.
    - `pending`: “Application under review” with status link.
    - `suspended`: “Employer access suspended” with status link.
    - `approved`: “Employer dashboard” link.
- No client-side authorization; the server still gates the destination.

### Navigation

- Keep the existing admin “Employers” item gated by `admin.access` + `employers.view`.
- Do not add a global nav item whose visibility cannot account for employer status. The candidate dashboard card and onboarding page provide discoverability.
- Employer dashboard can add an “Onboarding status” link for approved users if useful, but it is not required.

### TypeScript types

Add to `resources/js/types/authorization.ts` (or a dedicated employer types file re-exported from `types/index.ts`):

```ts
type EmployerStatus = 'pending' | 'approved' | 'rejected' | 'suspended';

type EmployerOnboardingRequestSummary = {
    company_name: string;
    company_website: string | null;
    job_title: string;
    message: string | null;
    submitted_at: string;
    reviewed_at: string | null;
    review_note: string | null;
};

type EmployerOnboardingPageProps = {
    status: EmployerStatus | null;
    request: EmployerOnboardingRequestSummary | null;
    suspension_reason: string | null;
    can_apply: boolean;
    can_access_employer_dashboard: boolean;
};
```

Extend `Auth` with `employer_status: EmployerStatus | null`. Update `AdminEmployerIndexProps`, `EmployerRow`, and add `AdminEmployerShowProps`. No `any`.

---

## Accessibility, empty, loading, and error states

- Every input has a visible `<Label htmlFor>`; errors use `InputError` (`role="alert"`) and `aria-describedby`; invalid fields set `aria-invalid`.
- Status panels use `role="status"` or `Alert` with a clear heading; rejection/suspension reasons are readable plain text.
- The application form has explicit required indicators and helper text for optional website/message.
- Submit buttons show a spinner and are disabled during processing; prevent double submission.
- The admin table uses `<th scope="col">`, a caption, horizontal overflow, and a meaningful empty row.
- The admin review page disables all actions while a write is processing and keeps the previous state visible if validation fails.
- Search/filter forms are real `<form>` elements or equivalent controls with labels; filter changes preserve state and show a progress indicator.
- Pagination uses the shared component with `aria-current="page"` and previous/next labels.
- Guests are redirected to login by `auth`; unauthorized users receive `403`; unrelated admin targets receive `404`; invalid transitions return a clear validation error rather than a silent no-op.
- Responsive layout: forms and detail cards stack on small screens; tables scroll horizontally; no keyboard traps.

---

## Test matrix

### New `tests/Feature/Employer/EmployerOnboardingTest.php`

- Guest GET/POST onboarding redirects to login.
- Job seeker with no request can view the form and submit valid data.
- Valid submit creates one request, sets `status = pending`, stores fields, and does not assign `employer`.
- Forged `status`, `role`, `role_id`, `permissions`, `user_id`, `reviewed_at`, or `review_note` fields are ignored.
- Invalid submissions: missing company name/job title, invalid website, overlong website/company/title/message; no request is created.
- Duplicate submission while pending/approved/suspended is rejected with a field error and does not create a second row.
- Rejected applicant sees `review_note` and can resubmit; resubmission reuses the same row, returns to `pending`, clears review metadata, and updates `submitted_at`.
- Approved applicant sees approved status, cannot submit, and can access `/employer/dashboard`.
- Suspended employer sees suspension reason, cannot submit, and gets `403` on `/employer/dashboard`.
- Pending/rejected applicant gets `403` on `/employer/dashboard`.
- No route permits reading another user’s onboarding request.
- Candidate dashboard shows the correct CTA for `null`, `pending`, `rejected`, `approved`, and `suspended` states.
- `auth.employer_status` is present in shared props.

### Update `tests/Feature/Admin/EmployerTest.php`

- Directory excludes unrelated job seekers and includes applicants with requests/status/employer role.
- Search matches name/email prefixes, escapes LIKE wildcards, and preserves status filters/pagination.
- Status filter works for all four statuses; invalid status fails validation.
- Approve pending: status becomes approved, employer role attached, review metadata set, suspension reason cleared.
- Approve from approved/rejected/suspended is rejected and leaves records unchanged.
- Reject pending with reason: status rejected, `review_note` stored, no employer role.
- Reject without reason fails validation; reject from non-pending fails.
- Suspend approved with reason: status suspended, reason stored, employer role preserved.
- Suspend without reason fails; suspend pending/rejected fails.
- Reactivate suspended: status approved, reason cleared, employer role ensured.
- Reactivate non-suspended fails.
- View-only admin can view index/show but gets `403` on approve/reject/suspend/reactivate.
- Guest index/show redirects to login; job seeker gets `403`.
- Reviewing an unrelated user returns `404`.
- Review page exposes request details, reviewer metadata, status-specific action flags, and read-only mode.
- Existing Feature 10 regression cases for ordering, search, suspension reasons, and pagination remain covered.

### Update `tests/Feature/DashboardRoutingTest.php`

- Approved employer → employer dashboard.
- Pending/rejected/suspended employer-role user → onboarding status page (or candidate dashboard for job-seeker-only pending/rejected users, per the final routing rule).
- Employer-role user with `null` status does not reach employer dashboard.
- Suspended user direct `/employer/dashboard` → `403`.
- Job seeker still reaches candidate dashboard.
- Shared auth props expose `employer_status` and do not expose request details globally.

### Regression / security tests

- `RegistrationAuthorizationTest`: registration still assigns only `job_seeker`, status remains `null`, and forged onboarding/status fields have no effect.
- `AuthorizationCatalogueTest`: unchanged permission count and system mappings; no new permission cases.
- Direct HTTP writes without permission are denied regardless of UI state.
- Account deletion still cascades onboarding requests and does not affect last-administrator safeguards.
- Role assignment remains server-controlled; adding `employer` through the admin role screen alone does not grant employer-route access without `approved` status.

### Frontend/build checks

- `npm run types:check`
- `npm run check`
- `npm run build` (regenerates Wayfinder routes for the new controller/actions)
- `vendor/bin/pint --test`
- `vendor/bin/phpstan analyse --no-progress`

No frontend unit-test harness exists; manual keyboard/responsive review is still required for the new form and status panels.

---

## Security and performance risks

### Security

- **Authorization boundaries:** applicant routes are self-scoped and `auth`-only; employer business routes require role + approved status; admin routes use permission middleware plus controller Policy checks.
- **No client-controlled privilege:** status, roles, permissions, applicant identity, review metadata, and ownership are never taken from the request. Approval is the only path that adds the employer role, through the existing service.
- **IDOR:** onboarding has no user-ID parameter. Admin target resolution is scoped; unrelated IDs return `404`.
- **Mass assignment:** `status`/`suspension_reason` remain outside `User::$fillable`; the request model has explicit fillable fields; services map validated data.
- **State-machine bypass:** all transitions are asserted server-side; invalid state changes return `422` and leave the database unchanged.
- **Duplicate/concurrent submission:** unique `user_id`, transaction, and row lock prevent duplicate rows; the service handles the unique-constraint race as a validation error where practical.
- **Role/status divergence:** role alone does not grant employer access; `employer.approved` enforces status. Admin role assignment without approval remains blocked.
- **Data exposure:** shared props expose only the current user’s status, not request notes; admin-only review metadata stays on admin pages. No sensitive documents are collected.
- **Text handling:** reasons/messages are plain text and rendered escaped by React; do not use `dangerouslySetInnerHTML`.
- **Rate limiting:** onboarding POST uses `throttle:10,1`; admin writes are permission-protected and low-volume.
- **Deployment:** no new permissions mean no seeder rerun is required for this feature. Approval still depends on the `employer` role existing from `AuthorizationSeeder`.

### Performance

- Admin directory is paginated at 25 and selects only needed columns; the onboarding relation is eager-loaded; no `companies` query or placeholder count.
- Search uses escaped prefix matching on indexed `users.name`; status filtering uses the existing `users.status` index. The OR with the employer role may require a subquery, but the admin list is bounded and low-volume.
- One request per user with a unique index; no unbounded request lists.
- Approval/rejection transactions are short; role assignment is a small pivot write. No external calls or notifications inside the transaction.
- `reviewed_by_id` and `user_id` are indexed; no speculative composite indexes are added.

### Compatibility / rollout

1. Create the new migration; do not modify Feature 10 migrations.
2. Add the enum/casts/relations and service.
3. Update Feature 10 admin routes/controller/UI to explicit actions and applicant scoping.
4. Add the applicant route/middleware and dashboard routing.
5. Deploy code and run the migration before exposing the onboarding UI.
6. Existing Feature 10 statuses remain valid; no backfill is required. There is no production employer-company data yet.
7. Rollback: remove the applicant/admin UI and routes first, then drop the new onboarding table if no request data must be retained. Do not drop `users.status`/`suspension_reason` while Feature 10 depends on them.

---

## Ordered implementation plan

1. Add `EmployerStatus` enum; cast `User::status`; add `employerOnboardingRequest()` and `isApprovedEmployer()`/applicant scope.
2. Add the `employer_onboarding_requests` migration/model/factory with unique user FK, reviewer FK, and review metadata.
3. Add `EmployerOnboardingService` and its transition assertions; reuse `RoleAssignmentService`.
4. Add Form Requests: onboarding store, reject, suspend.
5. Add `EmployerOnboardingRequestPolicy`; extend `EmployerPolicy`; register/update gates.
6. Add `EnsureEmployerIsApproved` middleware and the `employer.approved` alias.
7. Add applicant controller/routes; update the employer route group and `DashboardController`.
8. Update `HandleInertiaRequests` shared `auth.employer_status`.
9. Update Feature 10 `EmployerController` to explicit approve/reject/suspend/reactivate actions, applicant scoping, and request payloads.
10. Build the applicant onboarding page and candidate dashboard card; update shared/auth types.
11. Refactor the admin employer index and add the review page with server-provided action flags.
12. Add/update the tests listed above, including regression and authorization cases.
13. Run targeted tests, the full suite, type checks, formatter, static analysis, build, and route-list inspection; fix any failures.

---

## Verification commands

```text
php artisan migrate
php artisan test tests/Feature/Employer/EmployerOnboardingTest.php
php artisan test tests/Feature/Admin/EmployerTest.php
php artisan test tests/Feature/DashboardRoutingTest.php
php artisan test tests/Feature/Authorization/RegistrationAuthorizationTest.php
php artisan test
npm run types:check
npm run check
npm run build
vendor/bin/pint --test
vendor/bin/phpstan analyse --no-progress
php artisan route:list --path=employer/onboarding -v
php artisan route:list --path=admin/employers -v
```

Do not claim any command passed unless its output was inspected. If a command cannot run, record the exact blocker.

---

## Approval gate

Status: **PROPOSED**. This spec is submitted for review. Do not implement application code, create migrations, install packages, or change the Feature 10 implementation until the user explicitly approves this spec (or edits its assumptions/decisions).
