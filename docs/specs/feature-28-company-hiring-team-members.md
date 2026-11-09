---
name: feature-28-company-hiring-team-members
description: Specification for Feature 28 — Company hiring-team members and scoped permissions
metadata:
    type: project
    status: IMPLEMENTED
    created: 2026-11-09
---

# Feature 28 — Company hiring-team members and scoped permissions

## 1. Goal / non-goals

**Goal:** Allow a company's owner to grant other approved-employer users company-scoped access to manage that company's jobs and applicants, without granting platform-administration privileges or cross-company access.

**In scope:**

- `company_members` table recording membership, company-scoped role, and status.
- Add/revoke/change-role of members by an owner.
- Company-scoped role matrix (owner, recruiter, read-only reviewer).
- Policy integration so jobs, applicants, interviews, screening questions, resumes, and company profile use membership-based access instead of `owner_user_id` only.
- Query scoping so members see/manage only companies they belong to.
- Last-owner protection.

**Non-goals:**

- Platform role/permission catalogue changes; company roles are NOT platform roles and never grant `admin.access`.
- Invite/accept email flow (status column reserved; direct active add used for MVP — see Assumptions).
- Ownership transfer process (primary `owner_user_id` remains immutable in this feature).
- Company deletion, analytics, audit log (Feature 31), payments.
- Cross-company sharing of screening-question bank (bank stays per user).

## 2. User-visible behavior and acceptance criteria

- An approved employer who owns a company can open that company's members page and add another approved employer by email with role `recruiter` or `reviewer` (or `owner` for co-owner).
- Added members immediately gain the assigned actions within that company and see the company in their company/job lists.
- A member can be promoted/demoted among the three roles and revoked; revoked members lose access immediately.
- A reviewer can view jobs/applicants/application details/resumes but cannot mutate.
- A recruiter can create/edit/submit/close jobs, view/manage applicants, status transitions, interviews, screening questions for that company.
- Only an owner can manage members and edit the company profile.
- The primary owner (`owner_user_id`) cannot be removed or demoted (no transfer process).
- The last active `owner`-role membership cannot be removed or demoted to a non-owner role.
- Guessed company/job/application IDs never cross tenant boundaries.
- Company membership never grants platform-admin routes/permissions.

**Acceptance criteria (testable):**

1. Owner adds an approved employer → membership `active`, member can access company jobs/applicants.
2. Owner cannot add a non-approved employer / job seeker / non-existent user / duplicate / self.
3. Reviewer performing a mutating action (create job, transition status, schedule interview, manage members) → 403.
4. Recruiter performing owner-only action (edit company profile, manage members) → 403.
5. Revoked member accessing any company resource → 403.
6. Member of company A accessing company B resource by ID → 403.
7. Platform-admin-only routes remain 403 for company members without platform permissions.
8. Last-owner protection: removing/demoting the final owner membership → 422/403 with clear error.
9. Owner's own access unaffected; existing owner-based tests still pass.

## 3. Assumptions / open questions

**Assumptions (labeled):**

- A-1: Members must already be approved employers (role `employer` + status `approved`). Rationale: employer routes require `employer.approved` middleware. Company membership does not create employer capability.
- A-2: Direct active membership (no invite/accept step) for MVP. `status` column supports `pending`/`revoked` for a future invite flow; only `active`/`revoked` used now.
- A-3: Primary `owner_user_id` stays immutable; no ownership transfer in this feature. The primary owner is treated as an implicit `owner` role for access resolution and is not a `company_members` row.
- A-4: Company-scoped roles are stored as a string on `company_members.company_role`, validated against `CompanyMemberRole` enum (`owner`, `recruiter`, `reviewer`). They are not platform roles.
- A-5: Reviewers may download resumes (read-only applicant access). Adjust if product wants stricter.
- A-6: Screening-question bank remains per-user; only job-level screening-question management follows company access.
- A-7: No new platform permission slugs; company access is resolved via membership, not the platform permission catalogue.

**Open questions (smallest set, non-blocking):**

- Q-1: Should a revoked member retain read-only historical access to applications they previously handled? Proposed: no — revocation removes all access immediately. Confirm.
- Q-2: Should co-owners be allowed at all, or only a single owner + recruiters/reviewers? Proposed: allow co-owners (role `owner`) for flexibility, protected by last-owner rule. Confirm.
- Q-3: Should members be listed publicly on the company profile? Proposed: no, members are private. Confirm.

## 4. Routes and access boundary

All under existing `auth` + `role:employer` + `employer.approved` middleware (employer group). New routes:

