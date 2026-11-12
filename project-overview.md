# Project Overview — Job Portal and Recruitment Platform

> This document contains the product overview, business rules, and feature-by-feature backlog for the job portal. Use it to identify **one feature at a time**. General engineering rules and implementation workflow live in `AGENTS.md` and `.opencode/commands/`; this file is the source of truth for product scope and acceptance criteria.

## How to reference a feature in OpenCode

Features use simple, global numbers from **Feature 1** through **Feature 37**. Each feature has its own objective, dependencies, scope, rules, acceptance criteria, and test expectations. Features marked **deferred** are retained for future reference but are not part of the active portfolio implementation scope.

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

Build progressively. The first usable release should deliver secure authentication, authorization, job configuration, employer/company setup, job publication, public search, and job applications. Recruitment workflow, notifications, interviews, analytics, rule-based recommendations, messaging, and recruiter productivity features follow later. Payment-based monetization is optional and deferred; it is not required to complete the portfolio project.

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
- Application conversations, candidate resumes, recruiter notes, and evaluations are private. They must be visible only to the candidate and currently authorized employer/company participants for the relevant application.
- Real-time events must not expose message bodies or private application data to public channels. Authorize private channels on the server and re-check access when a participant is removed or their company permission changes.

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
- **Feature 32 — Employer and platform analytics** (completed)
- **Feature 33 — Employer plans, payments, and promoted jobs** (deferred; not required for the portfolio scope)
- **Feature 34 — Rule-based job recommendations** (no AI or external AI provider)

### Phase 6 — Communication and recruiter productivity

- **Feature 35 — Application-based real-time messaging**
- **Feature 36 — Recruiter notes and candidate evaluation**
- **Feature 37 — Visual recruitment pipeline**

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

**In scope:** Private file storage, file validation, metadata, resume versioning, replace/delete flow, and authorized download. Define allowed formats (recommended starting point: PDF only unless other formats are explicitly required), maximum size, and storage disk based on the environment. Each uploaded resume should create a new immutable file version or resume record rather than overwriting the file already referenced by an application.

**Rules:** Do not store uploaded files in a publicly guessable path. Validate MIME/content and size server-side. A private file must be streamed/downloaded through an authorization-checked route. Do not trust original filenames or path inputs. Replacing or removing the candidate's current resume must not invalidate the resume version attached to an existing application. Define the retention and deletion behavior for resume versions referenced by applications before implementation.

**Acceptance criteria:** A valid resume uploads securely; invalid files are rejected; candidates can identify and manage their current resume; replacing or removing the current resume does not change or break a previously submitted application; only the owner and specifically authorized recruiters for a relevant application can access each permitted version; no public URL leaks the document.

**Tests:** Valid/invalid uploads, size/type limits, unauthorized download, path tampering, version creation, replacement without altering an application-attached version, removal behavior, retention of referenced versions, and storage failure handling.

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

**In scope:** Apply action and form; validate that the job is still accepting applications; select/use an eligible resume version; optional cover letter or supporting fields only if specified; persist applicant, job, immutable resume-version reference or equivalent protected snapshot, the approved application-time candidate details needed for employer review, and submission timestamp; show success/failure feedback.

**Rules:** Enforce one active application per candidate/job unless product requirements explicitly allow reapplication. Never trust applicant user ID, company ID, job status, application status, or resume ownership from the client. Use a unique constraint or equivalent concurrency-safe protection for duplicates. Validate deadline/status and resume ownership at submission time. Use a transaction for related writes where needed. Define the minimal candidate details that should be snapshotted; do not copy unnecessary personal data. Later profile edits or resume replacement must not silently rewrite the historical application submission.

**Acceptance criteria:** An eligible candidate can submit an application; duplicate and closed-job applications are rejected clearly; the application is associated with the signed-in user and correct job; the submitted resume version and approved application-time details remain stable after profile edits or resume replacement; employer/candidate identity cannot be forged; submission confirmation is shown; candidate resume/document access remains private.

**Tests:** Successful apply, guest restriction, invalid/closed job, duplicate/concurrent duplicate protection, missing resume/profile behavior according to the approved rule, resume-version ownership and snapshot integrity, profile-edit-after-submission behavior, identity tampering, and cross-company data access.

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

**Rules:** Company permissions must be scoped to the specific company. A company role must never grant platform-admin permissions. Prevent removal/demotion of the last company owner unless an ownership-transfer process is completed. Define who may invite and what a revoked member can still access historically. Features 35 and 36 must use these company-scoped permissions when hiring-team members are enabled; a company membership must never grant access to another company's conversations, notes, or evaluations.

