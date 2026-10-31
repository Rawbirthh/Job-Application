# Feature 7 — Admin Manages Workplace Arrangements

**Metadata:**

- Feature ID: 7
- Title: Admin Manages Workplace Arrangements
- Spec Type: Feature specification
- Source: `project-overview.md` → "Feature 7 — Admin manages workplace arrangements" (Phase 1 — Platform configuration and governance)
- Dependencies: Feature 3 (Custom authorization foundation — verified implemented), Feature 4 (Shared application shell & navigation — verified implemented)
- Status: **APPROVED — implemented and verified (2026-10-31)**
- Created: 2026-10-31

---

## Goal

Provide authorized platform administrators with a secure CRUD interface to manage the workplace-arrangement catalogue (remote, onsite, hybrid, etc.) that employer job forms will later consume (Feature 13) and public job listings will display (Feature 16). Workplace arrangements are configurable data, never hard-coded UI labels, with stable machine keys separate from human-readable display labels, optional descriptions, an admin-controlled display order, and active/inactive state.

## Non-goals

- Deleting a workplace arrangement that is referenced by jobs — the `jobs` table does not exist yet (Feature 13); referenced-option protection is specified as a forward-looking rule and enforced once that schema exists (see "Risks and deferred decisions").
- Employer/public-side creation or editing of workplace arrangements.
- A separate `display_label` column or `is_remote` boolean flag — remote-ness is represented by the arrangement's stable slug (e.g. `remote`), and `name` already serves as the human-readable display label (see Assumptions).
- Salary bands, pay periods (Feature 9), experience levels (Feature 8), or other catalogues — each is its own feature.
- Public discovery endpoints or filters in this feature.
- Company/hiring-team scoping — platform configuration resource with a single admin audience.

---

## Actors and flows

**Primary actors:**

- Platform administrator (`platform_admin` role holding `admin.access`, plus the new `workplace_arrangements.view` / `workplace_arrangements.manage` permissions).
- Job seekers and employers are read-only consumers of _active_ workplace arrangements in later features; out of scope here.

**Key flows:**

1. Admin opens `/admin/workplace-arrangements` → sees a paginated, searchable list ordered by display order then name, with active/inactive status.
2. Admin creates an arrangement (Name, Slug, optional Description, Display order, Active checkbox) → server validates uniqueness and format.
3. Admin edits an existing arrangement → updates any field, including toggling active state and changing display order.
4. Admin deletes an arrangement that is not yet referenced by any job (no reference guard is possible until Feature 13).
5. A user without the required permission receives `403 Forbidden` for any index/write action, including direct URL/HTTP requests.

---

## User-visible behavior and acceptance criteria

