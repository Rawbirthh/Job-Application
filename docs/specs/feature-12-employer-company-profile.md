# Feature 12 — Employer Creates and Manages a Company Profile

**Metadata:**

- Feature ID: 12
- Title: Employer creates and manages a company profile
- Spec Type: Feature specification
- Source: `project-overview.md` → “Feature 12 — Employer creates and manages a company profile”
- Dependencies: Feature 11 (employer onboarding and eligibility state) — implemented and committed; Feature 2/3/4 — implemented
- Downstream dependents: Feature 13 (job draft CRUD), Feature 15 (employer dashboard), Feature 16/17 (public job listing/detail), Feature 23/28 (applicant/tenant scoping)
- Status: **APPROVE** (awaiting approval)
- Created: 2026-11-01
- Slug: `feature-12-employer-company-profile`

---

## Goal

Give an approved employer (role `employer` + status `approved`) the ability to create, view, and edit one or more company profiles that own job postings. The profile captures public-facing company information, an optional logo, and an owner reference that is set server-side. Only the owner (and, later, authorized company members) may read private edit data or modify the record; public job pages expose only approved public fields.

## Non-goals

- Admin management of companies (no admin CRUD routes in this feature; Feature 10 remains employer-account focused).
- Company hiring-team membership / company-scoped roles (Feature 28).
- Job drafts, submission, moderation, or closure (Features 13–15). This feature only provides the company that jobs will belong to.
- Company deletion. Deletion semantics (restrict when jobs exist) are deferred to Feature 13, which introduces the `jobs` table and its FK.
- Notifications, audit logging (Features 25/31).
- Industry/size-band catalogues. These are stored as optional free text until a future admin configuration feature exists.
- Public company profile page rendering. Public exposure is defined by the field set here; rendering belongs to Feature 17.

---

## Current repository evidence

- No `companies` table, `Company` model, or company routes exist. `company_count` in the admin employer UI is still `null` (Feature 11 removed the placeholder column).
- Employer capability is already gated by `role:employer` + `employer.approved` middleware (`EnsureEmployerIsApproved`). Approved employers reach `/employer/dashboard`.
- `User::isApprovedEmployer()` exists and is used by `DashboardController`.
- File storage uses the standard Laravel disks (`local` private, `public` with `/storage` URL). No upload code exists yet; Feature 12 introduces the first upload flow.
- `HandleInertiaRequests` already shares `auth.employer_status`.
- Wayfinder generates route helpers from controller actions; `npm run build` regenerates them.
- Verified baseline: `php artisan test` → 239 passed, 1732 assertions; `npm run types:check` passes.

---

## Assumptions (defaults for approval)

- **A1 — Multiple companies per owner.** An approved employer may create and manage multiple company profiles. This matches Feature 13’s “companies the current user can manage” wording. If a single company per employer is preferred, the create route blocks creation when one already exists.
- **A2 — Profile status is `is_active`.** Company profile status is represented by a boolean `is_active` (default `true`), consistent with catalogue features. A richer verification state (e.g. pending/verified) is deferred; if needed later, add a `status` column.
- **A3 — No platform permission.** Company management is employer self-service gated by approved-employer status and ownership. No new `PermissionSlug` cases or seeder changes.
- **A4 — Optional free-text fields.** `industry`, `size_band`, and `location` are nullable strings (max 100/50/255). No admin catalogue exists for them.
- **A5 — Logo is optional and public.** A single optional logo image (jpeg/png/webp, ≤ 2 MB) stored on the `public` disk with a hashed filename. It is publicly readable by URL; no private download route is needed.
- **A6 — Slug is globally unique and stable.** Auto-generated from `name` via `Str::slug` when not supplied; editable by the owner with a uniqueness check. The slug is not auto-regenerated on name change.
- **A7 — Owner is immutable.** `owner_user_id` is set from the authenticated approved employer on create and never updated from the request.
- **A8 — No deletion in this feature.** Delete is deferred to Feature 13, which must enforce restrict-on-delete when jobs exist.
- **A9 — Owner-only access for now.** Only the owner may view/edit. Company members (Feature 28) are not supported yet; Policy is written so a future `managesCompany()` check can be added.
- **A10 — No Service/Repository.** Single-resource CRUD with file handling stays in a thin controller + Form Request, matching Features 5–9.

