---
name: feature-29-saved-searches-and-job-alerts
description: Specification for Feature 29 — Saved searches and job alerts for candidates
metadata:
    type: project
    status: IMPLEMENTED
    created: 2026-11-09
---

# Feature 29 — Saved searches and job alerts

## 1. Goal / non-goals

**Goal:** Let job seekers save the exact filter set of the public job search (Feature 16), rerun it in one click, and optionally receive in-app alerts (reusing Feature 25 notifications) when newly published jobs match the stored criteria.

**In scope:**

- `saved_searches` table storing per-user search criteria + alert preference.
- Save / rerun / edit / delete saved searches (candidate-only).
- "Save this search" affordance on the public jobs listing (only for job seekers, only with active filters).
- Scheduled `daily` / `weekly` alert dispatch command producing in-app notifications for newly published matching jobs, with duplicate-alert prevention via an alert ledger.
- Extraction of the public-search query logic into a shared, reused query helper so saved-search matching is provably consistent with the public listing.

**Non-goals:**

- Email alerts, push notifications, external message channels (Feature 25 boundary).
- Instant-on-publish alerts. Alert generation is schedule-driven only (daily/weekly digests). The ledger design leaves the door open, but publish hooks are explicitly deferred — there is no scheduler infra in the repo today; adding one entry-point keeps the slice small.
- Salary-range search criteria. The public listing (Feature 16) has no salary filter today, so saved criteria cannot include one; if public search later gains filters, they can be added here deliberately.
- Job recommendations / ranking (Feature 34), monetized alerts (Feature 33), report/audit features (Feature 30/31).
- Any change to public job visibility rules — candidate alerts never include non-public jobs.

## 2. User-visible behavior and acceptance criteria

- On `/jobs`, a signed-in job seeker with active filters sees a "Save this search" button. It opens a dialog with: optional name (placeholder shows an auto-generated summary), an on-by-default "Alert me about new matching jobs" switch, and a frequency select (Daily / Weekly, shown when alerts are on).
- Saving redirects/refreshes with a success toast. The search is stored with the exact criteria currently applied to the listing.
- A dedicated page `/candidate/saved-searches` lists the seeker's searches (paginated 10), each with: name/summary, criteria badges, alert preference, last alert run, and actions Rerun / Edit / Delete.
- "Rerun" navigates to `jobs.index` with the saved criteria — results must be identical to manually applying the same filters (same server query logic).
- After an alert run, the owner receives one in-app notification per search that matched new jobs: "N new jobs match your saved search '…' (top: Job A, Job B…)", linking to the rerun URL.
- Disabled alerts (switch off) stop generating notifications but keep the saved search.
- A job only ever alerts a user once per search (ledger), even if the scheduler is delayed or re-run.
- Within one run, a user is never alerted for the same job twice, even across overlapping saved searches.
- Non-public jobs (draft, pending_review, rejected, closed, inactive company, non-approved owner company) are never alerted, and alert notifications contain no data beyond the public listing payload.
- Editing a search changes criteria/label/frequency only; it does not backfill alerts for jobs published since the previous run.

**Acceptance criteria (testable):**

1. Save with valid criteria → row persisted with `user_id` from auth (never request).
2. Guest → redirected to login; non-job-seeker → 403 on all saved-search routes.
3. Owner can list/rerun/edit/delete; another candidate or guesser gets 403/404.
4. Exceeding the per-user cap (20) → 422 validation error, no row created.
5. Invalid criteria (unknown category/type ids, >max lengths, bad frequency) → 422 field errors.
6. Command run for `daily`/`weekly` notifies only owners of enabled searches with that frequency; opted-out users get no notifications.
7. Re-running the command produces zero new notifications (duplicate prevention).
8. Jobs matching but not publicly visible → no notification, no ledger row.
9. Job that matched on a delayed schedule is still alerted exactly once (ledger catch-up), and never twice.
10. Overlapping searches of one user: same job appears in at most one notification per run.
11. `last_alerted_at` updated per processed search; disabled state untouched.

## 3. Assumptions / open questions

**Explicit assumptions (no blocking questions — all reviewable here):**

