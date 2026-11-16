# Feature 37 — Visual recruitment pipeline

    status: IMPLEMENTED (approved 2026-11-16)
    depends-on: Feature 23 (employer applicant list/detail), Feature 24 (application status
    transitions and history), Feature 28 (company hiring-team members and scoped permissions)
    scope-note: Feature 28 is honored through the existing company-scoped permission model
    (`applicants.view` read / `applicants.manage` write). No new platform permission and no new
    company-scoped permission is introduced (D10). Feature 24 remains the single status engine
    (D1). Feature 36 private notes/evaluations and Feature 35 message content are never exposed on
    board cards (D8).

## 1. Goal / non-goals

### Goal

Give an authorized employer/company participant a **visual Kanban pipeline** of their company's job
applications, grouped into columns by the **existing Feature 24 application statuses**, so they can
scan the whole hiring funnel at a glance and move a candidate forward **using the same authorized,
audited Feature 24 transition process** that the application detail page already uses.

The board is a **view over the existing workflow**, not a second workflow.

### Non-goals (explicitly out of scope)

- **New application statuses** or a second/parallel status engine. The board renders
  `App\Enums\JobApplicationStatus` as-is (D1).
- **Bulk status changes** (multi-select "move 20 cards") — out of scope per `project-overview.md`.
- **Automatic candidate ranking, AI prioritization, scoring, or auto-rejection** — the board sorts
  chronologically (`created_at DESC, id DESC`), nothing else.
- **New private recruitment data on cards**: no notes, no evaluations, no resume content or
  filenames, no cover letter, no screening answers, no message content, no candidate email
  (D8). Card payload is restricted to an explicit column list.
- **Cross-company, cross-tenant, or global recruiter boards.** The board is scoped to exactly one
  company the actor may manage.
- **A separate transition endpoint, service, or Form Request.** The board reuses the Feature 24
  route, Form Request, Policy, and service verbatim (D1, D2).
- **New dependencies / packages.** Drag-and-drop uses the native HTML5 Drag and Drop API; the
  accessible path uses the already-vendored shadcn `DropdownMenu` (D13).
- **Real-time board updates.** Live updates are not required; the board refreshes after each move
  and on navigation. (Realtime messaging from Feature 35 is unaffected.)
- **Analytics, reporting, export, or print views** of the pipeline.
- **A candidate-facing pipeline** and any candidate view of employer-side pipeline state.
- **Public job-status or application-status exposure** — the board is behind employer auth.

## 2. User-visible behavior and acceptance criteria

1. An approved employer who may manage a company opens **`/employer/pipeline`** and sees a Kanban
   board for that company: one column per Feature 24 status, in pipeline order
   (`submitted → reviewing → shortlisted → interview_scheduled → offer_sent → hired`), followed by
   `rejected` and `withdrawn` (D5).
2. Each column header shows the **status label** and the **number of matching applications** in
   that column. Counts are computed on the server from the same filtered result set that produces
   the cards — they are never estimated client-side (D7).
3. Each application is a **card** showing only: candidate name, candidate headline, candidate
   location, the job title it was applied to, and the application date. A card links to the existing
   employer application detail page (`/employer/applications/{application}`) (D8).
4. The board can be **filtered** by company, by job, and by candidate name. Filtering is
   **server-side**: the URL query string drives the query, counts and cards always agree, and the
   filters survive a page reload (D4).
5. The board is **bounded**: only a limited number of cards per column is rendered at once
   (default 25, maximum 100), with a **"Load more"** control that increases that column's limit
   (D6). The full application set for a company is never loaded into the browser.
6. A participant holding the company-scoped **`applicants.manage`** permission can **move** a card to
   another status in two ways:
    - by **dragging** the card onto another column, and
    - by using the card's **status action menu** (a keyboard-operable dropdown) — the accessible,
      always-available path (D13).
7. Both interactions call the **same** Feature 24 transition endpoint, request, Policy check, and
   service. The client never writes a status directly, and the client never decides which
   transitions are legal — the allowed targets are delivered by the server as a transition matrix
   (D1, D9).
8. A move that Feature 24 **permits** succeeds: the application status changes, a
   `job_application_status_histories` row is written with the actor, the previous status, the new
   status and the optional reason, the candidate receives the existing
   `ApplicationStatusChangedNotification`, and the board refreshes so the card appears in its new
   column with updated counts (D18).
9. A move that Feature 24 **rejects** (for example `submitted → hired`, or moving a terminal
   application) returns a **useful error** that is surfaced to the recruiter (toast), leaves the
   application status unchanged, and **does not corrupt the board** — the board reconciles with the
   server state afterwards.
10. A **stale** move (another recruiter already moved the card since the board was loaded) returns
    the Feature 24 **409 conflict**; the board shows a clear "the application changed, board
    refreshed" message and reloads the affected column.
11. A participant with only **`applicants.view`** (the `reviewer` company role) sees the board
    **read-only**: cards are visible, but no status menu and no drag affordance is offered, and the
    transition endpoint still rejects their attempts with 403 (D10).
12. **Applications from other companies are never exposed** — not as cards, not in the counts, not
    in the job filter options, and not through a forged `?company=` parameter (D3).
13. A guest is redirected to authentication; a candidate/job seeker, a non-approved employer, and a
    platform administrator without employer rights receive **403**; a non-existent `?company=` id
    receives **404** (D3).
14. The board renders meaningful **empty states**: no authorized company (actionable next step), no
    applications matching the current filters (with a "clear filters" action), and an empty column
    ("No applications in this stage") — while the remaining columns and the counts stay visible.
15. All board actions are **usable without drag-and-drop** and are keyboard reachable (D13).

## 3. Assumptions / decisions (labeled — approve or override at the approval gate)

- **D1 — Feature 24 stays the single status engine.** The board introduces no new status, no
  transition matrix of its own, no second service, and no second Form Request. It reuses:
  `App\Enums\JobApplicationStatus` (including `allowedEmployerTransitions()`),
  `App\Services\ApplicationStatusTransitionService::transitionAsEmployer()`,
  `App\Http\Requests\Employer\TransitionApplicationStatusRequest`, and
  `App\Policies\JobApplicationPolicy::transitionStatus()`.
- **D2 — One transition route, two response shapes.** The board posts to the **existing** route
  `POST /employer/applications/{application}/status` (`employer.applications.update-status`). The
  existing action gains a **content-negotiated JSON branch**: when the request
  `expectsJson()` (the shared axios instance sends `Accept: application/json`), it returns a small
  JSON payload; otherwise it keeps the current `Redirect::back()->with('success', …)` behaviour
  unchanged for the application-detail form. There is no duplicated transition logic — one action,
  one service call, one notification, one audit/history write.
