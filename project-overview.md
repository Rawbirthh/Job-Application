# Project Overview — Job Portal and Recruitment Platform

> This document contains the product overview, business rules, and feature-by-feature backlog for the job portal. Use it to identify **one feature at a time**. General engineering rules and implementation workflow live in `AGENTS.md` and `.opencode/commands/`; this file is the source of truth for product scope and acceptance criteria.

## How to reference a feature in OpenCode

Features use simple, global numbers from **Feature 1** through **Feature 34**. Each feature has its own objective, dependencies, scope, rules, acceptance criteria, and test expectations.

Architect one feature without coding:

```text
/architect Read project-overview.md. Architect Feature 1 only.
Inspect the existing project, follow Feature 1 dependencies and acceptance criteria,
and write a specification under docs/specs/. Do not implement code yet or start
another feature. Wait for my approval.
```

After you review and approve the generated specification:

```text
/develop Implement only the approved specification for Feature 1.
Use project-overview.md as the product requirements source. Add or update the
relevant tests, run available checks, and report results. Do not start Feature 2.
```

Replace `1` with the feature number you want. If dependencies are not complete, the agent must report the blocker instead of silently implementing later features or bypassing prerequisites.

## Product summary

Build a web-based job portal and recruitment platform with three primary audiences:

- **Platform administrators:** Configure platform-wide options, manage platform roles and permissions, moderate employers and job postings, and maintain trust and safety.
- **Employers:** Create and manage a company profile, publish approved job listings, and review candidates who apply to their company's jobs.
- **Job seekers:** Discover jobs, maintain a candidate profile and resume, save opportunities, apply, and track application status.

Build progressively. The first usable release should deliver secure authentication, authorization, job configuration, employer/company setup, job publication, public search, and job applications. Recruitment workflow, notifications, interviews, analytics, and monetization come later.

## Shared product and business rules

These rules describe expected product behavior. They do not replace the project engineering standards in `AGENTS.md`. A feature specification may refine a rule, but it must document the decision before implementation.

These are the default rules for the planned product. An Architect specification may refine them, but must document any behavior change before implementation.

### Account and role model

- Public job-seeker registration must assign only the approved default candidate/job-seeker capability server-side. A submitted role ID or role name from the browser must never be trusted.
- Employer onboarding is a controlled flow. It may assign employer capability only through the approved registration/onboarding process. Employer account/company approval can be required before publishing jobs.
- The initial administrator must be provisioned through a trusted seeder, command, or other protected setup mechanism. Never expose an administrator self-registration route.
- The custom authorization implementation must support roles and permissions without hard-coding permission checks across the UI. Seed a minimal set of required system roles/permissions and protect against removing the last usable administrator or deleting permissions that the system requires.
- Do not implement company-team collaboration until Feature 28. Before that feature, company access is based on the company owner and explicit Policy checks.

### Company and job ownership

- A company profile has an owner/authorized employer and a status. An employer cannot claim an existing company's records simply by supplying another company ID.
- Jobs belong to a company. Every employer query for private job data must be scoped to companies the current user can manage.
- A job is not publicly visible until its moderation/publication workflow allows it.

### Default job lifecycle

Use explicit, validated transitions. The planned initial states are:

`draft → pending_review → published → closed`

Additional outcomes: `rejected`, `expired`, and `archived` where the workflow needs them. An edit to a published job that materially changes its content may require re-review; the Architect must define this rule before implementing Feature 15. Deactivation/deletion must not orphan applications.

### Job compensation and work configuration

- A job posting stores its actual optional minimum salary, maximum salary, currency, and pay period (for example hour, month, or year), plus a clear salary-disclosure/negotiability setting if included in the approved spec.
- Validate that a provided minimum is not greater than a provided maximum. Define whether salary is required as an explicit product decision; do not assume all jobs publish salary.
- Admin-configured salary bands are optional filter/display configuration, not a substitute for a job's actual salary values. Do not require salary values to match one of the bands.
- Workplace arrangement (remote, onsite, hybrid), employment type, job category, and experience level are configuration-backed values managed by the platform where applicable. Deactivate an option that is already used rather than deleting history or breaking existing jobs.
- Keep salary currency and pay period explicit. Do not compare different currencies or pay periods as though they were equivalent without a conversion/normalization feature.

### Application lifecycle

Default planned application statuses:

`submitted → reviewing → shortlisted → interview_scheduled → offer_sent → hired`

Possible terminal/alternate states: `rejected`, `withdrawn`. The implementation must validate allowed transitions and authorize the actor for each transition. The exact transition matrix must be defined in Feature 24 before coding. Candidates can view only their own applications; employers can view applications only for jobs belonging to their authorized company. Never allow an employer to change the applicant's identity or job association through form input.

### Privacy and notifications

- Candidate resumes and application details are private personal information.
- Public job details must not expose internal moderation notes, private employer details, candidate data, or internal application counts unless the product explicitly approves a public field.
- Notification messages should avoid disclosing sensitive information unnecessarily. In-app notifications are the first planned channel; email delivery can be added where configured.

