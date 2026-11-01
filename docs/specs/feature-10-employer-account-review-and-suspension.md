# Feature 10 — Admin Manages Employer-Account Review and Suspension

**Metadata:**

- Feature ID: 10
- Title: Admin Manages Employer-Account Review and Suspension
- Spec Type: Feature specification
- Source: `project-overview.md` → "Feature 10 — Admin manages employer-account review and suspension" (Phase 1 — Platform configuration and governance)
- Dependencies: Feature 3 (custom authorization — implemented), Feature 11 (Employer onboarding and eligibility state — not yet implemented)
- Status: **APPROVE** — awaiting architect review and approval

---

## Goal

Give authorized platform administrators a controlled way to review employer onboarding requests and suspend/reactivate employer accounts without silently deleting associated company data or job postings. This feature integrates with the employer onboarding flow (Feature 11) and the employer dashboard (Feature 15) to enforce eligibility at the server level.

**Non-goals:**

- Do not automatically delete a company, its jobs, or its applications when an account is suspended. Suspended employers must retain historical records; only prohibited actions are blocked.
- Do not implement email notifications for suspension events (Feature 25 handles in-app notifications; email can be added later).
- Do not implement ownership transfer or company merge when the last owner is suspended (separate process).
- Do not change the employer's role or permission assignment; suspension is a status change on the user/company record, not a role revocation.

---

## Actors and flows

**Primary actor:** Platform administrator holding `admin.access` plus the relevant permission (`employers.view` / `employers.manage`).

**Key flows:**

1. Admin opens `/admin/employers` and sees a paginated, searchable list of all employer accounts with their verification/suspension state.
2. Admin views an employer's detailed profile, including company basic info, status, suspension reason (if suspended), and a count of associated jobs.
3. Admin approves a pending employer request: the user's status changes from `pending` to `approved`, and they may immediately proceed to company/profile creation.
4. Admin rejects a pending employer request: the user's status changes to `rejected`; the account is locked and an appropriate message is shown.
5. Admin suspends an employer account: the user's status changes to `suspended`; a required reason is captured; the employer is blocked from disallowed actions and sees a clear status message.
6. Admin reactivates a suspended employer: the user's status changes back to `approved` (or `pending` if re-onboarding is required); the reason is cleared.
7. A suspended employer attempting any disallowed employer action receives a `403 Forbidden` or a clear error message; direct HTTP requests are also covered by middleware/Policy.

---

## Decision summary

These are the material design choices. Each is labelled as an assumption in case approval wants a different policy.

1. **Two permission pair:** `employers.view` (read-only admin list and detail) and `employers.manage` (approve/reject/suspend/reactivate). This matches the existing convention of view/manage pairs used by Features 5–9.
2. **Three employer statuses:** `pending` (onboarding not yet approved), `approved` (eligible to create company and publish jobs), `suspended` (account restricted; cannot publish jobs or perform employer actions). The default for a newly registered user requesting employer capabilities is `pending`.
3. **Suspension preserves data:** Suspending an employer account does not delete the associated company record, jobs, or applications. Published jobs remain visible (or become hidden depending on the approved rule — see below). Applications remain in the system for audit/history.
4. **Suspension blocks:** A suspended employer cannot create new jobs, edit existing jobs, submit new applications, or change company profile data. Existing published jobs' status is not changed, but the employer cannot perform further actions on them.
5. **Required reason for suspension/reactivation:** The admin must supply a non-empty reason when suspending or reactivating; the reason is stored on the user record and surfaced in the UI.
6. **Pay-period/salary-band interaction:** Not applicable; these are employer-lifecycle resources.
7. **No Service or Repository:** This is CRUD + status-state management plus one reusable validation rule (suspension reason presence). No transaction or multi-step domain workflow justifies a service layer beyond the thin controller.
8. **Audit integration:** Once Feature 31 (Administrative audit log) exists, suspension and reactivation events should write audit entries. For this increment, the controller simply flashes a toast; the audit hook is a forward-looking comment.
9. **Pagination at 25 per page:** Slightly larger than the 20-row default for catalogues, because an employer list may have many entries; adjust if profiling justifies a change.

---

## User-visible behavior and acceptance criteria

### Employer list

