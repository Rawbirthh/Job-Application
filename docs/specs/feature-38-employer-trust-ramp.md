# Feature 38 — Employer Trust Ramp: Verified Email and Re-inquiry Cooldown

- **Feature:** 38
- **Slug:** `employer-trust-ramp`
- **Status:** IMPLEMENTED (approved 2026-11-16; implemented and verified same day)
- **Scope type:** Trust/safety hardening of the existing employer-onboarding flow (Feature 11); re-enables the email-verification machinery deliberately deferred by Feature 2 (decision A1), scoped narrowly to employer capability only
- **Explicitly unchanged:** candidate-facing flows (register, login, browse, apply, messaging) have **no** verification requirement; every job still passes through `pending_review`; no publish-without-review path of any kind is introduced; no new permission, role, package, or migration

## 1. Goal

1. A user must have a verified email address before they can submit an employer
   onboarding request, and an administrator must not be able to approve the
   request of an unverified applicant — enforced server-side on both paths.
2. After an employer request is rejected, the same user may submit a new request
   only after a **7-day cooldown** from the recorded rejection timestamp
   (`reviewed_at` on `employer_onboarding_requests`).

This closes the two cheapest trust gaps in the employer upgrade flow without
moving any friction onto candidates and without changing publication semantics.

## 2. Non-goals

- Any verification gate on candidate registration, login, browsing, applying,
  saved searches, or messaging. Only the employer capability path is gated.
- Any auto-publish / graduated-autonomy / trusted-employer bypass of
  `pending_review` (considered and explicitly declined; see Assumption A3).
- MX/domain matching automation, free-mail blocklists, work-email domain checks,
  or verification of company registration numbers (reviewed and declined for
  portfolio scope).
- Changes to job moderation, the re-review rule, or the jobs lifecycle.
- Email-based notifications for other events (application updates etc.) —
  out of scope; verification mail is the only new outbound email.
- Payment/plans (Feature 33 remains deferred).
- A general rebuild of Fortify or replacement of session-based auth.

## 3. Current-state evidence

- `config/fortify.php:163–175` — `Features::emailVerification()` is commented out;
  registration and password reset remain enabled; no verification routes are
  registered (`php artisan route:list --name=verification` → no routes).
- `app/Providers/FortifyServiceProvider.php` — `Fortify::verifyEmailView()` is
  **already registered** and renders `auth/verify-email`, but that page was
  removed in Feature 2 (A1); recreating the page is required for the view
  callback to resolve. `Fortify::registerView()` likewise in place.
- `app/Models/User.php` — does not implement `MustVerifyEmail`; `email_verified_at`
  is `datetime`-cast (`'email_verified_at' => 'datetime'`). No global verification
  gate exists anywhere (`verified` middleware was removed in Feature 2).
- `database/factories/UserFactory.php` — default `email_verified_at => now()`;
  `unverified()` state sets it to `null`. Existing employer tests create
  job-seekers via factory → verified by default.
- `database/migrations/0001_01_01_000000_create_users_table.php` — `users.email_verified_at`
  exists (nullable timestamp). `employer_onboarding_requests` has
  `submitted_at`, `reviewed_at`, `reviewed_by_id`, `review_note` (all nullable,
  `reviewed_at`/`submitted_at` datetime, `reviewed_by_id` FK→users).
- `app/Services/Employer/EmployerOnboardingService.php` — `submit()` locks the
  user row and allows only `status === null || status === Rejected` (otherwise
  ValidationException); `updateOrCreate` preserves re-application semantics.
  `approve()` locks, requires `status === Pending`, then sets `Approved`,
  updates the request row (`reviewed_at`, `reviewed_by_id`), assigns the
  employer role via `RoleAssignmentService`, and writes an audit record.
  `reject()` sets `reviewed_at` + `review_note` on the request row and sets
  `users.status = Rejected`.
