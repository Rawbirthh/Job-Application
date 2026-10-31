# Feature 9 — Admin Manages Salary Filter Bands and Pay-Period Options

**Metadata:**

- Feature ID: 9
- Title: Admin Manages Salary Filter Bands and Pay-Period Options
- Spec Type: Feature specification
- Source: `project-overview.md` → "Feature 9 — Admin manages salary filter bands and pay-period options" (Phase 1 — Platform configuration and governance)
- Dependencies: Feature 3 (custom authorization — implemented), Feature 4 (shared shell/navigation — implemented)
- Status: **APPROVED — implemented and verified (2026-10-31)**
- Created: 2026-10-31

---

## Goal

Give authorized platform administrators a secure way to maintain two related compensation catalogues:

1. **Pay-period options** such as hour, day, week, month, and year, with stable machine keys, display order, and active/inactive state.
2. **Salary filter bands** that provide optional, labelled compensation filter choices, scoped to an explicit currency and pay period, without restricting or replacing the actual minimum/maximum salary values stored on future job records.

Salary bands are **filter/display configuration only**. A job's real salary remains its own min/max/currency/pay-period values in Feature 13. A job is never required to match a configured band.

## Non-goals

- Currency exchange rates, automatic conversion, annualization, payroll, or comparing salaries across currencies or pay periods.
- Enforcing salary bands as the only permitted salary values.
- Employer/public-side creation or editing of pay periods or salary bands.
- Job salary fields, job forms, public job search/filter implementation, or job records — those belong to Features 13, 16, and 17.
- A currency catalogue or fixed ISO-4217 allowlist. This feature validates a three-letter uppercase currency code but does not add a currency-management feature.
- Saved-search references to bands (Feature 29).
- Deleting a pay period that is referenced by any salary band; deletion is blocked and deactivation is the retirement path.
- SoftDeletes, `is_system` protection, or audit-log integration (Feature 31).

---

## Actors and flows

**Primary actor:** Platform administrator holding `admin.access` plus the relevant catalogue permissions.

**Key flows:**

1. Admin opens `/admin/pay-periods` and sees a paginated, searchable list ordered by display order, with the number of salary bands referencing each period and active/inactive status.
2. Admin creates a pay period with name, stable slug/key, optional description, display order, and active state.
3. Admin edits a pay period; deactivates it to retire it without deleting it.
4. Admin attempts to delete an unreferenced pay period; deletion succeeds.
5. Admin attempts to delete a pay period referenced by any salary band; deletion is refused with an explanatory error and the row remains.
6. Admin opens `/admin/salary-bands` and sees a paginated, searchable list filterable by pay period, showing label, currency, pay period, half-open range, and status.
7. Admin creates a salary band by selecting a pay period, entering a label, a three-letter currency, lower/upper thresholds, and active state.
8. Admin edits or deletes an existing band. Deleting a band never touches a job because jobs do not reference bands.
9. A user without the required permission receives `403 Forbidden` for every protected action, including direct HTTP requests.
10. A view-only administrator sees the catalogue but cannot create, edit, or delete records.

---

## Decision summary

These are the material design choices. Each is labelled as an assumption in case approval wants a different policy.

1. **Two separate admin pages**, not one combined "Compensation" page: pay periods and salary bands are independent catalogues with their own permissions, matching the existing one-resource-per-page convention.
2. **Currency- and pay-period-specific bands.** A band is only comparable to a job salary when both `currency` and `pay_period_id` match exactly. No conversion, normalization factor, or cross-period comparison is introduced.
3. **Half-open ranges:** a band covers `[lower_bound, upper_bound)` — the lower threshold is included and the upper threshold is excluded. This makes adjacent bands (`0–50000`, `50000–100000`) non-overlapping and unambiguous.
4. **Overlapping bands are rejected** within the same currency + pay period, regardless of active status. Adjacent half-open bands are allowed. This is the explicit "overlapping or contradictory bands" rule.
5. **Both thresholds are required.** Open-ended "100k+" bands are not supported in this increment; an admin can use a sufficiently high upper bound.
6. **Four permissions:** `pay_periods.view`, `pay_periods.manage`, `salary_bands.view`, `salary_bands.manage`.
7. **No seed data** for either catalogue, matching Features 5–8. Pay periods start empty until an administrator configures them.
8. **No Service or Repository.** This is single-table CRUD plus one reusable overlap validation rule. No transaction or multi-step domain workflow justifies a service layer.

---

## User-visible behavior and acceptance criteria

### Pay periods