```
GET    /employer/companies/{company}/members        employer.companies.members.index
POST   /employer/companies/{company}/members        employer.companies.members.store   (throttle:20,1)
PATCH  /employer/companies/{company}/members/{member}  employer.companies.members.update (throttle:20,1)
DELETE /employer/companies/{company}/members/{member}  employer.companies.members.destroy (throttle:20,1)
```

Access boundary: middleware ensures approved employer; Policy ensures company membership/owner role for management. Members list view restricted to owners.

No admin routes added. No changes to platform permission middleware.

## 5. Policy actions and dynamic role/permission resolution

Introduce `CompanyMemberRole` enum (`owner`, `recruiter`, `reviewer`) with a `permissions(): array` method.

Permission strings (company-scoped, not platform):

- `company.view`, `company.update`
- `jobs.view`, `jobs.create`, `jobs.update`, `jobs.submit`, `jobs.close`
- `applicants.view`, `applicants.manage`
- `screening.manage`
- `members.manage`
- `resumes.download`

Role → permissions:

- owner: all of the above.
- recruiter: all except `company.update`, `members.manage`.
- reviewer: `company.view`, `jobs.view`, `applicants.view`, `resumes.download`.

Access resolution helper on `User`:

- `companyRole(?Company): ?string` — returns `'owner'` if `company->owner_user_id === $this->id`, else active membership's role, else null.
- `hasCompanyPermission(Company, string): bool`.

Policy updates (replace `owner_user_id === $actor->id` with membership-aware checks):

- `CompanyPolicy`: view (role !== null), update (company.update), viewMembers/manageMembers (members.manage).
- `JobPolicy`: update/submit (jobs.update/submit), close (jobs.close or platform perm), viewAny moderation unchanged.
- `JobApplicationPolicy`: viewAnyForJob/viewAsEmployer (applicants.view), transitionStatus (applicants.manage).
- `InterviewPolicy`: create/manage (applicants.manage), view (applicants.view or candidate owner).
- `JobScreeningQuestionPolicy`: eligible (screening.manage).
- `CandidateResumePolicy`: employer view path (active member with applicants.view, not just owner).

Query scoping:

- `Company::scopeForManagingUser(User)` — owner or active member.
- `Job::scopeForManagingUser(User)` — jobs whose company is managed by user.
- Controllers switch from `forOwner` to `forManagingUser` for company/job lists and company resolution in `StoreJobRequest` flow (`Company::forManagingUser($user)->findOrFail(...)`).

## 6. Form Requests and validation

- `StoreCompanyMemberRequest`:
    - `email`: required, email, exists:users,email.
    - `company_role`: required, in:owner,recruiter,reviewer.
    - Form Request `authorize()` returns true (controller Policy performs authorization).
    - Additional validation closure: target user must be approved employer; not already an active/pending member; not the primary owner; if role owner, allowed (co-owner).
- `UpdateCompanyMemberRequest`:
    - `company_role`: required, in:owner,recruiter,reviewer.
    - Last-owner protection: if demoting the last active owner membership → fail with error.

Controller enforces last-owner protection on destroy/update within a DB transaction.

## 7. Data model / migration / FKs / indexes

New migration `..._create_company_members_table.php`:

```
company_members
- id (bigint PK)
- company_id (FK companies, cascadeOnDelete)
- user_id (FK users, cascadeOnDelete)
- company_role (string 32)
- status (string 32, default 'active')
- invited_by_user_id (FK users nullable, nullOnDelete)
- invited_at (timestamp nullable)
- accepted_at (timestamp nullable)
- revoked_at (timestamp nullable)
- created_at, updated_at
- unique(company_id, user_id)
- index(user_id, status)
- index(company_id, status)
```

New model `App\Models\CompanyMember`:

- fillable: company_id, user_id, company_role, status, invited_by_user_id, invited_at, accepted_at, revoked_at.
- casts: invited_at/accepted_at/revoked_at datetime.
- belongsTo Company, User (member), User (inviter, foreignKey invited_by_user_id).

Relationships:

- `Company::members()` hasMany CompanyMember.
- `Company::activeMembers()` scope.
- `User::companyMemberships()` hasMany CompanyMember.

No changes to `companies` table (owner_user_id retained). No platform role/permission changes. No data backfill required (primary owner handled implicitly).

## 8. Query / pagination / eager-load plan

- Members index: `CompanyMember::where('company_id', $company->id)->with('user:id,name,email')->orderBy('created_at')->paginate(15)`.
- Company list for user: `Company::forManagingUser($user)->select(...)->orderBy('name')->paginate(15)`.
- Job list: existing eager loads unchanged; scoping via `forManagingUser`.
- Resume download employer path: single `exists()` query with membership-aware whereHas.
- Avoid N+1: eager-load `members` only where needed; use `whereHas` for access checks.
- Counts via DB where appropriate.