**Acceptance criteria:** Owner can add/revoke allowed members; members can only perform assigned actions within that company; guessed company/job/application IDs do not cross tenant boundaries; role changes take effect server-side and revoked members immediately lose access to protected hiring data.

**Tests:** Invitation/membership flow, each relevant permission, cross-company denial, revocation, owner protection, and platform privilege isolation, including access to application conversations and recruiter notes where those features are implemented.

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

## Feature 32 — Employer and platform analytics (completed)

**Objective:** Show useful, privacy-conscious metrics for employer and platform decision-making.

**Dependencies:** Feature 14, Feature 21, Feature 23; Feature 31 recommended for traceability.

**In scope:** Employer-scoped job views if tracking exists, application totals, conversion summaries if definitions are agreed, job status counts, and admin-level aggregate metrics.

**Rules:** Define metric formulas and date windows before implementation. Do not expose another company's metrics. Avoid expensive aggregation on every page request for large data; measure and consider pre-aggregation only when justified.

**Acceptance criteria:** Metrics have documented definitions, correct authorization scope, useful empty states, and consistent date/time handling.

**Tests:** Company data isolation, known-fixture calculations, date-range filters, and query performance for representative data.

## Feature 33 — Employer plans, payments, and promoted jobs (deferred)

**Status:** Deferred. This feature is not part of the current portfolio implementation scope.

**Objective:** Reserve a future place for optional monetization if the project later becomes a commercial job platform. Paid plans, payment processing, and promoted listings are not required for the portfolio project.

**Dependencies:** Feature 14, Feature 23. A payment provider, supported currency, tax/commercial rules, and the product's monetization policy would need to be decided before this feature is reopened.

**In scope (future consideration only):** Employer plan definitions, subscription entitlements, posting limits, promoted-job entitlements, payment status, and verified provider webhook processing may be considered in a future approved revision.

**Out of scope for the current project:** Payment gateway integration, subscription checkout, card handling, provider webhooks, billing portals, refunds, taxes, paid posting limits, and paid job promotion.

**Rules:** Do not implement this feature, add payment dependencies, or make core job posting/application workflows depend on a subscription. Never store raw payment-card details if monetization is approved in the future. Any future implementation must derive entitlements from verified server-side payment state and define webhook signature verification, idempotency, refunds, cancellations, failed payments, expiry, currency, and legal/commercial requirements before coding.

**Acceptance criteria:** The backlog clearly identifies this feature as deferred; no payment provider or subscription dependency is introduced into the current portfolio scope; all existing free job discovery, posting, and application workflows remain usable without payment.

**Tests/checks:** No payment-feature implementation tests are required while this feature remains deferred. Verify that no active feature depends on payment plans, paid entitlements, or a payment provider.

**Deliverables:** No code deliverable while deferred. Reopen this feature only through an explicit product-scope decision.

## Feature 34 — Rule-based job recommendations

**Objective:** Help job seekers discover relevant published jobs through transparent, deterministic matching without requiring AI, an external AI provider, a subscription, or local AI hardware.

**Dependencies:** Feature 16, Feature 18. Reuse existing job configuration and candidate profile data; inspect how skills and experience are currently represented before defining the matching implementation.

**In scope:** Provide a personalized job recommendation section for signed-in candidates using documented rule-based signals such as normalized skill overlap, job category, experience level, location, and workplace arrangement. Show understandable reasons for each recommendation (for example, matching skills or remote-work preference). Allow candidates to open the job detail page and continue through the existing application flow. Provide a useful fallback, such as recent eligible jobs, when profile information is missing.

**Out of scope:** Large language models, generative AI, machine-learning model training, embeddings/vector databases, external AI APIs, AI-written resumes or cover letters, automated candidate screening, employer-facing candidate scoring, and automatic rejection or hiring decisions.

**Rules:** Matching must be deterministic, explainable, and based only on approved profile/job fields. Do not infer sensitive personal attributes. Do not expose private candidate profile data to employers or other candidates through recommendation results. Recommendations must include only jobs that are currently publicly visible and accepting applications. Do not compare salary values across different currencies or pay periods without an approved normalization rule. Recommendations must not modify applications, bypass authorization, or replace normal search/filter functionality. Do not label a heuristic match as an AI result or imply a level of accuracy that the rules cannot support.

**Acceptance criteria:** Signed-in candidates receive recommendations based on the documented matching rules; each recommendation can show the relevant matching reasons; unpublished, closed, expired, or otherwise ineligible jobs are excluded; missing or incomplete profiles produce a useful fallback state; candidates can inspect and apply through the existing job workflow; recommendation queries respect privacy, authorization, and pagination/performance requirements; no AI service, paid provider, or incompatible local model is required.