1. **Criteria set = current public-search filter set**: `search` (text), `category_id`, `employment_type_id`, `workplace_arrangement_id`, `experience_level_id`. This is exactly what `PublicJobController::index` accepts today.
2. **Digest-only alerting**: `daily` at ~07:05 and `weekly` (Monday ~07:15) via the Laravel scheduler. No instant alerts; no email. Environment must run `schedule:work` (or cron); documented in README — this is a new, but mandatory, operational note.
3. **Frequency options**: `daily` | `weekly` (string-backed enum `AlertFrequency`). Switching frequency does not backfill.
4. **Opt-in default**: the save dialog proposes alerts ON at `daily`; storage default matches; user can opt out before/after saving.
5. **Optional name** (max 120). When empty, UI renders a deterministic criteria summary (same builder as server-side list summary).
6. **Duplicate saved searches allowed** (user may want same criteria under two names/frequencies). Overlapping searches are deduplicated per-run at the user level (§9).
7. **Per-user cap: 20 saved searches** — bounds per-user alert cost at scale; enforced as validation with a friendly message.
8. **Notification per search per run** (not merged across searches): at most one notification per matching search, mentions up to 3 job titles and the count, links to rerun.
9. **Per search cap: 50 new matches per run** (overflow silently deferred to next run; ledger keeps correctness).
10. **Catch-up window:** candidate jobs limited to `job_postings.created_at >= (last_alerted_at ?? search creation) − 1 h slack`, hard-clamped to the last 30 days — bounded work per run; ledger ensures no duplicates regardless of windowing.
11. **Newly created search sets `last_alerted_at = now`** at creation — the candidate is looking at the existing matches already; only future jobs alert.
12. **No new platform permission slugs.** Candidate role + ownership Policy only (same authorization model as Saved jobs / Features 25–28 precedent).
13. Catalogue FKs on criteria use `restrictOnDelete` (catalogues are deactivated, not deleted, per Features 5–9); a saved search referencing a since-deactivated option remains valid (validation uses `exists`, not `active`) and rerun simply yields fewer/zero results.
14. `search` text LIKE-wildcards (`%`, `_`) are not escaped — deferring the (non-blocking) wildcard-escaping fix; both public search and alert matching behave identically either way (consistency requirement).

## 4. Routes and access boundary

All under `Route::prefix('candidate')->name('candidate.')->middleware(['role:job_seeker'])` (auth group), consistent with `saved-jobs`:

| Method | URI                                       | Name                               | Extra middleware |
| ------ | ----------------------------------------- | ---------------------------------- | ---------------- |
| GET    | `/candidate/saved-searches`               | `candidate.saved-searches.index`   | —                |
| POST   | `/candidate/saved-searches`               | `candidate.saved-searches.store`   | `throttle:20,1`  |
| PUT    | `/candidate/saved-searches/{savedSearch}` | `candidate.saved-searches.update`  | `throttle:20,1`  |
| DELETE | `/candidate/saved-searches/{savedSearch}` | `candidate.saved-searches.destroy` | —                |

- Group-level `role:job_seeker` is the route access boundary; Policies enforce the per-record authority (AGENTS law: middleware boundary AND policy, server-authoritative).
- Route-model binding by `{savedSearch}` (model name convention, same as `{jobPosting}`).
- Public `jobs.index` remains unchanged as the rerun target (query params are the single source of truth both ways).
- No admin routes. No permission middleware additions.

## 5. Policy actions and dynamic role/permission resolution

New `app/Policies/SavedSearchPolicy.php`, mirroring `SavedJobPolicy` style:

| Action                                   | Rule                        |
| ---------------------------------------- | --------------------------- |
| `viewAny(User)`                          | `hasRole(JobSeeker)`        |
| `create(User)`                           | `hasRole(JobSeeker)`        |
| `view(User $actor, SavedSearch $search)` | `$actor->is($search->user)` |
| `update(User, SavedSearch)`              | owner only                  |
| `delete(User, SavedSearch)`              | owner only                  |

- Controllers call `$this->authorize(...)` before any read/mutation (same as `SavedJobController`).
- Per-user cap (20) is a validation rule, not a policy concern.
- Per-user deletions are `where user_id` scoped queries before authorize-on-record, matching `SavedJobController::destroy` (prevents resource enumeration).
- The alert dispatcher command is server-side infrastructure operating as the system on stored rows — it needs no Policy (system-internal batch job, no user actor).

## 6. Form Requests and validation