### Non-blocking questions (defaults above apply)

1. Should an employer be limited to one company profile? Default: no (A1).
2. Should industry/size band be constrained to admin-managed options now? Default: no (A4); deferred.
3. Should company deletion be added now? Default: no (A8); deferred to Feature 13.

---

## User flows and acceptance criteria

### F1 — Create a company profile

An approved employer with no company (or wanting another) opens the create form, fills fields, optionally uploads a logo, and submits.

Acceptance criteria:

- Fields: name (required), slug (optional, auto-generated), overview, industry, website, size band, location, logo, is_active.
- Server sets `owner_user_id` from the authenticated user; a forged `owner_user_id` has no effect.
- Valid submission creates the company and redirects to its detail/edit page with a success toast.
- Invalid input returns field-level errors; no partial record is created; an uploaded logo is not persisted if validation fails (file handled after validation).
- A non-approved or non-employer user cannot reach the form (403/redirect).
- Duplicate slug returns a field error.

### F2 — View own companies

Acceptance criteria:

- Index lists only companies owned by the authenticated user, paginated, with name, slug, active status, and logo thumbnail.
- Empty state with a “Create company” CTA.
- Cross-owner access to another company’s record returns 403 (Policy), even when the ID is guessed.

### F3 — Edit a company profile

Acceptance criteria:

- Owner can update all editable fields.
- Slug uniqueness is enforced ignoring the current record.
- Logo replacement deletes the previous file; `remove_logo` clears the logo.
- Invalid logo (type/size) returns a field error and leaves the existing logo intact.
- Non-owner receives 403.

### F4 — Public field set

Acceptance criteria:

- Public-facing data (name, slug, overview, industry, website, location, logo URL, is_active) is defined and selectable. Private edit data (owner email, etc.) is never exposed on public pages. (Rendering deferred to Feature 17.)

---

## Routes, middleware, and authorization

### Employer company routes (new)

All under the existing `auth` group in `routes/web.php`, inside the `employer` prefix. The company subgroup requires `role:employer` + `employer.approved`.

| Method | URI                                  | Name                        | Middleware                                                    |
| ------ | ------------------------------------ | --------------------------- | ------------------------------------------------------------- |
| GET    | `/employer/companies`                | `employer.companies.index`  | `auth`, `role:employer`, `employer.approved`                  |
| GET    | `/employer/companies/create`         | `employer.companies.create` | `auth`, `role:employer`, `employer.approved`                  |
| POST   | `/employer/companies`                | `employer.companies.store`  | `auth`, `role:employer`, `employer.approved`, `throttle:20,1` |
| GET    | `/employer/companies/{company}`      | `employer.companies.show`   | `auth`, `role:employer`, `employer.approved`                  |
| GET    | `/employer/companies/{company}/edit` | `employer.companies.edit`   | `auth`, `role:employer`, `employer.approved`                  |
| PUT    | `/employer/companies/{company}`      | `employer.companies.update` | `auth`, `role:employer`, `employer.approved`                  |

No delete route in this feature.

### Policy actions (`CompanyPolicy`, target `Company`)

| Action    | Rule                                                           |
| --------- | -------------------------------------------------------------- |
| `viewAny` | Actor is an approved employer (`isApprovedEmployer()`).        |
| `view`    | Actor is the company owner (or approved employer managing it). |
| `create`  | Actor is an approved employer.                                 |
| `update`  | Actor is the company owner.                                    |

Register the policy via Laravel’s auto-discovery (model-based) — no manual gate registration needed, unlike `EmployerPolicy` (which targets `User`). Controllers still call `$this->authorize()`.

### Middleware

Reuse existing `role` and `employer.approved` aliases. No new middleware.

---

## Form Requests and validation

### `StoreCompanyRequest` and `UpdateCompanyRequest`

`authorize()` returns `true`; the controller performs the Policy check.

