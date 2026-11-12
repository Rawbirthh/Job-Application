---
name: feature-32-employer-and-platform-analytics
description: Specification for Feature 32 — Employer and platform analytics
metadata:
    type: project
    status: IMPLEMENTED
    created: 2026-11-11
    approved: 2026-11-11
    implemented: 2026-11-11
---

# Feature 32 — Employer and platform analytics

## 1. Goal / non-goals

**Goal:** Give employers a privacy-conscious view of how their own jobs and hiring pipeline are performing, and give platform administrators an aggregate view of platform-wide activity. Every metric has a documented formula, an explicit date window, and an explicit authorization scope.

**In scope:**

- Employer-scoped analytics page showing job status counts, application totals, a documented conversion summary, and a per-job performance table for the companies the signed-in employer can manage.
- Platform-level aggregate analytics page (admin) showing job, application, and account totals and breakdowns across the whole platform.
- A shared, server-side date-window filter (`from` / `to`) applied consistently to every metric.
- A documented metric dictionary (§4) with explicit formulas, denominators, and limitations.
- Authorization boundaries that guarantee an employer can never see another company's metrics.
- Audit-log traceability for analytics reads, integrated with Feature 31.
- Query and index work sized so the metrics are computed in the database, not in PHP.

**Non-goals (deliberate, with reasons):**

- **Job view / impression tracking.** The roadmap scopes this as "employer-scoped job views **if tracking exists**". Repository evidence: `database/migrations/` contains **no** view, impression, or page-view table (grep for `views|_view_count|impression|page_view` returns no matches), and no model or service records job impressions. Building a tracking pipeline would require inventing product rules that the roadmap does not define (what counts as a view, bot filtering, a de-duplication window, retention). Out of scope; recorded as a follow-up in §15.
- **Pre-aggregation tables, caches, or materialized rollups.** The roadmap says to "measure and consider pre-aggregation only when justified". No measurement yet exists. §12 defines the trigger conditions that would justify it later.
- **"Ever reached shortlist" funnel.** Requires joining `job_application_status_histories` and is a different metric from the current-status funnel in §4. Out of scope; recorded as a follow-up.
- **Per-candidate or per-applicant analytics.** Employer analytics is aggregate-only. No candidate identity, resume, cover letter, or application detail appears in any payload (§10).
- **Real-time / live dashboards, WebSockets, or export-to-CSV.** Not in the roadmap.
- **Paid/promoted job reporting (Feature 33) and recommendations (Feature 34).** Separate features.
- **Replacing the existing employer/admin dashboards.** This feature adds analytics pages; it does not restructure `employer/dashboard` or `admin/dashboard` beyond adding a navigation link.

## 2. User-visible behavior and acceptance criteria

### 2.1 Employer analytics

**Flow:** An approved employer opens `GET /employer/analytics`. The page shows, for the selected company (or all companies the user manages) and the selected date window:

1. A filter bar: company selector, `from` / `to` date inputs, and preset buttons (7 / 30 / 90 days, All time).
2. Summary tiles: Total Jobs, Published Jobs, Total Applications, Advanced (shortlist or beyond), Conversion Rate, Hires.
3. A "Jobs by status" breakdown (5 job statuses).
4. An "Applications by status" breakdown (8 application statuses).
5. A "New jobs / New applications per day" trend for the window.
6. A paginated per-job performance table: job title, status, created date, applications, advanced, conversion rate.

**Acceptance criteria:**

- AC-E1. An approved employer with at least one managed company can load the page and sees metrics for **only** those companies.
- AC-E2. Selecting a company the employer does not manage returns **403** — never another company's numbers, never a partially masked result.
- AC-E3. Selecting a company the employer manages but where the employer lacks `analytics.view` returns **403**.
- AC-E4. An employer who is pending/rejected/suspended is blocked by the existing `employer.approved` middleware (403).
- AC-E5. A job seeker, a guest, or a user with no roles cannot reach the page (guest → login redirect; authenticated but unauthorized → 403).
- AC-E6. Changing the date window or the company updates the page via Inertia navigation and the URL query string is updated (filters are shareable and survive a reload).
- AC-E7. Every displayed number matches the formulas in §4 for a known fixture.
- AC-E8. Applications outside the window are excluded from every metric, including the per-job table.
- AC-E9. The page renders an explicit empty state (not a blank area, not a bare `0`) when the employer has no jobs, no applications, or no rows in the selected window. The empty state distinguishes "no data in this window" from "no data at all".
- AC-E10. The per-job table is paginated server-side, shows only the employer's jobs, and never shows a job belonging to another company.
- AC-E11. No candidate personal data (name, email, resume, cover letter, application detail) appears anywhere in the page props or rendered output.
- AC-E12. The page displays the metric definitions inline (a "How these numbers are calculated" disclosure) so the conversion definition is not ambiguous to the reader.

### 2.2 Platform analytics

**Flow:** An administrator with `admin.access` **and** `platform_analytics.view` opens `GET /admin/analytics` and sees, for the selected window:

1. A filter bar: `from` / `to` date inputs and preset buttons.
2. Summary tiles: Published Jobs, Total Jobs, Total Applications, Advanced, Conversion Rate, Hires, Registered Users, Companies.
3. "Jobs by status" and "Applications by status" breakdowns.
4. Account breakdown: employers by employer status.
5. A "Top 5 job categories by published jobs" list (bounded).
6. A "New jobs / New applications / New users per day" trend.

**Acceptance criteria:**

- AC-P1. Only a user holding `admin.access` + `platform_analytics.view` can load the page. Every other authenticated user gets 403; a guest is redirected to login.
- AC-P2. Platform totals aggregate **all** companies and **all** users — no employer-scoping is applied, and no company-scoped metrics are shown to admins by default.
- AC-P3. The window filter applies to every metric on the page, including the account breakdown and the top-categories list.
- AC-P4. The top-categories list is bounded to 5 rows and states the ordering rule.
- AC-P5. No candidate personal data and no candidate-level record appears in the props.
- AC-P6. The page renders useful empty states for a platform with no data in the selected window.
- AC-P7. The metrics match the formulas in §4 for a known fixture.

### 2.3 Cross-cutting

- AC-X1. Every analytics page read writes exactly one `analytics.viewed` audit row (§8).
- AC-X2. Analytics reads never mutate business data and never change job, application, or user state.
- AC-X3. Date handling is identical on both pages and identical between the displayed filter and the applied query (same source of truth: the validated Form Request).
- AC-X4. The existing 582-test suite stays green; no existing route, page, or permission behaviour changes.

## 3. Assumptions / questions

### 3.1 Assumptions (non-blocking — proceed on these unless told otherwise)

- **A1 — Record selection rule.** Every metric counts records whose `created_at` falls inside the window. A window of "last 30 days" therefore means _records created in the last 30 days_, grouped by their **current** status. This is uniform across every metric, which is what makes the numbers internally consistent. Consequence: an application submitted 40 days ago and shortlisted yesterday is not counted in a 30-day window. Stated on the page (§2.1 AC-E12).
- **A2 — Conversion funnel uses current status.** "Advanced" = current status ∈ {`shortlisted`, `interview_scheduled`, `offer_sent`, `hired`}. An application that was shortlisted and later rejected counts in the denominator but not the numerator. This is a documented approximation, not a leak; the "ever reached" variant is a follow-up (§1).
- **A3 — `rejected` and `withdrawn` applications are in the denominator** of the conversion rate. `hires` is reported separately as an absolute count, and the page must not imply that conversion rate is a hire rate.
- **A4 — Timezone.** `config/app.php` sets `'timezone' => 'UTC'`. All windows are computed in UTC. Date-only inputs (`Y-m-d`) are interpreted as UTC calendar days: `from` = `00:00:00`, `to` = `23:59:59.999999`. No timezone conversion or DST handling is needed.
- **A5 — Default window.** First visit with no query string = last 30 calendar days (from = today − 29 days, to = today). Presets: 7 / 30 / 90 days and "All time".
- **A6 — "All time" window** (both `from` and `to` absent) disables the window filter and the daily trend (a daily series over unbounded history is not computable and not useful). All-time totals remain available.
- **A7 — Charting.** `package.json` contains **no** charting library (no `recharts`, `chart.js`, `visx`). This spec therefore renders the breakdown bars and the daily trend with plain Tailwind/shadcn primitives and CSS widths — **no new dependency**. If richer charts are wanted, a package-manager change is required and must be approved separately (§3.2 Q1).
- **A8 — Company-scoped permission naming.** The company permission is `analytics.view`; the platform permission is `platform_analytics.view`. They live in two independent namespaces (a hard-coded list in `App\Enums\CompanyMemberRole` vs. the DB-backed platform catalogue), but the distinct strings avoid one slug meaning two things in `docs/authorization.md`.
- **A9 — Platform permission grant.** `platform_analytics.view` is granted to `platform_admin` only, matching `audit_logs.view` and `reports.moderate`.
- **A10 — Company permission grant.** `analytics.view` is granted to all three company roles (`owner`, `recruiter`, `reviewer`). The reviewer role is read-only, so read-only analytics are consistent with it. The permission exists so the analytics boundary is explicit and can later be narrowed without touching the route.
- **A11 — No `published_at` column exists.** `job_postings` has `status`, `rejection_reason` (added in `2026_11_02_000010_add_moderation_fields_to_job_postings_table.php`) and timestamps, but no publication timestamp. "Published jobs" therefore means _jobs whose current status is `published` and whose `created_at` is inside the window_ — a cohort, not a publication-rate metric. Publication-rate metrics are a follow-up requiring a new column.
- **A12 — Existing known gap (not fixed here).** `App\Http\Controllers\Employer\JobController::jobPayload()` returns a hard-coded `'applications_count' => 0`. Feature 32's per-job table will show **real** counts, so the two screens will disagree. Fixing `jobPayload` is a Feature 15 defect, out of Feature 32's scope; it is recorded in §15. It must not be "fixed" as a side effect of this feature.