- [ ] An admin with `pay_periods.view` can open `/admin/pay-periods` and see a paginated list (20 per page) with search by name or slug.
- [ ] The list shows name, slug, display order, number of referencing salary bands, and active/inactive status.
- [ ] Only users holding `pay_periods.manage` see create/edit/delete controls and can perform writes; view-only admins see a read-only panel.
- [ ] Users without `pay_periods.view` receive 403 on index; users without `pay_periods.manage` receive 403 on store/update/destroy, including direct HTTP requests.
- [ ] Creating a pay period with a duplicate name or slug returns field-level validation errors rendered inline.
- [ ] Name and slug are required; slug is `alpha_dash`, ≤100 characters; description is optional ≤500 characters; `sort_order` is an optional integer 0–9999 defaulting to 0; `is_active` defaults to `true`.
- [ ] Search filters server-side with LIKE-escaped prefix matching on name or slug, preserves the query string across pagination, and returns a clear empty state.
- [ ] Deactivating a pay period succeeds immediately and does not delete or mutate its salary bands.
- [ ] Deleting an unreferenced pay period succeeds.
- [ ] Deleting a pay period referenced by any salary band is refused with an explanatory toast; the row remains. The database foreign key is the final guard.
- [ ] The "Pay Periods" navigation item appears only for users holding `admin.access` + `pay_periods.view`.
- [ ] List ordering is stable: `sort_order` ascending, then `name` ascending, then `id` ascending.

### Salary filter bands

- [ ] An admin with `salary_bands.view` can open `/admin/salary-bands` and see a paginated list (20 per page) with search by label or currency prefix and an optional exact pay-period filter.
- [ ] The list shows label, currency, pay period (including an inactive marker), half-open range, and active/inactive status.
- [ ] Only users holding `salary_bands.manage` see create/edit/delete controls and can perform writes; view-only admins see a read-only panel.
- [ ] Users without `salary_bands.view` receive 403 on index; users without `salary_bands.manage` receive 403 on store/update/destroy, including direct HTTP requests.
- [ ] Creating a band requires a label, a three-letter uppercase currency, an existing pay period, and numeric lower/upper thresholds with at most two decimal places.
- [ ] The lower threshold must be ≥ 0 and strictly less than the upper threshold.
- [ ] A band is uniquely labelled within its currency + pay period; the same label may be reused in a different currency or pay period.
- [ ] Exact duplicate ranges are rejected by validation and by a database unique constraint.
- [ ] Overlapping ranges are rejected within the same currency + pay period, including when the overlapping band is inactive.
- [ ] Adjacent half-open ranges are accepted; the same range is accepted in a different currency or a different pay period.
- [ ] New bands may only select an **active** pay period. When editing a band whose current pay period is inactive, the band may keep that period and may be updated; switching to another inactive period is rejected.
- [ ] Inactive bands remain visible in the admin list and can be reactivated; reactivation is subject to the same overlap rule.
- [ ] Deleting a band succeeds and never affects a job, because jobs do not reference bands.
- [ ] Search and pay-period filters are server-driven, preserve query strings across pagination, and return a clear empty state.
- [ ] The "Salary Bands" navigation item appears only for users holding `admin.access` + `salary_bands.view`.
- [ ] List ordering is stable: `currency` ascending, then `pay_period_id` ascending, then `lower_bound` ascending, then `id` ascending.

### Cross-feature rules (documented now, enforced later)

- [ ] Feature 13 stores a job's actual optional min/max salary, currency, and pay-period foreign key. Jobs never reference a salary band.
- [ ] Feature 13 adds `pay_period_id` to jobs with restrict-on-delete semantics and only permits active pay periods for new/edited jobs.
- [ ] Feature 16/17 apply bands only when the selected currency and pay period match exactly. Bands attached to inactive pay periods are excluded from public filters.
- [ ] Feature 16/17 must not compare different currencies or pay periods, and must not require a job to match a band.
- [ ] Feature 29 must define what happens to a saved search that references a deleted band; snapshotting filter criteria is the recommended direction.

---

## Assumptions and focused questions

**Assumptions (override in approval if you disagree):**

1. **Currency-specific bands are sufficient.** No cross-currency or cross-period normalization is introduced. If the product later needs meaningful cross-currency filters, that requires a separate conversion/normalization feature and a revised Feature 16 spec.
2. **Half-open interval semantics** `[lower, upper)` are used. If you prefer inclusive upper bounds, adjacent bands would need a one-cent gap and the overlap rule would change.
3. **Overlaps are rejected across all statuses.** An inactive band still reserves its range. To replace a range, edit the existing band rather than creating a second overlapping row.
4. **Two pages and four permissions** are preferred over a single combined page with shared `compensation.*` permissions. This keeps the permission catalogue consistent with Features 5–8.
5. **No seed data.** The admin must create pay periods before creating salary bands. If you want standard `hour`, `day`, `week`, `month`, `year` rows seeded, approval can add a repeatable seeder.
6. **Both thresholds required.** No nullable upper bound for "and above" bands in this increment.
7. **Currency validation is structural, not a fixed ISO list.** Any three ASCII letters are accepted and normalized to uppercase. A fixed allowlist or currency catalogue is out of scope.
8. **Slugs remain editable**, matching existing catalogues. The slug is the stable machine key, but this feature does not make it immutable. If downstream code must rely on specific slugs, protect them in a later decision.
9. **Pay-period deletion is blocked by any band reference**, active or inactive. Deactivation is the retirement path.
10. **No automatic cascade** deactivates bands when a pay period is deactivated. Downstream filters must join active pay periods; the admin can deactivate bands separately.