- `app/Http/Requests/Candidate/StoreSavedSearchRequest.php` and `UpdateSavedSearchRequest.php` (self-contained, like `StoreCompanyMemberRequest`/`UpdateCompanyMemberRequest`).
- `authorize()` returns `$this->user()->can('create', SavedSearch::class)` / `...->can('update', $savedSearch)` — Policy does the real authorization.
- Rules (identical for store and update; update = full PUT replacement of criteria):
    - `search`: nullable string, `max:180`; trimmed, normalized (`String::trim`), plain text (rendered via Inertia escaping — no HTML filtering needed).
    - `category_id`: nullable integer + `exists:job_categories,id`.
    - `employment_type_id`: nullable integer + `exists:employment_types,id`.
    - `workplace_arrangement_id`: nullable integer + `exists:workplace_arrangements,id`.
    - `experience_level_id`: nullable integer + `exists:experience_levels,id`.
    - `alerts_enabled`: `boolean`.
    - `alert_frequency`: `string`, `in:daily,weekly`.
    - `name`: nullable string, `max:120`.
    - Cap check (store only): closure failing with `You can save up to :max searches.` when `savedSearches()->count() >= 20`.
- Criteria may be entirely empty (an "everything" search is valid — notes it as low-value but harmless) — accepted; summary labels render as "All jobs".
- `user_id`, `last_alerted_at`, ledger fields are never accepted from the input.

## 7. Data model / migration / FKs / indexes

New migration `2026_11_11_000001_create_saved_searches_and_alerts_tables.php` (all FKs as in-house convention):

**`saved_searches`**

| Column                   | Type                      | Notes                                     |
| ------------------------ | ------------------------- | ----------------------------------------- |
| id                       | PK                        |                                           |
| user_id                  | FK users                  | `cascadeOnDelete` — private per-user data |
| name                     | varchar(120)              | nullable                                  |
| search                   | varchar(180)              | nullable — mirrors public `search` param  |
| category_id              | FK job_categories         | nullable, `restrictOnDelete`              |
| employment_type_id       | FK employment_types       | nullable, `restrictOnDelete`              |
| workplace_arrangement_id | FK workplace_arrangements | nullable, `restrictOnDelete`              |
| experience_level_id      | FK experience_levels      | nullable, `restrictOnDelete`              |
| alerts_enabled           | boolean                   | default `true` (opt-out is a user action) |
| alert_frequency          | varchar(16)               | default `'daily'` (`daily                 | weekly`) |
| last_alerted_at          | timestamp                 | nullable; null = never alerted            |
| timestamps               |                           |                                           |

Indexes: `unique(user_id, name)`? **No** — duplicates allowed (assumption 6); only `index(user_id)` (coverage for per-user listing and dispatcher chunking).

**`saved_search_alerts`** (ledger — the duplicate-alert anchor)

| Column          | Type              | Notes                                              |
| --------------- | ----------------- | -------------------------------------------------- |
| id              | PK                |                                                    |
| saved_search_id | FK saved_searches | `cascadeOnDelete`                                  |
| job_posting_id  | FK job_postings   | `cascadeOnDelete` (same as `saved_jobs` precedent) |
| timestamps      |                   |                                                    |

Indexes: `unique(saved_search_id, job_posting_id)` — enforces one alert per job per search at the DB level (concurrency-safe even with overlapping scheduler runs); secondary `index(job_posting_id)` for visibility-change checks / cleanup reasoning (only if a concrete query needs it — otherwise **not added** to avoid speculation; primary uniqueness covers all planned queries).

**Index on `job_postings`** (separate migration section in the same file): `index(['status', 'created_at'])`. Justified by the exact `status = published ± created_at window` + `latest()` ordering shape shared by the public listing (Feature 16, currently unindexed) and every alert window query. Not speculative: both access paths are in this codebase today.

**Models:**

- `App\Enums\AlertFrequency` (string-backed: `Daily`, `Weekly`) with labels for UI.
- `App\Models\SavedSearch`: `$fillable` limited to `user_id, name, search, category_id, employment_type_id, workplace_arrangement_id, experience_level_id, alerts_enabled, alert_frequency`; casts `alerts_enabled` → bool, `alert_frequency` → `AlertFrequency`, relations `user`, `category`, `employmentType`, `workplaceArrangement`, `experienceLevel`, `alerts (HasMany)`. Helper `criteria(): array` returning the public-listing-shaped filter map (server reuse in rerun URL/notification payload).
- `App\Models\SavedSearchAlert`: minimal model, no Policy (internal ledger).
- `App\Models\User::savedSearches(): HasMany`.