- **D3 — Company context is a query parameter.** `?company=<id>` selects the company. When omitted,
  the board uses the actor's **first authorized company** ordered by `name` then `id` (deterministic).
  A supplied id that does not exist → **404**; a supplied id the actor may not view → **403**. The
  board always shows exactly one company at a time.
- **D4 — Filter set.** `company` (context), `job` (optional job id, must belong to the selected
  company), `q` (optional candidate-name search, trimmed, ≤ 100 chars). All filters are applied in
  the database; nothing is filtered in the browser.
- **D5 — Columns.** All eight `JobApplicationStatus` cases are rendered, in the order above.
  Terminal columns (`hired`, `rejected`, `withdrawn`) are shown because they are part of the real
  pipeline, and a candidate can still be in `withdrawn`. Columns are always rendered (even at count 0) so the structure is stable.
- **D6 — Bounded loading.** Per-column limit supplied as `limit[<status>]` (array query parameter,
  e.g. `?limit[submitted]=50`). Default **25**, maximum **100**, integer only. Only columns whose
  count is greater than zero are queried. The board never issues an unbounded query and never
  renders the full application set.
- **D7 — Counts come from the server-side filtered result.** One aggregate query produces
  `status → count` for the exact filtered scope used by the cards. The job-filter option list is
  computed with **all active filters except the job filter itself**, so the recruiter can always
  switch jobs (and each option shows its matching count).
- **D8 — Card payload is minimal and privacy-safe.** Cards are produced from an **explicit selected
  column list** (no `SELECT *`, no eager loading of private relations):
  `job_applications.id`, `job_applications.status`, `job_applications.created_at`,
  `job_applications.job_posting_id`, `users.id`, `users.name`, `candidate_profiles.headline`,
  `candidate_profiles.location`, `job_postings.title`. **Never** exposed on the board: note bodies,
  evaluation ratings/comments, resume ids/filenames/mime types, cover letters, screening answers,
  message content, candidate email, candidate phone.
- **D9 — Transition rules are server-supplied.** The page prop `transition_matrix` maps each status
  to its allowed employer targets, computed server-side from
  `JobApplicationStatus::allowedEmployerTransitions()`. The client uses it only to render the menu
  and to decide whether a drop target is acceptable; the server re-validates every move.
- **D10 — No new permissions.** Read = existing company-scoped **`applicants.view`**; move = existing
  company-scoped **`applicants.manage`**. No `PermissionSlug` case, no `AuthorizationSeeder`
  change, no change to `CompanyMemberRole::permissions()`, and no change to
  `docs/authorization.md`. A `reviewer` therefore sees a read-only board; `owner` and `recruiter`
  can move cards.
- **D11 — One new Policy method.** `App\Policies\CompanyPolicy::viewPipeline(User $actor, Company
  $company): bool` → `$actor->isApprovedEmployer() && $actor->hasCompanyPermission($company,
  'applicants.view')`, mirroring the existing `viewAnalytics()` company-scoped pattern. The
  controller calls `$this->authorize('viewPipeline', $company)` before any query runs.
- **D12 — One new model scope, to avoid an N+1.** `Company::scopeForUserWithPermission(Builder
  $query, User $user, string $permission): Builder` resolves the role→permission mapping from
  `App\Enums\CompanyMemberRole::permissions()` (single source of truth) and filters in SQL:
  the company owner (when the owner role grants the permission) **or** an **active** membership
  whose `company_role` grants it. This produces the authorized-company list and the board's default
  company in **one query**, instead of calling `User::hasCompanyPermission()` inside a loop.
- **D13 — Native drag-and-drop plus a status action menu.** Drag-and-drop uses the **HTML5 Drag
  and Drop API** (no new package). The **shadcn `DropdownMenu` status action menu on every card**
  is the primary, always-present, fully keyboard-operable path — it is not a fallback that only
  appears when drag-and-drop is unavailable. Cards are `draggable` only when the actor holds
  `applicants.manage`.
- **D14 — Refresh strategy.** After a successful move the board performs an **Inertia partial
  reload** of the `board` prop (`router.reload({ only: ['board'], preserveScroll: true,
  preserveState: true })`) — never an axios re-fetch of page data (AGENTS.md rule 10). On failure
  the board shows a **sonner toast** carrying the server's message and then reconciles with a
  reload.
- **D15 — Read-only query service, no repository layer.** A single new
  `App\Services\RecruitmentPipelineService` composes the shared filter scope and the three query
  shapes (counts, job options, column cards). This mirrors the existing
  `App\Services\JobSearchService` precedent (a plain service whose job is to apply filters to a
  query builder) and avoids duplicating the filter scope across three queries. No repository
  interface, no new abstraction, and **no new service for the transition** (that stays in
  `ApplicationStatusTransitionService`).
- **D16 — No schema change by default.** The board needs no new table and no new column, and the
  existing indexes already serve the queries (§8). A wider composite index is **benchmark-gated**
  and only added if a representative measurement shows the sort dominating; the exact recipe and
  the MySQL FK-backing-index rules are in §7.
- **D17 — Read-only for everyone else.** Candidates, platform administrators, and public visitors
  have no board route and receive no board props.
- **D18 — Notifications are unchanged.** A board move triggers the **same**
  `NotificationService::applicationStatusChanged()` call as the detail page (the candidate is
  notified exactly once, with the same notification type). No new notification type, no duplicate
  notification, and no notification content beyond the existing name + job title + URL.
- **D19 — Audit log is unchanged.** Application status transitions are **not** audit-logged today,
  and the board introduces no new audited action. No `AuditAction` is added, and the board does not
  write to `audit_logs`.