**Focused questions (non-blocking; defaults above apply):**

- Should standard pay periods be seeded for development, or start empty like the other catalogues?
- Do you want open-ended top bands (e.g. `100000+`) instead of requiring both thresholds?
- Do you want one combined "Compensation" page instead of two navigation entries?

---

## Existing code and conventions inspected

- `docs/specs/feature-08-experience-levels.md` — closest catalogue pattern: migration/model/factory, permission pair, Policy, Store/Update Form Requests, thin controller, `SplitLayout`, `Pagination`, tests.
- `app/Models/ExperienceLevel.php`, `WorkplaceArrangement.php`, `JobCategory.php` — `$fillable`, boolean/integer casts, `scopeActive`, typed factories.
- `app/Http/Controllers/Admin/ExperienceLevelController.php` — `$this->authorize()` per action, inline validation for the read-only `search` query param, `paginate(20)->withQueryString()`, LIKE escaping, `Inertia::flash` toast, `to_route()`.
- `app/Http/Requests/Admin/StoreExperienceLevelRequest.php` / `UpdateExperienceLevelRequest.php` — typed data accessors using `$this->safe()->all()`, never raw request payloads.
- `app/Policies/ExperienceLevelPolicy.php` — permission-based policy methods.
- `routes/admin.php` — `permission:admin.access` group, `permission:<resource>.view` on index, `permission:<resource>.manage` on write verbs.
- `app/Enums/PermissionSlug.php` and `database/seeders/AuthorizationSeeder.php` — closed enum plus explicit platform-admin allowlist.
- `resources/js/pages/admin/experience-levels/index.tsx` — `useForm`, Wayfinder actions, `SplitLayout`, `Pagination`, `InputError`, active/inactive badges, view-only gating.
- `resources/js/lib/navigation.ts` — nav items with `requiredPermissions`.
- `tests/Feature/Admin/ExperienceLevelTest.php`, `tests/Feature/Authorization/AuthorizationCatalogueTest.php` — test patterns and hardcoded permission counts.
- `resources/js/components/ui/select.tsx` — existing shadcn/Radix select primitive for the pay-period picker.
- `package.json` / `composer.json` — available scripts and no new packages required.
- Database targets: tests run on SQLite; production target is MySQL. Schema types and queries must remain portable.

---

## Routes, middleware, and authorization

| Method | URI                                 | Name                         | Middleware                                                          |
| ------ | ----------------------------------- | ---------------------------- | ------------------------------------------------------------------- |
| GET    | `/admin/pay-periods`                | `admin.pay-periods.index`    | `auth`, `permission:admin.access`, `permission:pay_periods.view`    |
| POST   | `/admin/pay-periods`                | `admin.pay-periods.store`    | `auth`, `permission:admin.access`, `permission:pay_periods.manage`  |
| PUT    | `/admin/pay-periods/{pay_period}`   | `admin.pay-periods.update`   | same as store                                                       |
| DELETE | `/admin/pay-periods/{pay_period}`   | `admin.pay-periods.destroy`  | same as store                                                       |
| GET    | `/admin/salary-bands`               | `admin.salary-bands.index`   | `auth`, `permission:admin.access`, `permission:salary_bands.view`   |
| POST   | `/admin/salary-bands`               | `admin.salary-bands.store`   | `auth`, `permission:admin.access`, `permission:salary_bands.manage` |
| PUT    | `/admin/salary-bands/{salary_band}` | `admin.salary-bands.update`  | same as store                                                       |
| DELETE | `/admin/salary-bands/{salary_band}` | `admin.salary-bands.destroy` | same as store                                                       |

- Add both route groups inside the existing `auth` + `admin` prefix/name group in `routes/admin.php`.
- Each group is wrapped in `permission:admin.access`; index adds the `.view` permission; write verbs add `.manage`.
- Controllers call `$this->authorize(...)` per action for defense in depth, matching Features 5–8.
- No company/owner/tenant scoping applies; these are global platform-configuration resources with a single admin audience.
- No moderation or public fields exist on these tables. Public forms never touch these routes.
- Wayfinder exposes hyphenated route names as `admin.payPeriods.*` and `admin.salaryBands.*`; frontend imports must use those generated names.

### Permission catalogue changes

- Add enum cases:
    - `PayPeriodsView = 'pay_periods.view'`
    - `PayPeriodsManage = 'pay_periods.manage'`
    - `SalaryBandsView = 'salary_bands.view'`
    - `SalaryBandsManage = 'salary_bands.manage'`
- Add all four to `AuthorizationSeeder` with display names:
    - "View pay periods"
    - "Manage pay periods"
    - "View salary bands"
    - "Manage salary filter bands"
- Add all four to the explicit `platformAdminPermissionSlugs` allowlist.
- Update `AuthorizationCatalogueTest` hardcoded counts from 15 → 19 and 16 → 20.
- Deployed environments must rerun `AuthorizationSeeder`; otherwise existing administrators receive 403 on the new routes.

---

## Policy actions and dynamic permission resolution

### `PayPeriodPolicy`