### 3.2 Questions (non-blocking)

- **Q1 — Charting library.** Proceed with CSS-only bars (recommended, zero dependency risk), or approve adding a charting package? _Recommendation: CSS-only. The breakdown and trend shapes are simple enough that a dependency would not earn its maintenance cost._
- **Q2 — Should the employer dashboard (`/employer/dashboard`) gain a summary strip?** This spec only adds a link to the new page. Pulling 4–6 headline numbers onto the dashboard would duplicate work and would make the dashboard query-count-sensitive. _Recommendation: link only, keep the dashboard static._
- **Q3 — Should the admin dashboard (`/admin/dashboard`) gain a summary strip?** Same reasoning. _Recommendation: link only._

**Blocking questions: none.** Every design choice above is derivable from the repository or from the roadmap text; the questions above only affect optional polish.

## 4. Metric dictionary (the formulas)

All metrics are computed in SQL. Counts are integers. A ratio is a float rounded to 4 decimal places (`round($x, 4)`), rendered as a percentage with one decimal.

**Window** = optional pair of UTC dates. Effective interval = `[from 00:00:00.000000, to 23:59:59.999999]` (UTC). If `from` is null the lower bound is unbounded; if `to` is null the upper bound is unbounded; if both are null the window is disabled entirely.

**Scope** = the set of `company_id` values in play (employer scope) or "all companies" (platform scope).

| ID  | Metric                                      | Formula                                                                                                                                                                                                                                                                                                           |
| --- | ------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| M1  | Jobs total                                  | `COUNT(job_postings)` where `company_id ∈ scope` AND `created_at ∈ window`                                                                                                                                                                                                                                        |
| M2  | Jobs published                              | same set as M1, `status = 'published'`                                                                                                                                                                                                                                                                            |
| M3  | Jobs by status                              | same set as M1, grouped by `status` (5 values: `draft`, `pending_review`, `published`, `rejected`, `closed`)                                                                                                                                                                                                      |
| M4  | Applications total                          | `COUNT(job_applications)` where `job_posting_id ∈ (job ids of scope)` AND `created_at ∈ window`                                                                                                                                                                                                                   |
| M5  | Applications by status                      | same set as M4, grouped by `status` (8 values, per `App\Enums\JobApplicationStatus`)                                                                                                                                                                                                                              |
| M6  | Applications advanced                       | same set as M4, `status ∈ {shortlisted, interview_scheduled, offer_sent, hired}`                                                                                                                                                                                                                                  |
| M7  | Applications hired                          | same set as M4, `status = 'hired'`                                                                                                                                                                                                                                                                                |
| M8  | Conversion rate                             | `M6 / M4`, or `0.0` when `M4 = 0`                                                                                                                                                                                                                                                                                 |
| M9  | Per-job applications                        | for one job, `COUNT(job_applications)` where `job_posting_id = job.id` AND `created_at ∈ window`                                                                                                                                                                                                                  |
| M10 | Per-job advanced                            | same set as M9, `status ∈ {advanced set}`                                                                                                                                                                                                                                                                         |
| M11 | Per-job conversion rate                     | `M10 / M9`, or `0.0` when `M9 = 0`                                                                                                                                                                                                                                                                                |
| M12 | Users total (platform)                      | `COUNT(users)` where `created_at ∈ window`                                                                                                                                                                                                                                                                        |
| M13 | Employers by status (platform)              | `COUNT(users)` where `status IS NOT NULL` AND `created_at ∈ window`, grouped by `status` (`pending`, `approved`, `rejected`, `suspended`)                                                                                                                                                                         |
| M14 | Companies total (platform)                  | `COUNT(companies)` where `created_at ∈ window`                                                                                                                                                                                                                                                                    |
| M15 | Daily jobs created                          | `COUNT(job_postings)` where `created_at ∈ window`, grouped by `DATE(created_at)` (employer scope applies)                                                                                                                                                                                                         |
| M16 | Daily applications created                  | `COUNT(job_applications)` where `created_at ∈ window`, grouped by `DATE(created_at)` (employer scope applies)                                                                                                                                                                                                     |
| M17 | Daily users created (platform)              | `COUNT(users)` where `created_at ∈ window`, grouped by `DATE(created_at)`                                                                                                                                                                                                                                         |
| M18 | Top categories by published jobs (platform) | `COUNT(job_postings)` grouped by `category_id`, restricted to `status = 'published'` and `created_at ∈ window`, ordered by count DESC then category name ASC, `LIMIT 5`. A deactivated category is **not** excluded: the metric counts published postings, and a retired category's postings are still published. |

**Breakdown series are always complete.** A status with zero matching rows is emitted with count `0`, so the UI never has to guess whether a missing key means zero or "not applicable". The status vocabulary is taken from the backend enums and shipped to the frontend (see §11), not hard-coded in React.

**Daily series is gap-filled.** Days inside the window with no rows are emitted with `0`, so the trend does not have holes. The series is only produced when the window is bounded (A6).

**Terminology shown in the UI.** "Advanced (shortlist or beyond)" is the label for M6 — it must never be called "hired" or "success rate".

## 5. Routes and access boundary

| Method | URI                   | Name                       | Middleware                                                              | Handler                                                   |
| ------ | --------------------- | -------------------------- | ----------------------------------------------------------------------- | --------------------------------------------------------- |
| GET    | `/employer/analytics` | `employer.analytics.index` | `auth`, `role:employer`, `employer.approved`                            | `App\Http\Controllers\Employer\AnalyticsController@index` |
| GET    | `/admin/analytics`    | `admin.analytics.index`    | `auth`, `permission:admin.access`, `permission:platform_analytics.view` | `App\Http\Controllers\Admin\AnalyticsController@index`    |

Both routes are registered in the existing files (`routes/web.php` inside the existing `employer` prefix group; `routes/admin.php` inside the existing `admin` prefix group). No new route file.

**Employer route boundary.** `role:employer` (`EnsureUserHasRole`) + `employer.approved` (`EnsureEmployerIsApproved`) are the existing employer-group middleware and are reused unchanged. The company-level gate is the Policy (§6).

**Admin route boundary.** `permission:admin.access` is the existing `/admin` boundary convention; `permission:platform_analytics.view` is the new resource-specific permission. Both are AND-combined, matching every other admin route in `routes/admin.php`.

**Both routes are read-only GET.** No write endpoints, no export endpoint, no POST/PUT/DELETE. There is nothing for a browser form to submit.

**Navigation.** Add to `resources/js/lib/navigation.ts`:

- "Analytics" → `employer.analytics.index()`, `requiredRoles: ['employer']`.
- "Analytics" → `admin.analytics.index()`, `requiredPermissions: ['admin.access', 'platform_analytics.view']`.

Navigation visibility is presentation only; the server gates remain authoritative (§6). The nav label is "Analytics" for employers and "Platform Analytics" for admins, so the two entries are distinguishable in the shared sidebar.

## 6. Policy actions and dynamic role/permission resolution

### 6.1 Company-scoped (employer)

`App\Policies\CompanyPolicy` gains one method:

```php
public function viewAnalytics(User $actor, Company $company): bool
{
    return $actor->isApprovedEmployer()
        && $actor->hasCompanyPermission($company, 'analytics.view');
}
```

This mirrors the existing `CompanyPolicy::update()` shape exactly (`isApprovedEmployer()` + `hasCompanyPermission()`), so no new authorization concept is introduced.

**Resolution chain for the employer page:**

1. `auth` middleware → unauthenticated users are redirected to login.
2. `role:employer` → a job seeker is rejected with 403.
3. `employer.approved` → a pending/rejected/suspended employer is rejected with 403.
4. `CompanyPolicy::viewAnalytics` → per company.

**The authorized company set** is computed server-side as: companies where the user is the owner **OR** an active member (`Company::scopeForManagingUser`), further restricted to companies where the user's company role grants `analytics.view` (`App\Enums\CompanyMemberRole::permissions()`). The company list is loaded with `with('members:id,company_id,user_id,company_role,status')` so the role test does not run one query per company (see §12 N+1 note).

**Company filter is re-scoped, never trusted.** The `company_id` query parameter is never used directly in a `where`. The controller resolves it through the authorized set:

```php
$company = $authorizedCompanies->firstWhere('id', $requestedCompanyId);
```

If a `company_id` is supplied that is not in the authorized set → **403** (not 404, not an empty result — an empty result would confirm/deny by omission and is a weaker boundary). A `company_id` that does not exist at all fails Form Request validation (`exists:companies,id`) → 422.

**Employer with zero authorized companies** → 403 with a clear message ("You do not manage any company yet"). The page cannot render meaningful metrics without a scope.

### 6.2 Platform-scoped (admin)

Admin analytics is **middleware-only**; no Policy is added.

