# Feature 6 — Admin Manages Employment Types

**Metadata:**

- Feature ID: 6
- Title: Admin Manages Employment Types
- Spec Type: Feature specification
- Source: `project-overview.md` → "Feature 6 — Admin manages employment types" (Phase 1 — Platform configuration and governance)
- Dependencies: Feature 3 (Custom authorization foundation — verified implemented), Feature 4 (Shared application shell & navigation — verified implemented)
- Status: **APPROVED**
- Created: 2026-10-30
- Updated: 2026-10-30 (implemented and verified: full suite 170 passed / 1061 assertions; build + typecheck pass)

---

## Goal

Provide authorized platform administrators with a secure CRUD interface to manage the employment-type catalogue (full-time, part-time, contract, temporary, internship, etc.) that employer job forms will later consume (Feature 13) and public job listings will display (Feature 16). Employment types are configurable data, never hard-coded UI labels, with unique identifiers, optional descriptions, and active/inactive state.

## Non-goals

- Deleting an employment type that is referenced by jobs — the `jobs` table does not exist yet (Feature 13); referenced-option protection is specified as a forward-looking rule and enforced once that schema exists (see "Risks and deferred decisions").
- Employer/public-side creation or editing of employment types.
- Ordering/display sort configuration beyond alphabetic listing (unlike Feature 7 which may add ordering).
- Salary bands, pay periods (Feature 9), workplace arrangements (Feature 7), or experience levels (Feature 8) — each is its own catalogue.
- Public discovery endpoints or filters in this feature.

---

## Actors and flows

**Primary actors:**

- Platform administrator (`platform_admin` role holding `admin.access`, plus the new `employment_types.view` / `employment_types.manage` permissions).
- Job seekers and employers are read-only consumers of *active* employment types in later features; out of scope here.

**Key flows:**

1. Admin opens `/admin/employment-types` → sees a paginated, searchable list with active/inactive status.
2. Admin creates an employment type (Name, Slug, optional Description, Active checkbox) → server validates uniqueness and format.
3. Admin edits an existing type → updates any field, including toggling active state.
4. Admin deletes a type that is not yet referenced by any job (no reference guard is possible until Feature 13).
5. A user without the required permission receives `403 Forbidden` for any index/write action, including direct URL/HTTP requests.

---

## User-visible behavior and acceptance criteria