| Field         | Rules (store)                                                | Rules (update)                                                         | Notes                              |
| ------------- | ------------------------------------------------------------ | ---------------------------------------------------------------------- | ---------------------------------- |
| `name`        | required, string, min:2, max:150                             | same                                                                   | trim                               |
| `slug`        | nullable, string, max:100, alpha_dash, unique:companies,slug | nullable, string, max:100, alpha_dash, unique:companies,slug,{company} | blank → server generates from name |
| `overview`    | nullable, string, max:5000                                   | same                                                                   | trim; blank → null                 |
| `industry`    | nullable, string, max:100                                    | same                                                                   | trim; blank → null                 |
| `website`     | nullable, url, max:255                                       | same                                                                   | trim; blank → null                 |
| `size_band`   | nullable, string, max:50                                     | same                                                                   | trim; blank → null                 |
| `location`    | nullable, string, max:255                                    | same                                                                   | trim; blank → null                 |
| `logo`        | nullable, file, image, mimes:jpeg,png,webp, max:2048         | same                                                                   | handled in controller              |
| `remove_logo` | nullable, boolean                                            | same                                                                   | update only                        |
| `is_active`   | boolean                                                      | boolean                                                                | default true                       |

Do not accept `owner_user_id`, `logo_path`, or any internal field. The data accessor `companyData()` returns the mapped scalar fields (excluding `logo` and `remove_logo`), with blanks normalized to `null`.

---

## Models, relationships, migration, constraints, and indexes

### New table: `companies`

New migration only. Do not edit existing migrations.

| Column                      | Type                     | Constraints/notes                            |
| --------------------------- | ------------------------ | -------------------------------------------- |
| `id`                        | bigint PK                |                                              |
| `owner_user_id`             | foreign key → `users.id` | not null, `cascadeOnDelete`, indexed         |
| `name`                      | string(150)              | required at validation                       |
| `slug`                      | string(100)              | unique, required (server-generated if blank) |
| `overview`                  | text, nullable           |                                              |
| `industry`                  | string(100), nullable    | free text (A4)                               |
| `website`                   | string(255), nullable    | URL-validated                                |
| `size_band`                 | string(50), nullable     | free text (A4)                               |
| `location`                  | string(255), nullable    |                                              |
| `logo_path`                 | string(255), nullable    | public-disk relative path                    |
| `is_active`                 | boolean                  | default true                                 |
| `created_at` / `updated_at` | timestamps               |                                              |

Indexes:

- Unique index on `slug`.
- Index on `owner_user_id` (FK).
- No composite indexes justified yet (queries are owner-scoped single-table reads).

### `Company` model

- `$fillable`: `name`, `slug`, `overview`, `industry`, `website`, `size_band`, `location`, `logo_path`, `is_active`. (`owner_user_id` is set explicitly, not mass-assigned.)
- Casts: `is_active` → boolean.
- Relationships: `owner(): BelongsTo<User>` (FK `owner_user_id`).
- Scopes: `forOwner(User $owner)` (where owner_user_id = owner id), `active()`.
- Accessor: `logo_url` → `Storage::disk('public')->url($this->logo_path)` when present, else `null`.
- No `SoftDeletes` in this feature; deletion is deferred.

### `User` model change

- Add `companies(): HasMany<Company>` relationship. (Used by Feature 13+; harmless now.)

### Factory

- Add `CompanyFactory` with sensible defaults (owner, name, slug, is_active true).

---

## Query, payload, pagination, and eager-loading plan

### Company index (employer)

- Authorize `viewAny`.
- Query: `Company::query()->forOwner($user)->orderBy('name')->orderBy('id')->paginate(15)->withQueryString()`.
- Select only needed columns (exclude none extra; small table).
- `through()` projection: `id`, `name`, `slug`, `is_active`, `logo_url` (via accessor), `created_at`, `updated_at`.
- Props: `companies` (paginated), plus `canCreate` (always true for approved employer).

### Company show / edit

- Authorize `view`/`update` on the resolved company.
- Route-model binding loads by id; Policy enforces owner. Non-owner → 403.
- Select needed columns; no eager loads required (owner not needed in payload beyond id).
- Payload: company fields including `logo_url`, plus `isOwner` boolean.