- [ ] An admin with `workplace_arrangements.view` can open `/admin/workplace-arrangements` and see the paginated list (20 per page) with search by name or slug.
- [ ] Only users holding `workplace_arrangements.manage` see create/edit/delete controls and can perform write actions; users with view-only access see the list read-only.
- [ ] Users without `workplace_arrangements.view` receive 403 on the index; users without `workplace_arrangements.manage` receive 403 on store/update/destroy, even via direct HTTP requests.
- [ ] Creating an arrangement with a duplicate name or slug returns field-level validation errors rendered inline.
- [ ] Name and slug are required; slug must be `alpha_dash`, ≤100 chars; description optional ≤500 chars; `sort_order` optional integer 0–9999 defaulting to 0; `is_active` defaults to `true` when omitted.
- [ ] Search filters results server-side with LIKE-escaped prefix matching on name or slug, preserves the query string across pagination, and returns a clear empty state.
- [ ] Deactivating an arrangement succeeds immediately; the list reflects the new status.
- [ ] Active arrangements are available to later job forms (server-provided, Feature 13); inactive arrangements are excluded from new selections while historical jobs retain them (enforced in Feature 13's spec, documented here as the product rule).
- [ ] All writes go through dedicated Form Requests; the client cannot set fields beyond the validated set.
- [ ] The "Workplace Arrangements" navigation item appears only for users holding `admin.access` + `workplace_arrangements.view`.
- [ ] List ordering is stable: `sort_order` ascending, then `name` ascending, then `id` ascending.

---

## Assumptions / questions

**Assumptions (labeled; override in approval if you disagree):**

1. **Permission slug naming** follows the existing snake_case resource-prefix convention (`categories.view`, `employment_types.view`): `workplace_arrangements.view` / `workplace_arrangements.manage`. (Alternative dot-hierarchy `workplace.arrangements.view` is rejected for consistency with the established convention.)
2. **No separate `display_label` column.** `name` (unique, human-readable) is the display label; `slug` (unique, `alpha_dash`) is the stable machine key. This already satisfies "stable machine identifiers separate from display labels"; a second label column would create two sources of truth.
3. **Remote-ness is not a separate boolean.** It is encoded in the arrangement's stable slug (`remote`, `onsite`, `hybrid`), per the shared rule "do not infer remote status from a free-text job description" — the selected arrangement's key is the structured fact. Feature 13's job record stores `workplace_arrangement_id` and derives remote-ness through the arrangement, not from text.
4. **`sort_order` column is included** (integer, default 0, validated 0–9999) to support "ordering … where useful." If you prefer to defer ordering entirely, drop the column and order by `name` then `id` (matching Feature 6). Flag in approval.
5. **No seed data** for arrangements (matching categories/employment types). If you want `remote`/`onsite`/`hybrid` pre-seeded for development, say so in approval and I'll add a repeatable `firstOrCreate` block.
6. **No SoftDeletes** — configuration options, not user data with retention semantics; delete is allowed now, reference protection deferred to Feature 13 (matching Feature 5/6).
7. **Navigation icon** — `MapPin` from `lucide-react` (placeholder; any location-ish icon is acceptable). Override if you prefer another.

**Focused questions (none blocking design — listed only if you want to refine before approval):**

- Should `sort_order` be included now, or deferred to a later "display ordering" pass? (Assumption 4 defaults to include.)
- Should arrangements be seeded with `remote`/`onsite`/`hybrid` for dev convenience? (Assumption 5 defaults to no.)

---

## Existing code and conventions inspected

- `docs/specs/feature-06-employment-types.md` — approved Feature 6 spec; this feature deliberately mirrors its architecture, adding `sort_order` as the Feature-7-specific delta.
- `app/Models/EmploymentType.php` / `JobCategory.php` — model pattern: `$fillable`, boolean cast, `scopeActive`.
- `app/Http/Controllers/Admin/EmploymentTypeController.php` — thin controller: `authorize()` per action, inline `validate()` for the read-only `search` query param, paginated query with `when()`, LIKE escaping, `Inertia::flash` toast, `to_route()`.
- `app/Http/Requests/Admin/StoreEmploymentTypeRequest.php` / `UpdateEmploymentTypeRequest.php` — Form Request pattern with a typed `employmentTypeData()` accessor using `$this->safe()->all()`.
- `app/Policies/EmploymentTypePolicy.php` — permission-based policy (`employment_types.view` / `employment_types.manage`).
- `routes/admin.php:69-80` — route grouping pattern: `permission:admin.access` on the group, `permission:<resource>.view` on index, `permission:<resource>.manage` on write verbs.
- `app/Enums/PermissionSlug.php` — closed string-backed enum; new permissions require new cases.
- `database/seeders/AuthorizationSeeder.php` — `firstOrCreate` repeatable seeding + explicit `platformAdminPermissionSlugs` allowlist that new permissions must be added to.
- `resources/js/pages/admin/employment-types/index.tsx` — page pattern: `useForm`, Wayfinder actions, `SplitLayout`, `Pagination`, `InputError`, badges for active/inactive, `canManage*` prop gates only presentation.
- `resources/js/lib/navigation.ts` — nav items with `requiredPermissions`.
- `tests/Feature/Admin/EmploymentTypeTest.php` and `tests/Feature/Authorization/AuthorizationCatalogueTest.php` — test patterns; the catalogue test hardcodes permission counts (11) and will need updating to 13.
- Database targets: tests run on SQLite; production target is MySQL — keep schema types portable.

---

## Routes, middleware, authorization

| Method | URI                                          | Name                                | Middleware                                                              |
| ------ | -------------------------------------------- | ----------------------------------- | ----------------------------------------------------------------------- |
| GET    | `/admin/workplace-arrangements`              | `admin.workplace-arrangements.index` | `auth`, `permission:admin.access`, `permission:workplace_arrangements.view` |
| POST   | `/admin/workplace-arrangements`              | `admin.workplace-arrangements.store` | `auth`, `permission:admin.access`, `permission:workplace_arrangements.manage` |
| PUT    | `/admin/workplace-arrangements/{workplace_arrangement}` | `admin.workplace-arrangements.update` | same as store |
| DELETE | `/admin/workplace-arrangements/{workplace_arrangement}` | `admin.workplace-arrangements.destroy` | same as store |

- New `WorkplaceArrangementPolicy`: `viewAny`/`view` → `workplace_arrangements.view`; `create`/`update`/`delete` → `workplace_arrangements.manage`. Controllers still call `$this->authorize(...)` per action (defense in depth, matching Feature 5/6).
- No company/owner scoping applies — platform configuration resource with a single admin audience.
- No tenant or moderation fields; public forms never touch this surface (admin-only routes).
- Wayfinder exposes the hyphenated route name as `admin.workplaceArrangements.*` in JS (per the established camel-casing rule).

---

## Form Requests and validation

- `StoreWorkplaceArrangementRequest` / `UpdateWorkplaceArrangementRequest` in `app/Http/Requests/Admin/`, mirroring the employment-type requests:
  - `name`: `required, string, max:100, unique:workplace_arrangements,name` (update adds `ignore` on the route model).
  - `slug`: `required, string, max:100, alpha_dash, unique:workplace_arrangements,slug` (update adds `ignore`).
  - `description`: `nullable, string, max:500`.
  - `sort_order`: `nullable, integer, min:0, max:9999` (defaults to 0 server-side when absent).
  - `is_active`: `boolean` (defaults `true` server-side when absent).
- Typed `workplaceArrangementData()` accessor using `$this->safe()->all()` — no raw `$request->all()` writes.

---

## Model, migration, constraints

- `WorkplaceArrangement` model (`app/Models/WorkplaceArrangement.php`): `$fillable = ['name','slug','description','sort_order','is_active']`, boolean cast, integer cast for `sort_order`, `scopeActive`, typed factory. SoftDeletes **not** used.
- Migration `create_workplace_arrangements_table`:
  - `id`, `name` string(100) **unique**, `slug` string(100) **unique + index**, `description` nullable text, `sort_order` unsignedSmallInteger (or integer) default 0, `is_active` boolean default true, `timestamps`.
  - Uniqueness constraints prevent duplicate identifiers at the database level, not just via validation (guards against races between validation and insert).
  - Index on `slug` (unique already implies index); composite index on `(sort_order, name)` is **not** added — the result set is small and the default ordering is satisfied by a filesort on paginated pages; revisit if profiling shows otherwise. (Avoid index speculation per `AGENTS.md`.)
- No foreign keys yet; Feature 13 adds `workplace_arrangement_id` FK on `jobs` with restrict-on-delete semantics when the referenced-option rule becomes enforceable.
- `ProtectsSystemDefinition` concern **not** applied — these are not system-flagged rows (no `is_system` column), matching categories/employment types.

---

## Query shape

- Index: paginate 20, `orderBy('sort_order')->orderBy('name')->orderBy('id')` for stable pagination, prefix-LIKE search on `name` OR `slug` with wildcard escaping, `withQueryString()`. No relationships to eager-load (small config table, no N+1 surface); no column pruning needed beyond the `through()` projection.
- Frontend receives via `through()`: `id`, `name`, `slug`, `description`, `sort_order`, `is_active` — identical shape to the employment-types page props plus `sort_order`.
- Inertia prop `canManageWorkplaceArrangements` (presentation gating only; server remains authoritative).

---

## Service/Repository decisions

- **No Service, no Repository.** Single-table CRUD with no transactions, no multi-step logic, and no query reuse beyond the index query — matching the Feature 5/6 decision and the `AGENTS.md` "simple single-use CRUD does not need layers" standard. The only Feature-7-specific logic (`sort_order` defaulting and ordering) is trivial and stays in the controller/query.

---

## React/Inertia frontend

- New page `resources/js/pages/admin/workplace-arrangements/index.tsx` mirroring `admin/employment-types/index.tsx`:
  - `Head` title "Workplace Arrangements Management"; `SplitLayout` with form (create/edit) left and searchable paginated table right.
  - `useForm<{ name, slug, description, sort_order, is_active }>`; Wayfinder actions from `WorkplaceArrangementController`; edit state via `useState`; toasts on success; `InputError` under each field; spinner/processing disabled states; empty-list state; `Pagination` component.
  - Active/inactive badges identical to employment types (emerald active / muted inactive).
  - Add a numeric "Display order" input (`type="number"`, min 0, max 9999) to the form.
  - Optionally show `sort_order` in the table (small column) — recommended for admin clarity.
- `resources/js/lib/navigation.ts`: add "Workplace Arrangements" item → `admin.workplaceArrangements.index()` with `requiredPermissions: ['admin.access', 'workplace_arrangements.view']` and icon `MapPin`.
- Typed props: `workplaceArrangements: PaginatedData<WorkplaceArrangement>`, `filters: { search: string }`, `canManageWorkplaceArrangements: boolean`. No `any`; page data comes from Inertia props only.

---

## Seeders and permission catalogue

- Add enum cases `WorkplaceArrangementsView = 'workplace_arrangements.view'`, `WorkplaceArrangementsManage = 'workplace_arrangements.manage'` to `PermissionSlug`.
- `AuthorizationSeeder`: register both permissions (`firstOrCreate`, `is_system`) and add both to the explicit `platformAdminPermissionSlugs` allowlist.
- Update `AuthorizationCatalogueTest` hardcoded permission counts `11 → 13` (both the base catalogue assertion and the reseeding test's `12 → 14`).

---

## Tests and verification

`tests/Feature/Admin/WorkplaceArrangementTest.php` (mirroring `EmploymentTypeTest`, using `InteractsWithAuthorizationCatalogue`):

- Admin sees index page with seeded rows (Inertia assertions, including `sort_order`).
- Create happy path → persisted with defaults (`is_active` true, `sort_order` 0 when omitted).
- Create with explicit `sort_order` → persisted.
- Update happy path → fields, `sort_order`, and active toggle persisted; duplicate name/slug on _another_ row rejected (unique-ignore correctness).
- Delete happy path → row removed.
- Duplicate name and slug on create → session errors on both fields.
- Invalid input: missing name, bad slug (e.g. `not a slug!`), overlong description, out-of-range `sort_order` → validation errors.
- Permission denial: job seeker (and a non-privileged admin without the permission, where feasible) gets 403 on index and store.
- Search: prefix match by name and slug; no results → empty state.
- Ordering: rows returned ordered by `sort_order` then `name` then `id` (assert via Inertia data order).
- Catalogue test updated to 13 permissions with both new permissions granted to `platform_admin` only.

**Verification commands:** `php artisan test` (full suite), `php artisan test tests/Feature/Admin/WorkplaceArrangementTest.php` (targeted), `npm run build` (required — Wayfinder actions regenerate after route/controller changes), plus `vendor/bin/pint` / `npm run types:check` (inspect `composer.json`/`package.json` for exact script names before running).

---

## Risks, assumptions, deferred decisions

- **Delete behavior deferred:** Feature 7's roadmap rule "do not delete an option referenced by jobs" cannot be enforced yet — `jobs` arrives in Feature 13. This spec allows delete now and documents that Feature 13's spec must add the FK + restrict-on-delete guard + admin-UI protection. No silent implementation of that prerequisite.
- **Inactive-option consumption rule** (new/edited jobs cannot select inactive arrangements) is enforced by Feature 13's form validation; this feature only guarantees the `is_active` data and `scopeActive` helper exist.
- **`sort_order` is optional and may be dropped** if you prefer pure alphabetic ordering (Assumption 4). If dropped, remove the column, the form input, and the ordering tests.
- **Remote-ness is not a separate flag** (Assumption 3). If Feature 13 later needs a boolean remote indicator beyond the arrangement slug, that decision belongs to Feature 13's spec and may add a column there — do not retroactively add it here without approval.
- **Seed data:** no arrangements are seeded (like categories/employment types). If you want `remote`/`onsite`/`hybrid` pre-seeded for development convenience, say so in approval.
- Assumes the existing `permission:` middleware alias, `InteractsWithAuthorizationCatalogue` trait, `platformAdmin()`/`jobSeeker()` factory states, and `PaginatedData` type remain as inspected.

---

## Ordered implementation steps

1. Migration + `WorkplaceArrangement` model + factory (including `sort_order` state).
2. `PermissionSlug` cases + `AuthorizationSeeder` allowlist + catalogue test count update (11 → 13; reseeding 12 → 14).
3. Policy + Form Requests + `WorkplaceArrangementController` + `routes/admin.php` group.
4. Frontend page + navigation entry.
5. `tests/Feature/Admin/WorkplaceArrangementTest.php`.
6. Run targeted tests → full suite → `npm run build` → report.

---

## Approval gate

This spec is a draft. No application code, migration, package, or destructive command has been run. Awaiting explicit approval or edits before implementation.