---

## Feature roadmap

Implement the features in dependency order. The phases group related capabilities; they are not a request to implement a whole phase in one pass.

### Phase 0 — Foundation

- **Feature 1 — Existing project foundation audit and verification**
- **Feature 2 — Authentication and account lifecycle**
- **Feature 3 — Custom roles, permissions, middleware, and Policies**
- **Feature 4 — Shared application shell and role-aware dashboard routing**

### Phase 1 — Platform configuration and governance

- **Feature 5 — Admin manages job categories**
- **Feature 6 — Admin manages employment types**
- **Feature 7 — Admin manages workplace arrangements**
- **Feature 8 — Admin manages experience levels**
- **Feature 9 — Admin manages salary filter bands and pay-period options**
- **Feature 10 — Admin manages employer-account review and suspension**

### Phase 2 — Employer and job publishing

- **Feature 11 — Employer onboarding and eligibility state**
- **Feature 12 — Employer creates and manages a company profile**
- **Feature 13 — Employer job draft CRUD**
- **Feature 14 — Job submission, admin moderation, publication, and closure**
- **Feature 15 — Employer job dashboard and listing filters**

### Phase 3 — Public discovery and candidate profile

- **Feature 16 — Public job listing with server-side pagination**
- **Feature 17 — Public job detail page**
- **Feature 18 — Candidate profile management**
- **Feature 19 — Private resume upload and management**
- **Feature 20 — Saved jobs**
- **Feature 21 — Submit a job application**
- **Feature 22 — Candidate application history and status view**

### Phase 4 — Recruitment workflow

- **Feature 23 — Employer applicant list and application detail**
- **Feature 24 — Application status transitions and history**
- **Feature 25 — In-app notifications**
- **Feature 26 — Interview scheduling**
- **Feature 27 — Job-specific screening questions**
- **Feature 28 — Company hiring-team members and scoped permissions**

### Phase 5 — Trust, engagement, and growth

- **Feature 29 — Saved searches and job alerts**
- **Feature 30 — Report a job or employer**
- **Feature 31 — Administrative audit log**
- **Feature 32 — Employer and platform analytics**
- **Feature 33 — Employer plans, payments, and promoted jobs**
- **Feature 34 — Job recommendations and optional AI assistance**

## Detailed feature specifications

## Feature 1 — Existing project foundation audit and verification

**Objective:** Audit and verify the existing Laravel project foundation so later features use the actual installed stack and conventions rather than assumptions.

**Dependencies:** None.

**In scope:**
- Inspect the existing repository and record the installed PHP/Laravel, Node, React, TypeScript, Inertia, Tailwind, shadcn/ui, database, and testing setup.
- Identify which starter kit and application structure are already present; do not replace or regenerate the existing application.
- Verify existing setup, environment-example safety, frontend build, relevant tests, and development commands where the environment allows.
- Identify missing configuration or broken setup and recommend the smallest required correction. Do not install or upgrade dependencies, replace the starter kit, or make broad foundation changes without approval.
- Document install, environment, migration, test, and development-server commands in `README.md` if they are missing or inaccurate.

**Out of scope:** Implementing authentication changes, roles/permissions, company/job tables, dashboards, or any business feature. Those are separate features in this roadmap.

**Acceptance criteria:**
- The actual installed stack, starter kit, key versions, and test framework are documented.
- The application starts with the existing documented environment and renders its current Inertia/React page when environment access permits.
- The frontend build and relevant existing tests are verified, or failures/blockers are accurately recorded.
- Database connectivity and non-destructive migration/test commands are documented and verified where possible.
- No secrets are added to source control; `.env.example` contains placeholders only.
- README instructions match commands that were actually verified, and unverified items are clearly identified.

**Tests/checks:** Run the available backend test command, frontend type/build/format checks, and a smoke test. Do not claim database checks passed if no usable database is available.

**Deliverables:** Existing-project stack audit, minimal approved foundation fixes if needed, accurate README setup instructions, and a short verification report.

## Feature 2 — Authentication and account lifecycle

**Objective:** Provide secure session-based authentication before protected portal features are built.

**Dependencies:** Feature 1.

**In scope:**
- Registration for the initial job-seeker account path.
- Login and logout.
- Password reset, email verification, and appropriate session handling using the compatible Laravel setup.
- Server-side validation, useful form errors, loading/processing states, and responsive authentication pages.
- Protected route group for authenticated users.
- Safe post-login and post-logout redirects.
- Provisioning path for the first platform administrator documented for the developer; never public administrator self-registration.

**Out of scope:** Full role/permission management (Feature 3), employer onboarding (Feature 11), social login, MFA, and SSO unless explicitly requested.

**Acceptance criteria:**
- A user can register with valid data, log in, log out, and reset a password through the supported flow.
- Invalid input returns server-side validation errors.
- Guests cannot directly access protected routes.
- Unverified-account behavior is consistent with the approved policy.
- Public request payloads cannot set admin role, permissions, account approval, or other privileged fields.
- Session/cookie and CSRF behavior follow the project's Laravel session-auth model.