| Action                         | Permission           |
| ------------------------------ | -------------------- |
| `viewAny` / `view`             | `pay_periods.view`   |
| `create` / `update` / `delete` | `pay_periods.manage` |

### `SalaryBandPolicy`

| Action                         | Permission            |
| ------------------------------ | --------------------- |
| `viewAny` / `view`             | `salary_bands.view`   |
| `create` / `update` / `delete` | `salary_bands.manage` |

- Policies use the existing `hasPermission()` service through `User`; no role-name checks.
- The controller remains the authorization entry point; Form Request `authorize()` returns `true` because the controller performs the Policy check, matching the established pattern.
- The pay-period "referenced by bands" delete guard is a business rule, not a permission. It is enforced in the controller after `authorize('delete', ...)` and backed by the database FK. It must not be implemented as a silent `false` Policy result, because a 403 would hide the actionable reason.
- `salary_bands.view` alone is sufficient to view the salary-band page and its pay-period option list. `pay_periods.view` is not required for that page; the pay-period names are non-sensitive configuration needed to interpret bands.

---

## Form Requests and validation

### `StorePayPeriodRequest` / `UpdatePayPeriodRequest`

Mirror the experience-level requests:

- `name`: `required`, `string`, `max:100`, unique on `pay_periods.name` (update ignores current row).
- `slug`: `required`, `string`, `max:100`, `alpha_dash`, unique on `pay_periods.slug` (update ignores current row).
- `description`: `nullable`, `string`, `max:500`.
- `sort_order`: `nullable`, `integer`, `min:0`, `max:9999`; defaults to 0.
- `is_active`: `boolean`; defaults to `true`.
- Typed `payPeriodData()` accessor using `$this->safe()->all()`.
- `UpdatePayPeriodRequest` resolves the current row for unique-ignore; the controller still `findOrFail()`s before authorizing.

### `StoreSalaryBandRequest` / `UpdateSalaryBandRequest`

- `prepareForValidation()` trims and uppercases `currency` before validation, so `usd` becomes `USD`.
- `pay_period_id`:
    - `required`, `integer`.
    - Store: must exist and be active (`Rule::exists('pay_periods', 'id')->where('is_active', true)`).
    - Update: must exist; if the selected period is inactive, it is only allowed when it equals the band's current `pay_period_id`. Switching to another inactive period is rejected with a field error. An `after()` validation hook or equivalent rule is acceptable.
- `label`: `required`, `string`, `max:100`; unique within the same `currency` + `pay_period_id`, ignoring the current band on update.
- `currency`: `required`, `string`, `size:3`, `regex:/^[A-Z]{3}$/`.
- `lower_bound`: `required`, `numeric`, `min:0`, `max:9999999999.99`, `decimal:0,2`.
- `upper_bound`: `required`, `numeric`, `max:9999999999.99`, `decimal:0,2`, `gt:lower_bound`, plus the overlap rule.
- `is_active`: `boolean`; defaults to `true`.
- Typed `salaryBandData()` accessor using `$this->safe()->all()`, preserving decimal values as received rather than casting to float.
- The client cannot set IDs, timestamps, or any fields outside the validated set.

### Overlap rule

Create `App\Rules\SalaryBandRangeDoesNotOverlap` (name may be adjusted to repository style):

- Constructed with currency, pay-period ID, lower bound, upper bound, and optional ignored band ID.
- Queries `salary_bands` for any row in the same `currency` + `pay_period_id` where:
    - `lower_bound < new upper_bound`, and
    - `upper_bound > new lower_bound`.
- Excludes the current row on update.
- Checks all statuses, active and inactive.
- Returns a clear message: "This range overlaps an existing salary band for the selected currency and pay period."
- The rule must guard against non-numeric inputs; place it after basic numeric/`gt` rules and use `bail` where appropriate.
- Adjacent ranges are allowed because the comparison is strict (`<` / `>`), not inclusive.

### Pay-period delete guard

`PayPeriodController@destroy`:

1. `findOrFail` the pay period.
2. `$this->authorize('delete', $payPeriod)`.
3. If `$payPeriod->salaryBands()->exists()`, redirect to `admin.pay-periods.index` with an error toast and do not delete.
4. Otherwise delete and show a success toast.
5. The FK `restrictOnDelete` is the final concurrency guard. If a `QueryException` occurs because a band was created between the check and delete, catch it and return the same friendly error instead of a 500.

---

## Models, relationships, migration, constraints, and indexes

### `PayPeriod` (`app/Models/PayPeriod.php`)

- Table: `pay_periods`.
- `$fillable = ['name', 'slug', 'description', 'sort_order', 'is_active']`.
- Casts: `is_active` boolean, `sort_order` integer.
- `scopeActive(Builder $query)`.
- `salaryBands(): HasMany<SalaryBand>`.
- No SoftDeletes, no `is_system`.

### `SalaryBand` (`app/Models/SalaryBand.php`)