## 9. Service / Repository decision

No Service/Repository. Membership CRUD is simple single-table with one invariant (last-owner protection) handled in controller within a transaction. Rationale: complexity does not justify a Service; mirrors existing thin-controller pattern (Companies, ScreeningQuestions). If invite/accept flow is added later, reconsider a `CompanyMembershipService`.

## 10. React / Inertia plan

- New page `resources/js/pages/employer/companies/members.tsx` (or `companies/members/index.tsx`).
- Company show page: add "Manage team" link when owner.
- Members page props: `company` (id,name), `members` (paginated), `canManage`, available roles list.
- Add-member form (email + role select) using `useForm`, field errors, processing state.
- Role change inline (select + submit) and revoke button with confirmation dialog.
- Empty state; pagination component; loading/error states consistent with existing pages.
- TypeScript types for member payload.
- Navigation: no new top-level nav item (access via company show). Optional.

## 11. Test matrix

**Authorization / tenancy:**

- Guest redirects on all new routes.
- Job seeker forbidden.
- Non-approved employer forbidden.
- Non-member (other owner) forbidden on members routes and company resources.
- Member of company A cannot access company B members/jobs/applications by ID (403).
- Platform-admin routes remain forbidden for company members without platform perms.

**Membership CRUD:**

- Owner adds approved employer as recruiter/reviewer/owner → active; duplicate → validation error.
- Owner cannot add job seeker / non-approved / missing user / self / duplicate email.
- Owner changes member role; last-owner protection blocks demoting final owner.
- Owner revokes member → membership revoked; revoked member loses access (403) on next request.
- Owner cannot revoke/demote primary owner (not a membership row; route targets member only).

**Role permission matrix (each role × key action):**

- reviewer: GET jobs/applicants/application detail/resume → 200; POST job create, status transition, interview schedule, member add → 403.
- recruiter: job create/update/submit/close → 200; applicant view/manage, interview schedule → 200; company profile edit, member manage → 403.
- owner: all above + company edit + member manage → 200.

**Regression:**

- Existing CompanyTest, JobDraftTest, JobApplicationTest, InterviewSchedulingTest, ScreeningQuestion tests still pass (owner path unchanged).
- `Company::forManagingUser` returns owned + member companies.

**Security/performance:**

- N+1 check on members index and company/job lists.
- Mass-assignment: fillable explicit; no raw request payloads.
- Resume download authorized for active members.

## 12. Security / performance risks

- IDOR on company/member IDs → Policy + scoped route model binding; member must belong to company.
- Privilege escalation: company role must never touch platform roles/permissions; membership table isolated. Verified by test (platform routes 403).
- Last-owner lockout → transactional count check.
- Stale revoked membership cached in permission memoization → `companyRole()` queries DB each call (no per-instance cache) to reflect revocation immediately. Note: User permissionSlugs cache is separate (platform perms) and unaffected.
- N+1 in company lists → eager-load only when needed; `forManagingUser` uses whereHas (single query).
- Race on concurrent member add → unique constraint on (company_id, user_id) prevents duplicates; handle 409 gracefully.

## 13. Rollout / compatibility notes

- Additive migration; no changes to existing tables. Existing companies continue to work: primary owner retains owner access via `owner_user_id` (implicit role), no backfill needed.
- New `company_members` table empty initially.
- No seeder changes required. AuthorizationSeeder untouched.
- Frontend assets rebuild required after route changes (Wayfinder).

## 14. Ordered implementation plan

1. Create enum `App\Enums\CompanyMemberRole` and `App\Enums\CompanyMemberStatus`.
2. Migration `create_company_members_table`.
3. Model `CompanyMember` + relationships on Company/User.
4. Add `scopeForManagingUser` to Company and Job.
5. Add `companyRole()` / `hasCompanyPermission()` helpers to User.
6. Update policies (Company, Job, JobApplication, Interview, JobScreeningQuestion, CandidateResume).
7. Update controllers scoping (CompanyController, JobController) from forOwner to forManagingUser.
8. Create `CompanyMemberController` + Form Requests.
9. Add routes.
10. Frontend members page + company show link + types.
11. Update Wayfinder types (npm run build).
12. Tests.
13. Run checks.

## 15. Verification commands

```
php artisan migrate
php artisan test
vendor\bin\pint --test
php vendor\bin\phpstan analyse --memory-limit=512M --no-progress
npm run types:check
npm run check
npm run build
```

Report actual results; do not claim passes without running.