**Tests:** Successful registration/login/logout, invalid credentials, guest redirect, password-reset flow as feasible, verification restriction where applicable, and attempts to submit privileged fields.

## Feature 3 — Custom roles, permissions, middleware, and Policies

**Objective:** Implement a custom database-backed authorization foundation that can be managed dynamically without a third-party role package.

**Dependencies:** Feature 2.

**In scope:**
- Inspect existing auth and authorization code before designing schema.
- Define roles, permissions, and their assignments using explicit foreign keys and uniqueness constraints. Add the pivots or assignment model required by the chosen design.
- Seed only necessary initial roles and permission definitions, such as platform administrator, employer, and job seeker. Seed data must be repeatable and must not overwrite administrator changes unintentionally.
- Add server-side helpers/services or a gate/middleware integration for permission checks using established Laravel practices.
- Create Policies for protected domain resources as they are introduced; this feature should establish the pattern with a small authorization test resource or existing user-related action, not prebuild every future Policy.
- Keep role assignment server-controlled. Protect the last functioning administrator and system-critical permissions from accidental lockout.
- Document how to add a permission and safely assign it.

**Out of scope:** Admin UI for editing the role catalogue (later feature if needed), company hiring-team roles (Feature 28), and all business-resource CRUD features.

**Acceptance criteria:**
- Authorization is enforced on the server and works for direct requests, not just the UI.
- A permission can be added/assigned without scattering role-name checks throughout controllers and React components.
- Public registration cannot grant itself privileged roles or permissions.
- Protected routes use middleware; protected model actions are authorized through Policies or the established equivalent.
- Role/permission queries are efficient and do not introduce N+1 behavior.
- The initial admin provisioning process works and is documented.

**Tests:** Allowed and denied permissions, guest behavior, public role-escalation attempts, missing permission, and last-admin/system-critical protections where implemented.

**Important design note:** Do not implement a fully generic permission editor without defining how each permission maps to a resource/action. Keep the initial permission catalogue understandable and auditable.

## Feature 4 — Shared application shell and role-aware dashboard routing

**Objective:** Provide consistent layouts and landing pages for authenticated personas.

**Dependencies:** Feature 2, Feature 3.

**In scope:**
- Shared responsive app layout, navigation, breadcrumbs/page headings where consistent with the starter kit, flash messages, and user menu.
- A role/permission-aware navigation menu that hides links the user cannot use but never replaces server authorization.
- Role-specific landing destinations for administrator, employer, and job seeker using server-confirmed permissions.
- Accessible mobile navigation and sensible empty dashboard content.

**Out of scope:** Full analytics, applicant statistics, or feature-specific management tables.

**Acceptance criteria:**
- Signed-in users reach the correct permitted landing page.
- Unsupported or missing roles fail safely and do not grant elevated access.
- Navigation is responsive, keyboard accessible, and uses existing shadcn/ui primitives if installed.
- Directly opening a forbidden route still returns the correct server response.

**Tests:** Routing/landing behavior for relevant roles and forbidden route access. Run frontend type/build checks.

## Feature 5 — Admin manages job categories

**Objective:** Let authorized administrators create and maintain job categories used by listings.

**Dependencies:** Feature 3, Feature 4.

**In scope:** Paginated list, search, create, edit, and activate/deactivate category; fields include name, stable slug/key if needed, optional description, active status, timestamps, and auditing fields only if the established design requires them.

**Rules:** Category names/slugs must have appropriate uniqueness. Deactivate a category already referenced by jobs rather than deleting history. Public forms cannot set internal moderation fields.

**Acceptance criteria:** Authorized admins can manage categories; other users cannot; validation is displayed inline; list is paginated and searchable; inactive categories cannot be selected for new jobs but remain readable on historical jobs where appropriate.

**Tests:** CRUD happy paths, duplicate name/slug, invalid input, role/permission denial, pagination/search, and deactivation of a referenced category.

## Feature 6 — Admin manages employment types

**Objective:** Maintain the employment types job posters can select, such as full-time, part-time, contract, temporary, and internship.

**Dependencies:** Feature 3, Feature 4.

**In scope:** Admin paginated CRUD/search and activation/deactivation for employment type configuration.

**Rules:** Treat values as configurable data, not hard-coded UI-only labels. Prevent duplicate identifiers. Do not delete an option referenced by jobs.

**Acceptance criteria:** Only authorized admins can change options; job forms can later consume active options from the server; inactive values cannot be used by new/edited job postings unless a deliberate migration rule allows preserving them.

**Tests:** CRUD, duplicate validation, permission denial, active/inactive behavior, and pagination/search.

## Feature 7 — Admin manages workplace arrangements

**Objective:** Maintain workplace arrangements such as remote, onsite, and hybrid.

**Dependencies:** Feature 3, Feature 4.

**In scope:** Admin configuration CRUD, search, activation/deactivation, and ordering/display label where useful.

