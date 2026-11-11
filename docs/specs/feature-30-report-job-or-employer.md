---
name: feature-30-report-job-or-employer
description: Specification for Feature 30 — Report a job or employer for moderation
metadata:
    type: project
    status: IMPLEMENTED
    created: 2026-11-10
---

# Feature 30 — Report a job or employer

## 1. Goal / non-goals

**Goal:** Let any logged-in user report scams, misleading listings, or suspicious employer behavior. Reports flow into an administrator moderation queue with a controlled reason list, an optional explanation, explicit status transitions, and an optional resolution message back to the reporter. The reported content is never automatically removed; admins act on it through the existing moderation surfaces.

**In scope:**

- `reports` table + `Report` model referencing a job posting (Feature 13/14) or a company (Feature 12) — one report targets exactly one subject.
- Report submission endpoint for authenticated users (form reachable from the public job detail page, Feature 17).
- Admin moderation queue, detail view, and explicit status actions under a new `reports.moderate` permission.
- Duplicate/abuse mitigation: route throttling, a cap on open reports per reporter, and one open report per reporter per subject.
- In-app notifications (Feature 25): admins with moderation permission on submission; reporter on resolution/dismissal.
- Reason list as fixed application configuration (enum), not an admin-managed catalogue.

**Non-goals:**

- Automatic removal/suspension on the existence of a report. Admins act via existing `jobs.moderate` (Feature 14) and `employers.manage` (Feature 10) surfaces.
- Structured escalation workflow, multi-target reports (one report != many subjects).
- Audit logging of admin actions (Feature 31, not implemented; noted below as a future hook).
- "My reports" page for reporters; reporter-facing feedback is the resolution notification.
- Anonymous reporting, email/push delivery, AI triage, reputation/scoring systems.
- Company public profile pages (none exist today; the company subject is reachable from the job detail context).

## 2. User flows and acceptance criteria

### Flows

