---
name: feature-31-administrative-audit-log
description: Specification for Feature 31 — Administrative audit log
metadata:
    type: project
    status: IMPLEMENTED
    created: 2026-11-11
    approved: 2026-11-11
    implemented: 2026-11-11
---

# Feature 31 — Administrative audit log

## 1. Goal / non-goals

**Goal:** Record important security and administrative events as an append-only, queryable trail so platform administrators can answer "who did what, to which target, when, and from where" during troubleshooting and accountability review. Entries are written by server-side code at the moment a sensitive action succeeds, exposed only to a dedicated admin permission, and readable through a paginated/filterable admin view.

**In scope:**

- `audit_logs` table + `App\Models\AuditLog` (append-only, no update/delete surface).
- One new permission `audit_logs.view` ("View audit logs"), granted to `platform_admin` only.
- A closed action catalogue (`App\Enums\AuditAction`) and a closed target-type vocabulary (`App\Enums\AuditTargetType`).
- `App\Services\Audit\AuditLogService` — the single write path, with metadata redaction, request-correlation capture, and actor/target identification.
- Auditing of the sensitive surfaces that exist today: authorization changes (Feature 3), employer review/suspension (Feature 10/11), job moderation (Feature 14), company hiring-team membership (Feature 28), report moderation (Feature 30), account deletion, and successful authentication events.
- Admin read surface: `admin/audit-logs` index (filters, search, pagination) and `admin/audit-logs/{auditLog}` detail.

**Non-goals:**

- No generic request/response logging middleware and no request-payload capture (explicitly forbidden by the roadmap rules).
- No write/edit/delete API for audit rows — the trail is read-only for everyone, including platform administrators.
- No audit of candidate profile edits, saved jobs, saved searches, application answers, interview scheduling details, or public job browsing. These are product actions, not administrative/security events.
- No duplicate audit rows for application status transitions (already append-only in `job_application_status_histories` — see A5).
- No failed-login capture (see A4), no IP reputation/rate-limit analytics, no SIEM/export integration, no email digests.
- No retention/pruning command in this increment (see A8) — flagged as an operational follow-up, not built here.
- No new frontend design system; the admin page reuses the existing admin table/filter/Pagination patterns.

## 2. User flows and acceptance criteria

### Flows

1. **Admin browses the trail:** A user holding `admin.access` + `audit_logs.view` opens `admin/audit-logs`. The newest entries are listed first with actor, action, target, timestamp, IP and request id. The admin filters by action, target type, actor, free text, and a date range; filters are server-side and survive pagination via the query string.
2. **Admin inspects one entry:** The admin opens a single entry to read the full metadata (e.g. which permissions were added to a role, which roles were removed from a user, the reason an employer was suspended). Metadata is rendered as a key/value list, never as a raw payload dump.
3. **Admin investigates an incident:** The admin filters to one action (for example `employer.suspended`) and a date window, then reads the actor and target of each entry. Entries identify the actor and the target even if the target row was later deleted.
4. **Administrator action is recorded automatically:** An administrator approves an employer, moderates a job, resolves a report, changes a role, or edits a company team. The action produces an audit entry **without the administrator doing anything extra**; there is no "write a log note" UI.
5. **Audit access is itself recorded:** Opening the audit log index or an entry writes an `audit_log.viewed` entry so reads are attributable.

### Acceptance criteria

- Every action in the catalogue (§10) writes exactly one audit entry on success, and **no** entry is written when the action fails or is denied (authorization failures, validation failures and invalid transitions write nothing).
- Each entry identifies: **actor** (nullable `actor_id` FK + `actor_email` snapshot), **action** slug, **target type** + **target id** + **target label** snapshot, **timestamp**, **request correlation id**, and **source IP** where a web request is in scope.
- Audit rows are **append-only**: no route, controller, policy, or mass-assignment path can update or delete them; model-level guards reject updates and deletes.
- Only users holding `audit_logs.view` (and `admin.access`) can list or read entries; guests are redirected to login, authenticated non-admins and admins without the permission receive 403 — enforced by **both** route middleware and Policy.
- The list is server-paginated with bounded page size; filters and search are applied in SQL; no unbounded loading.
- Sensitive data is never stored: passwords, session identifiers, tokens, resume contents, cover letters, 2FA secrets and raw request payloads are not accepted by the write path and are stripped defensively if present in metadata.
- Entries survive the deletion of the actor or target account (snapshot columns), so accountability is not lost.
- The trail is not exposed to employers, job seekers, or any public surface.

## 3. Assumptions (labeled, non-blocking) and open questions