- **D20 — No denormalization.** `company_id` is **not** copied onto `job_applications`. The board
  joins `job_postings`. `Job::$fillable` contains `company_id` and `UpdateJobRequest::jobData()`
  currently omits it (a job's company is immutable through the app today), but duplicating a
  tenancy key onto a high-volume fact table is exactly the kind of drift the project has avoided;
  the join is cheap and index-supported (§8).
- **D21 — The board collects an optional transition reason** _(amended 2026-11-16: the user
  requested it)_. A move opens a confirmation dialog showing the candidate, the from/to stage and
  an optional reason (≤ 500 characters), which is sent as `reason` on the existing Feature 24
  transition route. Drag-and-drop and the status menu both open the same dialog. The reason
  remains optional, so a move can be confirmed with a single click; the application detail page
  still offers the same field inline.
- **D22 — The board is an additional page, not a replacement.** `/employer/jobs/{job}/applicants`
  (Feature 23) and `/employer/applications/{application}` (Feature 23/24) keep their current
  behaviour. The board links to both; the applicant list gains a "Open pipeline" link scoped to
  that job.

## 4. Routes and access boundary

### New route

```php
// routes/web.php — inside the existing employer group
Route::get('pipeline', [RecruitmentPipelineController::class, 'index'])
    ->middleware(['role:employer', 'employer.approved'])
    ->name('pipeline.index');
```

- **Route name:** `employer.pipeline.index` → `/employer/pipeline`.
- **Middleware chain:** outer `auth` group → `role:employer` → `employer.approved`. The page
  requires an authenticated, employer-role, approved (not pending/suspended/rejected) account.
- **Read authorization (resource level):** `CompanyPolicy::viewPipeline` on the selected company —
  `applicants.view` (D11). A member without that permission gets 403.
- **Write authorization (per action):** the existing
  `JobApplicationPolicy::transitionStatus` — `applicants.manage` — enforced inside
  `TransitionApplicationStatusRequest::authorize()` and re-checked by the transition service
  through the same policy (D1).
- **Throttling:** the board page is a plain GET (no throttle added; it is a normal employer page).
  The transition route keeps its existing `throttle:30,1`.

### Reused route (unchanged route, extended response shape)

```php
Route::post('applications/{application}/status', [EmployerJobApplicationController::class, 'updateStatus'])
    ->middleware(['throttle:30,1'])
    ->name('applications.update-status');
```

`EmployerJobApplicationController::updateStatus` gains a JSON branch (D2). Non-JSON callers (the
existing application-detail form) behave **exactly as before**.

### Access boundary summary

| Actor                                        | Board page                | Move a card                   |
| -------------------------------------------- | ------------------------- | ----------------------------- |
| Guest                                        | redirect to login         | redirect to login             |
| Candidate / job seeker                       | 403 (`role:employer`)     | 403                           |
| Pending / suspended employer                 | 403 (`employer.approved`) | 403                           |
| Platform administrator (no employer company) | 403                       | 403                           |
| Approved employer, company owner             | 200                       | allowed (`applicants.manage`) |
| Active `recruiter` member                    | 200                       | allowed (`applicants.manage`) |
| Active `reviewer` member                     | 200 (read-only)           | 403                           |
| Non-member employer of the same company      | 403                       | 403                           |

## 5. Policy actions and dynamic role/permission resolution

### New Policy method

`App\Policies\CompanyPolicy::viewPipeline(User $actor, Company $company): bool`

```php
return $actor->isApprovedEmployer() && $actor->hasCompanyPermission($company, 'applicants.view');
```

Mirrors `viewAnalytics()`. Registered automatically (the app uses `Gate::guessPolicyNamesUsing` /
conventional resolution — verify during implementation that `CompanyPolicy` is already bound; it
is used by `CompanyController` today).

### Existing Policy reused (unchanged)

`App\Policies\JobApplicationPolicy::transitionStatus(User $actor, JobApplication $application): bool`
→ `isApprovedEmployer()` **and** the job's company is not null **and**
`hasCompanyPermission($company, 'applicants.manage')`.

### Permission resolution

- **Platform permissions:** none used, none added (D10).
- **Company-scoped permissions:** the existing `App\Enums\CompanyMemberRole::permissions()`
  namespace — `applicants.view` (read the board) and `applicants.manage` (move cards).
- **Resolution is live** — a revoked or downgraded member loses board read/move access on the very
  next request; there is no cached permission state on the page, the service, or the controller
  (and, per the project's durable note, no mutable state may be memoised on a controller or
  service).
- **Company list for the selector** is resolved with the new
  `Company::scopeForUserWithPermission()` (D12) so the role→permission mapping stays in
  `CompanyMemberRole::permissions()` and is applied in SQL — one query, no N+1.

## 6. Form Requests and validation

### `App\Http\Requests\Employer\RecruitmentPipelineRequest` (new, GET filters)

`authorize()` returns `true`; the **resource-level** authorization is the controller's
`$this->authorize('viewPipeline', $company)` (the pattern already used by `UpdateJobRequest` +
`JobController`). It validates **query input only**, never writes.

| Field     | Rule                            | Notes                                               |
| --------- | ------------------------------- | --------------------------------------------------- |
| `company` | `nullable`, `integer`, `min:1`  | company context id (D3)                             |
| `job`     | `nullable`, `integer`, `min:1`  | must belong to the selected company, else 404       |
| `q`       | `nullable`, `string`, `max:100` | trimmed in `prepareForValidation()`; empty → `null` |
| `limit`   | `nullable`, `array`             | per-column limits (D6)                              |

**Amendment (2026-11-16, implementation):** the board is a **GET page**, so a validation failure
would redirect back to the very same URL and could loop. Filter values are therefore
**normalised server-side instead of rejected**, so a request can never fail on a filter value:

- `company` / `job` that are not positive integers are treated as **absent** (no id filter).
- `q` is trimmed and **truncated to 100 characters**.
- `limit[<status>]` values are **clamped to 1..100**; a non-numeric value falls back to the
  default (25). A key that is **not** a known status is **ignored** so a stale bookmark still
  renders.

The maximum per column is therefore still enforced server-side — a client can never render more
than 100 cards per column — while the board always renders.

### `TransitionApplicationStatusRequest` (existing — unchanged)

Reused verbatim by the board (D1): `current_status` (required, `Rule::enum(JobApplicationStatus::class)`),
`new_status` (required, same enum), `reason` (nullable, string, max 500). The board always sends
`current_status` = the status the card was rendered with, which is what makes the service's
stale-check return 409.

### Client-supplied values the server never trusts

`company`, `job`, `q`, `limit`, `current_status`, `new_status`, and `reason` are all validated
server-side. The board never sends `job_id`, `user_id`, `company_id`, or a status to a new
application; the application is bound by route and re-authorized by the Policy.

## 7. Models, relationships, migration, constraints, indexes, deletion semantics

### Models touched

- `App\Models\JobApplication` — **read only** by the board. No new relation, no cast change, no
  fillable change. Existing relations (`jobPosting`, `user`, `statusHistories`, …) are untouched.
- `App\Models\Job` — **read only**.
- `App\Models\Company` — **one new query scope** `scopeForUserWithPermission()` (D12). No
  fillable/cast change.
- `App\Models\User`, `App\Models\CandidateProfile` — **read only**; joined explicitly with a
  narrowed column list (D8).

### Migration

**None required for the feature itself.** The board introduces no table, no column, and no
constraint.

### Indexes

Existing indexes that serve the board queries (verified in the repository):

| Index                                                            | Table              | Serves                                                                        |
| ---------------------------------------------------------------- | ------------------ | ----------------------------------------------------------------------------- |
| `job_postings_company_status_idx` `(company_id, status)`         | `job_postings`     | company scoping of the joined query                                           |
| `job_applications_posting_status_idx` `(job_posting_id, status)` | `job_applications` | per-job application lookup **and** the FK backing index for `job_postings_id` |
| `job_applications_created_at_idx` `(created_at)`                 | `job_applications` | chronological ordering tiebreak                                               |

**Benchmark-gated optional index (D16).** If — and only if — a representative measurement (§8)
shows the per-column sort dominating, add a **new** migration that widens the composite to
`(job_posting_id, status, created_at, id)` so the card query becomes an ordered index range scan:

- **Never edit the applied migration** `2026_11_14_000000_add_analytics_supporting_indexes.php`.
- Guard every DDL statement with `Schema::hasIndex()` and make `up()`/`down()` idempotent, exactly
  as that migration does.
- **MySQL/InnoDB owns foreign-key backing indexes**: the widened composite still backs the
  `job_posting_id` FK (leftmost prefix), but `down()` must restore
  `job_applications_job_posting_id_index` **before** dropping the composite, and must not drop an
  index a FK depends on.
- Verify the migration on the **dev MySQL** database, not only on the SQLite test suite.

No speculative indexes beyond that.

### Deletion semantics

Unchanged by this feature:

- Deleting a job cascades to its applications (`cascadeOnDelete`), which removes the cards and
  decrements the counts.
- Deleting a candidate cascades to their applications.
- `job_application_status_histories` keeps its existing cascade behaviour — the board does not
  write history directly; the Feature 24 service does.
- The board holds no state of its own, so there is nothing to retain or purge.

## 8. Query shape, selected columns, eager loading, pagination, scale

### 8.1 Shared filter scope (applied to every board query)

`RecruitmentPipelineService::apply(Builder $query, Company $company, array $filters): Builder`

```
job_applications
  JOIN job_postings ON job_postings.id = job_applications.job_posting_id
  WHERE job_postings.company_id = :company
  [AND job_postings.id = :job]                       -- job filter
  [AND users.name LIKE :q]                           -- candidate-name search (adds JOIN users)
```

- `users` is joined **only** when a search term is present, so the count query stays index-only in
  the common case.
- Every selected column is **fully qualified** after the joins.
- No `SELECT *`; no eager loading of private relations.

### 8.2 Q1 — Per-status counts (1 query)

```
SELECT job_applications.status, COUNT(*) AS aggregate
FROM job_applications
JOIN job_postings ON job_postings.id = job_applications.job_posting_id
[WHERE …filters…]
GROUP BY job_applications.status
```

Index-only over `job_applications_posting_status_idx` for the company-scoped case.

### 8.3 Q2 — Job filter options (1 query)

```
SELECT job_postings.id, job_postings.title, COUNT(*) AS aggregate
FROM job_applications
JOIN job_postings ON job_postings.id = job_applications.job_posting_id
[WHERE …filters except the job filter…]
GROUP BY job_postings.id, job_postings.title
ORDER BY aggregate DESC, job_postings.title ASC
```

Bounded by the number of jobs that actually have applications.

### 8.4 Q3 — Column cards (1 query **per non-empty column**, ≤ 8 total)

The bounded card set is resolved **first** and the display columns are joined **afterwards**, so
the number of joined rows is the column limit instead of every application of the column:

```sql
SELECT cards.id, cards.status, cards.created_at, cards.job_posting_id,
       users.id AS candidate_id, users.name AS candidate_name,
       candidate_profiles.headline, candidate_profiles.location,
       job_postings.title AS job_title
FROM (
    SELECT job_applications.id, job_applications.status, job_applications.created_at,
           job_applications.job_posting_id, job_applications.user_id
    FROM job_applications
    JOIN job_postings ON job_postings.id = job_applications.job_posting_id
    WHERE job_postings.company_id = :company
      AND job_applications.status = :status
      [AND job_postings.id = :job]
      [AND users.name LIKE :q]
    ORDER BY job_applications.created_at DESC, job_applications.id DESC
    LIMIT :limit
) AS cards
JOIN users ON users.id = cards.user_id
LEFT JOIN candidate_profiles ON candidate_profiles.user_id = cards.user_id
JOIN job_postings ON job_postings.id = cards.job_posting_id
```

- **One** query per column — **no N+1** (the joins are explicit, not eager loads).
- `candidate_profiles` is a **LEFT JOIN**: a candidate may have no profile.
- `ORDER BY created_at DESC, id DESC` — the `id` tiebreak keeps ordering deterministic under
  MySQL's second-precision timestamps.
- `LIMIT` is the per-column limit (≤ 100, D6).
- The shape was chosen by measurement, not by assumption — see §8.7.

### 8.5 Q4 — Authorized company list (1 query)

```
Company::query()->forUserWithPermission($user, 'applicants.view')
    ->select(['id', 'name'])
    ->orderBy('name')->orderBy('id')
```

### 8.6 Pagination / bounded loading

The board is a bounded, per-column "load more" surface rather than a single paginated list
(D6). Per request the server issues **at most 1 + 1 + 8 = 10 queries** plus the company list,
independently of the total number of applications. The shared `Pagination` component is **not**
used here (it paginates a single flat list); the board uses its own per-column control.

### 8.7 Scale considerations

- The card query is index-driven for the filtered `(job_posting_id, status)` slice; the residual
  cost is the sort of the matched rows per column.
- **Benchmark gate (D16) — executed 2026-11-16 on the dev MySQL database** with a synthetic
  dataset of **10 000 applications across 16 jobs** of one company (inserted and rolled back
  inside a transaction; the dev database was restored to its original state afterwards):

    | Query shape                                                              | 8 column queries (company wide) | 8 column queries (job filtered) |
    | ------------------------------------------------------------------------ | ------------------------------- | ------------------------------- |
    | Flat join (the §8.4 draft)                                               | 125.2 ms                        | 21.0 ms                         |
    | **Derived table (adopted)**                                              | **58.6 ms**                     | **9.7 ms**                      |
    | Derived table + widened index `(job_posting_id, status, created_at, id)` | 51.9 ms                         | 8.4 ms                          |
    | Flat join + widened index                                                | 138.3 ms                        | —                               |

    **Decisions taken from the measurement:**

    1. The **derived-table shape is adopted** — it resolves the bounded card set before the display
       joins and is ~2× faster than the flat join. It is a code change only, with no schema change.
    2. **No new index is added.** The widened composite improves the derived shape by only ~11 %
       while adding write overhead on every application insert and the MySQL FK-backing-index risk
       described in §7. The measurement does not justify a schema change, so the "avoid index
       speculation" rule applies.
    3. End-to-end timings of the **implemented service** on the same dataset: `statusCounts`
       31.7 ms, `jobOptions` 32.1 ms, the 8 company-wide column queries 70.2 ms, the 8 job-filtered
       column queries 34.3 ms, one column query with an active name search 26.0 ms.
    4. The remaining cost is linear in the number of applications **of the selected company**;
       the board never loads them all. A company with an order of magnitude more applications
       would need a per-company denormalised `company_id` (rejected by D20) — noted as the known
       ceiling of this design.

- `EXPLAIN` on the board queries confirms the expected index usage
  (`job_applications_posting_status_idx` for the application lookup,
  `job_postings_company_id_index` for the company scope, `candidate_profiles_user_id_unique` and
  the `users` primary key for the display joins).
- No cache, no queue, no background job is introduced. Nothing here is expensive enough to
  justify them, and the board must reflect the authoritative database state immediately.

## 9. Service / Repository decision

- **New:** `App\Services\RecruitmentPipelineService` — a **read-only query composer**, mirroring
  `App\Services\JobSearchService`. It owns:
    - `apply(Builder $query, Company $company, array $filters): Builder` — the shared scope (8.1);
    - `statusCounts(Company $company, array $filters): array` — `status => int` (8.2);
    - `jobOptions(Company $company, array $filters): Collection` — id/title/count (8.3);
    - `columnCards(Company $company, JobApplicationStatus $status, array $filters, int $limit): Collection`
      — the card rows (8.4).

    **Reason:** the filter scope is applied by three different queries; duplicating it across the
    controller would be the classic copy-paste drift the project rules forbid. This is the same
    justification that already produced `JobSearchService`.

- **Not added:** a repository interface, a query-object hierarchy, a DTO layer, a dedicated
  transition service, or a cache/queue layer. The board is a single read surface plus one reused
  write path.
- **Reused unchanged:** `App\Services\ApplicationStatusTransitionService` (the only writer of
  application status), `App\Services\NotificationService` (candidate notification on a move).

## 10. Inertia page props, React components, shared types, UI states

### 10.1 Page and props

Page: **`resources/js/pages/employer/pipeline/index.tsx`** → `Inertia::render('employer/pipeline/index', ...)`.

All board data lives under **one top-level prop `board`** so a post-move partial reload is
`only: ['board']` (D14).

```ts
export type PipelineStatus =
    | 'submitted'
    | 'reviewing'
    | 'shortlisted'
    | 'interview_scheduled'
    | 'offer_sent'
    | 'hired'
    | 'rejected'
    | 'withdrawn';

export type PipelineCard = {
    id: number;
    status: PipelineStatus;
    applied_at: string | null;
    candidate: {
        id: number;
        name: string;
        headline: string | null;
        location: string | null;
    };
    job: {
        id: number;
        title: string;
    };
};

export type PipelineColumn = {
    status: PipelineStatus;
    label: string;
    count: number;
    shown: number;
    has_more: boolean;
    items: PipelineCard[];
};

export type PipelineBoard = {
    company: { id: number; name: string } | null;
    companies: { id: number; name: string }[];
    jobs: { id: number; title: string; count: number }[];
    filters: {
        company_id: number | null;
        job_id: number | null;
        search: string | null;
    };
    status_counts: Record<PipelineStatus, number>;
    columns: PipelineColumn[];
    can_move: boolean;
    transition_matrix: Record<PipelineStatus, PipelineStatus[]>;
    status_labels: Record<PipelineStatus, string>;
};
```

Shared types file: **`resources/js/types/pipeline.ts`** (new), following the existing
`resources/js/types/recruitment.ts` (Feature 36) convention. No `any`; the status union is
explicit and mirrors the PHP enum.

Prop payload properties:

- `board.company` — the selected company (`null` only in the "no authorized company" empty state).
- `board.companies` — the authorized companies (for the selector), `id` + `name` only.
- `board.jobs` — the job filter options under the current filters, `id` + `title` + `count`.
- `board.filters` — the **applied** filters (echoed back so the UI can render the current state).
- `board.status_counts` — `status => count` for the whole filtered set (column counts read from
  `columns[].count`).
- `board.columns` — one entry per status, in order, each with `count`, `shown`, `has_more`, `items`.
- `board.can_move` — `true` when the actor holds `applicants.manage` on the selected company.
- `board.transition_matrix` — server-computed allowed employer targets per status (D9).
- `board.status_labels` — server-computed labels per status.

### 10.2 Components (new)

| Path                                                    | Responsibility                                                     |
| ------------------------------------------------------- | ------------------------------------------------------------------ |
| `resources/js/pages/employer/pipeline/index.tsx`        | page shell, prop wiring, filter + move orchestration               |
| `resources/js/components/pipeline/pipeline-board.tsx`   | column layout, horizontal scroll, empty/loading states             |
| `resources/js/components/pipeline/pipeline-column.tsx`  | column header (label + count), card list, empty state, "Load more" |
| `resources/js/components/pipeline/pipeline-card.tsx`    | one application card + status action menu + drag source            |
| `resources/js/components/pipeline/pipeline-filters.tsx` | company selector, job selector, name search, clear-filters         |

Existing shadcn primitives are reused: `Card`, `Badge`, `Button`, `Select`, `Input`, `DropdownMenu`,
`Skeleton`, `InputError`. **No new component library, no new package.**

### 10.3 Interactions

- **Filters** → `router.get(route, params, { preserveState: true, preserveScroll: true, replace: true })`
  (Inertia navigation, server-driven; no client-side filtering).
- **"Load more"** → `router.get` with `limit[<status>]` raised for that column only (other columns'
  limits are preserved in the query string).
- **Status menu move** → `http.post(route('employer.applications.update-status', applicationId),
  { current_status, new_status })` using the shared axios instance (**JSON response**, D2). On
  success → `router.reload({ only: ['board'], preserveScroll: true, preserveState: true })`. On
  error → `toast.error(message)` from the server payload, then a reconcile reload.
- **Drag-and-drop move** → the same axios call; the drop target is only accepted when the target
  status is in `transition_matrix[current_status]`; the card shows a pending state while the
  request is in flight and is disabled during it.
- **Move confirmation** → before any move lands (menu or drag-and-drop) the board opens a
  confirmation dialog: the candidate, the from → to stage and an optional **Reason** field
  (≤ 500 characters, with a counter). Confirming POSTs
  `{ current_status, new_status, reason?: string }` to the same Feature 24 route (JSON) and
  reloads the `board` prop on success; cancelling leaves the board untouched (D21, amended
  2026-11-16).

### 10.4 UI states

| State                                    | Behaviour                                                                                                                                                                                                                                |
| ---------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Loading / navigating                     | Inertia progress bar + column skeletons; the previous board stays visible until the response arrives                                                                                                                                     |
| No authorized company                    | Full-page empty state: "no company you can manage yet" + a link to `employer.companies.create` (or `employer.companies.index` when one exists)                                                                                           |
| No applications for the filters          | Board-level empty state + "Clear filters" action; columns still rendered at 0                                                                                                                                                            |
| Empty column                             | "No applications in this stage"                                                                                                                                                                                                          |
| Partial column                           | "Showing N of M" + "Load more" button                                                                                                                                                                                                    |
| Read-only board (`applicants.view` only) | No status menu, no drag affordance, no "Move" hint; a short read-only notice                                                                                                                                                             |
| Move confirmation                        | Dialog: "Move application" with the candidate, the from → to stage, and an optional reason (≤ 500 chars, with counter). Cancel leaves the board unchanged; Confirm performs the move. Focus-trapped and keyboard operable (Radix Dialog) |
| Pending move                             | The affected card is disabled and shows a spinner; other cards stay interactive                                                                                                                                                          |
| Stale move (409)                         | Toast: "The application status has changed since you loaded the page. The board has been refreshed." + reload                                                                                                                            |
| Invalid move (422)                       | Toast with the Feature 24 message ("Invalid status transition from X to Y.") + reload to reconcile                                                                                                                                       |
| Forbidden move (403)                     | Toast "You are not allowed to change this application's status." + reload                                                                                                                                                                |
| Validation error (422 on the request)    | Toast with the first field error                                                                                                                                                                                                         |

### 10.5 Accessibility

- Every card exposes a **keyboard-operable** status action menu (shadcn `DropdownMenu` → Radix),
  so **no action requires drag-and-drop** (acceptance criterion 15).
- The status menu is present on **every** card, not only on hover/focus.
- Columns are `role="list"` with `aria-label` = status label; cards are `role="listitem"` with an
  accessible name (candidate name + job title).
- The column header exposes the count as text (`"Reviewing (12)"`).
- Drag-and-drop is additive: HTML5 DnD is pointer-only, and the menu covers keyboard and touch.
- Focus is not moved by a move; the board reload preserves scroll and state, and the toast is
  announced by sonner.

### 10.6 Navigation

Add an employer entry to `resources/js/lib/navigation.ts`:

- label **"Pipeline"**, `href: employer.pipeline.index()`, `requiredRoles: ['employer']`.

Navigation visibility is presentation only; the server authorizes. The Wayfinder action
`employer.pipeline.index` is **generated** — `resources/js/actions/` is gitignored, so
`npm run build` must be run after the route is added (see §14).

Also add an "Open pipeline" link on `/employer/jobs/{job}/applicants` pointing at
`employer.pipeline.index()` with `?company=…&job={job}`.

## 11. Test matrix

New file: **`tests/Feature/Employer/RecruitmentPipelineTest.php`** (PHPUnit-style, matching the
existing `tests/Feature/Employer/JobApplicationTest.php` / `JobApplicationStatusTransitionTest.php`
convention). Extend the existing
`tests/Feature/Employer/JobApplicationStatusTransitionTest.php` only where the JSON response
branch (D2) needs explicit coverage.

### 11.1 Board visibility and scoping

1. An approved employer owner sees the board, grouped by status, for their company.
2. An active **recruiter** member sees the board (Feature 28 scoping).
3. An active **reviewer** member (`applicants.view`, no `applicants.manage`) sees the board and
   `board.can_move === false`.
4. A **guest** is redirected to the login page.
5. A **candidate / job seeker** receives 403.
6. A **pending or suspended employer** receives 403 (`employer.approved`).
7. A **platform administrator without employer company rights** receives 403.
8. An employer who is **not** a member/owner of the company receives 403 for `?company=<other>`.
9. `?company=<nonexistent>` → 404.
10. **No cross-company leakage**: applications of another company appear neither as cards nor in
    `status_counts` nor in the job options.
11. With no `company` parameter, the board defaults to the actor's first authorized company
    (deterministic by name then id); with several authorized companies the selector lists them all.
12. A **revoked** member loses board access on the next request (live permission check).

### 11.2 Filters, counts, and bounded loading

13. `?job=<id>` filters cards, counts, and the job options to that job.
14. `?job=` pointing at a job of **another company** → 404 (no leakage).
15. `?q=<name>` filters cards and counts by candidate name; a non-matching term yields an empty
    board with zero counts.
16. Counts always equal the server-side filtered result (assert exact numbers for a fixture).
17. The job-filter option list is computed **without** the job filter (so the user can switch jobs)
    and with the other filters applied.
18. The default per-column limit is 25 and `board.columns[].shown` reflects it.
19. `?limit[submitted]=50` raises only that column's limit; other columns are unchanged.
20. `?limit[submitted]=500` → clamped server-side to the maximum of 100 (the board still renders).
21. `?limit[not_a_status]=10` is ignored and does not error.
22. `board.columns[].has_more` is true only when the column's count exceeds what is rendered.

### 11.3 Transitions (board → Feature 24)

23. A valid board move (status menu) updates the status, writes exactly one
    `job_application_status_histories` row with the actor, the from/to statuses, and a timestamp.
24. A board move produces the existing `ApplicationStatusChangedNotification` for the candidate —
    exactly one.
25. An **invalid** transition (e.g. `submitted → hired`) returns 422 with a useful message and
    **does not** change the status and **does not** write history.
26. A **terminal** application offers no transitions and rejects a move (422).
27. A **stale** `current_status` returns **409** and does not change the status.
28. A **reviewer** (no `applicants.manage`) receives **403** on the transition endpoint, and the
    board renders no move controls for them.
29. An **unrelated employer** receives **403** on the transition endpoint.
30. The JSON branch (D2): a JSON request returns a JSON body with the new status; a **non-JSON**
    request still **redirects** with the success flash (existing behaviour preserved).
31. The board's move and the detail page's move produce the **same** history shape and the same
    notification (no behavioural drift between the two entry points).

### 11.4 Privacy of the board payload

32. The rendered board payload contains **no** note body, evaluation rating/comment, resume
    filename/mime/size, cover-letter text, screening answer, message content, candidate email, or
    candidate phone — asserted by inspecting the serialized Inertia response.
33. A card contains exactly the whitelisted keys (id, status, applied_at, candidate, job).
34. The board props are **not** present on candidate or admin pages.

### 11.5 Regression

35. The existing Feature 23/24 behaviour is unchanged: the applicant list page, the application
    detail page, the detail-page status form, candidate withdrawal, and the existing
    `JobApplicationStatusTransitionTest` suite all still pass unchanged.
36. `JobApplicationPolicy::transitionStatus` behaviour is unchanged (the board adds no new policy
    method for moves).

### 11.6 Frontend / static checks

37. `npm run types:check` — the new `resources/js/types/pipeline.ts` and components type-check
    with no `any`.
38. `npm run check` (vp) — formatting and lint clean.
39. `npm run build` — production build succeeds and regenerates the Wayfinder action for
    `employer.pipeline.index`.

## 12. Security / performance risks and mitigations

| #   | Risk                                                                                      | Mitigation                                                                                                                                                                                                                                                         |
| --- | ----------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| S1  | **IDOR / cross-company leakage** through a forged `?company=`, `?job=`, or application id | The company is authorized through `CompanyPolicy::viewPipeline` before any query; `?job=` must belong to the selected company (404 otherwise); the transition route re-authorizes the bound application through `JobApplicationPolicy::transitionStatus` (D3, D11) |
| S2  | **Privilege escalation** — a read-only member moving cards                                | Move requires the existing `applicants.manage` check inside `TransitionApplicationStatusRequest::authorize()`; the board merely hides the control (presentation only) and the server rejects the attempt (403)                                                     |
| S3  | **Client-side status forgery** (posting an arbitrary status)                              | The board sends only `current_status` + `new_status`; the Feature 24 service re-checks the matrix and aborts 422 for anything outside it; the transition matrix delivered to the client is advisory only                                                           |
| S4  | **Stale write / lost update**                                                             | The service's `lockForUpdate()` + `expectedCurrentStatus` check returns 409; the board surfaces it and reloads                                                                                                                                                     |
| S5  | **Private recruitment data leaking onto cards**                                           | Explicit selected-column list only (D8); no eager loading of notes/evaluations/resumes/messages; regression test 32 asserts the payload                                                                                                                            |
| S6  | **Unbounded loading** (a company with 100 000 applications)                               | Per-column `LIMIT` (default 25, max 100) and per-column queries (D6); no unbounded `get()`; the full set is never rendered                                                                                                                                         |
| S7  | **N+1 queries** on the board                                                              | Explicit joins with narrowed selects; ≤ 10 queries per board request regardless of data size (§8.6); the authorized-company list uses one scoped query (D12)                                                                                                       |
| S8  | **Unguarded index DDL on MySQL**                                                          | No schema change by default; any index change is a **new** migration, `Schema::hasIndex()`-guarded and idempotent, restoring the FK backing index in `down()` (§7)                                                                                                 |
| S9  | **Duplicate notifications** (one per move)                                                | The board reuses the existing `updateStatus` action, which notifies exactly once per successful transition (D18)                                                                                                                                                   |
| S10 | **Drag-and-drop bypassing validation**                                                    | Drag-and-drop calls the same endpoint with the same payload as the status menu; the client-side matrix check is only a UX affordance (D1, D9)                                                                                                                      |
| S11 | **Stale/cached permissions** after a revocation                                           | Permissions are resolved live per request; nothing is memoised on a controller or service (D12)                                                                                                                                                                    |
| S12 | **Payload size** (8 columns × 100 cards)                                                  | The default limit is 25 (≈ 200 cards max); the hard maximum is 100; cards carry only 9 fields                                                                                                                                                                      |
| S13 | **Client-side filtering of a large dataset**                                              | All filtering happens in SQL (D4); the browser never receives the unfiltered set                                                                                                                                                                                   |

## 13. Rollout / compatibility notes

- **No migration, no new table, no new column, no new package, no new dependency, no queue, no
  cache.** The feature is additive and reversible by removing the route + page + components.
- **Backward compatible:** the existing application-detail status form keeps its redirect + flash
  behaviour; only requests that send `Accept: application/json` receive the new JSON body (D2).
- **Wayfinder:** `resources/js/actions/` is generated and gitignored. `npm run build` must be run
  after the route is added so `employer.pipeline.index()` resolves; a missing generated action
  breaks the frontend build, not the backend.
- **Feature 28 compatibility:** company members are supported from day one through the existing
  company-scoped permission model; no separate Feature 28 work is required.
- **Feature 24 compatibility:** the transition matrix, history, notifications, and the interview
  auto-cancel rule on leaving `interview_scheduled` are unchanged — the board inherits them.
- **Feature 35 / 36 compatibility:** untouched. No new notification, broadcast, note, evaluation,
  or conversation behaviour. The board shows no message or note data.
- **No migration of existing data** and no backfill.
- **Feature 33 remains deferred** and is not touched by this feature.
- The board adds no `PermissionSlug`, so `docs/authorization.md`,
  `App\Enums\PermissionSlug`, and `AuthorizationSeeder` are **not** modified (D10).

## 14. Implementation sequence

Ordered per `AGENTS.md` §4, adjusted to this feature's shape (it is mostly a read surface plus a
reuse of an existing write path):

