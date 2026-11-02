# Feature 13 — Employer Job Draft CRUD

**Metadata:**

- Feature ID: 13
- Title: Employer job draft CRUD
- Spec Type: Feature specification
- Source: `project-overview.md` → “Feature 13 — Employer job draft CRUD”
- Dependencies: Feature 12 (implemented; commit `ae9857e`), Features 5–9 (implemented catalogues); Feature 11 eligibility foundation
- Downstream dependents: Feature 14 (submission/moderation/publication/closure), Feature 15 (employer dashboard/listing filters), Features 16/17 (public job listing/detail), Feature 21 (apply), Features 23/28 (applicant/tenant scoping)
- Status: **APPROVED** (approved by user 2026-11-02)
- Created: 2026-11-01
- Slug: `feature-13-employer-job-draft-crud`

---

## Goal

Let an approved employer (role `employer` + status `approved`) create and edit job drafts that persist draft content before submission for review (Feature 14). A draft belongs to a company the employer owns; only `draft` rows are editable here; drafts are never publicly visible.

## Non-goals

- Submission to `pending_review`, admin moderation queue, publish/close transitions, re-review rules (Feature 14).
- Public job listing/detail, applications, candidate flows (Features 16–22).
- Employer job list/dashboard UI and listing filters (Feature 15).
- Hiring-team/company-scoped permissions (Feature 28).
- Notifications, audit logging, interviews, analytics (Features 25/31 and later).

## Current repository evidence

- Feature 12 implemented and committed at HEAD `ae9857e` (clean tree, in sync with `origin/main` — verified during the `/remember restore` run).
- Catalogue tables already exist and are active-flagged by default: `job_categories`, `employment_types`, `workplace_arrangements`, `experience_levels`, `pay_periods`, `salary_bands` (migrations `2026_10_30_000000` … `2026_11_01_000003`).
- Critical naming conflict found: applied default migration `0001_01_01_000002_create_jobs_table.php` created the **queue** table `jobs` (queue/payload/attempts columns; status `Ran` in `migrate:status`). The recruitment job entity therefore requires a new table name (proposed `job_postings` — open question Q3).
- Auth foundation: `User::isApprovedEmployer()`, `EmployerStatus` enum, `RoleSlug`, `HasRolesAndPermissions`, `HandleInertiaRequests` sharing auth user/roles/permissions/employer_status.
- Company patterns: `CompanyPolicy`, `CompanyController`, Store/UpdateCompanyRequest with `companyData()`, employer routes under `employer` prefix with `role:employer`, `employer.approved`, `throttle:20,1` on store; tests in `tests/Feature/Employer/CompanyTest.php`.
- Baseline re-verified: `php artisan test` → 266 passed / 1911 assertions; pint, phpstan, types:check, check, build all green.

## Assumptions (explicit, labelled) and open questions

Assumptions:

- A1: A job draft requires a company owned by the creating employer; the company select lists owned companies, otherwise an empty state links to company creation.
- A2: Salary is optional (spec default; open question Q1). When provided: min ≤ max, currency + pay period required.
- A3: Required draft fields: title (3–150), description (required, max 20000), company_id, category_id (active), employment_type_id (active). Optional: responsibilities, requirements, workplace_arrangement_id (active), experience_level_id (active), salary, location, deadline.
- A4: Status is server-controlled: create → `draft`; update only when the row status is `draft`; status never accepted from client input.
- A5: Multiple drafts per company allowed (no unique constraint); submission semantics deferred to Feature 14.
- A6: Company association is immutable on update.
- A7: Draft preview is client-side only (no server route).
- A8: No new permissions; employer capability = role `employer` + status `approved` via existing middleware/policy.
- A9: No Service/Repository for this feature (simple CRUD); query helpers may justify one later.

Open questions (smallest set): Q1 — is job salary required or optional? Q2 — must the employer own a company to create drafts? Q3 — what table/model name for the recruitment job entity (collision with queue `jobs` forces a new name)? Q4 — one draft per company vs multiple?

---

## User flows and acceptance criteria

### F1 — Create a job draft

An approved employer opens the draft form, fills required/optional fields, saves.