**Rules:** Keep stable machine identifiers separate from display labels. Do not infer remote status from a free-text job description.

**Acceptance criteria:** The UI uses active configured options; stored jobs retain the correct arrangement even if an option is later deactivated; users without permission cannot modify the catalogue.

**Tests:** CRUD, uniqueness, permissions, and inactive-option behavior.

## Feature 8 — Admin manages experience levels

**Objective:** Maintain consistent experience levels used by job postings and filters.

**Dependencies:** Feature 3, Feature 4.

**In scope:** Admin configuration for levels such as entry-level, junior, mid-level, senior, and lead, with stable keys, labels, optional description/order, and active status.

**Rules:** The list is configurable; do not assume the example labels must be permanent or exhaustive.

**Acceptance criteria:** Only admins can manage levels; the values can be reused by employer forms and public search; historical job records remain understandable if an option is deactivated.

**Tests:** CRUD, uniqueness, permission denial, and active/inactive behavior.

## Feature 9 — Admin manages salary filter bands and pay-period options

**Objective:** Maintain consistent compensation filter choices without restricting the actual salary values employers may enter.

**Dependencies:** Feature 3, Feature 4.

**In scope:** Admin manages optional salary filter bands (label plus a validated lower/upper threshold in a defined currency/normalization policy) and supported pay-period options such as hour, day, week, month, or year. Support active/inactive state.

**Out of scope:** Currency exchange-rate service, automatic salary conversion, payroll, or enforcing salary bands as the only permitted salaries.

**Rules:** Do not compare different currencies or periods without normalization. Define band inclusivity and currency before implementing filtering. If meaningful cross-currency bands are not in scope, keep bands currency-specific or defer them and implement exact salary-range filters later.

**Acceptance criteria:** Pay periods have stable keys; overlapping or contradictory salary bands are handled according to an explicit validation rule; deactivation does not corrupt jobs; salary input remains a real min/max value on the job record.

**Tests:** Threshold validation, currency/pay-period cases, permissions, and safe handling of inactive options.

## Feature 10 — Admin manages employer-account review and suspension

**Objective:** Give administrators a controlled way to restrict abuse and manage employer eligibility.

**Dependencies:** Feature 3, Feature 11.

**In scope:** Paginated/searchable employer list, employer status and verification state, approve/reject onboarding where applicable, suspend/reactivate with a required reason, and block suspended employers from prohibited actions.

**Rules:** Do not silently delete a company or its jobs/applications when an account is suspended. Define what happens to already published jobs and notify the employer through the later notification system or show an actionable status message. Admin actions must be authorized and recorded at least through the application’s existing audit mechanism once Feature 31 exists.

**Acceptance criteria:** Only an administrator with the relevant permission can change review/suspension status; suspended accounts cannot perform disallowed employer actions even by direct request; state changes are visible to the affected employer.

**Tests:** Approval/rejection, suspension behavior, direct-request denial, unauthorized admin access, and preservation of existing records.

## Feature 11 — Employer onboarding and eligibility state

**Objective:** Allow a registered person to request employer capabilities through a controlled flow.

**Dependencies:** Feature 2, Feature 3, Feature 4.

**In scope:** Employer onboarding form; validation; employer account/profile record or equivalent state model; pending/approved/rejected/suspended status as justified; status page and next-step guidance; integration with administrator review.

**Rules:** Do not accept arbitrary role or permission IDs from the client. Decide whether email verification is required before submission. The default plan is that a pending employer cannot publish jobs until approved. Avoid collecting sensitive business documents unless the product explicitly requires them.

**Acceptance criteria:** A job seeker can submit an employer request; duplicate or invalid submissions are handled; status is persisted server-side; only approved/non-suspended employers can proceed to allowed company/job management actions; admins can review the request.

**Tests:** Valid/invalid onboarding, duplicate request behavior, unauthorized status changes, pending employer restrictions, and approved employer access.

## Feature 12 — Employer creates and manages a company profile

**Objective:** Give an approved employer a company profile that owns its job postings.

**Dependencies:** Feature 11.

**In scope:** Create and edit company name, slug/identifier, overview, industry where configured, website, size band if configured, company logo, and location fields appropriate to the initial product. Include profile status and owner reference.

**Rules:** Validate URLs, text lengths, image MIME/size, and unique slug/name rules chosen by the specification. Uploads must use safe storage and validation. Only the owner/authorized company manager may edit the record. Do not trust `owner_user_id` from the request.

**Acceptance criteria:** An approved employer can create the permitted company profile and update it; another employer cannot read private edit information or modify it; the public profile exposes only approved public fields; logo errors are understandable.

**Tests:** Create/edit, validation, company ID tampering, cross-owner denial, and upload validation if image upload is in scope.

## Feature 13 — Employer job draft CRUD

**Objective:** Let an authorized employer create and edit job drafts before submitting them for review.

**Dependencies:** Feature 5–Feature 9, Feature 12.