Rationale: AGENTS.md requires a Policy for _model/resource actions_. Platform analytics is an aggregate read over no single model — there is no `Analytics` resource to authorize against. `permission:admin.access` + `permission:platform_analytics.view` is the same pattern used by every other admin read route in `routes/admin.php` (e.g. `admin.audit-logs.index` uses `permission:admin.access` + `permission:audit_logs.view` and authorizes via `AuditLogPolicy::viewAny`, because audit logs _are_ a model-backed resource). Adding a Policy with a single `viewAny` that only re-checks the same permission would be a meaningless indirection.

`App\Enums\PermissionSlug` gains `PlatformAnalyticsView = 'platform_analytics.view'`.

**Adding this permission touches exactly the four documented places:**

1. `app/Enums/PermissionSlug.php` — new case.
2. `database/seeders/AuthorizationSeeder.php` — display-name entry **and** the `$platformAdminPermissionSlugs` allow-list.
3. `tests/Feature/Authorization/AuthorizationCatalogueTest.php` — `assertDatabaseCount('permissions', 24)` → `25`, and the `is_system` count `24` → `25`.
4. `docs/authorization.md` — catalogue row.

**Known documentation defect (report, do not silently fix):** the platform permission table in `docs/authorization.md` currently lists only 4 of the 24 seeded permissions (`admin.access`, `users.view`, `users.roles.manage`, `audit_logs.view`); the other 20 are missing. This feature adds **only** its own row. Bringing the table fully in line with the seeder is a documentation cleanup that belongs to the authorization feature, not to Feature 32 — it is recorded in §15.

### 6.3 Company-scoped permission catalogue

`App\Enums\CompanyMemberRole::permissions()` gains `'analytics.view'` to all three roles (`owner`, `recruiter`, `reviewer`). This list is hard-coded in the enum (not DB-backed), so no seeder, catalogue count, or `docs/authorization.md` change is needed for it.

**Invariants that must hold:** a company role must never grant a platform permission (it grants only company-scoped strings, all of which are absent from `App\Enums\PermissionSlug`), and platform permissions must never be granted through a company role.

## 7. Form Requests and validation

Two new Form Requests. No inline `$request->validate()` anywhere in the feature.

### 7.1 `App\Http\Requests\Employer\IndexAnalyticsRequest`

| Field        | Rules                                        | Notes                                                                                                                               |
| ------------ | -------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------- |
| `company_id` | `nullable`, `integer`, `exists:companies,id` | Existence only. Authorization is re-checked in the controller against the authorized set (§6.1). A valid-but-unauthorized id → 403. |
| `from`       | `nullable`, `string`, `date_format:Y-m-d`    | Not more than 366 days before today.                                                                                                |
| `to`         | `nullable`, `string`, `date_format:Y-m-d`    | Not more than 31 days after today.                                                                                                  |
| `preset`     | `nullable`, `string`, `in:all,7d,30d,90d`    | **Added during implementation.** A named window preset (§4).                                                                        |
| —            | cross-field                                  | `from` must be ≤ `to`; the inclusive span must be ≤ 366 days.                                                                       |

`authorize()` returns `true` — the actual authorization is `CompanyPolicy::viewAnalytics`, called by the controller, which is the documented pattern in `AGENTS.md` §2.3.

`prepareForValidation()` trims string values and maps empty strings to `null`, and leaves non-string values untouched so an array parameter is **rejected** rather than silently nulled. This mirrors `App\Http\Requests\Admin\IndexAuditLogRequest` exactly, including that file's `withValidator()` (not `after()`) usage — `after()` is container-resolved and does not bind the validator reliably (recorded as deviation 3 in the Feature 31 spec §18).

**The window presets.** A5 (a default 30-day window on a first visit) and A6 (an explicit "all time" window) are indistinguishable when only `from`/`to` are accepted, because both mean "no date parameter was sent". A `preset` parameter separates them:

| Request                          | Resolved window                                |
| -------------------------------- | ---------------------------------------------- |
| `from` and/or `to` present       | custom — the supplied bounds are used verbatim |
| `preset=all` only                | unbounded (no bounds, no daily trend)          |
| `preset=7d` / `30d` / `90d` only | that many days ending today                    |
| nothing                          | the default 30-day window                      |

The shared resolution lives in `App\Http\Requests\Concerns\ResolvesAnalyticsWindow`, a trait in the existing `app/Http/Requests/Concerns/` directory. The two requests are separate because the employer request accepts `company_id` and the admin request must not (an admin request that accepted and ignored `company_id` would be a silent no-op parameter); the shared trait carries the date rules and the preset expansion so neither request duplicates them.

**Why the span caps exist.** The daily trend is one point per calendar day. Without a cap, `from=1970-01-01` would try to materialise ~20 000 series points. The caps bound the series to ≤ 366 points and the request to a predictable size. "All time" (both null) bypasses the caps because it produces no series.

### 7.2 `App\Http\Requests\Admin\IndexPlatformAnalyticsRequest`

| Field    | Rules                                     | Notes                                     |
| -------- | ----------------------------------------- | ----------------------------------------- |
| `from`   | `nullable`, `string`, `date_format:Y-m-d` | Same bounds as above.                     |
| `to`     | `nullable`, `string`, `date_format:Y-m-d` | Same bounds as above.                     |
| `preset` | `nullable`, `string`, `in:all,7d,30d,90d` | Same presets as above.                    |
| —        | cross-field                               | `from` ≤ `to`; inclusive span ≤ 366 days. |

`authorize()` returns `true` — authorization is the route middleware (`permission:admin.access` + `permission:platform_analytics.view`).

The two requests are separate rather than one shared request because the employer request accepts `company_id` and the admin request must not (an admin request that accepted and ignored `company_id` would be a silent no-op parameter). The shared trait carries the common rules so the duplication is limited to the request classes themselves.

**Date range limits are validated server-side only.** The client date pickers are a convenience, never a boundary.

## 8. Audit log integration (Feature 31)

Analytics reveals commercially sensitive aggregates, and the roadmap names Feature 31 as "recommended for traceability". Reading an analytics page is therefore an audited action, following the `audit_log.viewed` precedent.

- `App\Enums\AuditAction` gains `AnalyticsViewed = 'analytics.viewed'` (label "Analytics viewed"). `values()` updated.
- `App\Enums\AuditTargetType` gains `Analytics = 'analytics'` (label "Analytics"). `values()` updated.
- The write uses `AuditLogService::recordWithoutModel(AuditAction::AnalyticsViewed, AuditTargetType::Analytics->value, targetId: null, targetLabel: 'employer'|'platform', metadata: [...])`.
    - `recordWithoutModel()` validates the target type against `AuditTargetType::values()`, so the new case is required.
- **Metadata (allow-list, ≤ 20 entries, values ≤ 200 chars):** `scope` (`employer` | `platform`), `company_ids` (array of ints), `company_count` (int), `from` (`Y-m-d` or null), `to` (`Y-m-d` or null), `window` (`bounded` | `all_time`). `company_count` is authoritative; `company_ids` is capped at twenty entries by the audit service, so it is a sample of the scope rather than the whole scope.
- **Fail-soft.** The audit write is wrapped so a logging failure cannot break the analytics page (`Log::error`), matching `audit_log.viewed` behaviour. A failed audit write must not surface a 500 to the user.
- `resources/js/types/audit-log.ts` must be kept in sync with the two enums (the action/target catalogues are rendered by the admin audit-log pages from the server catalogue — the frontend mirror must list the new slugs or the type union becomes incomplete).
- Exactly **one** audit row per page read, not one per metric.

## 9. Models, relationships, migration, constraints, indexes

### 9.1 No new models and no new tables

Every metric is derived from existing tables: `job_postings`, `job_applications`, `companies`, `users`, `job_categories`. Analytics is a **read-only projection**; there is no `Analytics` model, no `analytics` table, and no persisted snapshot.

`App\Models\Job` gains no new relationship — `Job::applications()` (HasMany → `JobApplication`) already exists and is what `withCount()` uses.

### 9.2 Migration: `2026_11_14_000000_add_analytics_supporting_indexes.php`

One new migration (never modify an applied migration). Driver-neutral: `index()` and `dropIndex()` only, no `CHECK`, no partial index, no `ALTER TABLE ... ADD COLUMN`, no data backfill. Identical on SQLite and MySQL.

| Table              | Index                                                                  | Rationale (query shape)                                                                                                                                                                         |
| ------------------ | ---------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `job_postings`     | `['company_id', 'status']` → `job_postings_company_status_idx`         | Employer scope: `WHERE company_id IN (...) GROUP BY status` (M3). The current standalone `status` index cannot serve a `company_id` filter.                                                     |
| `job_postings`     | `['created_at']` → `job_postings_created_at_idx`                       | Platform windowed counts (M1/M15): `WHERE created_at BETWEEN ? AND ?`. `job_postings` has **no** `created_at` index today.                                                                      |
| `job_applications` | `['created_at']` → `job_applications_created_at_idx`                   | Window filter on applications (M4/M16) in both scopes. `job_applications` has **no** `created_at` index today.                                                                                  |
| `job_applications` | `['job_posting_id', 'status']` → `job_applications_posting_status_idx` | Employer scope: join `job_postings` → `job_applications` then group by status (M5).                                                                                                             |
| `users`            | `['created_at']` → `users_created_at_idx`                              | Platform account totals (M12/M13) and the daily user trend (M17). **Added during implementation** — the EXPLAIN verification of §13.5 showed a full table scan of `users` without it (see §17). |
| `companies`        | `['created_at']` → `companies_created_at_idx`                          | Platform company total (M14). **Added during implementation** — the independent review found that `companies` had no `created_at` index, so M14 full-scanned the table (see §17).               |