Acceptance criteria:

- Required fields validated server-side; optional fields nullable; salary rules applied.
- Draft persisted with `status = draft`; `company_id` points to an owned company; actor cannot set status/moderation fields.
- Success → redirect to edit route + success toast; invalid input → field errors, no record created.

### F2 — Edit a job draft

The owner edits and saves their own draft.

Acceptance criteria:

- Only `draft` rows editable; update on a non-draft row is denied by the policy.
- Non-owner → 403; unknown job → 404; cross-company/forged `company_id` → 404 (owned-scope resolution).

### F3 — Preview

Client-side preview derived from form state (no server route).

Acceptance criteria: preview reflects current form state.

### Overall acceptance criteria (from feature request)

- Authorized employer can save and edit its own drafts; another employer cannot access or change them; invalid fields return Form Request errors; job content is persisted; forms use typed Inertia props and show processing/errors; job drafts are not public.

## Routes, middleware, and custom role/permission checks

New routes inside the existing `auth` group; employer prefix with `role:employer` + `employer.approved`:

| Method | URI                         | Name                   | Middleware                                                    |
| ------ | --------------------------- | ---------------------- | ------------------------------------------------------------- |
| GET    | `/employer/jobs/create`     | `employer.jobs.create` | `auth`, `role:employer`, `employer.approved`                  |
| POST   | `/employer/jobs`            | `employer.jobs.store`  | `auth`, `role:employer`, `employer.approved`, `throttle:20,1` |
| GET    | `/employer/jobs/{job}/edit` | `employer.jobs.edit`   | `auth`, `role:employer`, `employer.approved`                  |
| PUT    | `/employer/jobs/{job}`      | `employer.jobs.update` | `auth`, `role:employer`, `employer.approved`, `throttle:20,1` |

No delete, list, public, or admin routes in this feature (list → Feature 15; moderation → Feature 14).

No new permissions: employer capability boundary stays `role:employer` + `employer.approved`; no new `PermissionSlug` cases or seeder changes.

## Policy actions

- `JobPolicy` (new, auto-discovered): `update(User $actor, Job $job): bool` → approved employer AND actor owns the job's company AND job status is `draft`.
- Creation gate: `$this->authorize('create', Company::class)` (approved employer) then resolve the target through `Company::query()->forOwner($user)->findOrFail($validated['company_id'])` (owned-only; foreign ids → 404). Ownership enforced by owned-scope resolution.

## Form Requests and validation

- New `StoreJobRequest` and `UpdateJobRequest` (mirror the `StoreCompanyRequest`/`UpdateCompanyRequest` structure, including a `jobData(): array` mapping like `companyData()`).
- Store rules: `company_id` required + `exists:companies`; `title` required/string/min:3/max:150; `description` required/string/max:20000; `responsibilities`/`requirements` nullable/string/max:20000; `category_id` required + exists in `job_categories` where `is_active`; `employment_type_id` required + exists in `employment_types` where `is_active`; `workplace_arrangement_id` nullable + exists in `workplace_arrangements` where `is_active`; `experience_level_id` nullable + exists in `experience_levels` where `is_active`; `salary_min`/`salary_max` nullable numeric/min 0; `salary_currency` required when salary present (3-letter); `salary_negotiable` boolean default false; `pay_period_id` nullable + exists in `pay_periods` where `is_active`; `location` nullable string/max:255; `application_deadline` nullable date.

Salary cross-field validation (custom, in the Form Request): when `salary_min` or `salary_max` is present, require `salary_currency` and an active `pay_period_id`, and enforce min ≤ max when both bounds are provided. Update rules mirror store minus `company_id` (immutable).

---

## Data model, migration, constraints, and indexes