- **A1 — Explicit action catalogue, no blanket logging.** Auditing happens only where the application explicitly calls the audit service. Generic request logging is excluded (roadmap rule: "Avoid logging entire request payloads"), and Eloquent observers are excluded because they would also capture incidental writes such as `updated_at` touches.
- **A2 — Explicit service calls, no event/listener infrastructure for domain actions.** The repo has no event/listener/observer pattern (`app/Events`, `app/Listeners`, `app/Observers` do not exist). `NotificationService` is called explicitly from controllers/services; the audit service follows that same precedent. The single exception is authentication, which uses Laravel core auth events (§10) because those are the only source of login/logout facts.
- **A3 — One new permission.** `audit_logs.view` is granted to `platform_admin` only, mirroring how `jobs.moderate` and `reports.moderate` are scoped. There is no separate "manage" permission because the trail cannot be mutated through the application.
- **A4 — Failed login attempts are not captured in this increment.** A failed login is an unauthenticated write; capturing every attempt gives an attacker a cheap way to flood the audit table (write amplification) and forces an IP/e-mail capture policy that the roadmap does not define. Successful login/logout/password reset are audited. Capture of failures is a candidate follow-up with its own throttling design.
- **A5 — Application status transitions are not duplicated into the audit log.** `job_application_status_histories` already stores actor, from/to status, reason and timestamp, is append-only in practice, and is shown to the candidate. Writing the same event twice would create two sources of truth. If a unified admin view is later required, it can union both tables read-side.
- **A6 — Actor identity survives account deletion.** `actor_id` is a nullable FK with `nullOnDelete()` (a deleted user must not block or cascade-delete the trail), and `actor_email` is stored as a snapshot so the trail still names the actor. This is deliberate, minimal personal data kept in an admin-only table.
- **A7 — IP address is stored; user agent and payload are not.** `ip_address` comes from `$request->ip()` and is the proxy address unless trusted proxies are configured (documented caveat). User agent, headers and payload are never stored.
- **A8 — No retention/pruning in this increment.** The table grows append-only. A pruning command (`audit-logs:prune --days=N`) is a documented operational follow-up; it is deliberately not built now because a retention window is a product/compliance decision.
- **A9 — Audit reads are audited.** Opening the index or a detail page writes an `audit_log.viewed` entry (roadmap: "Access to logs is restricted and logged where appropriate"). It adds one row per visit; admins can filter the action out.
- **A10 — Audit failure semantics differ by context.** Inside a business transaction an audit write is **fail-closed**: a failed audit insert rolls back the business action, because a compliance record that silently disappears is worse than a failed action. For authentication events (no business transaction) the write is fail-soft and logs an error, so a logging problem cannot break login. This is the opposite of `NotificationService`, which is deliberately fail-soft.
- **A11 — Table name `audit_logs`, model `App\Models\AuditLog`, route prefix `admin/audit-logs`.** No name collisions exist in the current schema; "audit" is not used by any current table or route.

**Open questions for the reviewer (defaults are stated so implementation can proceed if unanswered):**

1. Should failed logins be captured (A4 default: **no**)?
2. Should application status transitions be duplicated into the audit log (A5 default: **no**)?
3. Should a retention/pruning policy exist (A8 default: **no, deferred**)?
4. Should audit-log reads be recorded (A9 default: **yes**)?

## 4. Routes and access boundary

Session auth (Feature 2) + `permission:` middleware (Feature 3, AND semantics per `docs/authorization.md:110`) + `routes/admin.php` group style.

| Method | URI                           | Name                     | Middleware                                                      |
| ------ | ----------------------------- | ------------------------ | --------------------------------------------------------------- |
| GET    | `admin/audit-logs`            | `admin.audit-logs.index` | `auth`, `permission:admin.access`, `permission:audit_logs.view` |
| GET    | `admin/audit-logs/{auditLog}` | `admin.audit-logs.show`  | as above                                                        |

- The group lives in `routes/admin.php` (`require`d from `routes/web.php:223`), mirroring the existing `reports` group (`routes/admin.php:178`) and the `jobs` group (`:169`).
- **No write routes exist** for audit logs — no POST/PUT/PATCH/DELETE, no export endpoint. This is the primary "unauthorized users cannot alter logs" control; model guards are the second layer.
- Controller: `App\Http\Controllers\Admin\AuditLogController` (index + show only). Route-model binding resolves `{auditLog}`; an unknown id yields 404.
- A new middleware `App\Http\Middleware\AssignRequestId` is appended to the `web` group in `bootstrap/app.php` (next to `HandleAppearance` / `HandleInertiaRequests`). It generates a server-side UUID per request, stores it in `$request->attributes`, and echoes it as the `X-Request-Id` response header. It never trusts a client-supplied request id. Console/queue contexts have no request id → the column is nullable.

## 5. Permission and Policy design