**`job_postings` already had a `(status, created_at)` index** (`job_postings_status_created_at_index`, added by the Feature 29 migration). That composite is _not_ redundant with the new standalone `created_at` index — its leftmost prefix is `status` — and the recorded EXPLAIN plans confirm the planner selects the standalone index for the windowed queries. Both are kept.

**Redundancy handled explicitly.** The standalone `job_applications_job_posting_id_index` created in `2026_11_05_120000_create_job_applications_table.php` becomes a redundant leftmost prefix of `job_applications_posting_status_idx`. The new migration **drops it** and `down()` **recreates it**. This follows AGENTS.md §2.8 ("avoid redundant indexes"). The `job_postings` standalone `status` index is **kept** — its leftmost prefix is `status`, not `company_id`, so it is not covered by `['company_id','status']` and it still serves platform-wide `GROUP BY status`.

**The FK index on `job_postings.company_id` is left alone.** On MySQL/InnoDB an index is required to back the foreign key, and it may be implicitly created rather than named by Laravel; dropping it by name is not portable. It is therefore knowingly left redundant on MySQL and noted here rather than papered over.

**MySQL/InnoDB owns the foreign key backing indexes, and this is not portable.** The verification on MySQL (see §17) showed three behaviours that a naive migration gets wrong:

1. InnoDB **silently drops** a redundant foreign key index when an index with the same leading column is added — so `job_postings_company_id_foreign` disappears once `job_postings_company_status_idx` exists, and the composite becomes the backing index.
2. InnoDB **refuses to drop** an index a foreign key depends on — so `down()` cannot remove `job_postings_company_status_idx` until a standalone `company_id` index exists again.
3. The redundant `job_applications_job_posting_id_index` may be either dropped or kept by InnoDB, so the rollback must tolerate both outcomes.

The migration therefore guards **every** index statement with `Schema::hasIndex()`, so `up()` and `down()` are idempotent and safe to run against a partially migrated schema on either driver. `down()` restores the `job_postings(company_id)` backing index before it removes the composite, and recreates `job_applications_job_posting_id_index` before it removes the composite that replaced it.

**Index verification is part of implementation, not an assumption** (§13): the engineer must run `EXPLAIN` on the four aggregate queries against a representative dataset and record the plan. If a proposed index is not used, it is dropped from the migration rather than kept speculatively. If a query needs an index the migration does not create, the index is added. The recorded plans are in §17.

### 9.3 Deletion semantics

No new deletion semantics are introduced — analytics reads existing rows and respects the existing ones:

- `job_applications.job_posting_id` is `cascadeOnDelete`; `job_postings.company_id` is `restrictOnDelete` (Features 6–9 protections). Deleting a job removes its applications and therefore removes them from historical metrics; deleting a company is restricted while jobs reference it. Analytics must not add a `softDeletes` column or any retention behaviour that would change these semantics.
- `users` deletion cascades to `job_applications.user_id`. A deleted candidate's applications disappear from the counts. This is existing behaviour and is **not** altered here.
- Because metrics are computed live, no orphan or stale rollup can exist. This is a deliberate advantage over pre-aggregation and is one reason the pre-aggregation follow-up (§15) needs real evidence first.

## 10. Query / data payload shape, eager loading, pagination

### 10.1 Selected columns

Aggregate queries select only what is aggregated (`COUNT(*)`, the grouping column). No `SELECT *` on any aggregate path. Concretely:

- M1/M3: `SELECT status, COUNT(*) FROM job_postings WHERE … GROUP BY status`
- M4/M5/M6/M7: `SELECT status, COUNT(*) FROM job_applications JOIN job_postings … GROUP BY status` (one grouped query produces M4, M5, M6 and M7 when the advanced/hired sets are computed from the grouped rows in PHP — **no extra query**).
- M9/M10 (per-job table): the paginated job query selects `id, title, status, company_id, created_at` and adds two `withCount` sub-selects constrained by the window (`applications_count`, `advanced_count`). `withCount` produces **one** correlated sub-select in the page query — not one query per job.
- M15/M16/M17 (trend): `SELECT DATE(created_at) AS day, COUNT(*) … GROUP BY day` — two columns, grouped.
- M18 (top categories): `SELECT category_id, COUNT(*) … GROUP BY category_id ORDER BY … LIMIT 5`, joined to `job_categories` for the label (5 rows).

### 10.2 Eager loading

- Employer page: `Company::query()->forManagingUser($user)->with('members:id,company_id,user_id,company_role,status')` — **one** extra query for all memberships, so the company-role test does not fan out per company (N+1 avoidance). The company list is bounded by the number of companies a user manages; it is `select(['id','name','slug','owner_user_id'])`.
- Per-job table: `with('company:id,name,slug')` — one eager query for the page, so company names do not N+1.
- Admin page: `job_categories` is a small admin-managed catalogue; the top-categories join reads at most 5 rows.
- **No eager loading is needed for the aggregate queries** — they return grouped rows, not models.

### 10.3 Pagination

| Surface                      | Strategy                            | Bound                                                                                                |
| ---------------------------- | ----------------------------------- | ---------------------------------------------------------------------------------------------------- |
| Employer per-job table       | `->paginate(10)->withQueryString()` | 10 rows/page, server-side                                                                            |
| Employer company filter list | eager-loaded collection             | number of managed companies (bounded in practice; each employer manages a small number of companies) |
| Admin top-categories list    | `LIMIT 5` in SQL                    | 5 rows                                                                                               |
| All aggregate breakdowns     | grouped aggregate queries           | ≤ 5 job statuses, ≤ 8 application statuses, ≤ 4 employer statuses, ≤ 366 trend points                |

`withQueryString()` preserves the active filter (company, from, to) across pagination — the same convention as `Employer\JobController::index` and `Admin\AuditLogController::index`.

### 10.4 Payload (Inertia props)

**`employer/analytics` — `EmployerAnalyticsIndexProps`**

```ts
{
    window: {
        from: string | null;
        to: string | null;
        preset: 'all' | '7d' | '30d' | '90d' | 'custom';
    }
    companies: Array<{ id: number; name: string; slug: string }>; // authorized companies only
    selectedCompanyId: number | null; // null = all authorized companies
    summary: {
        totalJobs: number;
        publishedJobs: number;
        totalApplications: number;
        advancedApplications: number;
        conversionRate: number; // 0..1, 4 dp
        hires: number;
    }
    jobsByStatus: Array<{ status: string; label: string; count: number }>;
    applicationsByStatus: Array<{
        status: string;
        label: string;
        count: number;
    }>;
    trend: Array<{ date: string; jobs: number; applications: number }>; // [] when all-time
    jobs: {
        data: Array<{
            id: number;
            title: string;
            status: string;
            company: { id: number; name: string } | null;
            created_at: string | null;
            applications_count: number;
            advanced_count: number;
            conversion_rate: number;
        }>;
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    }
    definitions: Array<{ id: string; label: string; description: string }>; // the §4 metric dictionary, for the UI disclosure
}
```

**Deviation from the written payload shape.** The spec originally described the per-job list as a nested `{ data, meta, links }` object. The implementation uses the repository's existing convention instead: the paginator is passed straight through and serialises to the flat `PaginatedData<T>` shape already exported from `resources/js/types/authorization.ts` (`data`, `current_page`, `last_page`, `per_page`, `total`, `prev_page_url`, `next_page_url`), which is exactly what the shared `Pagination` component consumes. Matching the surrounding code was preferred over the shape written in this document. The `canViewCompanyAnalytics` prop was also dropped, because it was always `true`: the page is unreachable without the permission.

**`admin/analytics` — `AdminAnalyticsIndexProps`**

```ts
{
    window: {
        from: string | null;
        to: string | null;
        preset: 'all' | '7d' | '30d' | '90d' | 'custom';
    }
    summary: {
        publishedJobs: number;
        totalJobs: number;
        totalApplications: number;
        advancedApplications: number;
        conversionRate: number;
        hires: number;
        totalUsers: number;
        totalCompanies: number;
    }
    jobsByStatus: Array<{ status: string; label: string; count: number }>;
    applicationsByStatus: Array<{
        status: string;
        label: string;
        count: number;
    }>;
    employersByStatus: Array<{ status: string; label: string; count: number }>;
    topCategories: Array<{ id: number; name: string; publishedJobs: number }>;
    trend: Array<{
        date: string;
        jobs: number;
        applications: number;
        users: number;
    }>;
    definitions: Array<{ id: string; label: string; description: string }>;
}
```

**Privacy constraint on the payload (binding):** no prop may contain a candidate name, email, user id, resume id, resume path, cover letter, or application id. The only application-derived values that may cross the boundary are **counts**. A test asserts this (§12).

**Status labels come from the server**, not from React constants: the controller ships `label` alongside each `status`, taken from `App\Enums\JobApplicationStatus::label()` and a job-status label map. `resources/js/types/audit-log.ts`-style duplication of the vocabulary in TypeScript is avoided for the _values_; the frontend keeps only the `status: string` type plus the server-provided label. This keeps the type union open to future statuses without a frontend release.

## 11. React / Inertia page, form, component plan

### 11.1 Pages

| File                                        | Purpose                 |
| ------------------------------------------- | ----------------------- |
| `resources/js/pages/employer/analytics.tsx` | Employer analytics page |
| `resources/js/pages/admin/analytics.tsx`    | Platform analytics page |

