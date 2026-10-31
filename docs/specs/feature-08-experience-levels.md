# Feature 8 — Admin Manages Experience Levels

**Metadata:**

- Feature ID: 8
- Title: Admin Manages Experience Levels
- Spec Type: Feature specification
- Source: `project-overview.md` → "Feature 8 — Admin manages experience levels" (Phase 1 — Platform configuration and governance)
- Dependencies: Feature 3 (Custom authorization foundation — verified implemented), Feature 4 (Shared application shell & navigation — verified implemented)
- Status: **APPROVED — implemented and verified (2026-10-31)**
- Created: 2026-10-31

---

## Goal

Provide authorized platform administrators with a secure CRUD interface to manage the experience-level catalogue (entry-level, junior, mid-level, senior, lead, etc.) that employer job forms will later consume (Feature 13) and public job listings/search will display (Features 16/17). Experience levels are configurable data, never hard-coded UI labels, with stable machine keys separate from human-readable display labels, optional descriptions, an admin-controlled display order, and active/inactive state.

## Non-goals

- Deleting an experience level that is referenced by jobs — the `jobs` table does not exist yet (Feature 13); referenced-option protection is specified as a forward-looking rule and enforced once that schema exists (see "Risks and deferred decisions").
- Employer/public-side creation or editing of experience levels.
- A separate `display_label` column or seniority numeric score — `name` serves as the display label and `slug` as the stable machine key; `sort_order` carries admin display/seniority ordering (see Assumptions).
- Salary bands/pay periods (Feature 9), workplace arrangements (Feature 7), or other catalogues — each is its own feature.
- Public discovery endpoints or filters in this feature.
- Company/hiring-team scoping — platform configuration resource with a single admin audience.

---

## Actors and flows

**Primary actors:**

- Platform administrator (`platform_admin` role holding `admin.access`, plus the new `experience_levels.view` / `experience_levels.manage` permissions).
- Job seekers and employers are read-only consumers of _active_ experience levels in later features; out of scope here.

**Key flows:**

1. Admin opens `/admin/experience-levels` → sees a paginated, searchable list ordered by display order then name, with active/inactive status.
2. Admin creates a level (Name, Slug, optional Description, Display order, Active checkbox) → server validates uniqueness and format.
3. Admin edits an existing level → updates any field, including toggling active state and changing display order.
4. Admin deletes a level that is not yet referenced by any job (no reference guard is possible until Feature 13).
5. A user without the required permission receives `403 Forbidden` for any index/write action, including direct URL/HTTP requests.

---

## User-visible behavior and acceptance criteria