**In scope:** Job title, description, responsibilities, requirements, company, category, employment type, workplace arrangement, experience level, optional salary min/max/currency/pay period, location or remote-location rules, application deadline if selected, and draft status. Provide create/edit/preview and validation feedback.

**Rules:** Only active configuration options are selectable for new drafts. Min salary cannot exceed max salary. Company association is chosen from companies the current user can manage, not arbitrary IDs. A newly created job begins as `draft`; the client cannot set `published`, `approved`, or moderation fields.

**Out of scope:** Public listing, candidate application, job approval, and application management.

**Acceptance criteria:** Authorized employer can save and edit its own drafts; another employer cannot access or change them; invalid fields return Form Request errors; job content is persisted; forms use typed Inertia props and show processing/errors; job drafts are not public.

**Tests:** Create/edit draft, invalid salary/date/options, forged company ID, cross-company access denial, forbidden status assignment, and draft non-public visibility.

## Feature 14 — Job submission, admin moderation, publication, and closure

**Objective:** Control when jobs become visible to job seekers.

**Dependencies:** Feature 13, Feature 3, Feature 10.

**In scope:** Employer submits a complete draft for review; admin sees a paginated review queue and job details; admin approves or rejects with a reason; employer can correct and resubmit a rejected job; authorized employer can close a published job; controlled status transitions are enforced on the server.

**Rules:** Define the allowed transition matrix before coding. Do not permit employers to publish by editing a status field. Rejecting requires a reason. A job cannot be submitted if required fields are missing or employer eligibility is invalid. Closing a job must not delete applications. Changes to a published job must follow an explicit re-review rule.

**Acceptance criteria:** Only eligible employers submit jobs; only authorized admins moderate; unapproved jobs never appear in public search; status and rejection reason are visible to the appropriate parties; invalid transitions return a meaningful error; closure retains history.

**Tests:** Valid/invalid transitions, admin approve/reject, rejection reason, employer status tampering, non-owner submission, suspended employer, and public visibility rules.

## Feature 15 — Employer job dashboard and listing filters

**Objective:** Let an employer manage the lifecycle and find the jobs belonging to its company.

**Dependencies:** Feature 13, Feature 14.

**In scope:** Paginated employer job table; search by title; filter by status/category; show created/updated/published dates and application counts only when available; links to edit, preview, submit, or close according to state and permission.

**Rules:** Scope every query to the authorized company/user. Compute counts at the database level where appropriate and avoid N+1 queries. Frontend actions reflect permitted transitions but server-side enforcement remains mandatory.

**Acceptance criteria:** The dashboard shows only permitted company jobs; filters and pagination are server-driven and preserve query strings; empty states and loading/error states are clear; actions vary with status and authorization.

**Tests:** Scoping, pagination, filtering, counts, access denial, and rendering/build checks.

## Feature 16 — Public job listing with server-side pagination

**Objective:** Let anyone browse currently published, eligible job listings.

**Dependencies:** Feature 5–Feature 9, Feature 14.

**In scope:** Public paginated job index with useful summary fields such as title, company name/logo where public, workplace arrangement, employment type, location, category, publication date, and salary summary if disclosed.

**Rules:** Only jobs in publicly visible states from eligible companies appear. Select only display columns and required relationship keys, eager-load needed company/config data, and avoid N+1. Do not return private employer moderation data or applicant information.

**Acceptance criteria:** Guest users can browse published jobs; drafts, rejected, closed, suspended-company listings, and unapproved listings follow the defined visibility policy; results are paginated; empty states and navigation work; list queries are bounded.

**Tests:** Visibility rules, pagination, public data exposure, and query behavior where practical.

## Feature 17 — Public job detail page

**Objective:** Show the complete public job description for a currently visible listing.

**Dependencies:** Feature 16.

**In scope:** Job title, company public profile, description, responsibilities, requirements, workplace arrangement, employment type, experience level, location, published date, deadline, disclosed salary, and call-to-action appropriate to guest/authenticated status.

**Rules:** Do not display internal review notes or private fields. Handle unlisted, closed, expired, or non-existent jobs consistently without leaking private records. Application links must not bypass authentication or application rules.

**Acceptance criteria:** Visible jobs show the full public information; non-public records are not exposed; guests receive a clear sign-in/register path to apply; responsive layout and accessibility are checked.

**Tests:** Public/private visibility, invalid/missing record behavior, and correct CTA for guests and signed-in users.

## Feature 18 — Candidate profile management

**Objective:** Let job seekers maintain professional information used in applications.

**Dependencies:** Feature 2, Feature 3.

**In scope:** Candidate profile with professional headline/summary, location, skills, education entries, and work experience entries. Build edit flows and validation. Decide which fields are optional and how repeated education/experience entries are stored before implementation.

**Rules:** A user can edit only their own profile. Do not expose private candidate information to public pages. Employer access to candidate details is limited to the permitted application/recruitment context. Keep form validation server-side.

**Acceptance criteria:** Candidate can create/edit profile data and see persisted values; validation errors map to fields; other users cannot update or retrieve private profile data; Inertia props contain only necessary fields.