- `app/Http/Controllers/Employer/EmployerOnboardingController.php` — `index()`
  loads the request (explicit column select) and passes `status`, `request`,
  `suspension_reason`, `can_apply` (`status === null || Rejected`), and
  `can_access_employer_dashboard`. `store()` authorizes via
  `EmployerOnboardingRequestPolicy@create` and delegates to the service.
- `app/Http/Controllers/Admin/EmployerController.php` — admin index/show already
  scoped (`User::employerApplicants()`); selects explicit column lists for users
  and the onboarding request; `show()` exposes `actions` booleans
  (`approve` only when status is `Pending`); approve/reject/suspend/reactivate
  authorize through `employers.review`/`employers.suspend` etc. policies.
- `resources/js/pages/employer/onboarding.tsx` — single-page form; shows
  "verify your email" nothing today. Receives typed `EmployerOnboardingPageProps`.
- `tests/Feature/Auth/EmailVerificationDisabledTest.php` — codifies the disabled
  state: (1) `verification.*` routes absent, (2) registration sends nothing,
  (3) unverified users can access dashboard/profile, (4) shared `auth.user`
  payload exposes only `id|name|email` and **no `email_verified_at`**.
- `resources/views/app.blade.php` — no `verified` anywhere.
- Shared auth payload (`HandleInertiaRequests`) — allow-listed
  `id|name|email|roles|permissions|employer_status`; `email_verified_at` is
  intentionally excluded.
- `.env.example` — `MAIL_MAILER=log`; local verification links therefore land in
  `storage/logs/laravel.log`; production requires SMTP (not committed).

## 4. Assumptions and explicit deferrals

- **A1 — Reusing Fortify machinery re-enables verification for everyone.**
  Re-enabling `Features::emailVerification()` makes Fortify register the
  `/email/verify` notice, `/email/verification-notification` resend, and
  signed `/email/verify/{id}/{hash}` endpoint, and makes the `Registered`
  listener send a verification email at registration (the listener only acts
  when the user implements `MustVerifyEmail` and is unverified). Candidate flows
  gain a **notification**, never a **gate** — no `verified` middleware is
  attached to any application route; the employer gate is enforced in the
  onboarding service (and mirrored in UI), not by the `verified` middleware.
- **A2 — Registration verification mail is desirable, not tolerable-by-accident.**
  Standard Laravel behavior; users verify once and both paths (employer button
  and any future verified-gated UX) benefit. Not a per-candidate opt-in.
- **A3 — Graduated autonomy declined.** The overview considered and declined
  trusted-employer auto-publish for this portfolio version (Option B).
- **A4 — Cooldown source of truth.** Cooldown is computed from
  `employer_onboarding_requests.reviewed_at` of the user's **current** onboarding
  request row, which `reject()` always populates. If a `Rejected` user somehow
  has `reviewed_at = null` (should not occur), the cooldown does not apply
  (fail-open availability, documented); the approval path remains fully guarded
  regardless.
- **A5 — `users.status` semantics unchanged.** `status` remains the employer
  lifecycle enum (`pending|approved|rejected|suspended`), distinct from
  verification (`email_verified_at`). Verified ≠ approved employer; unverified
  users remain able to use every candidate capability.
- **A6 — Mail delivery.** Local development uses `MAIL_MAILER=log` (verification
  URL visible in `storage/logs/laravel.log`) or a Mailtrap-compatible inbox;
  deployed environments require configured SMTP held in `.env` (never committed).
  The feature ships with a short README section documenting this.
- **A7 — No new permission.** Employer review continues through
  `employers.review` / `employers.manage`; no catalogue change, no seeder touch.
- **A8 — `MustVerifyEmail` contract.** Adding the contract to the model enables
  Fortify's verification flow and `$user->hasVerifiedEmail()`; it changes no
  access control because no `verified` middleware gates exist and none are added.

## 5. User flows and acceptance criteria

### F1 — Registration sends a verification email (candidate, unchanged gates)