1. **Confirm the baseline** — `git status` clean; note the pre-existing flaky tests
   (`JobFactory` random `application_deadline`; three tests already pinned) and pin the deadline in
   any new deadline-dependent fixture.
2. **Policy** — add `CompanyPolicy::viewPipeline()`.
3. **Model scope** — add `Company::scopeForUserWithPermission()`.
4. **Form Request** — add `RecruitmentPipelineRequest` (query-filter validation only).
5. **Service** — add `RecruitmentPipelineService` (read-only query composer, §9).
6. **Controller** — add `RecruitmentPipelineController@index` (thin: authorize → resolve filters →
   call the service → map props).
7. **Route** — add `GET /employer/pipeline` in the employer group.
8. **Existing transition action** — add the JSON branch to
   `EmployerJobApplicationController::updateStatus` (D2), keeping the redirect path unchanged.
9. **Frontend** — `resources/js/types/pipeline.ts`, then
   `resources/js/components/pipeline/{pipeline-filters,pipeline-board,pipeline-column,pipeline-card}.tsx`,
   then `resources/js/pages/employer/pipeline/index.tsx`; add the navigation entry; add the
   "Open pipeline" link on the applicant list page.
10. **Run `npm run build`** so the generated Wayfinder action exists.
11. **Tests** — `tests/Feature/Employer/RecruitmentPipelineTest.php` covering §11; extend the
    transition test for the JSON branch.