**Tests:** Owner CRUD, cross-user denial, validation, repeated-entry behavior if implemented, and field exposure.

## Feature 19 — Private resume upload and management

**Objective:** Allow a candidate to upload, view metadata for, replace, and remove their resume.

**Dependencies:** Feature 18.

**In scope:** Private file storage, file validation, metadata, replace/delete flow, and authorized download. Define allowed formats (recommended starting point: PDF only unless other formats are explicitly required), max size, and storage disk based on environment.

**Rules:** Do not store uploaded files in a publicly guessable path. Validate MIME/content and size server-side. A private file must be streamed/downloaded through an authorization-checked route. Do not trust original filenames or path inputs. Preserve existing application snapshots/attachments if product rules require them; define that behavior before allowing replacement/deletion.

**Acceptance criteria:** Valid file uploads securely; invalid files are rejected; resume can be replaced/removed according to rules; only the owner and specifically authorized recruiters for a relevant application can access it; no public URL leaks the document.

**Tests:** Valid/invalid uploads, size/type limits, unauthorized download, path tampering, replacement, removal, and storage failure handling.

## Feature 20 — Saved jobs

**Objective:** Let authenticated job seekers bookmark published jobs and view their saved list.

**Dependencies:** Feature 16, Feature 3.

**In scope:** Save/unsave action, unique user-job relationship, paginated saved-job list, and empty state.

**Rules:** Only the authenticated user controls their saved items. Prevent duplicates. Do not allow client-supplied user ID. Define whether closed jobs remain in saved history; default is to retain the record but mark/remove it from actionable current listings as appropriate.

**Acceptance criteria:** Candidate can save/unsave a visible job; duplicate requests do not create duplicate rows; saved list is private and paginated; invalid/nonexistent jobs fail safely.

**Tests:** Save, unsave, duplicate prevention, cross-user denial, and listing behavior when a job closes.

## Feature 21 — Submit a job application

**Objective:** Let an eligible job seeker apply to an eligible published job.

**Dependencies:** Feature 17, Feature 18, Feature 19.

**In scope:** Apply action and form; validate that the job is still accepting applications; select/use an eligible resume; optional cover letter or supporting fields only if specified; persist applicant, job, resume/reference, and submission timestamp; show success/failure feedback.

**Rules:** Enforce one active application per candidate/job unless product requirements explicitly allow reapplication. Never trust applicant user ID, company ID, job status, or application status from the client. Use a unique constraint or equivalent concurrency-safe protection for duplicates. Validate deadline/status at submission time. Use a transaction for related writes where needed.

**Acceptance criteria:** Eligible candidate can submit an application; duplicate and closed-job applications are rejected clearly; the application is associated with the signed-in user and correct job; employer/candidate identity cannot be forged; submission confirmation is shown; candidate resume/document access remains private.

**Tests:** Successful apply, guest restriction, invalid/closed job, duplicate/concurrent duplicate protection, missing resume/profile behavior according to the approved rule, identity tampering, and cross-company data access.

## Feature 22 — Candidate application history and status view

**Objective:** Let job seekers see their submitted applications and current progress.

**Dependencies:** Feature 21.

**In scope:** Paginated “My Applications” list with job/company summary, submission date, current status, and links to public job details when available; application detail page with candidate-visible status information.

**Rules:** Return only the authenticated candidate's applications. Do not display internal recruiter notes or private interview feedback. Define how a closed/deleted/archived job is represented without deleting application history.

**Acceptance criteria:** Candidate sees only their applications; status changes are reflected; list supports pagination and appropriate filters; closed jobs remain understandable in historical application records.

**Tests:** Candidate scoping, pagination, status display, and private/internal-field exclusion.

## Feature 23 — Employer applicant list and application detail

**Objective:** Let an authorized employer review candidates who applied to jobs belonging to its company.

**Dependencies:** Feature 21, Feature 12, Feature 14.

**In scope:** Paginated applicant list by job; candidate-visible profile summary, application date/status, authorized resume access, and application detail.

**Rules:** Every query must be scoped through the job's company and current employer authority. An employer cannot change `job_id`, `candidate_id`, or ownership through the URL/body. Do not expose applications belonging to other companies. Avoid showing candidate details beyond what the hiring workflow needs.

**Acceptance criteria:** Authorized employer can view applicants for its job; unrelated employers are forbidden even when IDs are guessed; resume downloads are authorized; tables are paginated and don't produce N+1 queries.

**Tests:** Company scoping, guessed-ID/cross-company denial, resume authorization, pagination, and private data protection.

## Feature 24 — Application status transitions and history

**Objective:** Let authorized recruiters move applications through a controlled hiring pipeline and preserve status history.

**Dependencies:** Feature 23.

**In scope:** Define and implement an explicit transition matrix; authorized status-change action; status history (old status, new status, actor, timestamp, optional reason); candidate-visible status; clear validation/error feedback.