- [ ] An admin with `employers.view` can open `/admin/employers` and see a paginated list (25 per page) with search by name or email.
- [ ] The list shows employer name, email, status badge (pending/approved/suspended), company name (if a company profile exists), and job count.
- [ ] Only users holding `employers.manage` see approve/reject/suspend/reactivate controls and can perform writes; view-only admins see a read-only panel.
- [ ] Users without `employers.view` receive 403 on index; users without `employers.manage` receive 403 on store/update/destroy actions, including direct HTTP requests.
- [ ] Creating a search returns field-level validation errors rendered inline.
- [ ] Search filters server-side with LIKE-escaped prefix matching on name or email, preserves the query string across pagination, and returns a clear empty state.
- [ ] Status badge color coding: pending (amber), approved (emerald), suspended (rose/crimson).
- [ ] Suspended employers display a suspension reason summary (truncated) adjacent to the status badge.
- [ ] The "Employers" navigation item appears only for users holding `admin.access` + `employers.view`.
- [ ] List ordering is stable: `status` ascending (pending → approved → suspended), then `name` ascending, then `id` ascending.

### Employer detail

- [ ] Admin can view an employer's detailed profile at `/admin/employers/{user}` or equivalent.
- [ ] The detail section shows: user name, email, status, suspension reason (if suspended), company profile summary (name, slug, status), job count, and creation dates.
- [ ] View-only panel when `canManageEmployers` is false.
- [ ] Buttons for approve, reject, suspend, reactivate are only visible when `canManageEmployers` is true.

### Approve employer

- [ ] Admin clicks "Approve" on a pending employer.
- [ ] The user's status changes to `approved` server-side; a success toast is shown.
- [ ] The admin is redirected to the employer list with the updated status.
- [ ] The newly approved employer can immediately proceed to create a company profile (Feature 12).
- [ ] If the employer already has a company profile, it remains associated.

### Reject employer

- [ ] Admin clicks "Reject" on a pending employer.
- [ ] The user's status changes to `rejected` server-side; a success toast is shown.
- [ ] The employer account is locked; login is blocked or a prominent rejection message is displayed.
- [ ] The admin is redirected to the employer list with the updated status.

### Suspend employer

- [ ] Admin clicks "Suspend" on an approved or pending employer.
- [ ] A modal/dialog appears requiring a non-empty suspension reason.
- [ ] Upon submitting the reason, the user's status changes to `suspended`; a success toast is shown.
- [ ] The admin is redirected to the employer list; the suspended employer's row now shows the reason (truncated) and a red status badge.
- [ ] Suspended employers are immediately blocked from disallowed actions (see below).

### Reactivate employer

- [ ] Admin clicks "Reactivate" on a suspended employer.
- [ ] A confirmation dialog appears; no reason is required for reactivation (the suspension reason is cleared).
- [ ] Upon confirmation, the user's status changes back to `approved`; the suspension reason is cleared; a success toast is shown.
- [ ] The admin is redirected to the employer list; the employer now shows an approved badge.

### Suspended employer action denial

- [ ] A suspended employer attempting to access `/employer/company/create` or any employer-protected route is redirected or receives a `403 Forbidden` with a message that the account is suspended.
- [ ] A suspended employer attempting to edit or create jobs receives a `403 Forbidden` or a clear inline error that the account is suspended; the action is not performed.
- [ ] The server-side enforcement is the authoritative guard; the UI visibility change is presentation-only.

---

## Assumptions and focused questions

**Assumptions (override in approval if you disagree):**

1. **Three-status model.** The employer statuses are `pending`, `approved`, and `suspended`. There is no `rejected` permanent state beyond the initial rejection that redirects the user to re-register; the focus is on the lifecycle of approved/suspended accounts.
2. **Status lives on the User model.** The `employers` feature adds a `status` column to the existing `users` table (or an equivalent user-extensions table) rather than a separate model. This avoids a new table for a feature that is purely a state machine on the authenticated user.
3. **Suspension reason is stored on the User model.** A `suspension_reason` text column (nullable) and `suspended_at` timestamp (nullable) track the when and why; these are soft values, not hard deletes.
4. **No company deletion on suspend.** The company record (Feature 12) is preserved even when the user is suspended; the admin can reactivate and the employer returns.
5. **Approve/reject only for pending employers.** Admins can approve a pending request or reject a pending request. Once an employer is `approved`, only `suspend` and `reactivate` actions are available. Rejecting an already-approved employer is not in scope for this increment.
6. **Permission scoping.** `employers.view` allows reading the list and detail; `employers.manage` allows all write actions (approve, reject, suspend, reactivate). This matches the view/manage pattern of Features 5–9.
7. **Pagination at 25 per page.** Larger than the catalogue 20 because employer lists may have many entries; adjust if profiling justifies a change.
8. **No Service or Repository.** CRUD + status-state management; the only new abstraction is the suspension reason validation (presence check), which stays in the controller/Form Request.
9. **Audit logging is forward-looking.** Once Feature 31 exists, suspension/reactivation should write audit entries. For this increment, the controller flashes a toast; the audit hook is a forward-looking comment.
10. **Email verification is separate.** This feature does not override or integrate with Laravel's email verification flow; a pending employer may or may not have a verified email depending on Feature 2 setup.