- [ ] An admin with `employment_types.view` can open `/admin/employment-types` and see the paginated list (20 per page) with search by name or slug.
- [ ] Only users holding `employment_types.manage` see create/edit/delete controls and can perform write actions; users with view-only access see the list read-only.
- [ ] Users without `employment_types.view` receive 403 on the index; users without `employment_types.manage` receive 403 on store/update/destroy, even via direct HTTP requests.
- [ ] Creating a type with a duplicate name or slug returns field-level validation errors rendered inline.
- [ ] Name and slug are required; slug must be `alpha_dash`, ≤100 chars; description optional ≤500 chars; `is_active` defaults to `true` when omitted.
- [ ] Search filters results server-side with LIKE-escaped prefix matching on name or slug, preserves the query string across pagination, and returns a clear empty state.
- [ ] Deactivating a type succeeds immediately; the list reflects the new status.
- [ ] Active types are available to later job forms (server-provided, Feature 13); inactive types are excluded from new selections while historical jobs retain them (enforced in Feature 13's spec, documented here as the product rule).
- [ ] All writes go through dedicated Form Requests; the client cannot set fields beyond the validated set.
- [ ] The "Employment Types" navigation item appears only for users holding `admin.access` + `employment_types.view`.

---

## Existing code and conventions inspected

- `docs/specs/feature-05-job-categories.md` — approved Feature 5 spec; this feature deliberately mirrors its architecture (same stack, same controller/policy/request/UI pattern).
- `app/Models/JobCategory.php` — model pattern: `$fillable`, boolean cast, `scopeActive`.
- `app/Http/Controllers/Admin/JobCategoryController.php` — thin controller: `authorize()` per action, inline `validate()` for the read-only `search` query param (allowed — not a write endpoint), paginated query with `when()`, LIKE escaping, `Inertia::flash` toast, `to_route()`.
- `app/Http/Requests/Admin/StoreJobCategoryRequest.php` / `UpdateJobCategoryRequest.php` — Form Request pattern with a typed `categoryData()` accessor using `$this->safe()->all()`.
- `app/Policies/JobCategoryPolicy.php` — permission-based policy (`categories.view` / `categories.manage`).
- `routes/admin.php:56-66` — route grouping pattern: `permission:admin.access` on the group, `permission:<resource>.view` on index, `permission:<resource>.manage` on write verbs. Wayfinder builds frontend action imports from these.
- `app/Enums/PermissionSlug.php` — closed string-backed enum; new permissions require new cases (deliberate, auditable catalogue per Feature 3's design note).
- `database/seeders/AuthorizationSeeder.php` — `firstOrCreate` repeatable seeding + explicit `platformAdminPermissionSlugs` allowlist that new permissions must be added to.
- `resources/js/pages/admin/categories/index.tsx` — page pattern: `useForm`, Wayfinder actions, `SplitLayout`, `Pagination`, `InputError`, badges for active/inactive, `canManage*` prop gates only presentation.
- `resources/js/lib/navigation.ts` — nav items with `requiredPermissions`.
- `tests/Feature/Admin/JobCategoryTest.php` and `tests/Feature/Authorization/AuthorizationCatalogueTest.php` — test patterns; the catalogue test hardcodes `assertDatabaseCount('permissions', 9)` and will need updating when permissions grow.
- Database targets: tests run on SQLite; production target is MySQL (per `AGENTS.md`/memory) — keep schema types portable.

## Routes, middleware, authorization

| Method | URI | Name | Middleware |
|---|---|---|---|
| GET | `/admin/employment-types` | `admin.employment-types.index` | `auth`, `permission:admin.access`, `permission:employment_types.view` |
| POST | `/admin/employment-types` | `admin.employment-types.store` | `auth`, `permission:admin.access`, `permission:employment_types.manage` |
| PUT | `/admin/employment-types/{employment_type}` | `admin.employment-types.update` | same as store |
| DELETE | `/admin/employment-types/{employment_type}` | `admin.employment-types.destroy` | same as store |

- New `EmploymentTypePolicy`: `viewAny`/`view` → `employment_types.view`; `create`/`update`/`delete` → `employment_types.manage`. Controllers still call `$this->authorize(...)` per action (defense in depth, matching Feature 5).
- No company/owner scoping applies — platform configuration resource with a single admin audience.
- No tenant or moderation fields; public forms never touch this surface (admin-only routes).

## Form Requests and validation

- `StoreEmploymentTypeRequest` / `UpdateEmploymentTypeRequest` in `app/Http/Requests/Admin/`, mirroring the category requests:
  - `name`: `required, string, max:100, unique:employment_types,name` (update adds `ignore` on the route model).
  - `slug`: `required, string, max:100, alpha_dash, unique:employment_types,slug` (update adds `ignore`).
  - `description`: `nullable, string, max:500`.
  - `is_active`: `boolean` (defaults `true` server-side when absent).
- Typed `employmentTypeData()` accessor using `$this->safe()->all()` — no raw `$request->all()` writes.

## Model, migration, constraints

- `EmploymentType` model (`app/Models/EmploymentType.php`): `$fillable = ['name','slug','description','is_active']`, boolean cast, `scopeActive`, typed factory. SoftDeletes **not** used — these are configuration options, not user data with retention semantics; the roadmap's "do not delete referenced options" rule is enforced by reference checks, not soft-deletes.
- Migration `create_employment_types_table`:
  - `id`, `name` string(100) **unique**, `slug` string(100) **unique + index**, `description` nullable text, `is_active` boolean default true, `timestamps`.
  - Uniqueness constraints prevent duplicate identifiers at the database level, not just via validation (guards against races between validation and insert).
- No foreign keys yet; Feature 13 adds `employment_type_id` FK on `jobs` with restrict-on-delete semantics when the referenced-option rule becomes enforceable.

## Query shape

- Index: paginate 20, `orderBy(name)->orderBy(id)` for stable pagination, prefix-LIKE search on `name` OR `slug` with wildcard escaping, `withQueryString()`, select-all-columns (small config table — no column pruning needed; no relationships to eager-load, so no N+1 surface).
- Frontend receives via `through()`: `id`, `name`, `slug`, `description`, `is_active` — identical shape to the categories page props.
- Inertia prop `canManageEmploymentTypes` (presentation gating only; server remains authoritative).

## Service/Repository decisions

- **No Service, no Repository.** Single-table CRUD with no transactions, no multi-step logic, and no query reuse beyond the index query — matching the Feature 5 decision and the CLAUDE.md "simple CRUD does not need layers" standard.

## React/Inertia frontend

- New page `resources/js/pages/admin/employment-types/index.tsx` mirroring `admin/categories/index.tsx`:
  - `Head` title "Employment Types Management"; `SplitLayout` with form (create/edit) left and searchable paginated table right.
  - `useForm<{ name, slug, description, is_active }>`; Wayfinder actions from `EmploymentTypeController`; edit state via `useState`; toasts on success; `InputError` under each field; spinner/processing disabled states; empty-list state; `Pagination` component.
  - Active/inactive badges identical to categories (emerald active / muted inactive).
- `resources/js/lib/navigation.ts`: add "Employment Types" item → `admin.employment-types.index()` with `requiredPermissions: ['admin.access', 'employment_types.view']`.
- Typed props: `employmentTypes: PaginatedData<EmploymentType>`, `filters: { search: string }`, `canManageEmploymentTypes: boolean`. No `any`; page data comes from Inertia props only.

## Seeders and permission catalogue

- Add enum cases `EmploymentTypesView = 'employment_types.view'`, `EmploymentTypesManage = 'employment_types.manage'` to `PermissionSlug`.
- `AuthorizationSeeder`: register both permissions (`firstOrCreate`, `is_system`) and add both to the explicit `platformAdminPermissionSlugs` allowlist.
- Update `AuthorizationCatalogueTest` hardcoded permission count `9 → 11` (the slug list itself derives from the enum automatically).

## Tests and verification

`tests/Feature/Admin/EmploymentTypeTest.php` (mirroring `JobCategoryTest`, using `InteractsWithAuthorizationCatalogue`):

- Admin sees index page with seeded rows (Inertia assertions).
- Create happy path → persisted with defaults (`is_active` true when omitted).
- Update happy path → fields and active toggle persisted; duplicate name/slug on *another* row rejected (unique-ignore correctness).
- Delete happy path → row removed.
- Duplicate name and slug on create → session errors on both fields.
- Invalid input: missing name, bad slug (e.g. `not a slug!`), overlong description → validation errors.
- Permission denial: job seeker (and a non-privileged admin without the permission, where feasible) gets 403 on index and store.
- Search: prefix match by name and slug; no results → empty state.
- Catalogue test updated to 11 permissions with both new permissions granted to `platform_admin` only.

**Verification commands:** `php artisan test` (full suite), `php artisan test tests/Feature/Admin/EmploymentTypeTest.php` (targeted), `npm run build` (required — Wayfinder actions regenerate after route/controller changes), plus `vendor/bin/pint` / `npm run typecheck` if configured in scripts (inspect `composer.json`/`package.json` before running).

## Risks, assumptions, deferred decisions

- **Permission slug naming decision (needs your approval):** I propose `employment_types.view` / `employment_types.manage` (snake_case resource prefix, matching `categories.view`). Alternative: `employment.types.view` for dot-hierarchy consistency. Flag if you prefer the alternative before approval.
- **Delete behavior deferred:** Feature 6's roadmap rule "do not delete an option referenced by jobs" cannot be enforced yet — `jobs` arrives in Feature 13. This spec allows delete now and documents that Feature 13's spec must add the FK + restrict-on-delete guard + admin-UI protection. No silent implementation of that prerequisite.
- **Inactive-option consumption rule** (new/edited jobs cannot select inactive types) is enforced by Feature 13's form validation; this feature only guarantees the `is_active` data and `scopeActive` helper exist.
- **Seed data:** no employment types are seeded (like categories). If you want the example types (full-time, part-time, contract, temporary, internship) pre-seeded for development convenience, say so in your approval and I'll add a repeatable `firstOrCreate` seeder block.
- Assumes the existing `permission:` middleware alias, `InteractsWithAuthorizationCatalogue` trait, `platformAdmin()`/`jobSeeker()` factory states, and `PaginatedData` type remain as inspected.

## Ordered implementation steps

1. Migration + `EmploymentType` model + factory.
2. `PermissionSlug` cases + `AuthorizationSeeder` allowlist + catalogue test count update.
3. Policy + Form Requests + `EmploymentTypeController` + `routes/admin.php` group.
4. Frontend page + navigation entry.
5. `tests/Feature/Admin/EmploymentTypeTest.php`.
6. Run targeted tests → full suite → `npm run build` → report.