12. **Verification** — run the commands in §15; run `EXPLAIN` on the three board queries on dev
    MySQL and record the result; decide the benchmark gate in D16.
13. **Record** — update this spec's §16 implementation record and `memory.md`; note any
    unverified item (e.g. no browser QA) explicitly.

## 15. Verification commands available in this repository

```bash
# Backend
php artisan test --filter=RecruitmentPipeline
php artisan test
vendor\bin\pint
php vendor/bin/phpstan analyse --memory-limit=512M --no-progress

# Frontend
npm run types:check
npm run check          # vp check (format + lint); `npx vp check --fix` to autofix
npm run build          # also regenerates the gitignored Wayfinder actions

# Database (dev MySQL — the test suite runs on SQLite in-memory)
php artisan migrate:status
php artisan migrate    # only if the benchmark-gated index in §7 is added
```

Query verification for §8 (run against the dev MySQL connection):

```sql
EXPLAIN SELECT job_applications.status, COUNT(*) FROM job_applications
  JOIN job_postings ON job_postings.id = job_applications.job_posting_id
  WHERE job_postings.company_id = ? GROUP BY job_applications.status;
```

## 16. Implementation record (2026-11-16)

- **Approval:** the user approved this spec on 2026-11-16 and requested the implementation.
- **Status: IMPLEMENTED.**
- **Files added**
    - `app/Policies/CompanyPolicy.php` — `viewPipeline()` (D11).
    - `app/Models/Company.php` — `scopeForUserWithPermission()` (D12).
    - `app/Http/Requests/Employer/RecruitmentPipelineRequest.php` — query-filter normalisation.
    - `app/Services/RecruitmentPipelineService.php` — read-only query composer (D15).
    - `app/Http/Controllers/Employer/RecruitmentPipelineController.php` — the board page.
    - `routes/web.php` — `GET /employer/pipeline` → `employer.pipeline.index`.
    - `resources/js/types/pipeline.ts`, `resources/js/components/pipeline/{pipeline-filters,pipeline-board,pipeline-column,pipeline-card}.tsx`,
      `resources/js/pages/employer/pipeline/index.tsx`.
    - `tests/Feature/Employer/RecruitmentPipelineTest.php` — 42 tests.