1. **Report from a public job (logged-in):** User opens `/jobs/{job}`, clicks "Report", picks "Report this job" or "Report this employer" (company is taken from the job's public company panel), picks a reason from a required list, optionally writes an explanation, submits → confirmation toast.
2. **Guest:** The report affordance is visible; submitting requires authentication — the dialog presents a sign-in prompt (consistent with Feature 17 CTA behavior).
3. **Admin review:** Admin with `reports.moderate` opens `admin/reports` (default: `pending`, newest first), filters by status/subject type/reason, opens a report, starts investigation or resolves/dismisses, optionally records an internal note and a reporter-facing resolution message.
4. **Reporter outcome:** Reporter receives an in-app notification when the report is resolved or dismissed.

### Acceptance criteria

- A logged-in user can submit a valid report against a **publicly visible** job or an **active** company; guests cannot submit.
- Controlled reason list server-enforced; explanation optional with length bound.
- Duplicate spam reports are prevented: one _open_ (pending/investigating) report per reporter per subject, enforced twice (validation + DB-level unique emulation); per-rate throttling; per-reporter cap on open reports.
- Report details (reporter identity, explanation, internal note, resolution message) are visible **only** to authorized admins. Public/employer surfaces never expose report counts or data.
- Reported content is not altered by the existence of a report (no auto-close/auto-suspend, tested).
- Admin queue + actions are permission-gated (`admin.access` + `reports.moderate`) with server-side enforcement, status transition validation, and resolvable outcome tracking (`resolved_by_id`, `resolved_at`).
- Resolution/dismissal notifies the reporter with outcome and optional message; notification failure does not corrupt the primary write.

## 3. Assumptions (labeled, non-blocking)

- **A1 — Fixed reason catalogue:** Reasons are enum values (`scam_or_fraud`, `misleading_listing`, `offensive_content`, `spam`, `other`), not an admin-managed DB table. The roadmap defines no admin CRUD feature for report reasons; changing this later adds a catalogue table like Features 5–9.
- **A2 — Any authenticated user may report** (criteria: `logged-in user` in acceptance criteria) — including employers and suspended accounts; reporting is a safety channel, not a prohibited employer action. Throttling and duplicate rules mitigate abuse.
- **A3 — Subject visibility gate:** A `job` report requires the job to pass exactly the `PublicJobController@show` visibility conditions (published + active company + approved owner). A `company` report requires the company to exist and be `is_active`. Prevents using reports as an IDOR.
- **A4 — Dismissal note optional, resolution message optional.** No hard requirement is defined in the roadmap; both are recommended-but-not-required.
- **A5 — `reports` is the table name.** No collision exists in the current schema; future structured "analytics reports" (Feature 32) are distinct.
- **A6 — Status naming:** `pending → investigating → resolved|dismissed`; all terminal outcomes use `resolved_at`/`resolved_by_id` bookkeeping, and open-marker clearing.

## 4. Routes and access boundary

Follows the existing session auth (Feature 2), `permission:` middleware aliases (Feature 3), and `routes/admin.php` group style.

| Method | URI                                  | Name                        | Middleware                                                       |
| ------ | ------------------------------------ | --------------------------- | ---------------------------------------------------------------- |
| POST   | `/reports`                           | `reports.store`             | `auth`, `throttle:10,1`                                          |
| GET    | `admin/reports`                      | `admin.reports.index`       | `auth`, `permission:admin.access`, `permission:reports.moderate` |
| GET    | `admin/reports/{report}`             | `admin.reports.show`        | as above                                                         |
| POST   | `admin/reports/{report}/investigate` | `admin.reports.investigate` | as above                                                         |
| POST   | `admin/reports/{report}/resolve`     | `admin.reports.resolve`     | as above                                                         |
| POST   | `admin/reports/{report}/dismiss`     | `admin.reports.dismiss`     | as above                                                         |

- `reports.store` lives in the top-level `Route::middleware(['auth'])` group in `routes/web.php` (next to notification routes) — this feature is cross-role, not candidate- or employer-scoped.
- Admin routes go in `routes/admin.php`, mirroring the `jobs.moderate` group (Route 168).
- Controllers: `App\Http\Controllers\ReportController` (public) and `App\Http\Controllers\Admin\ReportController`.

## 5. Permission and Policy design

- New `PermissionSlug::ReportsModerate = 'reports.moderate'` ("Moderate reports"), seeded by `AuthorizationSeeder` (existing repeatability pattern: `firstOrCreate` + explicit `platformAdminPermissionSlugs` mapping + `syncWithoutDetaching`). No other role receives it — matching how `jobs.moderate` is scoped.
- New `App\Policies\ReportPolicy`:

| Action                                                     | Result                           | Basis                                                                                                                        |
| ---------------------------------------------------------- | -------------------------------- | ---------------------------------------------------------------------------------------------------------------------------- |
| `create(User $user)`                                       | `true`                           | Authenticated reporter (A2); `$user` is non-guest by middleware; Form Request authorize mirrors existing policy-call pattern |
| `viewAny` / `view` / `investigate` / `resolve` / `dismiss` | `hasPermission(ReportsModerate)` | Mirrors `JobPolicy::viewAny/moderate`                                                                                        |

- Form Request `authorize()` returns via `$this->user()->can(...)`, per `StoreSavedSearchRequest` / `RejectJobRequest`; controllers also call `$this->authorize()` before reading (Admin queue) as the regulations require route middleware AND Policy.

## 6. Form Requests and validation

- **`App\Http\Requests\StoreReportRequest`**
    - `authorize()`: `$user->can('create', Report::class)`.
    - `subject` required, `in:job,company`.
    - `job_posting_id` `required_if:subject,job|integer|exists:job_postings,id` + closure rule asserting A3 visibility (published job, active company, approved owner).
    - `company_id` `required_if:subject,company|integer|exists:companies,id` + closure asserting `is_active`.
    - `reason` required, `Rule::in(ReportReason::values)`.
    - `explanation` `nullable|string|max:2000` (trimmed in `prepareForValidation`).
    - `withValidator()` after-hook: reject duplicate open report (same reporter + same subject, status pending/investigating) and reject when reporter has ≥10 open reports (cap, mirrors saved-search cap pattern).
- **`App\Http\Requests\Admin\ResolveReportRequest`** — authorize via `resolve` policy; `resolution_action` required `Rule::in(ReportResolutionAction::values)`; `resolution_message` `nullable|string|max:1000` (reporter-facing); `internal_note` `nullable|string|max:2000` (admin-only).
- **`App\Http\Requests\Admin\DismissReportRequest`** — authorize via `dismiss` policy; `internal_note` `nullable|string|max:2000`. (Optional per A4.)
- Controllers map only validated keys into model attributes (no `$request->all()`); `resolved_by_id`/`resolved_at` are set server-side only.

## 7. Data model, migration, constraints, indexes

**Migration:** `2026_11_12_000000_create_reports_table.php` (new migration; nothing existing is modified).

```
Schema::create('reports'):
  id
  reporter_id      FK users, cascadeOnDelete()      (reporter's account loss drops their reports, consistent with saved_searches.user_id)
  job_posting_id   FK job_postings, nullable, restrictOnDelete()
  company_id       FK companies, nullable, restrictOnDelete()
  reason           string(24)
  explanation      text nullable
  status           string(16) default 'pending'
  resolution_action string(32) nullable
  resolution_message text nullable
  internal_note    text nullable
  resolved_by_id   FK users, nullable, nullOnDelete()
  resolved_at      timestamp nullable
  open_marker      string(8) nullable default 'open'   -- dedupe marker, see below
  timestamps

  $table->unique(['reporter_id','job_posting_id','company_id','open_marker'], 'reports_open_dedupe');
  $table->index('reporter_id');                          // cap checks
  $table->index(['status','created_at']);                // queue default filter+sort
```

Notes:

- **Two nullable FKs instead of polymorphic morphTo:** consitent with the repo's real-FK discipline (`saved_searches` uses constrained FKs; `AGENTS.md` requires FK indexes). MySQL cannot foreign-key into polymorphic columns. An XOR CHECK (`DB::statement`) that exactly one of `job_posting_id`/`company_id` is set is added as defense in depth on both MySQL and SQLite; the same rule is enforced in validation regardless.
- **`open_marker` partial-unique emulation:** MySQL has no partial indexes. While a report is pending/investigating the marker is `'open'`; on resolve/dismiss it is `NULL`. The four-column unique index therefore (a) blocks two open reports by the same reporter for a subject at the DB level, and (b) allows re-reporting after a closed outcome. Fallback if the team rejects the synthetic column: validation-only duplicate guard (`lockForUpdate` + check inside a transaction); documented lower-guarantee.
- No SoftDeletes: reports are operational trust records but have no downstream FK dependents; activation/deactivation semantics do not apply. Restrict-on-delete FKs keep history if the job/company is ever removed.

**Model `App\Models\Report`:**

- `$fillable`: `reporter_id`, `job_posting_id`, `company_id`, `reason`, `explanation`, `status`, `resolution_action`, `resolution_message`, `internal_note` — controllers pass explicit validated values only.
- `casts()`: `status => ReportStatus::class`, `reason => ReportReason::class`, `resolution_action => ReportResolutionAction::class`, `resolved_at => datetime`.
- Enums (new, `app/Enums/`): `ReportReason`, `ReportStatus` (with `isOpen()`/`isResolved()` helpers), `ReportResolutionAction` (`no_action`, `content_removed`, `account_restricted`, `other`).
- Relations: `reporter(): BelongsTo(User)`, `jobPosting(): BelongsTo(Job, 'job_posting_id')`, `company(): BelongsTo(Company)`, `resolvedBy(): BelongsTo(User, 'resolved_by_id')`.
- Scopes: `scopeOpen` (`status IN pending, investigating`), `scopeForSubject(Builder, ReportStatus|string $status)` helpers as used.

## 8. Query, payload, and pagination plan

- **Admin queue: `admin/reports`** — default `status = pending`, newest first, `paginate(15)->withQueryString()`, mirroring `JobModerationController@index`.
    - Filters: `status` (`pending|investigating|resolved|dismissed|all`), `subject` (`job|company|all`), `reason`. Search is not planned (explanations are free text; searching them invites PII scanning).
    - Eager load `reporter:id,name` and `jobPosting:id,company_id,title,status` / `company:id,name,slug` — subject title derived (`subject_title` accessor in the controller-generated payload).
    - Select only needed columns (never `SELECT *`; explanation text can be long → excluded from the list payload, shown on detail page).
    - Index `(status, created_at)` serves the default queue query.
- **Admin show** — full report + context: reporter (id/name/email), explanation, internal note, resolution fields, subject details (via `jobPosting`/`company` with minimal columns; company `owner:id,name,email` when subject is a job, mirroring admin jobs show).
- **Public job show** — no new props besides the report-dialog trigger; the dialog receives the already-rendered job/company identifiers client-side. No report counts are exposed anywhere non-admin.

## 9. Service / Repository decision

**No dedicated Service or Repository.** This is a single-use, mostly-CRUD feature: write path = validate → dedupe-check (transaction + `lockForUpdate` around insert for the race window) → notify; admin path = transition validation and column updates. This matches the repo's precedent of thin controllers (`JobModerationController`, `SavedSearchController`) and the AGENTS decision tree. `NotificationService` is **reused** with two new methods:

- `reportSubmitted(Report $report)`: notify users holding `admin.access` **and** `reports.moderate` (mirrors `jobSubmittedForReview`'s conjunctive lookup).
- `reportOutcome(Report $report)`: notify the reporter (`ReportOutcomeNotification`); wrapped in the existing try/catch-`Log` resilience.

New notification classes: `App\Notifications\ReportSubmittedNotification`, `App\Notifications\ReportOutcomeNotification`. Message content: subject title + reason + outcome only — no reporter identity, no explanation text to anyone but admins; resolution message is admin-authored plain text.

## 10. React / Inertia plan

- **Types:** `resources/js/types/report.ts` (`ReportListItem`, `ReportDetail`, status/reason union literals) exported via `types/index.ts` barrel.
- **`resources/js/components/report/report-dialog.tsx`:** dialog with radio "This job / This employer", required reason `Select`, optional explanation `Textarea`, `useForm()` (`processing` disables submit, field errors surfaced, success toast via existing toast infra). Trigger button placed on `resources/js/pages/jobs/show.tsx`; for guests the dialog CTA links sign-in.
- **`resources/js/pages/admin/reports/index.tsx`:** mirrors `admin/jobs/index.tsx` — filter form (`router.get` with `preserveState`, query-string preserved), `Pagination` component, empty state ("No reports match this filter").
- **`resources/js/pages/admin/reports/show.tsx`:** prints reporter, subject card, explanation, notes; action buttons per current status (`investigate` on pending, `resolve` + `dismiss` on pending/investigating) each as its own `<form onSubmit>` posting via `useForm` with processing state and error display.
- **Navigation:** sidebar item "Reports" (`Flag` icon) with `requiredPermissions: ['admin.access','reports.moderate']` in `resources/js/lib/navigation.ts`.
- Wayfinder route definitions regenerate with the sources; **`npm run build` is mandatory** after route/controller changes (durable repo note).

## 11. Accessibility and states

- Dialog focus trap/Escape via `ui/dialog` (Radix); labels + `aria-describedby` on reason/explanation; errors announced visually near controls; submit disabled while processing.
- Admin queue empty state and zero-result filter state explicit; loading behavior ships with Inertia router defaults plus button `processing` spinners consistent with existing pages.
- Error feedback: validation field errors inline; transition violations → redirect back with `error` flash (matching `JobModerationController`).

## 12. Test matrix (repo style: PHPUnit classes, `RefreshDatabase`, `InteractsWithAuthorizationCatalogue`)

**`tests/Feature/ReportSubmissionTest.php`** (public side)

1. Guest POST `/reports` → redirect to login.
2. Job seeker reports published job → row created (`reporter_id` = actor regardless of payload), redirect + DB assert.
3. Company subject happy path.
4. Employer user may also report (A2).
5. Validation: missing subject/id, unknown reason, >2000 explanation → 422 with field errors; no row.
6. Non-public subject (draft job / closed job / inactive company / suspended owner) → validation error; no row (A3, IDOR guard).
7. Forged `reporter_id` in payload is ignored (row keeps actor's id).
8. Duplicate open report (same reporter, same subject) → 422; **after resolve** re-report allowed (open_marker) → row 2 OK.
9. Cap: ≥10 open reports → next is 422 (cap message).
10. Throttle: seed limiter via `RateLimiter::increment` pattern (`AuthenticationTest`) → `assertTooManyRequests`.
11. Resolve/dismiss does **not** mutate the target (job remains `published`; company remains active) — no auto-removal.

**`tests/Feature/Admin/ReportModerationTest.php`**

1. Guest → redirect to login on queue; authenticated non-admin/employer/job-seeker → 403.
2. Admin lacking `reports.moderate` → 403 (route middleware AND policy).
3. Admin with permission sees queue: default `pending`, filters status/subject/reason, pagination `withQueryString`, eager-loaded payload shape (`assertInertia`).
4. `investigate` valid transition pending→investigating; invalid transition (investigate a resolved row) → error flash, no change.
5. `resolve`: sets `status=resolved`, `resolution_action`, `resolved_by_id`/`resolved_at`, clears `open_marker`, persists `internal_note`, fires `ReportOutcomeNotification` to reporter (`Notification::fake`), toast.
6. `dismiss`: analogous.
7. Reporter receives outcome notification including optional resolution message; non-reporting users receive nothing.

**Regression:** untouched surfaces — full suite (517 passing pre-change) must remain green; add `reports`-table migration coverage implicitly via `RefreshDatabase`.

## 13. Security and performance risks

- **IDOR via subject selection:** both directions mitigated by A3 visibility closure + `exists` rejection to expected validation errors rather than 404 leaks.
- **Mass assignment:** explicit validated-only mapping; `resolved_by_id` never client-writable.
- **Spam/abuse:** 3 layers — `throttle:10,1`, per-reporter open cap (10), unique open-report-per-subject + race-safe `lockForUpdate` insert inside a transaction with the DB unique as the hard backstop.
- **PII exposure:** reporter identity/explanation/internal notes/`resolution_message` only ever load in admin-authorized controllers; nothing added to public or employer props; permissions regression covered by admin 403 tests.
- **Notification volume:** submission notification goes to a bounded permission-holder set (mirrors existing lookup), not `User::all()`.
- **Queue scale:** indexed `(status, created_at)` scan with bounded page size; explanation text excluded from list queries. Feature 31 audit logging is the natural next hook — recorded as a comment point, not implemented here.

## 14. Verification commands

- `php artisan test --filter=Report` (new suites)
- `php artisan test` (full suite; expect 517+2 file-worth of passing tests, no regressions)
- `vendor\bin\pint --test`
- `php vendor/bin/phpstan analyse --memory-limit=512M --no-progress`
- `npm run types:check`
- `npm run check`
- `npm run build`

## 15. Implementation sequence

1. Migration `reports` (+ enums-safe columns); run `php artisan migrate:fresh` on SQLite test env only via tests, no destructive commands against other envs.
2. Enums `ReportReason`, `ReportStatus`, `ReportResolutionAction`; `PermissionSlug::ReportsModerate` + `AuthorizationSeeder` mapping.
3. `Report` model (relations, casts, scopes) + `ReportPolicy`.
4. Form Requests (`StoreReportRequest`, `ResolveReportRequest`, `DismissReportRequest`).
5. Notifications + `NotificationService` methods.
6. `ReportController` + `Admin\ReportController` (thin; transitions + toast; transactional submit).
7. Route definitions (`routes/web.php`, `routes/admin.php`).
8. Frontend: type barrel, `report-dialog.tsx`, jobs/show CTA, `admin/reports/index.tsx` + `show.tsx`, nav item; `npm run build` for Wayfinder.
9. Tests (both suites), run the §14 gates, fix, re-run.

**Spec status: IMPLEMENTED.**

## 16. Implementation notes (2026-11-11)

- **Dedupe hardening (deviation from the §7 sketch):** the sketched 4-column unique `(reporter_id, job_posting_id, company_id, open_marker)` cannot enforce anything when a subject FK is NULL (MySQL/SQLite treat NULL components as distinct), so an added server-managed, non-null `subject_key` string column (`job:{id}` / `company:{id}` via `Report::subjectKeyFor()`) backs the unique index `(reporter_id, subject_key, open_marker)`. The XOR-subject CHECK is MySQL-only (SQLite cannot `ALTER TABLE ADD CHECK`); application validation enforces it on all drivers. Redundant standalone `reporter_id` index dropped — the unique index prefix covers cap queries.
- **Validation surface:** this session-based app surfaces Form Request failures as redirect-back-with-session-errors for non-XHR POSTs (repo precedent), so tests assert `assertSessionHasErrors` with exact messages and bag names (`report`, `reports`) rather than literal 422s.
- **Test suites:** `tests/Feature/ReportSubmissionTest.php` (12 tests), `tests/Feature/Admin/ReportModerationTest.php` (15 tests); full suite 544 passed / 3,937 assertions at implementation time.
- **Not exercised:** the MySQL-only CHECK path and migration `down()` rollback (test env is SQLite; reviewed only). Live-browser behavior of the report dialog/admin pages unverified (code-level contract check + build/type gates only).