- Table: `salary_bands`.
- `$fillable = ['label', 'currency', 'pay_period_id', 'lower_bound', 'upper_bound', 'is_active']`.
- Casts: `is_active` boolean, `lower_bound` `decimal:2`, `upper_bound` `decimal:2`.
- `payPeriod(): BelongsTo<PayPeriod, $this>`.
- `scopeActive(Builder $query)`.
- No SoftDeletes, no `is_system`.
- No `sort_order`; ordering is by range thresholds.

### Migration `create_pay_periods_table`

Proposed filename: `2026_11_01_000002_create_pay_periods_table.php` (next sequential timestamp after Feature 8).

- `id`.
- `name` string(100) unique.
- `slug` string(100) unique.
- `description` nullable text.
- `sort_order` integer default 0.
- `is_active` boolean default true.
- `timestamps`.
- No FK yet. Feature 13 adds `pay_period_id` to jobs with restrict-on-delete.

### Migration `create_salary_bands_table`

Proposed filename: `2026_11_01_000003_create_salary_bands_table.php`.

- `id`.
- `label` string(100).
- `currency` string(3).
- `pay_period_id` foreign ID:
    - `->index()` (explicit FK index; MySQL can reuse it for the constraint).
    - `->constrained('pay_periods')->restrictOnDelete()`.
- `lower_bound` decimal(12, 2).
- `upper_bound` decimal(12, 2).
- `is_active` boolean default true.
- `timestamps`.
- Unique `(currency, pay_period_id, label)` with a short explicit name, e.g. `salary_bands_currency_period_label_unique`.
- Unique `(currency, pay_period_id, lower_bound, upper_bound)` with a short explicit name, e.g. `salary_bands_currency_period_range_unique`.
- The range unique index also provides the leftmost-prefix access path for the default list ordering by currency, pay period, and lower bound.
- Do not add speculative `is_active` or search indexes; these are small configuration tables and the query shape is bounded. Revisit only with profiling evidence.
- No `jobs` FK yet. Feature 13 adds `pay_period_id` to jobs; jobs must not add a `salary_band_id` FK.

### Factories

- `PayPeriodFactory`: unique name/slug, description, `sort_order` 0, active; `inactive()` state.
- `SalaryBandFactory`: unique label, currency default `USD`, a `PayPeriod` factory relation, non-overlapping default range, active; `inactive()`, `forPayPeriod(PayPeriod $period)`, and `currency(string $currency)` states as useful.
- Factories must avoid random overlapping ranges within the same currency + period; tests that need overlaps should create them explicitly with `SalaryBand::factory()->create([...])` after disabling the validation path or by constructing rows directly. Keep factory defaults simple and deterministic enough for tests.

---

## Query/data payload shape, eager loading, and pagination

### Pay periods index

- Authorize `viewAny`.
- Validate `search` as `nullable|string|max:100`.
- Trim and LIKE-escape the search; prefix match on `name` OR `slug`.
- Query:
    - `withCount('salaryBands')`.
    - `when(search, ...)`.
    - `orderBy('sort_order')->orderBy('name')->orderBy('id')`.
    - `paginate(20)->withQueryString()`.
- `through()` projection: `id`, `name`, `slug`, `description`, `sort_order`, `is_active`, `bands_count`.
- Props: `payPeriods`, `filters: { search }`, `canManagePayPeriods`.
- `withCount` avoids N+1 for the reference count. No relationship rows are needed.

### Salary bands index

- Authorize `viewAny`.
- Validate:
    - `search`: `nullable|string|max:100`.
    - `pay_period_id`: `nullable|integer|exists:pay_periods,id`.
- Trim and LIKE-escape search; prefix match on `label` OR `currency`.
- Query:
    - `with('payPeriod:id,name,slug,is_active')`.
    - `when(search, ...)`.
    - `when(pay_period_id, ...)`.
    - `orderBy('currency')->orderBy('pay_period_id')->orderBy('lower_bound')->orderBy('id')`.
    - `paginate(20)->withQueryString()`.
- `through()` projection:
    - `id`, `label`, `currency`, `lower_bound` (decimal string), `upper_bound` (decimal string), `is_active`.
    - `pay_period`: `{ id, name, slug, is_active }` or `null` if the relation is unexpectedly missing.
- Pay-period options for the form/filter:
    - `PayPeriod::query()->select(['id', 'name', 'slug', 'is_active', 'sort_order'])->orderBy('sort_order')->orderBy('name')->orderBy('id')->get()`.
    - This is a bounded configuration lookup (small table) and is justified; do not paginate the form options.
- Props: `salaryBands`, `payPeriods`, `filters: { search, pay_period_id }`, `canManageSalaryBands`.
- No job data, company data, or user data is exposed.

### Frontend query behavior

- Search/filter changes use `router.get(Controller.index.url(), data, { preserveState: true, preserveScroll: true, replace: true })`.
- Omit `pay_period_id` from the query string when no period is selected.
- Pagination uses the server-provided `prev_page_url` / `next_page_url`, preserving query strings via `withQueryString()`.
- Do not client-side paginate or filter a full dataset.

---

## Service/Repository decision

**No Service and no Repository.**

Reasons:

- Each resource is a single-table CRUD catalogue with no multi-step transaction, external integration, or reusable query beyond the index query.
- The only non-trivial logic is the overlap check, which is reused by Store and Update Form Requests and belongs in a dedicated validation rule, not a service.
- The pay-period delete guard is one `exists()` check plus the FK; it stays in the thin controller.
- This matches the Feature 5–8 decision and the `AGENTS.md` rule against adding layers for ceremony.

A `SalaryBandRangeDoesNotOverlap` validation rule is the only new abstraction and is justified by reuse across two requests.

---

## React/Inertia frontend

### New pages

1. `resources/js/pages/admin/pay-periods/index.tsx`
2. `resources/js/pages/admin/salary-bands/index.tsx`

Both use the existing `SplitLayout`, `Pagination`, `InputError`, shadcn/ui `Input`, `Textarea`, `Checkbox`, `Button`, `Badge`, `Spinner`, and `Select` primitives. No new component library or package.

### Pay periods page

Mirror `resources/js/pages/admin/experience-levels/index.tsx`:

- `Head` title "Pay Periods Management".
- `SplitLayout` with create/edit form on the left and searchable paginated table on the right.
- Form fields: Name, Slug, Description, Display order, Active.
- `useForm<{ name: string; slug: string; description: string; sort_order: number; is_active: boolean }>`.
- Wayfinder actions from `PayPeriodController`.
- Edit state via `useState`; cancel resets form and errors; success toast; processing disables controls.
- Table columns: Period (name + description), Slug, Order, Bands (count), Status, Actions.
- If `bands_count > 0`, disable the delete button and explain via `title`/helper text that the period is referenced and should be deactivated instead. The server still enforces the rule for direct requests.
- Empty state: "No pay periods found."
- View-only panel when `canManagePayPeriods` is false.
- Active/inactive badges use the established emerald/muted treatment.

### Salary bands page