**Rules:** Agree on allowed transitions before coding. Prevent arbitrary status assignment, unauthorized transitions, and double/stale updates. A candidate may withdraw only under explicitly defined conditions. Sensitive internal notes must be separate from candidate-visible reasons. Do not implement automated AI screening.

**Acceptance criteria:** Only authorized company users can transition applications for their own jobs; invalid transitions are rejected; every successful change records history; status is reflected to the candidate; concurrency behavior is defined and tested.

**Tests:** Valid and invalid transitions, wrong-company denial, candidate withdrawal rules if included, history integrity, and stale/double updates.

## Feature 25 — In-app notifications

**Objective:** Provide a reliable in-app notification center for important events.

**Dependencies:** Feature 21, Feature 22, Feature 24; employer/job moderation events when they become relevant.

**In scope:** Persisted notifications, unread/read state, paginated notification list, mark-one/read and mark-all-read actions, and notification creation for selected events such as application submission confirmation, application status changes, and employer job moderation outcomes.

**Rules:** A user may see/update only their own notifications. Do not create duplicate notifications for a retried event where idempotency is required. Keep notification content safe and concise. Email notifications are out of scope unless separately approved.

**Acceptance criteria:** Selected events create the right notification; unread state persists; read actions are authorized; list is paginated; failures are observable and don't silently corrupt the primary business operation.

**Tests:** Correct recipient, own-notification authorization, read/unread persistence, duplicate handling where needed, and event behavior.

## Feature 26 — Interview scheduling

**Objective:** Allow authorized employers to schedule interviews with applicants and let candidates see/respond to the invitation.

**Dependencies:** Feature 23, Feature 24, Feature 25.

**In scope:** Create/reschedule/cancel interview; date/time and time-zone handling; interview mode (online/on-site/phone), notes/instructions appropriate to the viewer, invitation state, and notifications.

**Rules:** Define time zone storage and display before implementation. Validate future dates and the relevant application status. Prevent unrelated employers from scheduling. Avoid exposing private meeting details to unauthorized viewers. External calendar integration is out of scope initially.

**Acceptance criteria:** Authorized recruiter can schedule; candidate can view the invitation and permitted response options; cancellation/reschedule is reflected consistently; notifications are sent through the in-app mechanism; time displays correctly across time zones.

**Tests:** Authorization, validation, reschedule/cancel, time-zone behavior, application relationship, and notifications.

## Feature 27 — Job-specific screening questions

**Objective:** Let employers define application questions for a job and review candidate answers.

**Dependencies:** Feature 13, Feature 14, Feature 21, Feature 23.

**In scope:** Supported question types chosen for the MVP (recommended: short text, long text, yes/no, and single choice); required/optional flag; order; candidate answer validation; employer viewing of answers.

**Rules:** Questions become fixed for an application once the candidate submits, or the product must explicitly define how edits affect prior applications. Validate question ownership and type-specific answers server-side. Do not execute HTML or scripts submitted as answers.

**Acceptance criteria:** Employer can manage questions for its own draft/eligible job; candidate sees the correct active questions; required answers are validated; submitted answers are preserved for recruiters; cross-company access is prevented.

**Tests:** CRUD/ownership, question type validation, required answer behavior, immutable/preserved submitted answers, and answer privacy.

## Feature 28 — Company hiring-team members and scoped permissions

**Objective:** Let a company owner invite or add other users to help manage jobs and applicants without granting access to other companies or platform administration.

**Dependencies:** Feature 3, Feature 12, Feature 23.

**In scope:** Company membership records, invite/accept or other approved membership flow, membership status, company-scoped roles/permissions (for example owner, recruiter, read-only reviewer), revocation, and Policy integration.

**Rules:** Company permissions must be scoped to the specific company. A company role must never grant platform-admin permissions. Prevent removal/demotion of the last company owner unless an ownership-transfer process is completed. Define who may invite and what a revoked member can still access historically.

**Acceptance criteria:** Owner can add/revoke allowed members; members can only perform assigned actions within that company; guessed company/job/application IDs do not cross tenant boundaries; role changes take effect server-side.

**Tests:** Invitation/membership flow, each relevant permission, cross-company denial, revocation, owner protection, and platform privilege isolation.

## Feature 29 — Saved searches and job alerts

**Objective:** Let candidates save search filters and optionally receive notifications about new matching jobs.

**Dependencies:** Feature 16, Feature 25.

**In scope:** Save/edit/delete search criteria; run filters through the same server-side query logic as job search; allow opt-in/opt-out alert frequency; create in-app alerts for newly matching published jobs.

**Rules:** Keep searches private to their owner. Prevent excessive repeated work; use queued/batched processing if required by observed scale. Define duplicate-alert behavior and preference controls.

**Acceptance criteria:** Candidate can save and rerun a search; alerts respect preferences and don't leak private job data; disabled alerts stop generating new notifications; search criteria validation is consistent with public search.

**Tests:** Ownership, filter consistency, opt-out, duplicate-alert prevention, and job visibility changes.

## Feature 30 — Report a job or employer

**Objective:** Let users report scams, misleading listings, or suspicious behavior for moderation.