- New `App\Enums\PermissionSlug` case: `AuditLogsView = 'audit_logs.view'`.
- `database/seeders/AuthorizationSeeder.php`:
    - add `'audit_logs.view' => 'View audit logs'` to the `$permissions` map (display names are globally unique — `permissions.name` has a unique index);
    - add `PermissionSlug::AuditLogsView->value` to `$platformAdminPermissionSlugs` (the seeder's own comment requires new slugs to be mapped there deliberately).
    - The seeder stays idempotent and uses `syncWithoutDetaching`, so custom grants survive.
- `tests/Feature/Authorization/AuthorizationCatalogueTest.php` count assertions must be updated: `23 → 24` (lines 44 and 47) and `24 → 25` (line 98, seeded catalogue + one custom permission). The permission set is asserted against `PermissionSlug::cases()`, so no other change is needed there.
- `docs/authorization.md` must be updated: add the permission row to the catalogue table and note the new entry in the "Adding a permission" section.

**New `App\Policies\AuditLogPolicy`:**

| Action                             | Result                         | Basis                                 |
| ---------------------------------- | ------------------------------ | ------------------------------------- |
| `viewAny(User $actor)`             | `hasPermission(AuditLogsView)` | Same shape as `ReportPolicy::viewAny` |
| `view(User $actor, AuditLog $log)` | `hasPermission(AuditLogsView)` | Same shape as `ReportPolicy::view`    |

- There are deliberately **no** `create` / `update` / `delete` policy methods: no code path is authorized to write through the model, and the absence of a method means `Gate` denies it.
- Controllers call `$this->authorize('viewAny', AuditLog::class)` / `$this->authorize('view', $auditLog)` **before** reading, in addition to the route middleware (defense in depth, per `AGENTS.md` §2).
- The `FormRequest::authorize()` for the index returns `true` because the controller Policy performs the actual authorization (allowed by `AGENTS.md` §2.3).

## 6. Form Requests and validation

**`App\Http\Requests\Admin\IndexAuditLogRequest`** (read-only filter input):

- `authorize()` → `true` (controller Policy is authoritative).
- Rules:
    - `action` → `nullable|string`, `Rule::in(AuditAction::values())`.
    - `target_type` → `nullable|string`, `Rule::in(AuditTargetType::values())`.
    - `actor_id` → `nullable|integer|exists:users,id`.
    - `search` → `nullable|string|max:100`.
    - `from` → `nullable|date_format:Y-m-d`; `to` → `nullable|date_format:Y-m-d`; after-validation check `from <= to`.
- `prepareForValidation()` trims `search` and normalizes empty strings to `null` (repo pattern).
- Invalid filter values are rejected rather than silently coerced; the controller additionally whitelists filter values before building the query (defense in depth, mirroring `Admin\ReportController:29-32`).

No write Form Request exists because there are no write endpoints. All audit writes go through the service, never through request input.

## 7. Data model, migration, constraints, indexes

**Migration:** `database/migrations/2026_11_13_000000_create_audit_logs_table.php` (new migration; nothing existing is modified).

```
Schema::create('audit_logs'):
  id
  actor_id         FK users, nullable, nullOnDelete()      -- actor may be deleted; trail must survive
  actor_email      string(255) nullable                     -- actor snapshot (A6)
  action           string(64)                              -- AuditAction slug
  target_type      string(64)                              -- AuditTargetType slug
  target_id        unsignedBigInteger nullable             -- numeric target id when applicable
  target_label     string(255) nullable                    -- target snapshot (e.g. job title, role slug)
  metadata         json nullable                          -- safe, redacted, allow-listed metadata
  ip_address       string(45) nullable                     -- request source (A7)
  request_id       string(36) nullable                     -- per-request correlation UUID
  occurred_at      timestamp                              -- no updated_at: rows are never mutated

  index(['action','occurred_at'])   -- action filter + default sort
  index(['target_type','target_id'])-- target drill-down
  index(['actor_id','occurred_at']) -- actor drill-down
  index('occurred_at')             -- default sort + date-range filter
```

Notes:

- **No `updated_at` and no `timestamps()`** — the table is append-only, so a mutation timestamp would be misleading. The model sets `$timestamps = false` and writes `occurred_at` explicitly.
- The `actor_id` FK index is covered by the `(actor_id, occurred_at)` composite (leftmost prefix), so no redundant standalone index is added. MySQL requires an index on FK columns; the composite satisfies it.
- **No driver-specific SQL.** Unlike the `reports` migration, this table needs no CHECK constraint, no partial index and no `ALTER TABLE`, so it is identical on SQLite (tests) and MySQL (baseline target). No MySQL-only code path is left unexercised.
- No soft deletes: an audit row is never deleted, so `deleted_at` would be meaningless. Deletion is instead **prevented** (below).

**Model `App\Models\AuditLog`:**

- `$fillable` = `['actor_id','actor_email','action','target_type','target_id','target_label','metadata','ip_address','request_id','occurred_at']` — narrow and explicit; the service is the only writer.
- `casts()`: `metadata => 'array'`, `occurred_at => 'datetime'`.
- `$timestamps = false`.
- Relation `actor(): BelongsTo(User, 'actor_id')` (nullable) — used for the current display name; `actor_email` remains the durable identifier.
- Append-only guard: new trait `App\Models\Concerns\AppendOnly` with `static::updating()` and `static::deleting()` hooks that throw `App\Exceptions\AuditLogImmutableException`. This mirrors the existing `ProtectsSystemDefinition` pattern (`app/Models/Concerns/ProtectsSystemDefinition.php:10-36`). As documented for system definitions (`docs/authorization.md:81`), query-builder deletes/truncates bypass model events — the absence of any write route plus the model guard is the enforced boundary, and raw SQL remains an operator-level action outside the application.

**Enums:**

- `App\Enums\AuditAction` — string-backed, with `values()` and `label()` helpers (mirrors `ReportStatus` / `ReportReason`). One case per catalogue entry in §10.
- `App\Enums\AuditTargetType` — `user`, `role`, `permission`, `job_posting`, `company`, `company_member`, `report`, `audit_log`, with `values()` and `label()`.

## 8. Query, payload, and pagination plan

**Admin index (`admin/audit-logs`):**

- Default order: `occurred_at DESC, id DESC` (newest first). Ties broken by `id` for deterministic pagination.
- Page size **25** (`paginate(25)->withQueryString()`), matching the other admin lists (`EmployerController:78`, `RoleController:51`).
- Filters applied in SQL:
    - `action` → `where('action', $value)`;
    - `target_type` → `where('target_type', $value)`;
    - `actor_id` → `where('actor_id', $value)`;
    - `from`/`to` → `where('occurred_at', '>=', from)` / `where('occurred_at', '<=', to_end_of_day)`;
    - `search` → prefix `LIKE` on `actor_email` **or** `target_label`, with `%`/`_`/`\` escaped exactly like `UserRoleController::escapeLike` (`app/Http/Controllers/Admin/UserRoleController.php:174-177`). The search is **prefix-only**, matching the documented house rule for admin search (`docs/specs/custom-authorization-foundation.md:238`).
- **No joins and no per-row queries.** The only eager load is `actor:id,name` (one extra query for the page). `metadata` is **excluded from the list payload** (it can be large); it is only returned on the detail page.
- Column selection is explicit (`select([...])`), never `SELECT *` — the list needs `id, actor_id, actor_email, action, target_type, target_id, target_label, ip_address, request_id, occurred_at`.
- `metadata` is read only on the detail route (`findOrFail` via route-model binding).

**Payloads (Inertia props):**

- `admin/audit-logs/index` → `{ auditLogs: PaginatedData<AuditLogListItem>, filters: {...}, actions: [{value,label}], targetTypes: [{value,label}] }`.
  `AuditLogListItem` = `{ id, action, action_label, target_type, target_type_label, target_id, target_label, actor: { id, name } | null, actor_email, ip_address, request_id, occurred_at }`.
- `admin/audit-logs/show` → `{ auditLog: AuditLogDetail }` where `AuditLogDetail` adds `metadata: Array<{ key, value }>` (flattened to a renderable key/value list; values are strings/booleans/numbers only, produced by the service).
- Timestamps are ISO-8601 strings (`->toIso8601String()`), matching every other admin payload in the repo.

**TypeScript types:** new `resources/js/types/audit-log.ts` with `AuditActionSlug` / `AuditTargetTypeSlug` unions mirroring the PHP enums, `AuditLogListItem`, `AuditLogDetail`, `AdminAuditLogsIndexProps`, `AdminAuditLogShowProps`, filter unions (`X | 'all'`) and `xxxLabel()` helpers with fallback — the exact template of `resources/js/types/report.ts`. Export it from the `resources/js/types/index.ts` barrel.

## 9. Service / Repository decision

**A Service, no Repository.**

- `App\Services\Audit\AuditLogService` is **required** (not ceremony): it is the single choke point that (a) maps a model to an allowed target type + label, (b) applies metadata redaction and size caps, (c) captures actor/ip/request id, and (d) enforces the write policy. Ten-plus call sites across controllers, services, a command and a listener must share exactly these rules; duplicating them per call site would guarantee drift and leaks.
- **No Repository / query object.** The admin list query is a single bounded query in the controller, exactly like `Admin\ReportController:34-61` and `Admin\UserRoleController:41-53`. Extracting it would add an abstraction without reuse.
- **No events/listeners for domain actions** (A2). The only listener is for authentication events, because login/logout facts exist only as framework events.

**`AuditLogService` contract:**

```php
public function record(AuditAction $action, Model $target, array $metadata = []): AuditLog
public function recordWithoutModel(AuditAction $action, string $targetType, ?int $targetId = null, ?string $targetLabel = null, array $metadata = []): AuditLog
```

- `record()` maps the target model through an explicit `match` on model class → target type (`User → user`, `Role → role`, `Permission → permission`, `Job → job_posting`, `Company → company`, `CompanyMember → company_member`, `Report → report`) and derives a short label (`email`, `slug`, `title`, `name`). An unsupported model class throws — the target vocabulary cannot grow by accident.
- `recordWithoutModel()` is used for targets with no row (e.g. `audit_log` index views).
- **Actor resolution:** `auth()->user()` when present, otherwise `null` (console/queue/system context). The actor's email is snapshotted from the authenticated user.
- **Request context:** `request_id` from `$request->attributes->get('request_id')` (set by `AssignRequestId`), `ip_address` from `$request->ip()`. Both nullable.
- **Metadata rules (redaction):**
    - the service never accepts a `Request` object and never accepts a raw payload — callers pass explicit key/value pairs only;
    - a deny-list of key substrings (case-insensitive): `password`, `secret`, `token`, `api_key`, `session`, `cookie`, `remember_token`, `authorization`, `credential`, `resume`, `cover_letter`, `two_factor`, `otp`, `recovery_code`, `file_path` — matching keys are **dropped entirely** (not masked);
    - values are limited to scalars/arrays; strings truncated to 200 characters; arrays capped at 20 entries; nesting capped at 3 levels; objects/resources rejected;
    - the result is stored as JSON. Empty metadata is stored as `null`.
- **Write semantics:** a plain `insert` inside the caller's transaction; no fail-soft wrapper inside business transactions (A10). Fail-soft + `Log::error` is used **only** for the authentication-event path.

## 10. Audited action catalogue and call sites

One entry per successful action. **No entry is written for a denied/failed action.** Metadata values must be safe to display to an administrator (A6/A7 rules still apply).

| Action slug                                                        | Source (call site)                                                                                   | Target type      | Metadata (allow-list)                                          |
| ------------------------------------------------------------------ | ---------------------------------------------------------------------------------------------------- | ---------------- | -------------------------------------------------------------- |
| `employer.approved`                                                | `App\Services\Employer\EmployerOnboardingService::approve()` (inside its existing `DB::transaction`) | `user`           | `previous_status`, `granted_role`                              |
| `employer.rejected`                                                | `EmployerOnboardingService::reject()`                                                                | `user`           | `previous_status`, `reason`                                    |
| `employer.suspended`                                               | `EmployerOnboardingService::suspend()`                                                               | `user`           | `previous_status`, `reason`                                    |
| `employer.reactivated`                                             | `EmployerOnboardingService::reactivate()`                                                            | `user`           | `previous_status`                                              |
| `job.approved`                                                     | `Admin\JobModerationController::approve()`                                                           | `job_posting`    | `previous_status`                                              |
| `job.rejected`                                                     | `Admin\JobModerationController::reject()`                                                            | `job_posting`    | `previous_status`, `rejection_reason`                          |
| `job.closed`                                                       | `Admin\JobModerationController::close()`                                                             | `job_posting`    | `previous_status`                                              |
| `report.investigated`                                              | `Admin\ReportController::investigate()`                                                              | `report`         | `previous_status`                                              |
| `report.resolved`                                                  | `Admin\ReportController::resolve()` (inside its existing `DB::transaction`)                          | `report`         | `previous_status`, `resolution_action`                         |
| `report.dismissed`                                                 | `Admin\ReportController::dismiss()` (inside its existing `DB::transaction`)                          | `report`         | `previous_status`                                              |
| `user.roles_updated`                                               | `Admin\UserRoleController::update()`                                                                 | `user`           | `roles_added[]`, `roles_removed[]` (role slugs only)           |
| `user.roles_updated`                                               | `App\Console\Commands\AssignRoleCommand::handle()` (console operator path)                           | `user`           | `source: console`, `roles_added[]`                             |
| `role.created`                                                     | `Admin\RoleController::store()`                                                                      | `role`           | `slug`, `permissions[]`                                        |
| `role.updated`                                                     | `Admin\RoleController::update()`                                                                     | `role`           | `permissions_added[]`, `permissions_removed[]`                 |
| `role.deleted`                                                     | `Admin\RoleController::destroy()`                                                                    | `role`           | `slug`                                                         |
| `permission.created` / `permission.updated` / `permission.deleted` | `Admin\PermissionController::store() / update() / destroy()`                                         | `permission`     | `slug`                                                         |
| `company_member.added`                                             | `Employer\CompanyMemberController::store()`                                                          | `company_member` | `company_id`, `company_name`, `member_user_id`, `company_role` |
| `company_member.role_changed`                                      | `Employer\CompanyMemberController::update()`                                                         | `company_member` | `company_id`, `old_role`, `new_role`                           |
| `company_member.revoked`                                           | `Employer\CompanyMemberController::destroy()`                                                        | `company_member` | `company_id`, `company_role`                                   |
| `account.deleted`                                                  | `Settings\ProfileController::destroy()`                                                              | `user`           | `self_service: true`                                           |
| `auth.logged_in`                                                   | `Illuminate\Auth\Events\Login` listener                                                              | `user`           | `remember: bool`                                               |
| `auth.logged_out`                                                  | `Illuminate\Auth\Events\Logout` listener                                                             | `user`           | —                                                              |
| `auth.password_reset`                                              | `Illuminate\Auth\Events\PasswordReset` listener                                                      | `user`           | —                                                              |
| `audit_log.viewed`                                                 | `Admin\AuditLogController::index()` / `show()`                                                       | `audit_log`      | `scope: index\|show`, `entry_id` (detail only)                 |

Notes:

- The reasons in `employer.rejected` / `employer.suspended` are already persisted in `employer_onboarding_requests.review_note` and `users.suspension_reason`; the audit copy adds no new personal data and is what makes the trail useful for accountability.
- `report.internal_note` is **not** copied into audit metadata: it already lives on the report row, and duplicating free-text notes widens the personal-data surface.
- The automatic employer-role grant performed inside `employer.approved` is recorded as `granted_role` metadata instead of a second `user.roles_updated` row, so one action produces exactly one entry.
- `account.deleted` is written **before** the user row is deleted (inside the existing transaction) so that the snapshot survives the deletion (`actor_id` becomes `null`, `actor_email` remains).

**Adding a new audited action later:** add a case to `AuditAction`, add the target type to `AuditTargetType` if new, call `AuditLogService::record(...)` at the success point inside the business transaction, and add a test asserting the row. No other file needs to change.

## 11. React / Inertia plan

- **Types:** `resources/js/types/audit-log.ts` + export from `resources/js/types/index.ts` (barrel pattern, `export type * from './audit-log';`).
- **`resources/js/pages/admin/audit-logs/index.tsx`** — mirrors `resources/js/pages/admin/reports/index.tsx`:
    - a filter `<form onSubmit>` that calls `router.get(auditLogsIndex.url(), {...filters}, { preserveState: true })` (server-side filtering, query string preserved);
    - filters: action `Select` (from the `actions` prop), target type `Select`, free-text `Input` (actor email / target label), `from`/`to` date `Input`s, and a reset link;
    - a plain `<table>` (the repo has no shadcn `Table` primitive — tables are hand-written Tailwind markup) with columns: When, Actor, Action, Target, IP, Request id; `Badge` for the action;
    - explicit empty state row (`colSpan`) for "No audit entries match this filter." and a distinct "No audit entries yet." state;
    - the shared `Pagination` component (`resources/js/components/pagination.tsx`) wired to `current_page/last_page/per_page/total/prev_page_url/next_page_url`.
- **`resources/js/pages/admin/audit-logs/show.tsx`** — entry summary card (actor, action, target, time, IP, request id) plus a metadata `<dl>` key/value list; link back to the index with the current filters preserved.
- **Navigation:** `resources/js/lib/navigation.ts` — add `{ title: 'Audit Log', href: admin.auditLogs.index(), icon: ScrollText, requiredPermissions: ['admin.access', 'audit_logs.view'] }` after the "Reports" item. The `href` must come from the Wayfinder-generated module (`@/routes/admin/audit-logs`), which is produced by `npm run build` (the generated directory is gitignored).
- **Wayfinder:** the route module is regenerated by the build; `npm run build` is mandatory after the route change, otherwise the TypeScript check fails on the missing import.
- No new component library, no new design tokens (`docs/ui-registry.md` needs no update — the page reuses existing admin table/filter patterns).

## 12. Accessibility and states

- Table headers use `<th scope="col">`; the empty state row uses `colSpan` and is readable as text.
- Every filter control has an associated `<Label>` (existing `ui/label` primitive) and the form submits on Enter.
- The detail page uses a description list (`<dl>`) so key/value pairs are announced in a predictable order; values are rendered as text, never as raw HTML (no `dangerouslySetInnerHTML`), so audit metadata cannot inject markup.
- Focus order follows DOM order (filters → table → pagination); the existing `Pagination` component already provides `aria-current` and `rel="prev"/"next"`.
- Loading/empty/error states: Inertia's default partial-reload/visit behaviour is used (no client-side fetch); an empty result set renders the empty state instead of a blank table; a filter value the server rejected re-renders the form with the previous filters applied.
- Long metadata values wrap and are truncated server-side; the UI never needs a horizontal scroll container for the metadata list (the table itself keeps the existing `overflow-x-auto` pattern).

## 13. Test matrix

Repo conventions: PHPUnit-style classes under `tests/Feature/`, `RefreshDatabase`, `InteractsWithAuthorizationCatalogue` for role/permission seeding, `Inertia\Testing\AssertableInertia` for prop assertions, `Notification::fake()` where relevant.

**`tests/Feature/Audit/AuditLogWritingTest.php`**

1. Employer approve → one `employer.approved` row: actor = admin, target = applicant user, `previous_status` = `pending`, `granted_role` = `employer`.
2. Employer reject → one `employer.rejected` row containing the reason; suspend → `employer.suspended` with reason; reactivate → `employer.reactivated`.
3. Invalid transitions (e.g. approving a non-pending employer) write **no** row.
4. Job approve / reject / close → `job.approved` / `job.rejected` (with `rejection_reason`) / `job.closed`; a job already in a non-reviewable state writes no row.
5. Report investigate / resolve / dismiss → the three report actions, with `resolution_action` on resolve (this is the action `memory.md:45` requires).
6. Role create / update / delete → rows with `permissions_added` / `permissions_removed` / `slug`.
7. Permission create / update / delete → rows with `slug`.
8. User role sync → `user.roles_updated` with `roles_added` / `roles_removed`; the console `auth:assign-role` path writes a row with `actor_id = null` and `source: console`.
9. Company member add / role change / revoke → the three `company_member.*` rows.
10. Account deletion → `account.deleted` row survives the user row deletion (`actor_id` null, `actor_email` still set).
11. Successful login / logout / password reset → `auth.logged_in` / `auth.logged_out` / `auth.password_reset` rows.
12. Denied actions write nothing: a non-admin employer cannot moderate a job (403) and no row exists afterwards.
13. **Redaction:** metadata containing `password`, `remember_token`, `resume_path`, `cover_letter`, `session_id` is dropped; long strings are truncated to 200 chars; arrays are capped; the stored JSON contains none of the denied keys.
14. **Transaction integrity:** a failure after the audited write (e.g. an exception raised later in the same transaction) leaves **no** orphan audit row.
15. **Append-only:** `$log->update([...])` throws `AuditLogImmutableException`; `$log->delete()` throws it as well; the row still exists afterwards.
16. **No noise:** a non-audited action (e.g. updating a candidate profile or saving a job) writes no rows.
17. `audit_log.viewed` is written when the admin opens the index and when they open an entry.

**`tests/Feature/Admin/AuditLogAccessTest.php`**

1. Guest → redirect to `login` (route `auth` middleware).
2. Authenticated job seeker and employer → 403 (route `permission:admin.access`).
3. Admin holding `admin.access` but not `audit_logs.view` → 403 (route `permission:audit_logs.view`).
4. Admin holding `audit_logs.view` → 200; `assertInertia` on the list shape (props `auditLogs`, `filters`, `actions`, `targetTypes`), newest-first ordering, and the absence of `metadata` in the list payload.
5. Filters: `action`, `target_type`, `actor_id`, `from`/`to` and prefix `search` each narrow the result set; unknown filter values are rejected (validation error) and never widen the query.
6. Pagination: 26+ entries paginate at 25 with `withQueryString()` preserving filters in the links.
7. Detail page: 200 with the metadata key/value list; unknown id → 404.
8. **No mutation surface:** `Route::has('admin.audit-logs.update')` / `.destroy` are false, and POST/PUT/DELETE against the audit-log paths do not resolve to a route (assert 404/405).
9. **Permission catalogue:** `audit_logs.view` is seeded, is a system permission, and is granted to `platform_admin` only (`employer` and `job_seeker` hold none) — asserted in `AuthorizationCatalogueTest` alongside the updated counts (23 → 24, 24 → 25).
10. **Tenant isolation:** an employer's company-member audit rows are visible to the admin trail (platform-level accountability) but the trail is never reachable from any employer or candidate route, and no audit props are added to employer/candidate pages.

**Regression:** the whole existing suite must stay green. Current baseline: **544 passed / 3937 assertions**; expected after this feature: ~569+.

## 14. Security and performance risks

- **Personal data in the trail:** `actor_email`, `target_label` and `ip_address` are personal data. Mitigations: admin-only access (`admin.access` + `audit_logs.view`), a closed target vocabulary, no free-text metadata from untrusted users, and no exposure to public/employer/candidate surfaces. Retention is undefined (A8) — flagged as an operational follow-up.
- **Mass assignment / client-controlled fields:** the trail has no write endpoint; `$fillable` is narrow; the service writes explicit values; `actor_id` is never taken from a request.
- **Metadata leakage:** callers pass explicit key/value pairs, the service applies a deny-list, size caps and depth limits. Raw payloads, request bodies, headers and user agents are never passed in.
- **Append-only enforcement is model-level, not database-level:** the guards stop Eloquent updates/deletes; a query-builder delete or truncate bypasses them (same caveat as `docs/authorization.md:81`). The absence of write routes plus the guards is the enforced boundary.
- **Write amplification:** audited actions are authenticated and infrequent (admin/owner actions). Authentication events are bounded by successful credentials. Failed logins are excluded (A4) precisely to avoid an unauthenticated flood. A hard DB-level cap is not added in this increment.
- **Availability vs. compliance:** a failed audit insert rolls back the business action (A10). This can surface a 500 to an administrator; that is the intended trade-off, and the failure is logged.
- **Growth:** the table grows without bound. Indexes keep list queries bounded; a future pruning command is required before long-running production use (A8).
- **Query performance:** the list query is a single bounded query with at most one eager load (`actor`), explicit columns, and no JSON parsing. `metadata` is never selected in the list. Date-range filters use the `occurred_at` index.
- **SQL portability:** no driver-specific SQL, so SQLite (tests) and MySQL (baseline) behave identically — unlike the `reports` migration, there is no MySQL-only CHECK path left unexercised.
- **Proxy/IP correctness:** `$request->ip()` is the proxy address unless trusted proxies are configured; documented, and the value is informational only.
- **Self-audit noise:** `audit_log.viewed` adds a row per visit (A9); filterable by action, and removable if the reviewer prefers not to record reads.

## 15. Implementation sequence

1. **Schema + enums:** migration `2026_11_13_000000_create_audit_logs_table.php`; `app/Enums/AuditAction.php`; `app/Enums/AuditTargetType.php`; `PermissionSlug::AuditLogsView` + `AuthorizationSeeder` (name map **and** `$platformAdminPermissionSlugs`); update `AuthorizationCatalogueTest` counts (23 → 24, 24 → 25); update `docs/authorization.md`.
2. **Model + guards + policy:** `app/Models/AuditLog.php`; `app/Models/Concerns/AppendOnly.php`; `app/Exceptions/AuditLogImmutableException.php`; `app/Policies/AuditLogPolicy.php`.
3. **Write path:** `app/Services/Audit/AuditLogService.php` (target mapping, redaction, actor/ip/request capture); `app/Http/Middleware/AssignRequestId.php` + registration in `bootstrap/app.php`.
4. **Wire the catalogue (§10):** employer onboarding service; job moderation controller; admin report controller; role / permission / user-role controllers; company member controller; profile deletion; `auth:assign-role` command; authentication event listener (`app/Listeners/RecordAuthenticationAuditEvents.php` + registration in `AppServiceProvider::boot()`).
    - Where a transaction already exists, the audit write goes **inside** it; where none exists, the audited write is wrapped in `DB::transaction` so the write and its audit entry are atomic.
5. **Read surface:** `App\Http\Requests\Admin\IndexAuditLogRequest`; `App\Http\Controllers\Admin\AuditLogController` (index + show); routes in `routes/admin.php`.
6. **Frontend:** `resources/js/types/audit-log.ts` (+ barrel); `resources/js/pages/admin/audit-logs/index.tsx`; `resources/js/pages/admin/audit-logs/show.tsx`; nav item in `resources/js/lib/navigation.ts`; **`npm run build`** to regenerate the Wayfinder route module.
7. **Tests:** `tests/Feature/Audit/AuditLogWritingTest.php`, `tests/Feature/Admin/AuditLogAccessTest.php`; then run the §16 gates, fix failures, re-run.
8. **Docs:** update `docs/authorization.md` (permission) and `memory.md` via `/remember save` after the feature is verified.

## 16. Verification commands

- `php artisan test --filter=AuditLog` — the two new suites.
- `php artisan test` — full suite (baseline 544 passed / 3937 assertions; expect ~569+ and no regressions).
- `vendor\bin\pint --test` (equivalently `composer lint:check`) — formatting.
- `php vendor/bin/phpstan analyse --memory-limit=512M --no-progress` (equivalently `composer types:check`) — level 7 Larastan, `database/` is analysed so the migration must be type-clean.
- `npm run types:check` — TypeScript strict check (requires the regenerated Wayfinder module).
- `npm run check` — lint/format check.
- `npm run build` — **required** to regenerate `resources/js/routes/admin/audit-logs` (gitignored) so the frontend compiles.
- `composer ci:check` — the exact CI gate (runs `npm run check`, `npm run types:check`, Pint, PHPStan and the test suite).

## 17. Rollout / compatibility notes

- **Deploy order:** migrate → `php artisan db:seed --class=AuthorizationSeeder` (adds `audit_logs.view` to `platform_admin`) → deploy application code. If the code ships before the seeder runs, admins fail closed with 403 on the audit pages until the seeder is executed (same rule as `docs/authorization.md:108`).
- `resources/js/routes/**` is gitignored and regenerated by the build; CI runs `composer setup` (which includes `npm run build`), so the generated module exists in CI.
- The new middleware is appended to the `web` group only; console and queued contexts are unaffected (`request_id` / `ip_address` stay `null`).
- No existing route, model, migration or public payload changes shape; the only behaviour visible to existing users is the new sidebar item for administrators holding the permission.
- Rolling back: `migrate:rollback` drops `audit_logs` — **destructive**; back up before rolling back on a shared environment (mirrors the warning in `docs/authorization.md:127`).

**Spec status: IMPLEMENTED** — approved by the user on 2026-11-11 and implemented the same day (see §18).

## 18. Implementation notes (2026-11-11)

- **Approval:** the user approved this specification in conversation on 2026-11-11 ("I approve docs/specs/feature-31-administrative-audit-log.md implement it"); implementation followed the §15 sequence.

- **Schema + enums:** `database/migrations/2026_11_13_000000_create_audit_logs_table.php` (driver-neutral: no CHECK, no partial index, no `ALTER TABLE` — identical on SQLite and MySQL); `app/Enums/AuditAction.php` (25 cases + `values()`/`label()`); `app/Enums/AuditTargetType.php` (8 cases + `values()`/`label()`); `PermissionSlug::AuditLogsView` + `AuthorizationSeeder` (name map **and** `$platformAdminPermissionSlugs`); `AuthorizationCatalogueTest` counts 23 → 24 and 24 → 25; `docs/authorization.md` catalogue row added.

- **Model + guards + policy:** `app/Models/AuditLog.php` (narrow `$fillable`, `$timestamps = false`, `metadata`/`occurred_at` casts, `actor()` relation); `app/Models/Concerns/AppendOnly.php` (update/delete guards); `app/Exceptions/AuditLogImmutableException.php`; `app/Policies/AuditLogPolicy.php` (`viewAny`/`view` only).

- **Write path:** `app/Services/Audit/AuditLogService.php`; `app/Http/Middleware/AssignRequestId.php` (server-generated UUID + `X-Request-Id` header, registered in the `web` group in `bootstrap/app.php`).

- **Read surface:** `app/Http/Requests/Admin/IndexAuditLogRequest.php`; `app/Http/Controllers/Admin/AuditLogController.php` (`index` + `show` only); `routes/admin.php` group `admin.audit-logs` (read-only, `permission:admin.access` + `permission:audit_logs.view`).

- **Catalogue wiring:** `EmployerOnboardingService` (4 actions), `Admin\JobModerationController` (3), `Admin\ReportController` (3), `Admin\UserRoleController` (1), `Admin\RoleController` (3), `Admin\PermissionController` (3), `Employer\CompanyMemberController` (3), `Settings\ProfileController` (1), `AssignRoleCommand` (1), `App\Listeners\RecordAuthenticationAuditEvents` (3), `Admin\AuditLogController` (1 — `audit_log.viewed`).

- **Frontend:** `resources/js/types/audit-log.ts` (+ barrel export), `resources/js/pages/admin/audit-logs/index.tsx`, `resources/js/pages/admin/audit-logs/show.tsx`, "Audit Log" nav item in `resources/js/lib/navigation.ts`.

- **Deviations from the written spec (all deliberate):**
    1. `AuditLogService::record()` / `recordWithoutModel()` accept an explicit `?User $actor` argument (defaulting to `auth()->user()`). The `Login` event fires **before** the guard sets the authenticated user, so the listener must pass `$event->user` explicitly.
    2. The authentication listener opts out of Laravel event auto-discovery (`ShouldBeDiscovered::shouldBeDiscovered() === false`) because it is registered explicitly in `AppServiceProvider`; without this, every authentication event was written twice.
    3. `IndexAuditLogRequest` uses `withValidator()` instead of `after()` (the latter is container-resolved and does not bind the validator reliably), and `prepareForValidation()` leaves non-string values untouched so an array filter value is **rejected** instead of silently nulled.
    4. Company-member actions use `recordWithoutModel()` with the company name as the target label (no extra query); the member's user id stays in the metadata allow-list. `record()` therefore maps User/Role/Permission/Job/Company/Report only.
    5. `AuditLog::$timestamps` is `public` — PHP does not allow reducing the visibility of the parent's public property.
    6. `audit_log.viewed` is written **after** the data is read and is fail-soft (`Log::error`), so a logging problem cannot break the admin page.
    7. Atomicity: audited writes that had no transaction are now wrapped in `DB::transaction`; `RoleAssignmentService::syncRoles()` keeps its own transaction and is wrapped by an outer one, so the audit row is atomic with the role sync (nested transactions use savepoints).

- **Tests:** `tests/Feature/Audit/AuditLogWritingTest.php` (26 tests) and `tests/Feature/Admin/AuditLogAccessTest.php` (12 tests) — 38 new tests, 471 assertions. They cover every catalogue action, the "no row on a denied or invalid action" rule, redaction and size caps, transaction rollback, the append-only guards, the "no noise" rule, guest/non-admin/permission-denial access, every filter, pagination, the detail page, the absence of any write route, and the permission catalogue.

- **Verification actually run (final tree):**
    - `php artisan test` → **582 passed / 582, 4411 assertions**, 0 failures (baseline before this feature: 544 / 3940).
    - `vendor\bin\pint --test` → passed.
    - `php vendor/bin/phpstan analyse --memory-limit=512M --no-progress` → passed, 0 errors.
    - `npm run types:check` (`tsc --noEmit`) → passed.
    - `npm run check` → passed (213 files formatted, no lint errors).
    - `npm run build` → succeeded (Wayfinder module `resources/js/routes/admin/audit-logs` regenerated).
    - `composer ci:check` → exit code 0.

- **Not exercised:** live-browser QA of the two admin pages (type-check, lint and production build only); the `PasswordReset` event path (Fortify-driven, not covered by an automated test); `record()` with an unsupported model class and `recordWithoutModel()` with an out-of-vocabulary target type throw by design and are not covered by a test; MySQL-specific behaviour does not exist here because the migration is driver-neutral.

- **Follow-ups (not built, by design):** a retention/pruning command for the append-only table (A8); `resources/js/types/audit-log.ts` must be kept in sync with `App\Enums\AuditAction` / `App\Enums\AuditTargetType` when the catalogue grows (the filter selects themselves are driven by the server catalogue).