- New migration `2026_11_01_000008_create_<entity>_table.php` (table name per open question Q3; recommended `job_postings`). Only new migrations; never edit existing ones.
- Entity columns (proposal): `id`; `company_id` FK → `companies.id` NOT NULL + index + `restrictOnDelete`; `title` string(150) NOT NULL; `description` text NOT NULL; `responsibilities`/`requirements` text nullable; `category_id` FK → `job_categories.id` NOT NULL + index + `restrictOnDelete`; `employment_type_id` FK → `employment_types.id` NOT NULL + index + `restrictOnDelete`; `workplace_arrangement_id` FK → `workplace_arrangements.id` nullable + index + `restrictOnDelete`; `experience_level_id` FK → `experience_levels.id` nullable + index + `restrictOnDelete`; `salary_min`/`salary_max` decimal(12,2) nullable; `salary_currency` string(3) nullable; `salary_negotiable` boolean default false; `pay_period_id` FK → `pay_periods.id` nullable + index + `restrictOnDelete`; `location` string(255) nullable; `application_deadline` date nullable; `status` string default `draft`; timestamps.

- `Job` model: fillable excludes `status` (server-controlled); casts for decimals/booleans; relations `company`, `category`, `employmentType`, `workplaceArrangement`, `experienceLevel`, `payPeriod`; scopes for owner/company and draft filtering.

- This migration activates the deferred restrict-on-delete protections for catalogue options now that jobs reference them (employment type, workplace arrangement, experience level, pay period, category).

## Query, payload, pagination, and eager-loading plan

- Store/update are single-record writes; no unbounded queries.
- Options for selects are loaded with explicit `is_active = true` filters (bounded).
- No list endpoint in this feature; employer draft listing/filters deferred to Feature 15.

## Service / Repository decision

- No Service and no Repository for this feature (simple CRUD + single owned-company resolution), consistent with Feature 12. Revisit if Feature 15 query reuse justifies one.

## React / Inertia page and form plan

- New pages: `resources/js/pages/employer/jobs/create.tsx` and `resources/js/pages/employer/jobs/edit.tsx`; shared field set via typed form state.
- New types in `resources/js/types/job.ts` (re-exported via `types/index.ts`): `Job`, `JobForm`, `JobCreateProps`, `JobEditProps` (no `any`).
- Create props: owned `companies` (id/name/slug) + active catalogue options. Edit props: same options plus the `Job` draft.
- `useForm<JobForm>`; create → `router.post` to `employer.jobsStore`; edit → `router.put` to `employer.jobsUpdate`; disable submit while `processing`; inline field errors; spinner; toast on success.

## Accessibility, empty, loading, and error states

- Visible `<Label htmlFor>` per input, `InputError` with `role="alert"`, `aria-invalid`/`aria-describedby`; select labels; processing/disabled submit; empty state when no owned company (CTA to create company); Inertia loading; field errors inline; 403/404 via framework.

## Test matrix and regression

- New `tests/Feature/Employer/JobDraftTest.php`: guests, job seeker, non-approved employer, approved-employer happy paths (create draft, edit draft), forged `company_id`, status-forgery, validation failures (incl. salary min > max), owner/non-owner denials, update-on-non-draft denial, no-record-on-error cases.
- Regression: existing suite (266 passed / 1911 assertions baseline), Company/Admin catalogue tests.

## Security and performance risks

- IDOR: policy owner check via company owner; status-draft gate; store via owned-scope resolution (404).
- Mass assignment: `status` not fillable/client-settable; validated + mapped data only.
- Public exposure: no public routes in this feature; drafts not publicly reachable.
- Performance: bounded option queries; single-record writes.

## Rollout and compatibility

- New migration only; no edits to existing migrations. After migration, admin catalogue deletes of referenced options become DB-blocked (restrict-on-delete).

## Ordered implementation plan

1. Migration (entity/table name from Q3).
2. Job model/relations/factory.
3. JobPolicy.
4. Store/Update Job Requests.
5. Job controller + routes.
6. Inertia pages/types/Wayfinder regen + build.
7. Tests.
8. Run checks.

## Verification commands

```text
php artisan migrate
php artisan test tests/Feature/Employer/JobDraftTest.php
php artisan test
npm run types:check
npm run check
npm run build
vendor/bin/pint --test
vendor/bin/phpstan analyse --memory-limit=512M
php artisan route:list --path=employer/jobs -v
```

## Approval gate

Status: **PROPOSED**. Stop here; do not implement application code, migrations, packages, or destructive commands until the user explicitly approves this spec (or edits its assumptions/decisions).