**Focused questions (non-blocking; defaults above apply):**

- Should the employer `status` column be on the `users` table, or on a separate `employer_profiles` table? (The User model extension approach is simpler and matches the existing pattern; if a separate table is preferred, the schema changes.)
- Should a rejected employer be permanently locked out, or should they be able to re-apply with a new submission? (The assumption is: reject locks the account; re-application is a new request — a separate flow if needed.)
- Should suspension also block access to Inertia pages via direct URL, or only to form submissions? (Both — the Policy/middleware guard covers all routes; the UI simply hides controls for view-only admins.)
- Should the suspension reason be visible to the suspended employer when they next log in? (The assumption is: yes, a flash or profile-message is shown on login; this can be refined later.)

---

## Existing code and conventions inspected

- `docs/specs/feature-09-salary-filter-bands-and-pay-periods.md` — closest recent catalogue pattern with pagination, search, view/manage permissions, Form Requests, Policies, Inertia pages.
- `app/Models/User.php` — existing User model; will add `$casts` for `status` and `suspension_reason` if on the users table, or a migration for a new table if decided.
- `app/Enums/PermissionSlug.php` — already has `PayPeriodsView`, `PayPeriodsManage`, `SalaryBandsView`, `SalaryBandsManage`; new cases `EmployersView` and `EmployersManage` will be added.
- `database/seeders/AuthorizationSeeder.php` — already seeds the four salary-band permissions; new cases `EmployersView` and `EmployersManage` will be added.
- `routes/admin.php` — route groups use `permission:admin.access` with `.view`/`.manage` sub-permissions; new route groups will follow the same pattern.
- `app/Policies/ExperienceLevelPolicy.php` / `SalaryBandPolicy.php` — permission-based policy methods using `$actor->hasPermission()`. New `EmployerPolicy` will follow the same pattern.
- `app/Http/Requests/` — Form Request patterns use `$this->safe()->all()` / `$this->salaryBandData()` accessors; new requests will follow.
- `resources/js/pages/admin/experience-levels/index.tsx` — `SplitLayout`, `Pagination`, `InputError`, active/inactive badges, view-only gating.
- `resources/js/lib/navigation.ts` — nav items with `requiredPermissions`.
- `tests/Feature/Admin/SalaryBandTest.php` and `tests/Feature/Authorization/AuthorizationCatalogueTest.php` — test patterns and hardcoded permission counts.
- `app/Http/Controllers/Admin/SalaryBandController.php` — `$this->authorize('viewAny', SalaryBand::class)`, `$this->authorize('create', SalaryBand::class)`, form request injection, `Inertia::flash`, `to_route()`.
- `app/Http/Requests/Admin/StoreSalaryBandRequest.php` / `UpdateSalaryBandRequest.php` — typed data accessors using `$this->safe()->all()`.

---

## Routes, middleware, and authorization

| Method                  | URI                                  | Name                         | Middleware                                                       |
| ----------------------- | ------------------------------------ | ---------------------------- | ---------------------------------------------------------------- |
| GET                     | `/admin/employers`                   | `admin.employers.index`      | `auth`, `permission:admin.access`, `permission:employers.view`   |
| POST                    | `/admin/employers`                   | `admin.employers.store`      | `auth`, `permission:admin.access`, `permission:employers.manage` |
| GET                     | `/admin/employers/{user}`            | `admin.employers.show`       | `auth`, `permission:admin.access`, `permission:employers.view`   |
| PUT                     | `/admin/employers/{user}`            | `admin.employers.update`     | `auth`, `permission:admin.access`, `permission:employers.manage` |
| DELETE (or custom POST) | `/admin/employers/{user}/suspend`    | `admin.employers.suspend`    | `auth`, `permission:admin.access`, `permission:employers.manage` |
| POST                    | `/admin/employers/{user}/reactivate` | `admin.employers.reactivate` | `auth`, `permission:admin.access`, `permission:employers.manage` |