- [ ] An admin with `experience_levels.view` can open `/admin/experience-levels` and see the paginated list (20 per page) with search by name or slug.
- [ ] Only users holding `experience_levels.manage` see create/edit/delete controls and can perform write actions; users with view-only access see the list read-only.
- [ ] Users without `experience_levels.view` receive 403 on the index; users without `experience_levels.manage` receive 403 on store/update/destroy, even via direct HTTP requests.
- [ ] Creating a level with a duplicate name or slug returns field-level validation errors rendered inline.
- [ ] Name and slug are required; slug must be `alpha_dash`, ≤100 chars; description optional ≤500 chars; `sort_order` optional integer 0–9999 defaulting to 0; `is_active` defaults to `true` when omitted.
- [ ] Search filters results server-side with LIKE-escaped prefix matching on name or slug, preserves the query string across pagination, and returns a clear empty state.
- [ ] Deactivating a level succeeds immediately; the list reflects the new status.
- [ ] Active levels are available to later job forms (server-provided, Feature 13); inactive levels are excluded from new selections while historical jobs retain them (enforced in Feature 13's spec, documented here as the product rule).
- [ ] All writes go through dedicated Form Requests; the client cannot set fields beyond the validated set.
- [ ] The "Experience Levels" navigation item appears only for users holding `admin.access` + `experience_levels.view`.
- [ ] List ordering is stable: `sort_order` ascending, then `name` ascending, then `id` ascending.

---

## Assumptions / questions

**Assumptions (labeled; override in approval if you disagree):**

1. **Permission slug naming** follows the established snake_case resource-prefix convention (`categories.view`, `employment_types.view`, `workplace_arrangements.view`): `experience_levels.view` / `experience_levels.manage`. (Alternative dot-hierarchy is rejected for consistency.)
2. **No separate `display_label` column.** `name` (unique, human-readable) is the display label; `slug` (unique, `alpha_dash`) is the stable machine key. A second label column would create two sources of truth.
3. **No seniority numeric score column beyond `sort_order`.** Admin-controlled `sort_order` (0–9999) carries display/seniority ordering; Feature 13 derives any needed ordering from the selected level's `sort_order`/`slug`. If a fixed seniority rank is later required, that decision belongs to Feature 13's spec.
4. **`sort_order` column is included** (integer, default 0, validated 0–9999) to support "optional description/order" from the roadmap and consistent ordering with Feature 7. If you prefer pure alphabetic ordering (matching Feature 6), drop the column, the form input, and the ordering tests.
5. **No seed data** for levels (matching categories/employment types/workplace arrangements). If you want `entry-level`/`junior`/`mid-level`/`senior`/`lead` pre-seeded for development, say so in approval and I'll add a repeatable `firstOrCreate` block.
6. **No SoftDeletes** — configuration options, not user data with retention semantics; delete is allowed now, reference protection deferred to Feature 13 (matching Features 5/6/7).
7. **Navigation icon** — `GraduationCap` from `lucide-react` (placeholder; any credential/level-ish icon is acceptable). Override if you prefer another.
8. **No `is_remote`/seniority boolean** — experience level is represented entirely by its stable slug and ordering.

**Focused questions (none blocking design — listed only if you want to refine before approval):**

- Should `sort_order` be included now, or deferred to a later "display ordering" pass? (Assumption 4 defaults to include.)
- Should levels be seeded with example values (`entry-level`, `junior`, `mid-level`, `senior`, `lead`) for dev convenience? (Assumption 5 defaults to no.)

---

## Existing code and conventions inspected

- `docs/specs/feature-07-workplace-arrangements.md` — approved Feature 7 spec; this feature deliberately mirrors its architecture (including `sort_order`), the only Feature-8-specific deltas being the resource name and permission slugs.
- `app/Models/WorkplaceArrangement.php` / `EmploymentType.php` / `JobCategory.php` — model pattern: `$fillable`, boolean cast, integer cast for `sort_order`, `scopeActive`.
- `app/Http/Controllers/Admin/WorkplaceArrangementController.php` — thin controller: `authorize()` per action, inline `validate()` for the read-only `search` query param, paginated query with `when()`, LIKE escaping, `Inertia::flash` toast, `to_route()`.
- `app/Http/Requests/Admin/StoreWorkplaceArrangementRequest.php` / `UpdateWorkplaceArrangementRequest.php` — Form Request pattern with a typed `workplaceArrangementData()` accessor using `$this->safe()->all()`.
- `app/Policies/WorkplaceArrangementPolicy.php` — permission-based policy (`workplace_arrangements.view` / `workplace_arrangements.manage`).
- `routes/admin.php` — route grouping pattern: `permission:admin.access` on the group, `permission:<resource>.view` on index, `permission:<resource>.manage` on write verbs.
- `app/Enums/PermissionSlug.php` — closed string-backed enum; new permissions require new cases.
- `database/seeders/AuthorizationSeeder.php` — `firstOrCreate` repeatable seeding + explicit `platformAdminPermissionSlugs` allowlist that new permissions must be added to.
- `resources/js/pages/admin/workplace-arrangements/index.tsx` — page pattern: `useForm`, Wayfinder actions, `SplitLayout`, `Pagination`, `InputError`, badges for active/inactive, `canManage*` prop gates only presentation.
- `resources/js/lib/navigation.ts` — nav items with `requiredPermissions`.
- `tests/Feature/Admin/WorkplaceArrangementTest.php` and `tests/Feature/Authorization/AuthorizationCatalogueTest.php` — test patterns; the catalogue test hardcodes permission counts (13) and will need updating to 15.
- Database targets: tests run on SQLite; production target is MySQL — keep schema types portable.

---

## Routes, middleware, authorization

| Method | URI                                           | Name                              | Middleware                                                               |
| ------ | --------------------------------------------- | --------------------------------- | ------------------------------------------------------------------------ |
| GET    | `/admin/experience-levels`                    | `admin.experience-levels.index`   | `auth`, `permission:admin.access`, `permission:experience_levels.view`   |
| POST   | `/admin/experience-levels`                    | `admin.experience-levels.store`   | `auth`, `permission:admin.access`, `permission:experience_levels.manage` |
| PUT    | `/admin/experience-levels/{experience_level}` | `admin.experience-levels.update`  | same as store                                                            |
| DELETE | `/admin/experience-levels/{experience_level}` | `admin.experience-levels.destroy` | same as store                                                            |

- New `ExperienceLevelPolicy`: `viewAny`/`view` → `experience_levels.view`; `create`/`update`/`delete` → `experience_levels.manage`. Controllers still call `$this->authorize(...)` per action (defense in depth, matching Features 5/6/7).
- No company/owner scoping applies — platform configuration resource with a single admin audience.
- No tenant or moderation fields; public forms never touch this surface (admin-only routes).
- Wayfinder exposes the hyphenated route name as `admin.experienceLevels.*` in JS (per the established camel-casing rule).

---

## Form Requests and validation

- `StoreExperienceLevelRequest` / `UpdateExperienceLevelRequest` in `app/Http/Requests/Admin/`, mirroring the workplace-arrangement requests:
    - `name`: `required, string, max:100, unique:experience_levels,name` (update adds `ignore` on the route model).
    - `slug`: `required, string, max:100, alpha_dash, unique:experience_levels,slug` (update adds `ignore`).
    - `description`: `nullable, string, max:500`.
    - `sort_order`: `nullable, integer, min:0, max:9999` (defaults to 0 server-side when absent).
    - `is_active`: `boolean` (defaults `true` server-side when absent).
- Typed `experienceLevelData()` accessor using `$this->safe()->all()` — no raw `$request->all()` writes.

---

## Model, migration, constraints

- `ExperienceLevel` model (`app/Models/ExperienceLevel.php`): `$fillable = ['name','slug','description','sort_order','is_active']`, boolean cast, integer cast for `sort_order`, `scopeActive`, typed factory. SoftDeletes **not** used.
- Migration `create_experience_levels_table`:
    - `id`, `name` string(100) **unique**, `slug` string(100) **unique + index**, `description` nullable text, `sort_order` integer default 0, `is_active` boolean default true, `timestamps`.
    - Uniqueness constraints prevent duplicate identifiers at the database level, not just via validation (guards against races between validation and insert).
    - Index on `slug` (unique already implies index); composite index on `(sort_order, name)` is **not** added — the result set is small and the default ordering is satisfied by a filesort on paginated pages; revisit if profiling shows otherwise. (Avoid index speculation per `AGENTS.md`.)
- No foreign keys yet; Feature 13 adds `experience_level_id` FK on `jobs` with restrict-on-delete semantics when the referenced-option rule becomes enforceable.
- `ProtectsSystemDefinition` concern **not** applied — these are not system-flagged rows (no `is_system` column), matching categories/employment types/workplace arrangements.

---

## Query shape

- Index: paginate 20, `orderBy('sort_order')->orderBy('name')->orderBy('id')` for stable pagination, prefix-LIKE search on `name` OR `slug` with wildcard escaping, `withQueryString()`. No relationships to eager-load (small config table, no N+1 surface); no column pruning needed beyond the `through()` projection.
- Frontend receives via `through()`: `id`, `name`, `slug`, `description`, `sort_order`, `is_active` — identical shape to the workplace-arrangements page props.
- Inertia prop `canManageExperienceLevels` (presentation gating only; server remains authoritative).

---

## Service/Repository decisions

- **No Service, no Repository.** Single-table CRUD with no transactions, no multi-step logic, and no query reuse beyond the index query — matching the Feature 5/6/7 decision and the `AGENTS.md` "simple single-use CRUD does not need layers" standard. The only Feature-8-specific logic (`sort_order` defaulting and ordering) is trivial and stays in the controller/query.

---

## React/Inertia frontend

- New page `resources/js/pages/admin/experience-levels/index.tsx` mirroring `admin/workplace-arrangements/index.tsx`:
    - `Head` title "Experience Levels Management"; `SplitLayout` with form (create/edit) left and searchable paginated table right.
    - `useForm<{ name, slug, description, sort_order, is_active }>`; Wayfinder actions from `ExperienceLevelController`; edit state via `useState`; toasts on success; `InputError` under each field; spinner/processing disabled states; empty-list state; `Pagination` component.
    - Active/inactive badges identical to workplace arrangements (emerald active / muted inactive).
    - Add a numeric "Display order" input (`type="number"`, min 0, max 9999) to the form.
    - Optionally show `sort_order` in the table (small column) — recommended for admin clarity.
- `resources/js/lib/navigation.ts`: add "Experience Levels" item → `admin.experienceLevels.index()` with `requiredPermissions: ['admin.access', 'experience_levels.view']` and icon `GraduationCap`.
- Typed props: `experienceLevels: PaginatedData<ExperienceLevel>`, `filters: { search: string }`, `canManageExperienceLevels: boolean`. No `any`; page data comes from Inertia props only.

---

## Seeders and permission catalogue

- Add enum cases `ExperienceLevelsView = 'experience_levels.view'`, `ExperienceLevelsManage = 'experience_levels.manage'` to `PermissionSlug`.
- `AuthorizationSeeder`: register both permissions (`firstOrCreate`, `is_system`) and add both to the explicit `platformAdminPermissionSlugs` allowlist.
- Update `AuthorizationCatalogueTest` hardcoded permission counts `13 → 15` (base catalogue assertion) and `14 → 16` (reseeding/custom-permission test).

---

## Tests and verification

`tests/Feature/Admin/ExperienceLevelTest.php` (mirroring `WorkplaceArrangementTest`, using `InteractsWithAuthorizationCatalogue`):

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
- Catalogue test updated to 15 permissions with both new permissions granted to `platform_admin` only.

**Verification commands:** `php artisan test` (full suite), `php artisan test tests/Feature/Admin/ExperienceLevelTest.php` (targeted), `npm run build` (required — Wayfinder actions regenerate after route/controller changes), plus `vendor/bin/pint` / `npm run types:check` (inspect `composer.json`/`package.json` for exact script names before running).

---

## Risks, assumptions, deferred decisions

- **Delete behavior deferred:** Feature 8's roadmap rule "do not delete an option referenced by jobs" cannot be enforced yet — `jobs` arrives in Feature 13. This spec allows delete now and documents that Feature 13's spec must add the FK + restrict-on-delete guard + admin-UI protection. No silent implementation of that prerequisite.
- **Inactive-option consumption rule** (new/edited jobs cannot select inactive levels) is enforced by Feature 13's form validation; this feature only guarantees the `is_active` data and `scopeActive` helper exist.
- **`sort_order` is optional and may be dropped** if you prefer pure alphabetic ordering (Assumption 4). If dropped, remove the column, the form input, and the ordering tests.
- **No seed data** (Assumption 5). If you want example levels pre-seeded for development convenience, say so in approval.
- Assumes the existing `permission:` middleware alias, `InteractsWithAuthorizationCatalogue` trait, `platformAdmin()`/`jobSeeker()` factory states, and `PaginatedData` type remain as inspected.

---

## Ordered implementation steps

1. Migration + `ExperienceLevel` model + factory (including `sort_order` state).
2. `PermissionSlug` cases + `AuthorizationSeeder` allowlist + catalogue test count update (13 → 15; reseeding 14 → 16).
3. Policy + Form Requests + `ExperienceLevelController` + `routes/admin.php` group.
4. Frontend page + navigation entry.
5. `tests/Feature/Admin/ExperienceLevelTest.php`.
6. Run targeted tests → full suite → `npm run build` → report.

---

## Approval gate

This spec was approved by the user and implemented. See the implementation report for verification outcomes.