No changes to existing tables other than the one new `job_postings` index. No payload mutation of `job_postings`.

## 8. Query / pagination / eager-load plan

- **Listing page** (`index`): `SavedSearch::where('user_id')` → `paginate(10)`; eager-load `category:id,name`, `employmentType:id,name`, `workplaceArrangement:id,name`, `experienceLevel:id,name` (≤4 × ≤10 rows); select bounded columns. Option lists (categories/types/arrangements/levels) reuse the exact queries of `PublicJobController::index` (`active()`, bounded selects, ordered). No N+1.
- **Rerun**: `Link`/`router.visit(route('jobs.index', criteria))` (Wayfinder module if generated, else constructed URL from the `criteria` prop) — no server-side reimplementation, no duplicate query path.
- **Dispatcher (per scheduled run)** — chunked, bounded:
    1. `User::whereHas('savedSearches', enabled + frequency)` → `chunkById(250)`.
    2. Per user: user's enabled searches filtered to the run's frequency (single query, eager-loaded criteria relations limited to `id/name` selects).
    3. Precompute `recentlyAlertedJobIds` = ledger rows for ANY of the user's searches with `created_at >= min-window` (one bounded query; bound ≈ user's ≤20 searches × ≤50 matches/day) — used to suppress cross-search duplicates and delayed-rerun duplicates.
    4. Per search: match query =
       `Job::published()` + public-visibility whereHas (company active + owner approved) `+ created_at >= catchUpStart` `+ id not in (recentlyAlertedJobIds ∪ pickedInRun)` `->orderByDesc('id')` `->limit(50)` **+ `JobSearchService::apply` criteria (shared logic)** — selects bounded columns (`id, title, company_id, created_at`, company `name` only) for notification payload.
    5. If matches: send notification (NotificationService), insert ledger rows, merge `pickedInRun`, set `last_alerted_at = now()`.
    6. All per-user writes in one DB transaction (ledger + `last_alerted_at` + notification rows commit/fail together — notification is a local DB write, consistent with in-app-only channel; try/catch per the NotificationService precedent so one user failure never aborts the batch, logged).
- **Extraction (`JobSearchService`)** — see §9: `filtersFromRequest()`, `apply(Builder $jobQuery, array $filters)`, `visibility(Builder)`; `PublicJobController::index`/`show` refactored to use them with zero behavior change (regression-protected by `tests/Feature/PublicJobListTest.php` + full suite).
- Database-level filtering/sorting only; no in-memory filtering. No pointer/cursor pagination needed here (small per-user lists).

## 9. Service / Repository decision

- **`App\Services\JobSearchService` (new, justified by reuse):** the public-search criteria application is now used by two independent consumers (public listing controller + scheduled matcher). Per AGENTS decision tree ("query reused in multiple places"), a small stateless service is warranted — mirrors `ScreeningTemplateService` precedent. Static/instance kept consistent with existing Services; unit-tested via both consumers rather than mocked.
- **No Repository:** query complexity is criteria composition on a single table + 1 whereHas visibility clause — a query object would be ceremony, not architecture.
- **Dispatcher logic lives in a Command calling the Service**; no separate "AlertService" — the command's orchestration (chunking, ledger, transactions) is console infrastructure, not business logic invoked from UI paths.
- **No queued jobs** (per AGENTS: no queues without evidence): synchronous chunk processing in the scheduler process; notifications follow Feature 25's in-process try/catch write pattern. Bounded work (users × searches × 50 matches), 30-day clamp, per-user caps — scale-safe by construction. Revisit with real numbers if a single run ever exceeds the maintenance window.
- **No new scheduler-infra beyond entries:** `Schedule::command('saved-searches:dispatch-alerts daily')->daily()->at('07:05')` and `..., ['frequency' => 'weekly'])->weeklyOn(1, '07:15')` in `routes/console.php` (Laravel 11+ inline scheduling, no Kernel). `onOneServer()` omitted (needs a cache-lock backend; single-node today — noted in risks).