- Add both route groups inside the existing `auth` + `admin` prefix/name group in `routes/admin.php`, matching the existing convention.
- Each group is wrapped in `permission:admin.access`; index adds the `.view` permission; write verbs add `.manage`.
- Controllers call `$this->authorize(...)` per action for defense in depth, matching Features 5–8.
- No company/owner/tenant scoping applies beyond the existing company ownership (Feature 12); these are platform-lifecycle resources with a single admin audience.
- Admin actions (suspend/reactivate) must capture a non-empty reason; the controller validates this before status change.

### Permission catalogue changes

- Add enum cases:
    - `EmployersView = 'employers.view'`
    - `EmployersManage = 'employers.manage'`
- Add both to `AuthorizationSeeder` with display names:
    - "View employers"
    - "Manage employers"
- Add both to the explicit `platformAdminPermissionSlugs` allowlist.
- Update `AuthorizationCatalogueTest` hardcoded counts from 19 → 21 and 20 → 22 (depending on whether the test counts system permissions only or all; adjust accordingly).
- Deployed environments must rerun the `AuthorizationSeeder`; otherwise existing admins receive 403 on the new routes.

### Policy actions and dynamic permission resolution

#### `EmployerPolicy`

| Action                         | Permission         |
| ------------------------------ | ------------------ |
| `viewAny` / `view`             | `employers.view`   |
| `create` / `update` / `delete` | `employers.manage` |

- Policies use the existing `hasPermission()` service through `User`; no role-name checks.
- The controller remains the authorization entry point; Form Request `authorize()` returns `true` because the controller performs the Policy check, matching the established pattern.
- Suspension/reactivate business rules (required reason, status transition matrix) are enforced in the controller after `authorize()`, not in the Policy, to keep Policies focused on permission resolution.

---

## Form Requests and validation

### `StoreEmployerRequest` / `UpdateEmployerRequest`

These Form Requests handle the admin's approve/reject/suspend/reactinate actions. They share a common shape but differ in which fields are required.

- `status`: `required`, `string`, `in:pending,approved,suspended`; the admin sets this field.
- `suspension_reason`: `nullable`, `string`, `min:1`, `max:500`; **required** when `status` is `suspended`; **must not be set** when `status` is `approved` or `rejected` (the controller clears it). The Form Request `authorize()` or `rules()` method enforces this conditional logic.
- `action`: `nullable`, `string`, `in:approve,reject,suspend,reactivate`; optional, for explicit action routing.

`StoreEmployerRequest`:

- When `status` is `suspended`, `suspension_reason` is required.
- When `status` is `approved` or `rejected`, `suspension_reason` must not be present (or is forced to null).

`UpdateEmployerRequest`:

- Same conditional rules as Store, applied on update.

The Form Request `authorize()` returns `true` because the controller performs the Policy check; the Form Request only validates data shape and conditional rules.

---

## Models, relationships, migration, constraints, and indexes

### `User` model additions (or new table)

If the `status` and `suspension_reason` columns are added to the existing `users` table:

- `$casts = ['status' => 'string', 'suspension_reason' => 'string']` (or `status` could be a custom cast enum).
- Add a `scopeActive` / `scopePending` / `scopeSuspended` if useful.
- No SoftDeletes; the status field replaces the need for deletion flags.

If a separate `employer_profiles` table is decided:

- Migration creates `employer_profiles`: `user_id` unique FK → `users.id`, `status` string, `suspension_reason` text, `suspended_at` timestamp, `created_at`, `updated_at`.
- Model `EmployerProfile` with `belongsTo(User)`.
- No SoftDeletes.