- **Files modified**
    - `app/Http/Controllers/Employer/EmployerJobApplicationController.php` — `updateStatus()` gained
      the negotiated JSON branch (D2). The redirect + flash path is unchanged.
    - `app/Models/Company.php`, `app/Policies/CompanyPolicy.php`, `routes/web.php`.
    - `resources/js/lib/navigation.ts` — the "Pipeline" employer entry.
    - `resources/js/pages/employer/jobs/applicants/index.tsx` — the "Open pipeline" link (D22).
- **No schema change, no new package, no new dependency, no queue, no cache** (D16).
- **Deviations from the spec text, all within its approved decisions:**
    1. **§6 — filter values are normalised instead of rejected.** The board is a GET page, so a
       validation failure would redirect back to the same URL and could loop. `company`/`job` that
       are not positive integers are treated as absent, `q` is trimmed and truncated to 100
       characters, and `limit[<status>]` values are clamped to 1..100. The per-column maximum is
       still enforced server-side.
    2. **§8.4 — the column query uses a derived table** so the bounded card set is resolved before
       the display joins. Chosen by measurement (§8.7), ~2× faster than the flat join.
    3. **§8.1 — the `users` join** is only applied to the inner query when a search term is
       present; the display joins of the card query are applied to the derived table.
    4. **§10.3 — `router.reload({ only: ['board'] })`** does not accept `preserveScroll` /
       `preserveState` in the Inertia v3 type definitions, but `doReload()` sets both
       unconditionally at runtime, so the behaviour required by D14 is met.
    5. **§2 item 12 / access table — a revoked member receives 403**, not an empty board: an actor
       with a company membership but no read permission is denied explicitly, while an employer
       without any company relationship still receives the actionable empty state.