## 10. React / Inertia plan

- `resources/js/pages/jobs/index.tsx`:
    - Add a "Save this search" `Button` in the filter panel, visible only when `isJobSeeker && hasActiveFilters` (guests keep the existing no-filter sign-in pattern; other roles don't see it).
    - Opens `SaveSearchDialog` (new shared component in `resources/js/components/saved-search/save-search-dialog.tsx`): name input (optional), alerts `Switch` (default on), frequency `Select` (Daily/Weekly, shown when enabled), criteria summary preview (shared summary builder).
    - Submit via `useForm()` `POST route('candidate.saved-searches.store', criteria…)` (Wayfinder typed action), `processing` disables submit; success toast + link to the saved-searches page; field errors inline.
- `resources/js/pages/candidate/saved-searches/index.tsx` (new, in the existing `split` candidate layout):
    - Paginated cards; each shows name/summary badges (criteria labels), alert state chip (`Daily`/`Weekly`/`Off`), `last_alerted_at` (humanized/never), and actions.
    - **Rerun**: `Link href = route('jobs.index', search.criteria)` (or fallback string-built URL) — one navigation, server truth.
    - **Edit**: same `SaveSearchDialog` in edit mode (`PUT route('candidate.saved-searches.update', { savedSearch })`), prefilled.
    - **Delete**: confirm dialog (existing confirm pattern) then `router.delete(..., { preserveScroll: true })`.
    - Empty state: description + "Browse jobs" CTA + hint that the button on `/jobs` saves a search.
    - Loading: Inertia default or skeleton consistent with saved-jobs page; error: toast.
- `resources/js/lib/navigation.ts`: add "Saved Searches" (`requiredRoles: ['job_seeker']`, `Search`/bookmark-family icon) between "Saved Jobs" and "My Applications".
- `resources/js/types/saved-search.ts`: shared type (`SavedSearch`, `SavedSearchCriteria`, dialog form shape) — strict, no `any`.
- Notification center needs no change — the new payload matches the existing `{title, message, url}` renderer contract.
- Build needed anyway (Wayfinder route definitions added).

## 11. Test matrix

Pest, following `SavedJobTest` conventions (`InteractsWithAuthorizationCatalogue`, seeded catalogue).

**`tests/Feature/Candidate/SavedSearchTest.php` (CRUD/auth/Validation)**

1. Guests redirected to login on all four routes.
2. Employer / admin / non-owner candidate → 403 (viewAny/access), guessed other-user search id → 403/404.
3. Store happy path persists auth-side `user_id` + criteria; forged `user_id` in payload ignored.
4. Validation: unknown `category_id` → 422; oversized `search`/`name` → 422; `alert_frequency` outside enum → 422; `alerts_enabled` boolean cast.
5. Cap: 21st search → 422 with message.
6. Update replaces criteria/frequency/toggle; owner only.
7. Delete owned search cascades ledger rows; other user's delete attempt stays non-destructive.
8. `criteria()` map shape matches public `filters` prop keys (consistency guard).
9. Listing: pagination, eager-loaded names, empty state route OK.

**`tests/Feature/Candidate/SavedSearchAlertsTest.php` (dispatcher)**

1. Command with invalid frequency arg exits non-zero with error.
2. Happy path: published matching job + enabled daily search + `artisan saved-searches:dispatch-alerts daily` → one DB notification with correct title/count/top-titles/rerun URL containing the query string; ledger row + `last_alerted_at` set.
3. Frequency isolation: weekly search receives nothing on the daily run and vice-versa; disabled alerts → nothing.
4. Duplicate prevention: second identical run → zero new notifications, no new ledger rows.
5. Cross-search dedup in one run: two overlapping searches of one user → job in exactly one notification.
6. Visibility: draft / pending_review / rejected / closed jobs, and published-by-active-company-but-suspended-owner variants → no notification, no ledger row.
7. Catch-up: job published before `last_alerted_at` slack edge + command rerun after scheduler delay → alerted exactly once (ledger anti-join, independent of window).
8. 50-match cap: 60 matching new jobs → 50 alerted, count message reflects cap; remainder eligible next run.
9. Ledger uniqueness race: direct unique-violation safe behavior (duplicate insert attempt doesn't crash the run).
10. Per-user transaction: notification failure logged, ledger/`last_alerted_at` consistent, other users proceed.
11. Search referencing a deactivated category still parses (exists-only validation) and rerun matches nothing without error.

**Regression**

- `tests/Feature/PublicJobListTest.php` must pass untouched after the `JobSearchService` extraction (refactor equivalence proof).
- Full suite green (Feature 20/25 behavior untouched).

**Checks:** Pint, PHPStan, `npm run types:check`, `npm run check`, `npm run build`.

## 12. Security / performance risks

- **Authorization bypass/IDOR:** owned-record gate on update/delete via owner-scoped retrieval + Policy; route boundary restricts candidates; guessed IDs fail. Tested.
- **Mass assignment:** explicit `$fillable` minus `user_id/last_alerted_at`; validated-mapped writes only; `last_alerted_at` managed solely by the dispatcher. Tested (forged `user_id`).
- **Sensitive data exposure:** notifications contain only public-listing-shape data (`title`, count) + a rerun URL; no candidate/resume data; visibility clause identical to the public listing and regression-tested — alerts cannot leak non-public jobs even if criteria change. Private-alert notification content reviewed in tests.
- **Scale risks (millions target):** dispatcher bounded per user (≤20 searches × ≤50 matches = ≤1000 notifications/day/user cap is still high — mitigate: per-run match cap plus the 30-day clamp; count rather than push in the digest message). Full-run cost = O(users-with-searches) chunked × small set-based queries on `(status, created_at)` index; no unbounded `all()`, no per-loop queries on the job corpus. Ledger can grow linearly with alert events — acceptable append-only table; the added `job_postings` index is the only hot-path index change and also benefits the existing public listing.
- **Race conditions:** scheduler overlap guarded by the ledger unique constraint + catch-up anti-join semantics; two concurrent runs can double-insert ledger rows only once per (search, job) — inserts use `firstOrCreate`-guarded unique so the second fails silently/consistently.
- **Background failure semantics:** per-user try/catch (NotificationService precedent) — one bad user never aborts the batch; notification failures logged, primary business writes are unaffected (alerts are notifications side-channel only).
- **Wildcard/LIKE behavior shared** (not a regression; deferred improvement noted in assumptions).

## 13. Rollout / compatibility notes

- Requires **scheduler execution in production** (`schedule:work` daemon or cron `* * * * *`). README updated with the two commands and a note that without a runner, saved searches work but never dispatch alerts (page shows "never" as last run) — graceful no-op failure mode.
- No existing UI/props change; `jobs.index` contract unchanged (pure internal refactor + one new button).
- No new permissions → no AuthorizationSeeder change, no permission reseed on deploy.
- Append-only ledger: rollback safe `dropIfExists` (down migrations included); index migration on `job_postings` is additive (online DDL trivial at this scale; flag for release notes).
- Notifications events have a synthetic `saved-search.new-jobs` payload consistent with the existing renderer; no notification-center change needed.

## 14. Ordered implementation plan

1. Migrations (both tables + `job_postings` index) — new migration file; verify seeds run.
2. `AlertFrequency` enum, models (`SavedSearch`, `SavedSearchAlert`), `User::savedSearches()`.
3. `JobSearchService` extraction; refactor `PublicJobController` (zero behavior change); run public-listing regression.
4. Form Requests (`Store`/`Update`) with cap rule.
5. `SavedSearchPolicy` + controller (`SavedSearchController`) + named routes in the candidate group.
6. `SavedSearchMatchedNotification` + `NotificationService::savedSearchMatched()`.
7. Dispatcher Command + Schedule entries in `routes/console.php`; README scheduler note.
8. Frontend: shared types, `SaveSearchDialog`, jobs-index affordance, saved-searches page, navigation item, empty/loading/error states.
9. Tests (two feature files + regression runs), then the verification command battery.

## 15. Verification commands

```bash
php artisan test --filter=SavedSearch
php artisan test --filter=PublicJobListTest
php artisan test
vendor\bin\pint --test
php vendor/bin/phpstan analyse --memory-limit=512M --no-progress
npm run types:check
npm run check
npm run build

# manual smoke (local, optional)
php artisan saved-searches:dispatch-alerts daily
php artisan schedule:list
```

No destructive commands; no dependency changes; no application code runs before approval.