### No N+1: index is a single paginated query; show/edit are single-record reads.

---

## Service / Repository decision

**No Service and no Repository.**

Reasons:

- Create/update are single-model writes with file handling; no multi-step transaction or external integration.
- Ownership scoping is a simple `where owner_user_id = auth id`; no complex query reuse yet (Feature 13 will introduce job queries that may warrant a Repository).
- File upload/delete is handled in the controller with `Storage` facade, consistent with standard Laravel practice.
- Matches the AGENTS.md rule against adding layers for ceremony.

---

## React / Inertia page and component plan

### New pages

- `resources/js/pages/employer/companies/index.tsx` — paginated list of own companies, empty state, “Create company” CTA, edit/view links.
- `resources/js/pages/employer/companies/create.tsx` — create form.
- `resources/js/pages/employer/companies/edit.tsx` — edit form (reused layout; receives company).
- `resources/js/pages/employer/companies/show.tsx` — read-only detail (name, overview, industry, website, size band, location, logo, status, dates).

### Form fields

Use existing shadcn/ui: `Input`, `Textarea`, `Label`, `InputError`, `Button`, `Spinner`, `Badge`, `Card`, `Select` (for is_active), plus a native `<input type="file">` styled with Tailwind (no new component library).

Form uses `useForm` with fields: `name`, `slug`, `overview`, `industry`, `website`, `size_band`, `location`, `logo` (File | null), `remove_logo` (boolean), `is_active` (boolean). Submit via `router.post`/`router.put` with `forceFormData: true` for file upload. Disable submit while processing; show spinner.

Logo preview: when editing, show current `logo_url` thumbnail; selecting a new file previews via `URL.createObjectURL` (client-side preview only; server remains authoritative).

### Employer dashboard

Update the “Company Profile” card in `resources/js/pages/employer/dashboard.tsx` to link to `employer.companies.index()` when approved, instead of the static “Pending Setup” placeholder. (Minor UI update included in this feature.)

### TypeScript types

Add `resources/js/types/company.ts` (re-exported from `types/index.ts`):

```ts
export type Company = {
    id: number;
    name: string;
    slug: string;
    overview: string | null;
    industry: string | null;
    website: string | null;
    size_band: string | null;
    location: string | null;
    logo_path: string | null;
    logo_url: string | null;
    is_active: boolean;
    created_at: string;
    updated_at: string;
};

export type CompanyForm = {
    name: string;
    slug: string;
    overview: string | null;
    industry: string | null;
    website: string | null;
    size_band: string | null;
    location: string | null;
    logo: File | null;
    remove_logo: boolean;
    is_active: boolean;
};

export type CompanyIndexProps = {
    companies: PaginatedData<
        Pick<
            Company,
            | 'id'
            | 'name'
            | 'slug'
            | 'is_active'
            | 'logo_url'
            | 'created_at'
            | 'updated_at'
        >
    >;
    canCreate: boolean;
};

export type CompanyShowProps = { company: Company };
export type CompanyEditProps = { company: Company };
```

No `any`.

---

## Accessibility, empty, loading, and error states

- Every input has a visible `<Label htmlFor>`; errors use `InputError` (`role="alert"`) with `aria-describedby`/`aria-invalid`.
- Logo input has helper text (accepted types, max size) and an accessible label.
- Submit buttons show a spinner and are disabled during processing; prevent double submission.
- Index table uses `<th scope="col">`, horizontal overflow container, and a meaningful empty row with CTA.
- Empty state: “No company profiles yet. Create your first company to start posting jobs.”
- 403/404 handled by framework; field errors render inline.
- Responsive: form and detail cards stack on small screens; tables scroll horizontally.

---

## Test matrix

### New `tests/Feature/Employer/CompanyTest.php`