**Assumption:** Columns `status` and `suspension_reason` are added to the `users` table (simpler, matches the project's incremental approach). The `status` column is a string with values `pending`, `approved`, `suspended`; `suspension_reason` is nullable text.

### Indexes

- Index on `users.status` for the employer-list query ordering/filtering.
- Index on `users.suspension_reason` is not needed (small table, full-text search not required).
- No new unique constraints beyond what the existing migration provides.

---

## Query/data payload shape, eager loading, and pagination

### Employers index

- Authorize `viewAny`.
- Validate `search` as `nullable|string|max:100`.
- Trim and LIKE-escape the search; prefix match on `name` OR `email`.
- Query:
    - `whereHas('companies', fn ($q) => $q->where('status', 'approved'))` — optional: show only employers with approved companies (or show all; the spec assumes all employers in the list regardless of company status, since the admin may want to see pending ones too).
    - `when(search, ...)`.
    - `orderBy('status')->orderBy('name')->orderBy('id')`. (Status sort: pending → approved → suspended, using a `case` expression or alphabetical if the values are ordered in the enum.)
    - `paginate(25)->withQueryString()`.
- `through()` projection:
    - `id`, `name`, `email`, `status`, `suspension_reason` (truncated to 50 chars in UI), `companies_count`, `created_at`.
- Props: `employers`, `filters: { search }`, `canManageEmployers`.
- `withCount('companies')` avoids N+1 for the job/company count. No relationship rows are needed.

### Employer detail

- Authorize `view` on the user.
- Query the user with `with('company')` if the user has one.
- Projection: `id`, `name`, `email`, `status`, `suspension_reason`, `company_id`, `created_at`, `updated_at`.
- Props: `employer`, `canManageEmployers`.

---

## Service/Repository decision

**No Service and no Repository.**

Reasons:

- Each resource is a status-transition CRUD on the `users` table (or equivalent). No multi-step transaction, external integration, or reusable query beyond the index query justifies a service layer.
- The only non-trivial logic is the conditional `suspension_reason` presence rule, which belongs in the Form Request's `rules()` method, not a service.
- The suspend/reactivate flow is one status change at a time; no complex domain workflow exists that requires a service.
- This matches the Feature 5–8 decision and the `AGENTS.md` rule against adding layers for ceremony.

A Form Request with conditional `rules()` is the only new abstraction and is justified by the `suspension_reason` requirement across multiple actions (suspend vs. approve/reject).

---

## React/Inertia frontend

### New pages

1. `resources/js/pages/admin/employers/index.tsx` — Employer list page.
2. No separate "detail" page is needed as a standalone Inertia route; the admin views details inline on the list or via a show route that renders the same component with different props.

### Employers page

Mirror `resources/js/pages/admin/experience-levels/index.tsx`:

- `Head` title "Employer Account Management".
- `SplitLayout` with create/edit form on the left (for admin actions: approve, reject, suspend, reactivate) and searchable paginated table on the right.
- Form fields for admin actions:
    - Status `Select` (pending, approved, suspended).
    - Suspension reason `Textarea` (shown only when status is suspended; required when switching to suspended).
- `useForm<{ status: string; suspension_reason: string | null }>`.
- Wayfinder actions from `EmployerController`.
- Edit state via `useState`; cancel resets form and errors; success toast; processing disables controls.
- Table columns: Employer (name + email), Email, Status badge, Company count, Suspension reason (truncated), Actions.
- If status is `suspended`, the reason is shown in a muted badge; if no reason (should not happen), explain via helper text.
- Empty state: "No employers found."
- View-only panel when `canManageEmployers` is false.
- Active/inactive badges use the established emerald/muted treatment.

### Navigation

Add to `resources/js/lib/navigation.ts`:

- "Employers" → `admin.employers.index()`, icon `Users`, `requiredPermissions: ['admin.access', 'employers.view']`.

The navigation is presentation only; direct requests remain server-authorized.

### TypeScript types

```ts
type Employer = {
    id: number;
    name: string;
    email: string;
    status: 'pending' | 'approved' | 'suspended';
    suspension_reason: string | null;
    companies_count: number;
    created_at: string;
};

type EmployerForm = {
    status: 'pending' | 'approved' | 'suspended';
    suspension_reason: string | null;
};
```

Use `PaginatedData<Employer>` from `@/types/authorization`. No `any`.

---

## Accessibility, empty, loading, and error states

- Every form control has a visible `<Label htmlFor>`; helper text and errors use `aria-describedby` and `aria-invalid`.
- `InputError` remains `role="alert"`.
- The employers table uses `<th scope="col">`, a clear empty row, and action buttons with `aria-label`/`title`.
- Suspension reason helper text explains why the field is required when status is switched to suspended.
- Delete/suspend buttons that are contextually disabled explain why via `title` and adjacent helper text.
- Search and filter forms are real `<form onSubmit={...}>` elements; submit buttons are disabled while `processing`/searching and show a spinner.
- `Pagination` provides `aria-label="Pagination"`, `aria-current="page"`, and `rel="prev"/"next"`.
- Loading: Inertia progress plus search spinner; no full-page skeleton is required for these small admin tables.
- Errors:
    - Field errors render inline via `InputError`.
    - Blocked action (e.g., suspend without reason) shows an error toast and leaves the row unchanged.
    - Successful writes show success toasts.
    - 403/404 are handled by the framework; no custom error page is required.
- Empty:
    - No employers: clear empty row and a prompt to (if applicable) check permissions.
    - No suspension reason: not applicable; the reason is always present when status is suspended.
- Responsive: tables scroll horizontally in a bordered container; the split layout stacks below `lg`.

---

## Test matrix

Use Pest, `RefreshDatabase`, and `InteractsWithAuthorizationCatalogue`, matching existing feature tests. Guest, non-admin, view-only admin, and manage-permission cases.

### `tests/Feature/Admin/EmployerTest.php`

- Admin with permission sees the index with status badges, company counts, and `canManageEmployers`.
- Index listing orders by status, name, id.
- Create happy path: approve a pending employer with defaults.
- Create happy path: reject a pending employer.
- Create happy path: suspend an approved employer with a reason.
- Create happy path: reactivate a suspended employer.
- Update: toggling status from approved → suspended requires a reason; update without reason is rejected.
- Update: reactivating suspended → approved clears the reason.
- Duplicate name/email on create returns field errors (if uniqueness is enforced; the spec assumes email uniqueness already exists from the auth setup, so this tests name if a separate check is needed).
- Invalid input: missing status, bad status value, suspension reason too long, suspension reason missing when status is suspended.
- Non-admin (job seeker) gets 403 on index, store, update, suspend, reactivate.
- Guest is redirected to login on index.
- View-only admin (`admin.access` + `employers.view`) sees the page but gets 403 on all writes.
- Search matches name prefix, email prefix, and empty state.
- Ordering is `status`, `name`, `id`.
- Suspended employer cannot perform disallowed actions (form submission denied, 403).
- Approved employer can proceed to allowed actions.
- Rejected employer account is locked.

### Authorization catalogue regression

- Update `AuthorizationCatalogueTest`:
    - Exact permission count 21 (or 22, depending on counting method).
    - Reseeding count adjusted accordingly.
    - All new permissions are `is_system` and granted to `platform_admin` only.
    - Employer and job seeker roles still have zero permissions (they are not granted these new permissions).

### Frontend/build regression

- `npm run types:check` passes.
- `npm run check` passes.
- `npm run build` passes and regenerates Wayfinder actions for the new controller.
- Manual/browser checks (not automated in this feature): keyboard navigation, select behavior, table overflow, inline errors, toast feedback, suspension reason helper text visibility.

---

## Security and performance risks

### Security

- **Authorization:** every route has middleware plus a Policy check; direct requests are covered. View-only admins cannot write.
- **Mass assignment:** explicit `$fillable` on User (or no new model); Form Request data accessors only write the validated fields (`status`, `suspension_reason`).
- **Client-controlled IDs:** `user_id` comes from the route parameter, which is `findOrFail`ed; the controller verifies the authenticated user is an admin before any status change.
- **Suspension reason injection:** reason is validated as `min:1` string; it is not used in SQL wildcards or file paths. It is stored as plain text for display only.
- **Status transition validation:** the Form Request enforces that `suspension_reason` is present when status is `suspended` and absent when `approved`/`rejected`; the controller also checks the current status before allowing the transition.
- **Data exposure:** only configuration fields are sent to Inertia; no user passwords, session secrets, resume contents, or private application data is included.
- **CSRF/session:** routes live in the existing `web`/`auth` boundary.
- **Permission deployment:** new permissions require rerunning `AuthorizationSeeder`; otherwise admins receive 403. This is an operational risk, not a code bypass.
- **Suspension does not delete:** the company record, jobs, and applications are preserved; only the user's ability to perform employer actions is blocked. This prevents data loss and orphaned records.

### Performance

- Employers list paginates at 25; no unbounded endpoint data.
- `withCount('companies')` and status/eager-loading prevent N+1 queries.
- The `status` index on `users` is a small table; no speculative search indexes are added.
- Status transition writes are low-volume admin actions; no performance concern.
- Race condition: two concurrent admin writes could theoretically change the status between validation and update. The Form Request + controller atomic write (single update query) handles this at the database level; the final state is the one committed last. Given admin-only, low-concurrency writes, this is accepted and documented.

### Deferred/forward-looking risks

- Feature 11 (Employer onboarding) must integrate with this feature: a pending employer cannot publish jobs until approved; the status flow is pending → approved → (suspended if needed).
- Feature 15 (Employer dashboard) must check the employer's status before allowing job creation/editing; the Policy or a `scopeActiveEmployer` guard is the right place.
- Feature 25 (In-app notifications) should create a notification when an employer is suspended or reactivated, so the employer sees the status change in their in-app center.
- Feature 31 (Administrative audit log) should write entries for suspension and reactivation events once the feature exists.
- If the `status` column is on the `users` table, ensure it does not conflict with any future user role-expansion plans; the three values are intentionally narrow.

---

## Rollout and compatibility

1. Create migration adding `status` string column and `suspension_reason` text column to the `users` table (or create `employer_profiles` table if decided otherwise).
2. Run `php artisan db:seed --class=AuthorizationSeeder` to create the two new permissions and attach them to `platform_admin`.
3. Deploy the routes/pages after the seeder has run so existing administrators do not receive 403.
4. No existing table is modified beyond the `users` column addition. No Feature 5–8 data is changed.
5. The feature is additive and can be rolled back by removing the two new columns from `users` (or dropping the `employer_profiles` table) after the routes/pages are removed. Do not roll back in production without confirming no employer accounts are currently suspended.

---

## Ordered implementation steps

1. Add `EmployersView` and `EmployersManage` enum cases to `app/Enums/PermissionSlug.php`.
2. Add both to the `platformAdminPermissionSlugs` allowlist in `database/seeders/AuthorizationSeeder.php`.
3. Create migration adding `status` column (string) and `suspension_reason` column (nullable text) to the `users` table.
4. Run `php artisan migrate --force` to add the columns.
5. Run `php artisan db:seed --class=AuthorizationSeeder` to seed the new permissions and attach to `platform_admin`.
6. Add `EmployerPolicy` at `app/Policies/EmployerPolicy.php` with `viewAny`, `create`, `update`, `delete` methods mapped to `employers.view` / `employers.manage`.
7. Add `StoreEmployerRequest` and `UpdateEmployerRequest` at `app/Http/Requests/Admin/StoreUpdateEmployerRequest.php` (or separate files) with conditional `rules()` for `suspension_reason` presence.
8. Add `EmployerController` at `app/Http/Controllers/Admin/EmployerController.php` with `index`, `show`, `store` (approve), updated store/reject, `update` (suspend/reactivate), and appropriate `authorize()` calls.
9. Add both route groups to `routes/admin.php` following the existing pattern.
10. Build the Inertia page at `resources/js/pages/admin/employers/index.tsx` and add the navigation entry to `resources/js/lib/navigation.ts`.
11. Add `tests/Feature/Admin/EmployerTest.php` covering the matrix above.
12. Run targeted tests, full suite, frontend build/type/lint checks, Pint, PHPStan, and route-list inspection; fix failures and report actual results.

---

## Verification commands

Run from the project root after implementation:

```text
php artisan test tests/Feature/Admin/EmployerTest.php
php artisan test tests/Feature/Authorization/AuthorizationCatalogueTest.php
php artisan test
npm run build
npm run types:check
npm run check
vendor/bin/pint --test
vendor/bin/phpstan analyse --no-progress
php artisan route:list --path=admin/employers -v
```

Do not claim any command passed unless its output was inspected. If a command cannot run, record the exact blocker.

---

## Approval gate

Status: **DRAFT**. This spec is submitted for architect and user review. Please verify that the design decisions, acceptance criteria, and implementation sequence match the product roadmap and existing conventions before approving. Do not proceed to implementation until the approval gate is explicit.