**Tests:** Matching behavior for known candidate/job fixtures; matching and non-matching skills; category, experience, location, and workplace-arrangement rules; exclusion of non-public/ineligible jobs; no-profile and partial-profile behavior; explanation accuracy; privacy boundaries; stable deterministic output; and query performance for representative data.

## Feature 35 — Application-based real-time messaging

**Objective:** Allow a job seeker and an authorized employer to communicate about an existing job application through a private, persistent messaging interface with real-time message delivery.

**Dependencies:** Feature 21, Feature 23, Feature 25. Feature 28 is required only if the product enables additional company hiring-team members to participate; otherwise, follow the existing company-owner/authorized-employer access model.

**In scope:** Create or open one application-specific conversation; show a paginated conversation list with the related job and company; show paginated message history, sender, and timestamp; send and persist text messages; deliver new messages in real time without requiring a page refresh; show the latest-message preview and unread count; track read/unread state per participant; and create an in-app notification for the recipient using Feature 25. Use the existing Laravel broadcasting stack where available. Evaluate Laravel Reverb as the self-hosted WebSocket server and Laravel Echo as the frontend listener after inspecting the installed Laravel version and existing setup. Use MySQL as the persistent source of truth for conversations and messages.

**Out of scope:** File attachments, image uploads, voice messages, audio/video calls, group chats, public chat rooms, typing indicators, presence indicators, message reactions, and integration with paid third-party messaging services.

**Rules:** A conversation must belong to exactly one existing application and must not become unrestricted user-to-user messaging. Only the candidate associated with the application and currently authorized employer/company participants may view or send messages. Scope every conversation and message query through the related application, job, company, and participant authorization; never trust sender, candidate, company, or application IDs from the client. Authorize private broadcast channels server-side and revoke access when a participant loses the relevant company permission. Persist a message successfully before broadcasting it, and broadcast only after the database transaction commits. Keep message content plain text or safely escaped; do not render user-submitted HTML. Validate message length, rate-limit sending, and prevent empty messages. Store read state per participant so unread state is reliable across sessions and devices. Preserve conversation history when an application or job becomes closed; define whether new messages remain allowed for rejected, withdrawn, hired, or archived applications before implementation. Do not expose private message content through public channels, logs, or unrelated notifications. Queued broadcasting requires a functioning queue worker; the feature must document its local development requirements. Do not install or depend on a paid hosted WebSocket provider.

**Acceptance criteria:** Candidate and authorized employer can open the conversation for the correct application; unrelated candidates and employers cannot view it even when they guess IDs or channel names; a sent message is saved and remains available after refresh/relogin; the recipient receives the message in real time when connected and can retrieve it later when disconnected; conversation list previews, timestamps, unread counts, and read state stay consistent; new-message notifications are delivered to the correct recipient through Feature 25; private channel authorization is enforced; the feature works with the approved local Reverb/Echo configuration or the project's verified equivalent; and message history is paginated.

**Tests:** Conversation creation/uniqueness per application; candidate ownership; employer/company scoping; unauthorized and revoked participant access; forged sender/application/company IDs; empty/oversized message validation; rate limiting; persistence and retrieval; broadcast only after commit; private channel authorization; unread/read state per participant; notification recipient and duplicate handling; disconnected-recipient retrieval; and real-time broadcast behavior using the project's supported testing approach.

**Deliverables:** Application-specific conversation and message workflow, secured persistence and private broadcasting, responsive messaging UI, integration with in-app notifications, required local Reverb/queue setup documentation, and backend/frontend tests.

## Feature 36 — Recruiter notes and candidate evaluation

**Objective:** Let authorized employers keep private application-specific notes and record a consistent, human-controlled evaluation of candidates during recruitment.

**Dependencies:** Feature 23, Feature 24. Feature 26 may be integrated for interview-specific feedback. Feature 28 is required only when company hiring-team members need shared or role-restricted access to notes and evaluations.

**In scope:** Add, view, and edit private notes associated with an application; identify the author and timestamps; provide a simple evaluation scorecard with an approved set of criteria (for example relevant experience, technical skills, communication, and interview performance); allow authorized reviewers to record ratings and supporting comments; and show the evaluation history or latest assessment to permitted company reviewers. Keep notes and evaluations within the existing employer application detail workflow.

**Out of scope:** Candidate-visible feedback, public reviews, AI-generated evaluations, automated candidate ranking, automatic rejection/hiring decisions, personality inference, and complex configurable assessment forms unless separately approved.