- Guest GET index/create/show/edit redirects to login.
- Non-approved/non-employer (job seeker) GET routes → 403.
- Approved employer can create a company with valid data; `owner_user_id` set to auth user; redirect + toast.
- Forged `owner_user_id` in payload is ignored (not accepted by Form Request).
- Validation: missing name, overlong fields, invalid website URL, invalid slug (non alpha_dash), duplicate slug → field errors, no record created.
- Logo validation: invalid MIME (e.g. txt/pdf) rejected; oversize (>2MB) rejected; valid jpeg/png/webp accepted and `logo_path` set.
- Logo replacement on update deletes the old file and sets the new path; `remove_logo` clears path and deletes file.
- Update by owner succeeds; non-owner (another approved employer) → 403 on show/edit/update.
- ID tampering: GET/PUT another user’s company id → 403.
- Index lists only owned companies; pagination at 15; empty state.
- Slug auto-generation when slug omitted.
- `is_active` defaults to true.
- Cross-owner denial does not leak existence (403, not 404) — consistent with Policy; or 404 if preferred. Spec: 403 via Policy.

### Regression

- `EmployerOnboardingTest`, `EmployerTest`, `DashboardRoutingTest` still pass.
- `AuthorizationCatalogueTest` unchanged (no new permissions).
- Frontend/build: `npm run types:check`, `npm run check`, `npm run build`, `vendor/bin/pint --test`, `vendor/bin/phpstan`.

---

## Security and performance risks

### Security

- **Authorization:** all company routes use `role:employer` + `employer.approved`; controllers call `$this->authorize()`; Policy enforces ownership. Direct requests covered.
- **IDOR:** route model binding + Policy denies non-owners (403). No user ID in payload.
- **Mass assignment:** `owner_user_id` not in `$fillable`; Form Request excludes internal fields; data accessor maps validated scalars only.
- **File upload:** validated MIME + size; stored with hashed filename on `public` disk; original filename never used; path never taken from request. Old file deleted on replace/remove.
- **Slug uniqueness:** DB unique constraint + validation prevents collisions; server generates from name to avoid client control.
- **Data exposure:** payload selects needed columns; owner email not exposed; logo URL is public by design.
- **No new permissions/seeder:** avoids deployment drift; approved-employer gate is the boundary.

### Performance

- Index paginated at 15; owner-scoped single-table query; no N+1.
- Logo storage on local public disk; no image processing in this feature (resize deferred).
- No unbounded queries.

### Compatibility / rollout

1. Create migration; do not modify existing migrations.
2. Add model, factory, policy, Form Requests, controller, routes.
3. Run migration before exposing UI.
4. Existing approved employers can create their first company immediately; no backfill needed.
5. Rollback: remove routes/UI, then drop `companies` table if no data must be retained. Do not drop while Feature 13 depends on it.

---

## Ordered implementation plan

1. Create migration `companies` (owner FK, unique slug, indexes).
2. Add `Company` model (fillable, casts, owner relation, scopes, logo_url accessor) and `CompanyFactory`.
3. Add `companies()` relation to `User`.
4. Add `CompanyPolicy` (viewAny/view/create/update) and register via auto-discovery.
5. Add `StoreCompanyRequest` and `UpdateCompanyRequest` with `companyData()` accessor.
6. Add `CompanyController` (index, create, store, show, edit, update) with file handling.
7. Add employer company routes in `routes/web.php` under approved-employer subgroup.
8. Build Inertia pages (index, create, edit, show) and update employer dashboard card link.
9. Add TypeScript types (`types/company.ts`, re-export).
10. Add `CompanyTest.php` covering the matrix.
11. Run targeted tests, full suite, type checks, formatter, static analysis, build, route-list inspection; fix failures.

---

## Verification commands

```text
php artisan migrate
php artisan test tests/Feature/Employer/CompanyTest.php
php artisan test tests/Feature/Employer/EmployerOnboardingTest.php
php artisan test tests/Feature/Admin/EmployerTest.php
php artisan test tests/Feature/DashboardRoutingTest.php
php artisan test
npm run types:check
npm run check
npm run build
vendor/bin/pint --test
vendor/bin/phpstan analyse --memory-limit=512M
php artisan route:list --path=employer/companies -v
```

Do not claim any command passed unless its output was inspected. If a command cannot run, record the exact blocker.

---

## Approval gate

Status: **PROPOSED**. This spec is submitted for review. Do not implement application code, create migrations, install packages, or change existing features until the user explicitly approves this spec (or edits its assumptions/decisions).