- `Head` title "Salary Filter Bands Management".
- `SplitLayout` with form left and searchable/filterable table right.
- Form fields:
    - Pay period `Select` (active periods selectable; inactive periods disabled except the band's current period when editing).
    - Label `Input` (max 100).
    - Currency `Input` (maxLength 3, uppercase on change, `autoCapitalize="characters"`).
    - Lower bound `Input` (`type="number"`, `step="0.01"`, `min={0}`, `inputMode="decimal"`).
    - Upper bound `Input` (`type="number"`, `step="0.01"`, `min={0}`, `inputMode="decimal"`).
    - Active `Checkbox`.
- Helper text: "The lower bound is included; the upper bound is excluded. Bands with the same currency and pay period cannot overlap."
- `useForm` shape: `{ pay_period_id: string; label: string; currency: string; lower_bound: string; upper_bound: string; is_active: boolean }`.
    - The Select value is a string; the backend `integer` rule accepts the numeric string.
    - Amounts stay strings in the client to avoid float precision loss.
- If there are no active pay periods and the admin is creating, disable the form and show an actionable message with a link to `/admin/pay-periods`. Editing a band on an inactive period remains possible.
- Table columns: Label, Currency, Pay period (with inactive badge), Range, Status, Actions.
- Range display: `formatAmount(lower) – < formatAmount(upper)`, using `Intl.NumberFormat` with two fraction digits; do not use currency symbols because the currency code is not validated against a fixed ISO list. Example: `USD 0.00 – < 50,000.00`.
- Search form: search input (label/currency) plus pay-period `Select` ("All periods") and Search/Clear buttons. Clear resets both filters.
- Empty state: "No salary bands found." If no pay periods exist, explain that a pay period must be created first.
- View-only panel when `canManageSalaryBands` is false.
- Delete confirmation via the existing `confirm()` pattern; success/error toasts.
- `Pagination` below the table when `total > 0`.

### Navigation

Add to `resources/js/lib/navigation.ts`:

- "Pay Periods" → `admin.payPeriods.index()`, icon `Clock`, `requiredPermissions: ['admin.access', 'pay_periods.view']`.
- "Salary Bands" → `admin.salaryBands.index()`, icon `Banknote`, `requiredPermissions: ['admin.access', 'salary_bands.view']`.

The navigation is presentation only; direct requests remain server-authorized.

### TypeScript types

Define page-local or shared types:

```ts
type PayPeriod = {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    sort_order: number;
    is_active: boolean;
    bands_count: number;
};

type SalaryBand = {
    id: number;
    label: string;
    currency: string;
    lower_bound: string;
    upper_bound: string;
    is_active: boolean;
    pay_period: {
        id: number;
        name: string;
        slug: string;
        is_active: boolean;
    } | null;
};

type PayPeriodOption = {
    id: number;
    name: string;
    slug: string;
    is_active: boolean;
};
```

Use `PaginatedData<T>` from `@/types/authorization`. No `any`.

---

## Accessibility, empty, loading, and error states

- Every form control has a visible `<Label htmlFor>`; helper text and errors use `aria-describedby` and `aria-invalid`.
- `InputError` remains `role="alert"`.
- The pay-period and salary-band tables use `<th scope="col">`, a clear empty row, and action buttons with `aria-label`/`title`.
- Delete buttons that are disabled because of a band reference explain why via `title` and adjacent helper text.
- Search and filter forms are real `<form onSubmit={...}>` elements; submit buttons are disabled while `processing`/searching and show a spinner.
- `Pagination` provides `aria-label="Pagination"`, `aria-current="page"`, and `rel="prev"/"next"`.
- Loading: Inertia progress plus search spinner; no full-page skeleton is required for these small admin tables.
- Errors:
    - Field errors render inline via `InputError`.
    - Blocked pay-period deletion shows an error toast and leaves the row unchanged.
    - Successful writes show success toasts.
    - 403/404 are handled by the framework; no custom error page is required.
- Empty:
    - No pay periods: clear empty row and a prompt to create one.
    - No salary bands: clear empty row.
    - No active pay periods for a new band: disabled form with a link to pay periods.
- Responsive: tables scroll horizontally in a bordered container; the split layout stacks below `lg`.

---

## Test matrix

Use Pest, `RefreshDatabase`, and `InteractsWithAuthorizationCatalogue`, matching existing feature tests. Owner/non-owner cases do not apply because these are global platform-configuration resources; use guest, non-admin, view-only admin, and manage-permission cases instead.

### `tests/Feature/Admin/PayPeriodTest.php`

- Admin with permission sees the index with seeded rows, `bands_count`, and `canManagePayPeriods`.
- Create happy path with defaults (`is_active` true, `sort_order` 0).
- Create with explicit `sort_order` and inactive state.
- Update happy path, including active toggle and sort order.
- Delete an unreferenced pay period succeeds.
- Delete a pay period referenced by an active band is refused; row remains; error toast.
- Delete a pay period referenced by an inactive band is also refused.
- After deleting all bands, the pay period can be deleted.
- Duplicate name and slug on create return field errors.
- Update ignores unique rules for the same row and rejects duplicates from another row.
- Invalid input: missing name, bad slug, overlong description, out-of-range sort order.
- Non-admin (job seeker) gets 403 on index, store, update, destroy.
- Guest is redirected to login on index.
- View-only admin (`admin.access` + `pay_periods.view`) sees the page but gets 403 on all writes.
- Unknown pay-period ID returns 404 on update/destroy.
- Search matches name prefix, slug prefix, and empty state.
- Ordering is `sort_order`, `name`, `id`.
- `bands_count` reflects active and inactive bands.

### `tests/Feature/Admin/SalaryBandTest.php`

- Admin sees the index with rows, eager-loaded pay-period data, pay-period options, filters, and `canManageSalaryBands`.
- Create happy path: uppercase currency normalization, decimal strings persisted, default active.
- Create with explicit inactive state.
- Update happy path: label, range, period, currency, active toggle.
- Delete happy path.
- Duplicate label within the same currency + period is rejected.
- Same label is allowed in a different currency and in a different period.
- Exact duplicate range is rejected (validation and/or DB constraint).
- Overlap cases rejected:
    - New range starts inside an existing range.
    - New range ends inside an existing range.
    - New range fully contains an existing range.
    - New range is fully inside an existing range.
    - Identical range.
- Adjacent half-open ranges are accepted (`0–50000` and `50000–100000`).
- Same range is accepted in a different currency and in a different period.
- An inactive existing band still blocks an overlapping new band.
- Invalid input: lower > upper, lower == upper, negative lower, more than two decimal places, invalid currency (too short/long/non-letters), missing/nonexistent pay period.
- Inactive pay period is rejected on create.
- Updating a band while keeping its current inactive pay period succeeds.
- Switching a band to a different inactive pay period is rejected.
- Non-admin gets 403 on index, store, update, destroy.
- Guest is redirected to login.
- View-only admin (`admin.access` + `salary_bands.view`) sees the page but gets 403 on all writes.
- Unknown band ID returns 404 on update/destroy.
- Search matches label prefix and currency prefix; pay-period filter works; combined filters work; empty state.
- Pagination preserves `search` and `pay_period_id` query strings.
- Ordering is `currency`, `pay_period_id`, `lower_bound`, `id`.
- Deactivating a pay period leaves its bands intact and visible in the admin list.

### Authorization catalogue regression

- Update `AuthorizationCatalogueTest`:
    - Exact permission count 19.
    - Reseeding count 20.
    - All four new permissions are `is_system` and granted to `platform_admin` only.
    - Employer and job seeker roles still have zero permissions.

### Frontend/build regression

- `npm run types:check` passes.
- `npm run check` passes.
- `npm run build` passes and regenerates Wayfinder actions for both controllers.
- Manual/browser checks (not automated in this feature): keyboard navigation, select behavior, table overflow, inline errors, toast feedback.

---

## Security and performance risks

### Security

- **Authorization:** every route has middleware plus a Policy check; direct requests are covered. View-only admins cannot write.
- **Mass assignment:** explicit `$fillable` and Form Request data accessors; no raw `$request->all()` writes.
- **Client-controlled IDs:** `pay_period_id` is validated to exist and be active (or be the current period on update); band and period IDs come from the route and are `findOrFail`ed.
- **Currency injection:** currency is trimmed, uppercased, and restricted to exactly three ASCII letters. It is not used in SQL or file paths.
- **Numeric injection:** thresholds are validated as numeric decimals and persisted as decimal columns; no float arithmetic in business logic.
- **Delete integrity:** pay-period deletion is blocked while any band references it; the FK `restrictOnDelete` is the final guard. Band deletion is safe because jobs do not reference bands.
- **Data exposure:** only configuration fields are sent to Inertia; no user, company, job, or moderation data is included.
- **Search:** LIKE wildcards are escaped, preventing unintended wildcard searches.
- **CSRF/session:** routes live in the existing `web`/`auth` boundary.
- **Permission deployment:** new permissions require rerunning `AuthorizationSeeder`; otherwise admins receive 403. This is an operational risk, not a code bypass.

### Performance

- Both lists paginate at 20; no unbounded endpoint data.
- `withCount('salaryBands')` and `with('payPeriod')` prevent N+1 queries.
- Pay-period options for the salary-band form use a bounded `get()` on a small configuration table; this is explicitly justified and not a general unbounded query.
- Composite unique indexes support currency + period + range lookups and the default ordering path.
- Prefix `LIKE` search may not use an index; the tables are small configuration catalogues, so this is acceptable. Revisit only if profiling shows a problem.
- Overlap validation adds one indexed existence query per write. Admin writes are low-volume.
- **Race condition:** two concurrent admin writes could theoretically create overlapping bands between validation and insert. The unique exact-range constraint catches identical ranges, but not arbitrary overlaps. Given admin-only, low-concurrency writes, this is accepted and documented. If strict serialization is required later, wrap band writes in a transaction that locks the pay-period row and rechecks overlap.
- **SQLite/MySQL portability:** decimal comparisons and unique indexes must be verified on both. Tests run on SQLite; production is MySQL. No MySQL-only SQL is introduced.

### Deferred/forward-looking risks

- Feature 13 must add `pay_period_id` to jobs with restrict-on-delete and only allow active periods for new/edited jobs. It must not add a `salary_band_id` FK.
- Feature 16/17 must require matching currency + pay period before applying bands, exclude bands on inactive periods, and use the half-open interval semantics.
- Feature 29 must handle deleted bands in saved searches; snapshot criteria rather than relying on a band FK.
- If downstream code depends on fixed pay-period slugs, changing a slug later could break formatting. Either protect slugs in a later change or resolve formatting by ID/configured metadata.
- No seed data means a fresh environment has no pay periods until an administrator creates them. Feature 13's spec should state that prerequisite.

---

## Rollout and compatibility

1. Run the two new migrations. They only create tables and indexes; no backfill, no destructive changes.
2. Run `php artisan db:seed --class=AuthorizationSeeder` to create the four permissions and attach them to `platform_admin`.
3. Deploy the routes/pages after the seeder has run so existing administrators do not receive 403.
4. No existing table is modified. No Feature 5–8 data is changed.
5. The feature is additive and can be rolled back by dropping the two new tables after the routes/pages are removed. Do not roll back in production without confirming no bands/periods are referenced by future jobs.

---

## Ordered implementation steps

1. Create `pay_periods` migration, `PayPeriod` model, and `PayPeriodFactory`.
2. Create `salary_bands` migration (FK restrict, unique constraints, indexes), `SalaryBand` model, and `SalaryBandFactory`.
3. Add the four `PermissionSlug` cases, update `AuthorizationSeeder`, and update `AuthorizationCatalogueTest` counts/assertions.
4. Add `PayPeriodPolicy`, `SalaryBandPolicy`, `SalaryBandRangeDoesNotOverlap`, and the four Form Requests.
5. Add `PayPeriodController` and `SalaryBandController`; add both route groups to `routes/admin.php`.
6. Build the two Inertia pages and add both navigation entries.
7. Add `PayPeriodTest` and `SalaryBandTest`; cover the matrix above.
8. Run targeted tests, full suite, frontend build/type/lint checks, Pint, PHPStan, and route-list inspection; fix failures and report actual results.

---

## Verification commands

Run from the project root after implementation:

```text
php artisan test tests/Feature/Admin/PayPeriodTest.php
php artisan test tests/Feature/Admin/SalaryBandTest.php
php artisan test tests/Feature/Authorization/AuthorizationCatalogueTest.php
php artisan test
npm run build
npm run types:check
npm run check
vendor/bin/pint --test
vendor/bin/phpstan analyse --no-progress
php artisan route:list --path=admin/pay-periods -v
php artisan route:list --path=admin/salary-bands -v
```

Do not claim any command passed unless its output was inspected. If a command cannot run, record the exact blocker.

---

## Approval gate

Status: **APPROVED**. The user approved this feature spec in conversation and the implementation has been started and completed through the backend, frontend, and verification slices in dependency order. The original approval gate language was satisfied by the user's explicit approval before implementation began; implementation did not proceed without it.