**Dependencies:** Feature 12, Feature 14, Feature 16, Feature 3.

**In scope:** Report form with a controlled reason list, optional explanation, moderation queue, status, admin action, and resolution message where appropriate.

**Rules:** Rate-limit or otherwise mitigate abuse; prevent users from viewing other reporters' private details; do not automatically remove a listing solely because a report exists unless a defined safety rule says so; avoid duplicate spam reports.

**Acceptance criteria:** A logged-in user can submit a valid report; admins can review and resolve reports; report details are visible only to authorized users; reported content remains subject to explicit moderation rules.

**Tests:** Validation, abuse/duplicate protection, report privacy, admin queue authorization, and resolution behavior.

## Feature 31 — Administrative audit log

**Objective:** Record important security and administrative events for troubleshooting and accountability.

**Dependencies:** Feature 3; integrate progressively with Feature 10, Feature 14, Feature 24, Feature 28, and other sensitive actions.

**In scope:** Actor, action, target type/ID, timestamp, safe metadata, and request correlation details where available; paginated/searchable admin view with filters.

**Rules:** Never store passwords, session secrets, resume contents, or unnecessary personal data in logs. Audit events should be append-only for ordinary users. Access to logs is restricted and logged where appropriate. Avoid logging entire request payloads.

**Acceptance criteria:** Selected sensitive actions write audit entries; log entries identify the actor and target; unauthorized users cannot view or alter logs; list queries are paginated; sensitive fields are redacted.

**Tests:** Event creation, tamper/access denial, filtering/pagination, and redaction.

## Feature 32 — Employer and platform analytics

**Objective:** Show useful, privacy-conscious metrics for employer and platform decision-making.

**Dependencies:** Feature 14, Feature 21, Feature 23; Feature 31 recommended for traceability.

**In scope:** Employer-scoped job views if tracking exists, application totals, conversion summaries if definitions are agreed, job status counts, and admin-level aggregate metrics.

**Rules:** Define metric formulas and date windows before implementation. Do not expose another company's metrics. Avoid expensive aggregation on every page request for large data; measure and consider pre-aggregation only when justified.

**Acceptance criteria:** Metrics have documented definitions, correct authorization scope, useful empty states, and consistent date/time handling.

**Tests:** Company data isolation, known-fixture calculations, date-range filters, and query performance for representative data.

## Feature 33 — Employer plans, payments, and promoted jobs

**Objective:** Add optional monetization after the core job application workflow is reliable.

**Dependencies:** Feature 14, Feature 23; payment provider decision required.

**In scope:** Approved plan definitions, subscription status, posting limits or promotion entitlements, payment status handling, and provider webhook processing only after a provider is chosen.

**Rules:** Never store raw payment-card details. Verify webhook signatures, make handlers idempotent, and do not grant entitlements from an unverified client redirect. Define refunds, cancellations, failed payments, and plan expiry before launch. Do not begin this feature until payment provider, currency, taxes, and legal/commercial rules are decided.

**Acceptance criteria:** Entitlements are derived from verified server-side payment/subscription state; replayed webhooks do not duplicate effects; expired/unpaid plans follow documented restrictions; sensitive payment data is not stored locally.

**Tests:** Entitlement rules, verified/unverified webhook behavior, replay/idempotency, failed payment, expiry, and authorization.

## Feature 34 — Job recommendations and optional AI assistance

**Objective:** Help candidates discover relevant jobs without replacing their control over applications or employer decisions.

**Dependencies:** Feature 16, Feature 18, Feature 21; privacy and provider decisions required.

**In scope:** Start with transparent rule-based matching by skills, category, location/work arrangement, and experience level. AI-based matching or content assistance is optional and must be a separate approved increment.

**Rules:** Do not automatically reject or rank people for consequential employment decisions without a separately reviewed fairness, privacy, transparency, and human-oversight plan. Do not send resumes or personal data to an external AI provider without explicit product/privacy approval. Recommendations must not change application data or permission checks.

**Acceptance criteria:** Recommendations use documented input signals, allow candidates to inspect relevant jobs, handle missing profile data gracefully, and preserve normal search/application functionality.

**Tests:** Matching inputs, no-profile behavior, privacy boundaries, and stable output for known fixtures. For AI additions, add provider-failure, data-minimization, and human-review tests appropriate to the design.

---

## Later capabilities and product boundaries

- Do not introduce AI candidate scoring or automated employment decisions as part of ordinary job posting/application features. Feature 34 covers optional recommendations and requires separate privacy, fairness, transparency, and human-oversight decisions for any AI-based expansion.
- Payments, subscriptions, paid job promotions, external calendar integrations, SSO/social login, real-time chat, and video interviews are separate capabilities. Do not add them to an earlier feature unless the roadmap is explicitly revised.
- Company hiring-team membership and company-scoped roles belong to Feature 28; do not treat an employer account as an implicit member of every company.
- Prefer a working, verifiable vertical slice per feature over implementing the entire roadmap in one prompt.