### 11.2 Shared types

`resources/js/types/analytics.ts` (new), exported from the `resources/js/types/index.ts` barrel alongside `audit-log.ts`:

- `AnalyticsWindow`, `AnalyticsPreset`, `AnalyticsWindowState`
- `AnalyticsStatusCount` (`status`, `label`, `count`)
- `AnalyticsSummary`
- `AnalyticsTrendPoint`
- `AnalyticsCompanyOption`
- `AnalyticsJobRow`, `AnalyticsJobPaginated`
- `AnalyticsDefinition`
- `EmployerAnalyticsIndexProps`, `AdminAnalyticsIndexProps`

No `any`. All props are typed and consumed as typed props on the page components. TypeScript strict mode stays on (`npm run types:check`).

### 11.3 Components

New local components under `resources/js/components/analytics/`, built **only** from existing shadcn/ui primitives and the project's design tokens — no new component library:

| Component                   | Purpose                                                                                      | Built from                                                            |
| --------------------------- | -------------------------------------------------------------------------------------------- | --------------------------------------------------------------------- |
| `stat-tile.tsx`             | One headline number with label, optional hint, optional delta-free note                      | `Card`, `Badge`                                                       |
| `breakdown-bar.tsx`         | A single labelled bar for a status breakdown                                                 | `Card`, `Badge`, `div` with token colours, inline `style={{ width }}` |
| `trend-chart.tsx`           | A daily series rendered as a CSS bar row (no chart library, per A7)                          | `div`, `Skeleton` for loading                                         |
| `analytics-empty.tsx`       | Empty state with a message that distinguishes "no data in this window" from "no data at all" | `Card`, `Button`                                                      |
| `analytics-definitions.tsx` | Collapsible disclosure listing the §4 definitions                                            | `Card`, existing `Collapsible` primitive                              |

Reused as-is: `Pagination` (`resources/js/components/pagination.tsx`), `InputError` (`resources/js/components/input-error.tsx`), `Button`, `Card`, `Badge`, `Input`, `Label`, `Select`, `Skeleton`, `Spinner`.

**Bar colours** must come from the existing Tailwind/shadcn token set (`bg-primary`, `bg-muted`, `bg-teal-600` per the existing admin pages) — no hard-coded hex values and no new palette.

### 11.4 Filter form behaviour

Mirrors `resources/js/pages/admin/audit-logs/index.tsx`:

- Filters live in React state, initialised from the server-supplied `window` / `selectedCompanyId` props.
- The form is a real `<form onSubmit={...}>`; the submit handler calls `router.get(url, params, { preserveState: false, preserveScroll: true, replace: true })` so the URL reflects the filter and the page is shareable.
- Preset buttons are `<Button type="button">` elements that compute `from`/`to` and issue the same `router.get` call.
- Field-level validation errors (`from`, `to`, `company_id`) are rendered with `InputError` from `usePage().props.errors`.
- The submit control is disabled while the request is in flight (`processing` flag), and the affected cards show `Skeleton` during the partial reload.

**No `useEffect` fetch.** All page data arrives as Inertia props (AGENTS.md §2.10). No `axios`/`fetch` is used.

### 11.5 UI states

| State                          | Behaviour                                                                                                                                                                                   |
| ------------------------------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Loading (first load)           | `Skeleton` in each card                                                                                                                                                                     |
| Loading (filter change)        | `Skeleton` in the affected cards; the filter bar stays interactive                                                                                                                          |
| Empty — no data at all         | "No jobs yet" / "No applications yet" with a link to the relevant action (create a job draft / view jobs)                                                                                   |
| Empty — nothing in this window | "No activity between {from} and {to}" with a "Show all time" action — distinct from the "no data at all" message                                                                            |
| Zero-count status              | The status is still listed with a `0` count and an empty bar (never omitted)                                                                                                                |
| Error — validation             | Field-level `InputError` under the offending input                                                                                                                                          |
| Error — 403                    | The request fails; the page does not render partial metrics. The error is surfaced by the app's normal error handling; the controller must not catch the exception and render an empty page |
| Error — 500                    | Standard Inertia error page; no partial state                                                                                                                                               |

## 12. Service / Repository decision

**Decision: add exactly one service — `App\Services\Analytics\AnalyticsService`. No Repository, no DTO class, no query-object layer.**

**Why a Service is justified (AGENTS.md §3 decision tree):** the same aggregate query shapes are needed by two different consumers with different scopes (employer scope vs. platform scope), and the metric formulas must exist in exactly one place so the two pages cannot drift. That is the "query reused in multiple places" branch of the decision tree. It also keeps the controllers thin.

**Why no Repository:** the codebase has **no** `app/Repositories` directory and no repository pattern anywhere. Introducing one for a single aggregate projection would be ceremony, not architecture.

**Why no DTO/value object:** the codebase has no `app/Data`, `app/DTO`, or `app/Support` layer. The window is passed as two nullable `?CarbonImmutable` parameters (`$from`, `$to`) with a private `applyWindow(Builder, ?CarbonImmutable, ?CarbonImmutable): Builder` helper inside the service. A dedicated value object is not warranted for two nullable parameters and would be the first of its kind in the codebase.

**Why not put it in the controller:** the employer and admin controllers would each need the same four aggregate queries and the same status/label vocabulary, duplicating the metric definitions — exactly what §4 is meant to prevent.

**Service surface** (all read-only, no writes):

```php
final class AnalyticsService
{
    // Employer scope
    public function employerMetrics(array $companyIds, ?CarbonImmutable $from, ?CarbonImmutable $to): array;
    public function employerTrend(array $companyIds, ?CarbonImmutable $from, ?CarbonImmutable $to): array;
    public function employerJobsPaginator(array $companyIds, ?CarbonImmutable $from, ?CarbonImmutable $to, int $perPage = 10): LengthAwarePaginator;

    // Platform scope
    public function platformMetrics(?CarbonImmutable $from, ?CarbonImmutable $to): array;
    public function platformTopCategories(?CarbonImmutable $from, ?CarbonImmutable $to, int $limit = 5): array;
    public function platformTrend(?CarbonImmutable $from, ?CarbonImmutable $to): array;
}
```

**Deviation from the written service surface.** The granular per-metric methods were replaced by one page-level method per scope — `employerMetrics()` and `platformMetrics()` — each of which returns the summary, the status breakdowns and (for the platform scope) the account metrics. The reason is recorded in §17. Two problems forced the change:

1. **Stale totals.** The original design memoised the account query on the service instance. Laravel keeps the resolved controller — and therefore its injected service — alive on the route object between reads, so the memo served stale platform totals on the second read of a request lifecycle.
2. **Duplicate aggregates.** With the memo removed, the summary and the breakdown each ran their own aggregate, so both pages executed the job status aggregate and the application status aggregate **twice** per read.

The service now holds **no mutable state**, reads each aggregate exactly once per page, and derives every headline metric and breakdown from those counts. A regression test asserts that each aggregate is executed exactly once per page read.

**Where the scope is applied.** The service receives **already-authorized `company_id` values** (an array of ints), never a `User`. The controller resolves the authorized set; the service cannot widen it. This makes it structurally impossible for the service to leak another company's rows, and it keeps the service free of authorization concerns.