- **Verification (all run on the current tree)**
    - `php artisan test` — **851 passed / 851, 6818 assertions** (809 before this feature; +42).
    - `php artisan test --filter=RecruitmentPipeline` — **42 passed / 398 assertions**.
    - `vendor\bin\pint` — passed. `php vendor/bin/phpstan analyse --memory-limit=512M --no-progress`
      — 0 errors.
    - `npm run types:check`, `npm run check` — passed. `npm run build` — exit 0 (Wayfinder
      regenerated; build output is gitignored).
    - `php artisan migrate:status` — the dev MySQL database is reachable and unchanged (no
      migration was added).
    - Benchmark gate — executed, see §8.7.
- **Not verified:** no browser QA has been performed for the board (drag-and-drop gesture
  behaviour, toast wording, focus behaviour after a reload). The backend contract and the
  transition error payloads are covered by automated tests.
- **Amendments after implementation (2026-11-16, user-approved)**
    1. **Search filter**: added a candidate-name search (`?q=`) to the board (§6/§8.1) — the user
       reported the filter was needed; tests cover spaces, casing and company+job scoping.
    2. **Move confirmation dialog**: the user asked for an optional reason input on the board's
       "Move to" flow, mirroring the application detail status form (D21). A confirmation dialog now
       opens for both menu and drag-and-drop moves; the reason is optional (≤ 500 chars) and sent on
       the existing Feature 24 route. No server-side change was required — `TransitionApplicationStatusRequest`
       already accepted `reason`.

    3. **Closed-job read-only (2026-11-16, bug fix, cross-feature)** — all employer write actions on
       an application are rejected once its job is `closed`: status transitions (pipeline and detail
       page), interview schedule/reschedule/cancel, recruiter notes/evaluations and messaging (both
       participants). Read access remains for anyone who could already view the record; candidates can
       still withdraw. UI: detail page shows a read-only banner and hides write controls; the board
       renders closed-job cards as non-draggable, menu-less, labelled "Closed job". The server remains
       authoritative via policies and the transition/message services (`ClosedJobReadOnlyTest`, 12 tests).