**Rules:** Notes and evaluations are private recruitment data and must never be returned to the candidate or an unrelated company. Every read and write must be scoped to the application and the user's current company permissions. Record the author and timestamps for accountability. Define whether notes can be edited/deleted and how the history is retained before implementation. Use a documented, consistent rating scale if ratings are included; do not present a score as an objective or automated hiring decision. Do not allow notes or evaluations to change application status automatically; status changes must continue through Feature 24. If Feature 28 is enabled, enforce the relevant company-scoped permissions for viewing, creating, editing, and deleting notes/evaluations.

**Acceptance criteria:** An authorized employer can create and manage private notes for an application; permitted reviewers can record and view the defined evaluation criteria; candidates and unrelated employers cannot access the notes or evaluation; each entry identifies its author and timestamps; evaluation data does not change application status; and the existing applicant workflow remains usable when no evaluation has been recorded.

**Tests:** Authorized note/evaluation creation and retrieval; unauthorized candidate access; cross-company and guessed-ID denial; permission changes/revocation; validation of ratings and comments; author/timestamp persistence; edit/delete/history behavior according to the approved policy; protection against status changes through evaluation input; and privacy of Inertia props/responses.

**Deliverables:** Private application notes, a simple recruiter evaluation scorecard, integration with employer application details, authorization rules, and backend/frontend tests.

## Feature 37 — Visual recruitment pipeline

**Objective:** Give employers a visual overview of their applications by displaying candidates in columns based on the existing application statuses.

**Dependencies:** Feature 23, Feature 24. Feature 28 is required only if company hiring-team members will use the board with scoped permissions.

**In scope:** Provide a Kanban-style board for an authorized company; display application cards grouped by the statuses already defined in Feature 24; show only necessary candidate/job summary information; support filtering by job and other approved criteria; show counts per status; and allow an authorized user to move an application to another status through the existing Feature 24 transition workflow. Provide an accessible alternative to drag-and-drop (such as a status action menu) and useful empty/loading states.

**Out of scope:** New application statuses, a separate status engine, automatic candidate ranking, AI-based prioritization, bulk status changes, and cross-company or global recruiter boards.

**Rules:** Feature 24 remains the single source of truth for allowed status transitions and status history. A drag-and-drop action must call the same authorized server-side transition process as any other status update; never update a status directly from the client. Every board query must be scoped to the current user's authorized company and permitted jobs. Do not expose private notes, evaluations, resumes, or message content on application cards. Keep the board bounded through server-side filtering and pagination or an equivalent controlled loading strategy; do not load every application into the browser. Preserve keyboard accessibility and a non-drag interaction. If Feature 28 is enabled, honor the member's company-scoped permissions for viewing and moving applications.

**Acceptance criteria:** Authorized employers can view their company's applications grouped by the existing statuses; filters and counts reflect the server-side result; applications from other companies are never exposed; moving a card succeeds only when Feature 24 permits the transition; every successful move records the existing status history and updates the board; invalid/stale transitions return a useful error and do not corrupt the board; and all board actions are usable without drag-and-drop.

**Tests:** Company/application scoping; pagination or bounded loading; filters and status counts; valid and invalid status transitions; authorization denial; stale/double transition handling; status-history integrity; keyboard/status-menu operation; and ensuring private notes, evaluations, resumes, and messages are not exposed in board payloads.

**Deliverables:** Responsive Kanban-style recruitment board, server-driven filters/counts, integration with Feature 24 transitions, accessible status controls, and backend/frontend tests.

---

## Later capabilities and product boundaries

- Feature 33 (employer plans, payments, and promoted jobs) is deferred and is not required for the portfolio project. Do not install a payment provider, add subscription enforcement, or make job posting/application depend on payment unless the product scope is explicitly changed.
- Feature 34 is rule-based job recommendation only. Do not add AI models, LLM APIs, embeddings, or automated employment decisions as part of this feature. Any future AI capability must be proposed and approved as a separate scope with privacy, fairness, transparency, and human-oversight requirements.
- Feature 35 adds application-based real-time messaging. It does not authorize unrestricted direct messages, public chat, file attachments, audio/video calls, or paid third-party messaging services.
- Feature 36 contains private recruiter notes and human-controlled evaluations. Do not expose internal notes or scorecards to candidates or use them to automate hiring decisions.
- Feature 37 is a visual interface over the existing Feature 24 application-status workflow. Do not introduce a second transition engine or bypass the existing transition rules/history.
- External calendar integrations, SSO/social login, video interviews, and other integrations remain separate capabilities unless the roadmap is explicitly revised.
- Company hiring-team membership and company-scoped roles belong to Feature 28; do not treat an employer account as an implicit member of every company. Features 35 and 36 must respect Feature 28 if hiring-team access is enabled.
- Prefer a working, verifiable vertical slice per feature over implementing the entire roadmap in one prompt.