**N+1 note.** `User::companyRole()` issues its own membership query per call. The analytics code path must therefore compute the company role from the **eager-loaded** `members` relation rather than calling `hasCompanyPermission()` per company in a loop. The Policy method `CompanyPolicy::viewAnalytics()` still uses `hasCompanyPermission()`, but it is invoked **once per request** for the selected company, so it costs one query — not a loop. (If `analytics.view` is later differentiated per role, the service's batched role computation becomes the single place to change; §15 lists it as a follow-up.)

**Query count budget** (target, measured in tests):

| Page               | Queries                                                                                                                   |
| ------------------ | ------------------------------------------------------------------------------------------------------------------------- |
| Employer analytics | ≤ 10 (company list + members + 3 grouped aggregates + 1 trend pair + 1 paginated jobs + company eager load + audit write) |
| Admin analytics    | ≤ 9 (4 grouped aggregates + 1 top-categories + 2 trend groups + 1 category labels + audit write)                          |

The audit write is fail-soft and counts as one query. A regression test asserts the query count does **not** grow with the number of jobs/applications (the pattern already used in `tests/Feature/Authorization/AdminUserIndexTest.php`). The measured counts are recorded in §17.

## 13. Tests, security cases, and verification

### 13.1 New test files

- `tests/Feature/Employer/AnalyticsTest.php`
- `tests/Feature/Admin/AnalyticsTest.php`

### 13.2 Test matrix — employer analytics

| #   | Case                                                             | Assertion                                                                                                                       |
| --- | ---------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------- |
| E1  | Guest                                                            | Redirect to login (302)                                                                                                         |
| E2  | Job seeker (authenticated, `job_seeker` role)                    | 403                                                                                                                             |
| E3  | Approved employer with no company                                | 403                                                                                                                             |
| E4  | Approved employer, one company                                   | 200, page renders                                                                                                               |
| E5  | Employer of company A requests `company_id` of company B         | 403 — **and** company B's job titles and counts appear nowhere in the response body                                             |
| E6  | Employer A views default (all companies) while B exists          | B's jobs/applications absent from the payload and from the rendered HTML                                                        |
| E7  | Recruiter member of the same company                             | 200 (has `analytics.view`)                                                                                                      |
| E8  | Reviewer member of the same company                              | 200 (read-only role also has `analytics.view`)                                                                                  |
| E9  | Active member of a _different_ company                           | that company's metrics absent; requesting it → 403                                                                              |
| E10 | Revoked member (membership status ≠ active)                      | the company is not in the authorized set                                                                                        |
| E11 | Pending employer (not approved)                                  | 403 (`employer.approved`)                                                                                                       |
| E12 | Suspended employer                                               | 403                                                                                                                             |
| E13 | `company_id` of a non-existent company                           | 422 validation error on `company_id`                                                                                            |
| E14 | `company_id` of an existing but unauthorized company             | 403 (not 422, not 200)                                                                                                          |
| E15 | `from` after `to`                                                | 422 with an error on `to`                                                                                                       |
| E16 | `from` / `to` with an invalid format (`not-a-date`)              | 422                                                                                                                             |
| E17 | `from` older than 366 days                                       | 422                                                                                                                             |
| E18 | `from` = array (`from[]=x`)                                      | 422 (rejected, not silently nulled)                                                                                             |
| E19 | Known fixture: 10 applications with known statuses across 2 jobs | Exact assertion on M1–M11 values and the conversion rate                                                                        |
| E20 | Applications created outside the window                          | Excluded from M4–M8 and from the per-job counts                                                                                 |
| E21 | Rejected application                                             | In the denominator of the conversion rate, not in the numerator                                                                 |
| E22 | Withdrawn application                                            | In the denominator, not in the numerator                                                                                        |
| E23 | Zero applications                                                | Conversion rate is `0.0`, not a division error; empty state shown                                                               |
| E24 | Per-job table pagination                                         | `?page=2` returns a different page; `withQueryString` preserves `company_id`/`from`/`to`                                        |
| E25 | Per-job table                                                    | Only the employer's jobs appear                                                                                                 |
| E26 | Payload privacy                                                  | The Inertia props contain no candidate name, email, user id, resume id, or cover letter                                         |
| E27 | Query count                                                      | The query count does not grow when the number of jobs/applications grows (N+1 regression guard)                                 |
| E28 | Filter round-trip                                                | The filter form produces a URL whose query string matches the applied filters; the page re-renders identically for the same URL |
| E29 | Audit row                                                        | Exactly one `analytics.viewed` row is written, with the employer scope in metadata                                              |

### 13.3 Test matrix — platform analytics

| #   | Case                                                           | Assertion                                                                        |
| --- | -------------------------------------------------------------- | -------------------------------------------------------------------------------- |
| P1  | Guest                                                          | Redirect to login                                                                |
| P2  | Authenticated user without `admin.access`                      | 403                                                                              |
| P3  | User with `admin.access` but without `platform_analytics.view` | 403                                                                              |
| P4  | Administrator with both permissions                            | 200                                                                              |
| P5  | Employer (non-admin)                                           | 403                                                                              |
| P6  | Platform scope                                                 | Totals include **all** companies (a second company's data is counted)            |
| P7  | Known fixture                                                  | Exact assertion on M1–M8, M12–M14                                                |
| P8  | Date window                                                    | Records outside the window are excluded from every metric, including M13 and M18 |
| P9  | Top categories                                                 | Bounded to 5 rows, ordered by count DESC then name ASC                           |
| P10 | Empty platform                                                 | Every card renders an empty state, no exceptions/warnings                        |
| P11 | `from` > `to`                                                  | 422                                                                              |
| P12 | Out-of-range `from`                                            | 422                                                                              |
| P13 | Payload privacy                                                | No candidate personal data in the props                                          |
| P14 | Query count                                                    | Does not grow with data volume                                                   |
| P15 | Audit row                                                      | Exactly one `analytics.viewed` row with `scope = platform`                       |

### 13.4 Regression cases

| #   | Case                                       | Assertion                                                                                                                                                                          |
| --- | ------------------------------------------ | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| R1  | Full existing suite                        | `php artisan test` — the 582 existing tests stay green (no modification of existing expectations other than the two catalogue counts, which is an intentional, documented change). |
| R2  | `AuthorizationCatalogueTest`               | Permission counts updated 24 → 25; the new slug is seeded and granted to `platform_admin` only                                                                                     |
| R3  | `employer/dashboard` and `admin/dashboard` | Still render 200 and are otherwise unchanged (the nav link is the only change)                                                                                                     |
| R4  | `employer/jobs`                            | Unchanged — this feature does **not** fix the hard-coded `applications_count` (A12)                                                                                                |
| R5  | Audit-log pages                            | `resources/js/types/audit-log.ts` is in sync with the extended enums; the admin audit-log index still lists every action and target type                                           |
| R6  | Audit-log write path                       | The new `analytics.viewed` action does not break `AuditLogWritingTest` / `AuditLogAccessTest`                                                                                      |

### 13.5 Verification commands actually available in this repository

```text
php artisan test                                    # full suite (582 tests today)
php artisan test --filter=Analytics                 # the two new test classes only
vendor\bin\pint --test                              # PHP formatting
php vendor/bin/phpstan analyse --memory-limit=512M --no-progress   # static analysis
npm run types:check                                 # tsc --noEmit
npm run check                                       # lint + format check
npm run build                                       # production build; regenerates the Wayfinder module
composer ci:check                                   # npm run check + npm run types:check + @test
```

Notes carried over from the repository's own history:

- Run `composer ci:check` **unpiped**. When piped through PowerShell (`2>&1 | Select-Object`), Composer writes `> Composer\Config::disableProcessTimeout` to stderr and PowerShell reports a false exit code of 1.
- `npm run build` regenerates the Wayfinder route module; the new `employer.analytics` and `admin.analytics` routes will appear under `resources/js/routes/` (gitignored). The build must be re-run after the routes are added, or the frontend imports will not resolve.
- `php artisan migrate` must be run for the new indexes, and `php artisan db:seed --class=AuthorizationSeeder` for the new permission.

**Performance verification (required, not optional).** Before the feature is considered complete, the engineer must:

1. Seed a representative dataset (e.g. 50 000 `job_applications` across 5 000 `job_postings` across 200 companies) in a **local/test** database only.
2. Run `EXPLAIN` (SQLite: `EXPLAIN QUERY PLAN`; MySQL: `EXPLAIN`) on the four aggregate queries of §9.2 and record the plans.
3. Record the wall-clock time of each aggregate query.
4. **If a proposed index is not used by the planner, remove it from the migration** rather than shipping a speculative index.

This satisfies the roadmap's "query performance for representative data" test expectation.

## 14. Risks, rollout / compatibility notes

### 14.1 Security risks

| Risk                                                                                                  | Mitigation                                                                                                                                                                                                                                                                                                                                                      |
| ----------------------------------------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **IDOR — employer reads another company's metrics**                                                   | The service only ever receives an already-authorized `company_id` list; the controller re-resolves `company_id` against that list; an unauthorized id is 403. Covered by E5/E9/E14 and by the "company metrics of another company are absent from the payload" assertions.                                                                                      |
| **Known trade-off — a `company_id` that does not exist returns 422, an unauthorized one returns 403** | The existence of a company id is therefore observable by an authenticated employer. This is a deliberate ordering: the approved test matrix asserts both responses, and only the _existence_ of an identifier is disclosed — never a name, a metric, or any company content. The page is already behind the `role:employer` and `employer.approved` middleware. |
| **Known trade-off — a denied company selection writes no audit row**                                  | No page is read, so there is nothing to audit, and the audit catalogue is a closed vocabulary that this feature does not widen. The denial is reported to the application log instead, so a probing campaign is visible there.                                                                                                                                  |
| **Employer-scoped aggregation leaking through a shared query**                                        | The company filter is applied in the SQL (not in PHP), so another company's rows are never loaded and then filtered. Verified by E6.                                                                                                                                                                                                                            |
| **Candidate privacy leakage through counts**                                                          | Only counts cross the boundary; the payload contains no candidate identifier (§10.4). Asserted by E26/P13.                                                                                                                                                                                                                                                      |
| **Unbounded aggregation / DoS**                                                                       | The window is capped (§7), the trend is gap-filled to ≤ 366 points, the per-job table is paginated, the top-categories list is `LIMIT 5`.                                                                                                                                                                                                                       |
| **Parameter injection through filters**                                                               | All filter input is validated by Form Requests; the `status` filters are server-side enums, never client-supplied; the only raw SQL is `DATE(created_at)` grouping, which takes no user input.                                                                                                                                                                  |
| **Mass assignment**                                                                                   | Analytics are read-only; no model is created or updated anywhere in this feature.                                                                                                                                                                                                                                                                               |
| **Audit write failure breaking the page**                                                             | The audit write is fail-soft, mirroring `audit_log.viewed`.                                                                                                                                                                                                                                                                                                     |
| **Permission escalation via a company role**                                                          | `analytics.view` is a company-scoped string only; it grants nothing platform-wide.                                                                                                                                                                                                                                                                              |

### 14.2 Performance / scale risks

| Risk                                                                    | Mitigation                                                                                                                                      |
| ----------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------- |
| Platform-wide `GROUP BY` over millions of rows on every admin page load | The window filter plus the new `created_at` indexes bound the scan. The pre-aggregation trigger is in §15.                                      |
| `COUNT(*)` per metric                                                   | M4/M5/M6/M7 are derived from **one** grouped query, not four. The per-job counts use `withCount` sub-selects inside the single paginated query. |
| N+1 on company memberships                                              | `members` is eager-loaded once; the query-count regression test (E27) locks this in.                                                            |
| The daily trend on a 366-day window                                     | Bounded to 366 points; "All time" produces no series.                                                                                           |
| `DATE(created_at)` grouping portability                                 | `DATE()` is available on both SQLite and MySQL; results are UTC because `created_at` is stored in UTC and `config/app.php` sets UTC.            |

### 14.3 Rollout / compatibility

1. **Migration first, then seed, then code.** Deploy order: `php artisan migrate` (adds indexes; no data change, no column change) → `php artisan db:seed --class=AuthorizationSeeder` (adds `platform_analytics.view` and grants it to `platform_admin`) → deploy the application code. The seeder is idempotent and uses `syncWithoutDetaching`, so custom grants survive.
2. **Adding a permission touches four places** (PermissionSlug, the seeder's name map **and** the `$platformAdminPermissionSlugs` allow-list, the two catalogue counts in `AuthorizationCatalogueTest`, and the `docs/authorization.md` table). Missing the allow-list entry means the permission exists but is granted to nobody.
3. **`docs/authorization.md` is stale** — it lists 4 of the 24 seeded permissions. This feature adds only its own row and does not attempt the broader cleanup (§6.2).
4. **Index migration on a large MySQL table.** `ALTER TABLE ... ADD INDEX` on InnoDB is an in-place but blocking operation for some sizes. If `job_applications` is large in the target environment, run the index migration as a separate deploy step (or with the environment's online-DDL tooling) before enabling the analytics pages. The migration is reversible (`down()` drops the new indexes and recreates the dropped one).
5. **Dropping `job_applications_job_posting_id_index`** is safe for correctness (the new composite has `job_posting_id` as its leftmost prefix) but is verified in the deploy environment's `EXPLAIN` before shipping.
6. **Backward compatibility:** no existing route, page, prop shape, or API changes. `employer/dashboard` and `admin/dashboard` keep rendering their current static content. The two new permissions are additive; a deployment that skips the seeder leaves the new pages inaccessible (403) rather than exposed — fail-closed.
7. **Feature 33 (payments) / Feature 34 (recommendations)** may later need promotion and recommendation metrics. The service is the single place to add them; the metric dictionary (§4) is the contract.
8. **No `published_at` column** (A11) — do not add one in this feature. If publication-rate metrics are requested later, that needs its own migration and a decision about backfilling existing rows.

## 15. Follow-ups (not built by this feature)

1. **Job view / impression tracking** — requires product decisions (view definition, bot filtering, de-duplication window, retention) before it can be specified. This is what would make "job views" metrics possible.
2. **Pre-aggregation** — introduce a rollup table **only if** the measured query plans in §13.5 show the platform-wide windowed aggregates are the bottleneck at target scale. Trigger thresholds to be agreed at that point, not now.
3. **"Ever reached shortlist" funnel** — a second, more accurate conversion metric derived from `job_application_status_histories`. Currently a documented approximation (A2).
4. **`published_at` column** — required for publication-rate and time-to-publish metrics. Needs a backfill decision for existing rows.
5. **`User::companyRolesFor()` batched helper** — replace the ad-hoc eager-load role computation in the analytics path with a first-class model helper, so the role resolution logic lives in one place.
6. **Fix `Employer\JobController::jobPayload()`'s hard-coded `applications_count => 0`** — a Feature 15 defect. Until then the employer job list shows `0` applications while the analytics per-job table shows the real number. **Flag this to the user in the implementer report** so the discrepancy is not mistaken for a regression.
7. **Bring `docs/authorization.md`'s platform permission table in line with the seeder** — it currently lists 4 of 24 permissions.
8. **Time-to-hire** — derivable from `job_application_status_histories` once the funnel definition stabilises.

## 16. Ordered implementation plan

1. **Schema** — `database/migrations/2026_11_14_000000_add_analytics_supporting_indexes.php`: add the four indexes, drop the redundant `job_applications_job_posting_id_index`, reversible `down()`.2. **Authorization catalogue**
    - `app/Enums/PermissionSlug.php`: add `PlatformAnalyticsView`.
    - `database/seeders/AuthorizationSeeder.php`: display name + `$platformAdminPermissionSlugs` entry.
    - `app/Enums/CompanyMemberRole.php`: add `'analytics.view'` to all three roles.
    - `app/Policies/CompanyPolicy.php`: add `viewAnalytics(User, Company): bool`.
    - `tests/Feature/Authorization/AuthorizationCatalogueTest.php`: counts 24 → 25.
    - `docs/authorization.md`: add the catalogue row.
2. **Form Requests** — `App\Http\Requests\Employer\IndexAnalyticsRequest`, `App\Http\Requests\Admin\IndexPlatformAnalyticsRequest` (including the `prepareForValidation` / `withValidator` idiom from `IndexAuditLogRequest`).
3. **Service** — `app/Services/Analytics/AnalyticsService` with the query methods of §12, the window helper, the status/label vocabulary, and the gap-filling series builder.
4. **Audit integration** — `AuditAction::AnalyticsViewed`, `AuditTargetType::Analytics`, the fail-soft `recordWithoutModel()` call in both controllers, and the `resources/js/types/audit-log.ts` sync.
5. **Controllers + routes** — `Employer\AnalyticsController@index`, `Admin\AnalyticsController@index`; register `employer.analytics.index` in `routes/web.php` and `admin.analytics.index` in `routes/admin.php` with the middleware of §5.
6. **Frontend** — `resources/js/types/analytics.ts` (+ barrel export), `resources/js/components/analytics/*`, `resources/js/pages/employer/analytics.tsx`, `resources/js/pages/admin/analytics.tsx`, and the two navigation entries in `resources/js/lib/navigation.ts`. Run `npm run build` afterwards to regenerate the Wayfinder modules.
7. **Tests** — `tests/Feature/Employer/AnalyticsTest.php`, `tests/Feature/Admin/AnalyticsTest.php`, plus the regression checks of §13.4.
8. **Verification** — run the commands of §13.5, then the §13.5 performance verification with `EXPLAIN` on a representative dataset and record the plans and timings.
9. **Documentation** — update `memory.md` via `/remember save` with the confirmed facts, and record the implementation notes and deviations in this spec's §18-equivalent (add an "Implementation notes" section here, mirroring the Feature 31 spec's §18 pattern).

**Implementation must not begin until this specification is explicitly approved.**

## 17. Implementation notes (2026-11-11)

- **Approval:** the user approved this specification in conversation on 2026-11-11 ("I approved docs/specs/feature-32-employer-and-platform-analytics.md implement it"). Implementation followed the sequence of §16.

### 17.1 What was built

**Backend (new)**

- `database/migrations/2026_11_14_000000_add_analytics_supporting_indexes.php`
- `app/Services/Analytics/AnalyticsService.php`
- `app/Http/Requests/Concerns/ResolvesAnalyticsWindow.php` (shared window resolution)
- `app/Http/Requests/Employer/IndexAnalyticsRequest.php`
- `app/Http/Requests/Admin/IndexPlatformAnalyticsRequest.php`
- `app/Http/Controllers/Employer/AnalyticsController.php`
- `app/Http/Controllers/Admin/AnalyticsController.php`

**Backend (modified)**

- `app/Enums/PermissionSlug.php` (`platform_analytics.view`)
- `app/Enums/CompanyMemberRole.php` (`analytics.view` on all three roles)
- `app/Enums/AuditAction.php` (`analytics.viewed`)
- `app/Enums/AuditTargetType.php` (`analytics`)
- `app/Policies/CompanyPolicy.php` (`viewAnalytics`)
- `app/Services/Audit/AuditLogService.php` (null metadata values — see 17.3)
- `app/Http/Controllers/Admin/AuditLogController.php` (render a null metadata value as `null`)
- `database/seeders/AuthorizationSeeder.php`, `docs/authorization.md`
- `routes/web.php`, `routes/admin.php`

**Frontend (new)** — `resources/js/types/analytics.ts`, `resources/js/components/analytics/{stat-tile,breakdown-bar,trend-chart,analytics-empty,analytics-definitions}.tsx`, `resources/js/pages/employer/analytics.tsx`, `resources/js/pages/admin/analytics.tsx`.

**Frontend (modified)** — `resources/js/types/index.ts`, `resources/js/types/audit-log.ts`, `resources/js/lib/navigation.ts`.

**Tests** — `tests/Feature/Employer/AnalyticsTest.php` (Pest, 31), `tests/Feature/Admin/AnalyticsTest.php` (PHPUnit class, 17), plus three regression tests in `tests/Feature/Audit/AuditLogWritingTest.php` and `tests/Feature/Authorization/AuthorizationCatalogueTest.php` (the catalogue counts).

### 17.2 Deviations from the written specification

1. **Service surface** — one page-level method per scope (`employerMetrics()`, `platformMetrics()`) instead of the granular per-metric methods. Reason in 17.3.
2. **Payload shape** — the per-job list uses the repository's flat `PaginatedData<T>` shape, not the nested `{ data, meta, links }` written in §10.4. The `canViewCompanyAnalytics` prop was dropped (always `true`).
3. **A `preset` filter parameter** was added (§7) because A5 (a default 30-day window) and A6 (an explicit "all time" window) are indistinguishable when only `from`/`to` are accepted.
4. **A fifth index** on `users.created_at` was added after the EXPLAIN verification (§9.2, 17.4).
5. **The chart is CSS-only** — no charting library was added (A7). The breakdown bars and the daily trend are plain Tailwind/shadcn primitives.
6. The employer company selector narrows the metrics to the selected company; the "all companies" option aggregates every authorized company (§2.1).

### 17.3 Defects found during implementation and fixed

1. **An "all time" analytics read wrote zero audit rows.** `AuditLogService::sanitizeValue()` threw a `RuntimeException` for a `null` metadata value, and the unbounded window sends `from`/`to` as `null`. The fail-soft write path swallowed the exception, so no row was persisted — a silent loss of the audit trail. Fixed in the shared service (`null` is a legitimate JSON metadata value), with a regression test in `AuditLogWritingTest` and an end-to-end test on the admin page.
2. **The platform account totals were stale on the second read of a request lifecycle.** The service memoised the account query on its own instance, but Laravel keeps the resolved controller — and therefore its injected service — alive on the route object. The service now holds **no mutable state**.
3. **Both pages executed the job status aggregate and the application status aggregate twice.** The summary and the breakdown each ran their own query. Fixed by the page-level service methods, which read each aggregate once and derive every metric from it. A regression test asserts that each aggregate is executed exactly once per page read.
4. **The company selector did not narrow the metrics.** The controller resolved and authorized the selected company but still aggregated every authorized company. Fixed by scoping the metric queries to the selected company.
5. **`users` was full-scanned by the daily user trend.** Found by the EXPLAIN verification; fixed by the fifth index.

### 17.4 Performance verification (EXPLAIN) — recorded

Seeded on a local SQLite file database: **500 users, 200 companies, 5 000 job postings, 50 000 job applications**, spread over 400 days. Window = the last 30 days.

| Query                                    | Plan                                                                                                                                                   | Time    |
| ---------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------ | ------- |
| M1/M3 employer job status counts         | `SEARCH job_postings USING INDEX job_postings_company_status_idx (company_id=?)`                                                                       | 0.41 ms |
| M1/M3 platform job status counts         | `SEARCH job_postings USING INDEX job_postings_created_at_idx (created_at>? AND created_at<?)`                                                          | 0.42 ms |
| M4/M5 employer application status counts | `SEARCH job_postings USING COVERING INDEX job_postings_company_status_idx` + `SEARCH job_applications USING INDEX job_applications_posting_status_idx` | 3.30 ms |
| M4/M5 platform application status counts | `SEARCH job_applications USING INDEX job_applications_created_at_idx`                                                                                  | 7.61 ms |
| M15 daily job counts                     | `SEARCH job_postings USING COVERING INDEX job_postings_created_at_idx`                                                                                 | 0.27 ms |
| M16 daily application counts             | `SEARCH job_applications USING COVERING INDEX job_applications_created_at_idx`                                                                         | 1.19 ms |
| M17 daily user counts                    | `SEARCH users USING COVERING INDEX users_created_at_idx`                                                                                               | 0.14 ms |
| M18 top categories                       | `SEARCH job_postings USING INDEX job_postings_status_created_at_index`                                                                                 | 0.29 ms |

Every index the migration creates is used by the planner. The `users` row was a `SCAN users` before `users_created_at_idx` was added.

**Measured query counts per page read** (total, including the framework and app-shell queries every authenticated page pays):

| Page               | Total | Feature-owned |
| ------------------ | ----- | ------------- |
| Employer analytics | 13    | 10            |
| Platform analytics | 12    | 9             |

The three framework queries are the shared role/permission resolution and the unread-notification count. The counts are constant regardless of data volume — asserted by the N+1 regression tests.

### 17.5 Verification actually run (final tree)

- `php artisan test` → **633 passed / 633, 5273 assertions**, 0 failures (baseline before this feature: 582 / 4411).
- `vendor\bin\pint --test` → passed.
- `php vendor/bin/phpstan analyse --memory-limit=512M --no-progress` → passed, 0 errors.
- `npm run types:check` → passed.
- `npm run check` → passed (222 files formatted, no lint errors).
- `npm run build` → succeeded; three analytics page chunks emitted and the Wayfinder `employer/analytics` and `admin/analytics` modules regenerated.
- `composer ci:check` → exit code 0.
- `php artisan migrate --force` on a local SQLite file database → the migration ran; `php artisan migrate:rollback --step=1` → the `down()` ran cleanly; re-migrating succeeded.
- **`php artisan migrate --force` on the local MySQL/MariaDB database** → the migration ran and the final index inventory was verified with `php artisan db:table`: `job_postings_company_status_idx`, `job_postings_created_at_idx`, `job_applications_created_at_idx`, `job_applications_posting_status_idx`, `users_created_at_idx`, `companies_created_at_idx` all present, and the redundant `job_applications_job_posting_id_index` removed. `php artisan migrate:rollback --step=1` → the `down()` ran cleanly on MySQL after the portability fix; re-migrating succeeded.
- **`EXPLAIN` on MySQL** for every aggregate query. The local MySQL dataset is small (12 users, 6 companies, 19 job postings, 9 applications), so the planner prefers scans on the small tables; the plans confirm that `job_postings_created_at_idx`, `job_applications_created_at_idx`, `users_created_at_idx` and `companies_created_at_idx` are selected and used as covering indexes for the windowed queries.
- `php artisan route:list --name=analytics` → both routes registered with the intended middleware.

### 17.6 Not exercised

- No browser QA of the two analytics pages: the CSS bar rendering at narrow widths, the collapsed metric-definition disclosure, and the screen-reader summary of the trend chart were not observed in a running application.
- The `PasswordReset` audit path (pre-existing, not covered by an automated test) is unrelated to this feature.
- The MySQL query plans were measured on a dataset too small to be representative (19 job postings, 9 applications), so they confirm index _usability_ but not index _selection at scale_. The scale figures in §17.4 come from the SQLite dataset of 50 000 applications. The plans should be re-measured in the deploy environment, as §14.3 item 5 already requires.
- The audit-log pages were not re-checked in a browser after the two new enum entries were added; the type mirror and the existing audit tests cover the change.

### 17.7 Independent review

An independent read-only review was run over the whole feature (tenant isolation, authorization, validation, data exposure, query shape, the shared-service change and the migration). It found **no Critical and no High** issue. Its findings and their disposition:

| Finding                                                                                                                                                                | Severity | Disposition                                                                                                                                                                                                             |
| ---------------------------------------------------------------------------------------------------------------------------------------------------------------------- | -------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| The per-job table sorted only by `created_at`, which is not unique, so tied rows could be duplicated or skipped across paginated pages                                 | Medium   | **Fixed.** A secondary `id` sort makes the order total, matching the pattern already used by `Admin\AuditLogController`.                                                                                                |
| `companies` had no `created_at` index, so the platform company total full-scanned the table                                                                            | Medium   | **Fixed.** `companies_created_at_idx` added to the migration and verified on MySQL.                                                                                                                                     |
| The controller eager-loaded every member of every managed company to resolve the actor's own role                                                                      | Low      | **Fixed.** Only the actor's own active memberships are read now.                                                                                                                                                        |
| The authorized-company collection is loaded and shipped without a code-level bound                                                                                     | Low      | **Accepted and documented.** The bound is the number of companies an employer manages, which is small by construction (memberships are granted individually), and it is now stated in a code comment next to the query. |
| A cross-tenant 403 left no trace, so a probing campaign was invisible                                                                                                  | Low      | **Fixed.** A denied company selection is now reported to the application log. No audit row is written, because no page was read and the audit catalogue is a closed vocabulary.                                         |
| `company_id` existence was observable (422 for a missing id, 403 for an unauthorized one)                                                                              | Low      | **Accepted and documented.** The ordering is deliberate — the approved test matrix asserts both — and it discloses existence, not contents. Documented in §14.1.                                                        |
| The audit metadata `company_ids` array is capped at twenty entries while `company_count` records the true count                                                        | Low      | **Accepted and documented.** `company_count` is authoritative; the array is a sample. Documented in §8.                                                                                                                 |
| The summary totals and the per-job table did not reconcile, because an application to a job created before the window counts in the totals but appears in no table row | Low      | **Fixed as a presentation gap.** The rule is now stated in the `total_applications` definition and in the per-job card description. The metric itself is unchanged — it follows A1.                                     |
| The job status breakdown listed only the five known statuses, so an unexpected sixth status would inflate the total without appearing                                  | Low      | **Fixed.** The breakdown is now derived from the union of the known and the observed statuses.                                                                                                                          |
| The top-categories list ignored `job_categories.is_active`                                                                                                             | Low      | **Accepted and documented.** The metric counts published postings, and a retired category's postings are still published. Documented in §4 (M18).                                                                       |
| A custom window with only a `from` bound rendered a dangling separator                                                                                                 | Low      | **Fixed.** The frontend now names the open edge ("From 1 Nov 2026 onwards").                                                                                                                                            |
| Nothing prevented a future platform permission from reusing the `analytics.view` slug                                                                                  | Nit      | **Fixed.** `App\Enums\PermissionSlug` now documents that the company-scoped slug lives in a separate namespace.                                                                                                         |

The review also confirmed that the tenant scope is applied in SQL rather than in PHP in all four employer-scoped queries, that the `withCount` sub-selects are structurally unable to escape the company scope, that the daily series is provably bounded, that no candidate personal data crosses the prop boundary, and that the `AuditLogService` null change cannot bypass the redaction and size caps.