A guest registers. After the `Registered` event, the verification notification is
sent (user is unverified at that moment). The user is authenticated and lands on
`/dashboard` as today; nothing is blocked.

**Acceptance criteria**

- `Notification::fake()` + register → `SendEmailVerificationNotification` sent
  once to the new user.
- Guest → register → dashboard still works; login, logout, applications,
  saved jobs, search all behave exactly as before.
- No `verified` middleware protects any route; a fully unverified candidate
  retains 100% of candidate functionality.
- Shared `auth.user` payload still exposes only `id|name|email|roles|permissions|employer_status` — `email_verified_at` is not added.

### F2 — Unverified user sees a verify-first state on Employer Onboarding

An unverified authenticated user opens `/employer/onboarding`.

**Acceptance criteria**

- Page shows a locked "Verify your email" card (mail icon, explanation,
  **Resend verification email** button) instead of the form.
- A **direct `POST /employer/onboarding`** from an unverified user is rejected
  server-side with a field-level `status` error ("Verify your email address
  before applying to become an employer.") and creates/updates nothing.
- Inertia prop `is_verified: false` drives the UI; server is authoritative.

### F3 — Resend verification email

The user clicks **Resend verification email** (employer page and/or the
`/email/verify` notice page).

**Acceptance criteria**

- `POST /email/verification-notification` (Fortify `verification.send`,
  `throttle:6,1`) resends the signed link; caller sees a flashed
  `verification-link-sent` status and an accessible success message.
- Guests cannot resend (route is `auth`); a user who is already verified gets a
  benign redirect with the same status (Fortify's own behavior) — no leak of
  whether the mailbox exists.

### F4 — Verified user applies

An unverified user clicks the signed link (or a verified user opens the page).

**Acceptance criteria**

- `GET /email/verify/{id}/{hash}` (signed, `auth`) marks `email_verified_at`,
  fires `Verified`, and redirects to `/dashboard?verified=1` (Fortify default
  home) or the intended URL.
- The employer onboarding page then renders the normal form; `POST` succeeds;
  pending state renders exactly as today.
- `employer/onboarding` props include `is_verified: true`.

### F5 — Admin cannot approve an unverified applicant

An administrator opens `/admin/employers/{user}` for a **pending** request whose
applicant is unverified (registered before this feature shipped, or verification
mail ignored).

**Acceptance criteria**

- Show page displays an **"Email not verified"** badge and renders the Approve
  action disabled (client affordance only; explanation text).
- The index queue marks such pending applicants with the same badge.
- A **direct `POST /admin/employers/{user}/approve`** for an unverified
  applicant is rejected with a visible error; `users.status` stays `pending`,
  the request row's `reviewed_at`/`reviewed_by_id` stay `null`, no role
  assignment occurs, and an error message is returned (no audit entry — this is
  a validation failure, not an admin action; see §13).
- After the applicant verifies, approve succeeds and behaves exactly as today.

### F6 — 7-day re-inquiry cooldown after rejection

A rejected user returns to `/employer/onboarding`.

**Acceptance criteria**

- Form is shown (`can_apply` reflects cooldown: `false` during cooldown,
  `true` on or after day 7), but submitting within the cooldown is rejected
  server-side **inside the `lockForUpdate` transaction** with a `status` error
  naming the date they may try again, e.g. "You can request employer access
  again after {date}."
- No request row is written/updated and `users.status` is unchanged during the
  cooldown (existing `updateOrCreate` semantics are never reached).
- On or after day 7, submission behaves as today (resubmission supersedes the
  rejected row via `updateOrCreate`, clearing `reviewed_at`).
- Cooldown computation uses `reviewed_at` (server clock), never client input.
- Throttle `throttle:10,1` remains unchanged.

### F7 — Regression: verification is never a global gate

**Acceptance criteria**

- Dashboard, profile settings, public jobs, applications, and messaging all
  reachable by an `unverified()` user (the Feature 2 regression case survives).
- `EmailVerificationDisabledTest` is **replaced** by
  `tests/Feature/Auth/EmailVerificationTest.php`; the shared-payload allow-list
  assertion (`id|name|email`, no `email_verified_at`) is carried over verbatim.

## 6. Routes, middleware, and authorization boundary

| Method | URI                                | Name                        | Middleware                                          | Change                                                                                     |
| ------ | ---------------------------------- | --------------------------- | --------------------------------------------------- | ------------------------------------------------------------------------------------------ |
| GET    | `/email/verify`                    | `verification.notice`       | `web`, `auth`                                       | **Registered** (Fortify re-enabled); renders recreated `auth/verify-email` page            |
| POST   | `/email/verification-notification` | `verification.send`         | `web`, `auth`, `throttle` (Fortify default 6/min)   | Registered with the feature                                                                |
| GET    | `/email/verify/{id}/{hash}`        | `verification.verify`       | `web`, `auth`, `signed`, throttle (Fortify default) | Registered with the feature                                                                |
| GET    | `/employer/onboarding`             | `employer.onboarding.index` | existing                                            | No route change; controller adds `is_verified` prop and computes `can_apply` with cooldown |
| POST   | `/employer/onboarding`             | `employer.onboarding.store` | existing (`throttle:10,1`)                          | No route change; **service** rejects unverified applicants and cooldown violators          |
| POST   | `/admin/employers/{user}/approve`  | `admin.employers.approve`   | existing (`permission:employers.manage` + policy)   | No route change; **service** rejects unverified applicants                                 |

- No `verified` middleware is applied to any route. Candidate and public
  boundaries are untouched.
- Authorization split (defense-in-depth, both required): the Fortify
  verification feature owns link issuance/verification; the onboarding service
  owns the eligibility gate; the admin controller keeps `employers.review` /
  `employers.view` policy checks unchanged.
- No Policy changes are needed or permitted; `EmployerOnboardingRequestPolicy@create`
  remains the store boundary for other concerns (auth only).

## 7. Form Requests and validation rules

- **No new Form Request.** `StoreEmployerOnboardingRequest` is unchanged
  (server-side validation/normalization stays as-is; the gate is not a
  validation rule because it must run after the row lock).
- `EmployerOnboardingService::submit()` — after acquiring the row lock and
  before the duplicate/pending check, if `$locked->email_verified_at === null` →
  `ValidationException::withMessages(['status' => 'Verify your email address before applying to become an employer.'])` (422 session errors via Inertia).
- `EmployerOnboardingService::submit()` — after the pending/rejected status
  check: if `$locked->status === Rejected` and
  `$locked->employerOnboardingRequest?->reviewed_at !== null` and
  `now()->lessThan($reviewed_at->copy()->addDays(7))` →
  `ValidationException::withMessages(['status' => 'You can request employer access again after {reviewed_at->addDays(7)->toFormattedDateString()}.'])`.
- `EmployerOnboardingService::approve()` — after the row lock and the
  `Pending` check: if `$locked->email_verified_at === null` →
  `ValidationException::withMessages(['status' => 'The applicant has not verified their email address.'])` — thrown before any role assignment or request-row update, inside the existing transaction.
- Cooldown day arithmetic uses `now()` (Carbon) so tests can `Carbon::setTestNow()`.

## 8. Models, relationships, migration, constraints, and indexes

- **No migration.** `users.email_verified_at` (nullable timestamp) and
  `employer_onboarding_requests.reviewed_at` both exist and are cast to Carbon.
- `User`: add `Illuminate\Contracts\Auth\MustVerifyEmail` to the interface list
  (keeps `PasskeyUser`); no casts/fillable changes; `email_verified_at` stays
  non-fillable (already is).
- `EmployerOnboardingRequest`: unchanged.
- Deletion semantics: unchanged (no deletes involved).
- Indexes: cooldown reads the user's request row by `user_id` (unique index
  exists — the `updateOrCreate([])` pattern already relies on it). No new index.

## 9. Query, payload, pagination, and scale plan

- Onboarding `index()`: existing payload select (`id,user_id,company_name,company_website,job_title,message,submitted_at,reviewed_at,review_note`) — unchanged; new `is_verified` prop is a scalar from the locked-free `$request->user()` instance (no query).
- `can_apply` computation: needs `reviewed_at` of the user's request → already eager-loaded in `index()` via `load(['employerOnboardingRequest' => ...])`. Select `reviewed_at` (already selected); no extra query.
- Admin `index()`: add `'users.email_verified_at'` to the explicit `select([...])`; compute `email_verified: bool` per row. No relationship or pagination change (25/page stays; prefix/search/order unchanged).
- Admin `show()`: same addition; boolean only — no raw timestamp is exposed (keeps Feature 2's payload hygiene intent at the admin boundary; admin already sees `created_at`).
- Approval lock read: `whereKey(...)->lockForUpdate()->firstOrFail()` already loads the full user; nothing new (the gate needs only `email_verified_at`, present in the loaded instance).
- No list endpoints added; pagination untouched; N+1 unchanged; verification state is one column on an already-scanned row.

## 10. Service / Repository decision

- **No new service, no repository, no caching layer.** The gates live in
  `EmployerOnboardingService`, the existing transactional boundary for this
  domain — the same place the pending/duplicate check and approval invariants
  already live, and inside the same `lockForUpdate` transactions (which is what
  makes the unverified/cooldown checks race-safe under concurrent requests).
- Fortify keeps ownership of link issuance, signing, expiry (default 60 min
  `auth.verification.expire`), and verification marking — reimplementing any of
  that would duplicate framework behavior for no gain.
- "Resend" needs no backend code: the Fortify route + Wayfinder action import.

## 11. Inertia props and React plan

**Employer onboarding page** (`resources/js/pages/employer/onboarding.tsx`):

- New prop: `is_verified: boolean`. Extend `EmployerOnboardingPageProps`
  (`types/authorization.ts`) accordingly.
- When `is_verified === false`: render a `Card` with a `MailCheck` icon,
  title "Verify your email", body text, and a **Resend verification email**
  `Button` wired through `useForm({}).post(verificationSend.url())` (Inertia
  visit; `/email/verification-notification` returns back with flashed status —
  not a 204). Show flashed status as an accessible `role="status"` line when
  `page.props.flash.status`/session status is `verification-link-sent`
  ("Verification link sent — check your inbox."). The form card is not rendered.
- When `is_verified === true`: current behavior, including cooldown-aware
  `can_apply` (server-computed; UI shows the cooldown message from the field
  error when the user bypasses the disabled submit).
- `can_apply === false` for the cooldown case shows a muted note under the
  rejected alert: "You can request again after {date}." using `request.reviewed_at`
    - 7 days **only as a display hint**; the server is authoritative.

**Auth pages**:

- Recreate `resources/js/pages/auth/verify-email.tsx` (the Feature 2-removed
  page), reusing `AuthSimpleLayout` + existing shadcn primitives: heading,
  explanatory copy, resend button (`useForm({}).post(verification.send.url())`),
  `role="status"` for the `verification-link-sent` flash, a "Back to dashboard"
  link. `Fortify::verifyEmailView()` already renders this page with `status`.
- No changes to login/register/forgot/reset/2FA pages; registration behavior
  gains only the outbound mail.

**Admin employers pages**:

- `admin/employers/index.tsx`: render a small "Unverified" badge next to the
  status badge for pending rows with `email_verified === false`.
- `admin/employers/show.tsx`: render an "Email not verified" badge near the
  actions; when status is `pending` and `email_verified === false`, render the
  Approve button `disabled` with helper text "Applicant must verify their
  email first." (client affordance; server remains authoritative).
- New prop on both: `email_verified: boolean` per row / on the employer payload.

**Types**: add `is_verified: boolean` to `EmployerOnboardingPageProps`;
extend the admin employer row/payload types with `email_verified: boolean`.
`Auth`/shared props unchanged.

## 12. Test matrix

| Area                                 | Case                                                                                                                                                      | Expected evidence                                                |
| ------------------------------------ | --------------------------------------------------------------------------------------------------------------------------------------------------------- | ---------------------------------------------------------------- |
| F1 registration                      | `Notification::fake()`; register; assert `SendEmailVerificationNotification` sent to the new user                                                         | exactly one notification                                         |
| F1 dashboard regression              | `User::factory()->unverified()` → `GET /dashboard` 200                                                                                                    | Feature 2 regression preserved                                   |
| Shared payload                       | Unchanged allow-list; no `email_verified_at` key                                                                                                          | `EmailVerificationTest` carries the Feature 2 assertion verbatim |
| F2 onboarding gate (page)            | Unverified user → `GET /employer/onboarding` → `is_verified=false`; resend card visible                                                                   | Inertia prop assertion                                           |
| F2 onboarding gate (POST)            | Unverified user → direct POST → session error `status`; `assertDatabaseMissing('employer_onboarding_requests', ...)`; `users.status` stays `null`         | field error; nothing written                                     |
| F3 resend                            | `POST /email/verification-notification` as unverified user → 302/redirect; resend notification sent                                                       | `verification.send` throttle not exceeded in test                |
| F3 resend guest                      | Guest → `POST verification.send` → redirect to login                                                                                                      | guest boundary                                                   |
| F4 signed verify                     | Use `URL::signedRoute('verification.verify', ...)`; hit URL → `email_verified_at` set; user is verified                                                   | signed-link flow                                                 |
| F4 submit after verify               | Verify user → POST valid payload → pending state as Feature 11                                                                                            | happy path                                                       |
| F5 approve blocked                   | Seed pending unverified applicant → admin POST approve → session errors; `status` stays `pending`; `reviewed_at` null; employer role not assigned         | defense-in-depth                                                 |
| F5 approve after verify              | Same, but applicant verifies first → approve 200/redirect; role assigned; audit recorded                                                                  | happy path                                                       |
| F5 invalid token                     | Expired/forged `verification.verify` URL → 403/419 (signed middleware) and no status change                                                               | signed middleware contract                                       |
| F6 cooldown inside                   | Reject via service at t₀ → `Carbon::setTestNow(t₀+6days)` → POST → blocked; `assertDatabaseMissing` update (reviewed_at unchanged); message contains date | boundary inside                                                  |
| F6 cooldown boundary                 | `t₀+7days` exactly → POST succeeds; `reviewed_at` cleared to null; status `pending`                                                                       | boundary on/after                                                |
| F6 cooldown null-safe                | Rejected request row with `reviewed_at = null` (forced) → POST allowed (documented A4)                                                                    | fail-open                                                        |
| F6 submit blocked is pending-guarded | Pending user resubmits → still blocked by the existing pending rule, unaffected by cooldown                                                               | regression                                                       |
| Existing suite                       | `EmployerOnboardingTest` (factory-verified users) — all cases stay green                                                                                  | regression                                                       |
| `EmailVerificationDisabledTest`      | **Deleted** and replaced by `EmailVerificationTest` (F1–F7 assertions above + dashboard regression)                                                       | test file replaces                                               |
| Throttle                             | `POST verification.send` > 6/min → 429                                                                                                                    | existing Fortify limiter                                         |
| Audit                                | Approve-succeed writes audit (existing); approve-blocked writes **no** audit entry                                                                        | §13 decision                                                     |
| Authorization                        | Guest blocked from onboarding/admin approve; non-pending approve still blocked (existing kinds)                                                           | regression                                                       |

## 13. Security and performance risks

- **Race conditions:** verification/cooldown gates run inside the same
  `DB::transaction` + `lockForUpdate` as the existing invariant checks — a
  concurrent submit cannot slip past between check and write.
- **Enumeration:** Fortify's resend returns the same `verification-link-sent`
  status regardless of mailbox state; notice page shows only the user's own
  email; no cross-account leakage.
- **Signature abuse:** signed middleware + 60-minute expiry; forged/expired
  links are rejected before any model write.
- **Mass assignment:** no new fillable; `email_verified_at` remains unsettable
  via requests (registration action maps only name/email/password).
- **Approval integrity:** the guard throws **before** role assignment and before
  the request row update, inside the transaction → zero partial writes.
- **Cooldown bypass via `updateOrCreate`:** the gate is evaluated before the
  update path; cooldown user cannot reset `reviewed_at` (no code path reaches
  it).
- **Audit semantics decision:** blocked approve and cooldown denials are
  **validation failures**, not admin actions — no audit entry (keeps the audit
  log meaningful; all actual admin decisions — approve/reject/suspend/
  reactivate — remain logged exactly as before). Documented, deliberate.
- **Performance:** one extra boolean column on two already-bounded admin
  queries; no list endpoint changes; verification adds zero queries to
  candidate paths; the `/email/verify` endpoint is one signed indexed-by-id
  user lookup + update.
- **Secret/mail hygiene:** SMTP credentials stay in `.env`; the log mailer
  discloses links only to local disk; README documents this boundary.
- **Rollout compatibility:** dev DB users seeded pre-feature with factory data
  are already verified; any legacy unverified account can self-serve via resend;
  no data backfill required; reverting is `comment the Fortify feature + remove
  gates` with no migration to unwind.

## 14. Rollout, compatibility, and documentation

- Enable step order matters only in that the Fortify feature + `MustVerifyEmail`
  ship together with the gates in one commit.
- README gains a short "Email verification (employer onboarding)" subsection:
  how to fetch the link from `storage/logs/laravel.log` (log mailer), optional
  Mailtrap, production SMTP note, and the fact that candidate flows are never
  gated.
- `project-overview.md` Feature 38 section is the source of truth for scope;
  implementation updates it only if an approved scope change occurs (none
  expected).
- No production data migration; no maintenance window.

## 15. Implementation sequence

1. Re-enable `Features::emailVerification()`; add `MustVerifyEmail` to `User`.
2. Recreate `auth/verify-email.tsx`; confirm `Fortify::verifyEmailView()` resolves.
3. Add gates to `EmployerOnboardingService` (submit: verification then cooldown;
   approve: verification) — inside the existing transactions, before any writes.
4. Extend controller payloads: onboarding `index()` → `is_verified`; admin
   index/show → `email_verified` boolean (add `users.email_verified_at` to their
   explicit `select([...])` lists).
5. Frontend: onboarding page verify-first card + resend via Inertia; cooldown
   note; admin Unverified badges + disabled Approve affordance; types updated.
6. Replace `EmailVerificationDisabledTest` with `EmailVerificationTest`; extend
   `EmployerOnboardingTest` (or add `EmployerOnboardingTrustRampTest`) for F2–F6;
   adjust any helper that assumes `unverified()` state semantics.
7. `npm run build` (Wayfinder picks up `verification.*` routes),
   `npm run types:check`, `npm run check`, `vendor\bin\pint`,
   `php vendor/bin/phpstan analyse`, `php artisan test`.
8. Update README; record result against Feature 38 in `project-overview.md`
   only if scope changed; STOP for review.

## 16. Verification commands

```text
php artisan test --filter=EmailVerificationTest
php artisan test --filter=EmployerOnboarding
php artisan route:list --name=verification
npm run types:check
npm run check
composer ci:check
```

Manual smoke (log mailer): register with a fresh email → `grep -i "verify" storage/logs/laravel.log` → open the signed URL → confirm `email_verified_at` set → confirm employer onboarding unlocks; confirm a rejected re-inquiry inside 7 days shows the dated error.

## 17. Approved decisions

Approved by the user in conversation on 2026-11-16 ("I approved @docs/specs/feature-38-employer-trust-ramp.md implement it"). Assumptions A1–A8 stand as written; no amendments were made.

Outcomes recorded after implementation and full verification (`composer ci:check` — 890 passed / 7077 assertions, phpstan 0 errors, pint, `tsc`, `vp check`; suite grew from 867 to 890 tests):

- All §5 flows (F1–F7) implemented and covered by automated tests (`EmailVerificationTest` replaces `EmailVerificationDisabledTest`; new `EmployerOnboardingTrustRampTest`; `EmployerOnboardingTest` resubmission case updated for the 7-day cooldown).
- One pre-existing test required a cooldown-aware update: `EmployerOnboardingTest::test_rejected_applicant_can_resubmit_and_reuses_the_same_row` (travels 8 days past rejection before resubmitting).
- No migration, no new permission, no new service or repository, no package changes, as specced.

Independent `code-reviewer` pass performed (2026-11-16); findings reconciled:

- **W1** — profile email change reset verification silently. Fixed: `ProfileController::update` now sends a verification notification when the email changed (plus a toast mentioning it), asserted by an updated `ProfileUpdateTest`.
- **W2** — `reactivate()` re-granted the employer role with no verification check and no admin affordance. Fixed: the same gate as `approve()` (blocks before any write, zero audit), badge now shown for pending **and** suspended unverified rows, Reactivate disabled client-side when unverified; covered by `test_blocked_reactivation_of_unverified_suspended_employer` (block + re-verify + unblock).
- **W3** — spec test-matrix gaps closed: no audit entry on blocked approve/reactivate, explicit `limiters.verification` (6/min, named limiter in `FortifyServiceProvider`) with a 429 test, exact cooldown-date message assertion, expired-signature `assertForbidden`, already-verified resend benign (no mail, no session status), admin payload `missing('employer.email_verified_at')`.
- **I1** — `can_apply` now also requires `is_verified` (dev-time safety tightening; UI behaviour unchanged). **Deviation from spec §9/§11, documented.**
- **I2** — raw session `flash_status` pass-through replaced by `verification_link_sent: bool`. **Deviation from spec §11, documented.**
- **I3** — unverified users whose employer status needs no verification action (pending/approved/suspended) now see the normal page with a slim amber banner + resend CTA instead of the blocking verify-first card; the approved employer keeps the dashboard link.
- **I4** — cooldown display date is now server-computed (`retry_after` ISO prop), replacing client-side `reviewed_at + 7 days` math.
- **I5** — dead `$employerQuery` builder in admin `show()` removed.
- **I7/I8** — verification limiter now explicit in-repo (`config/fortify.php` + named limiter). **I6** — point-in-time verification (post-approval email change resets verification) is the deliberate scope; recorded, not mitigated.
- **Post-approval addendum (2026-11-16, user-requested):** verification email is now queued — `app/Notifications/QueuedVerifyEmail.php` (`extends VerifyEmail implements ShouldQueue`), sent via a `User::sendEmailVerificationNotification()` override, through the already-configured `database` queue. Registration/resend respond immediately instead of blocking on SMTP; delivery requires a running worker (`composer dev` / `queue:listen` locally, supervisor in production; without one, notifications accumulate in `jobs`). Tests updated to assert the queued class; phpunit uses `QUEUE_CONNECTION=sync`.
- **Post-approval addendum 2 (2026-11-16, user-requested):** registration no longer sends verification mail. The framework's `SendEmailVerificationNotification` listener is removed via an app-level `booted()` callback in `AppServiceProvider` (app providers boot before the framework provider registers it, so a plain `Event::forget()` in `boot()` was too early). Verification is requested on demand from the employer onboarding verify-first page only. `EmailVerificationTest` asserts registration sends nothing; resend assertions unchanged. In-app `UserRegisteredNotification` to platform admins is unaffected.
