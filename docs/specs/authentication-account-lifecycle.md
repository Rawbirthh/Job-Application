# Feature 2 — Authentication and Account Lifecycle

- **Feature:** 2
- **Slug:** `authentication-account-lifecycle`
- **Status:** Approved — implemented
- **Date:** 2026-10-26
- **Scope type:** Completion and hardening of the existing Fortify session-auth slice
- **Explicit exclusion:** Email verification is **not** part of this spec. The pre-existing verification flow is disabled in this implementation; the user will implement it separately.

## 1. Goal

Deliver the Feature 2 account-lifecycle slice on top of the installed Laravel Fortify foundation:

- Job-seeker registration.
- Login, logout, and session lifecycle.
- Password reset.
- Protected-route boundary for authenticated users.
- Safe post-login and post-logout redirects.
- A documented, non-public first-administrator boundary; the actual provisioning path is deferred to Feature 3.
- Server-side validation, accessible form errors, and responsive authentication pages.
- Regression coverage proving that untrusted input cannot grant privileged account state.

This is primarily a hardening and validation feature. Fortify already provides the routes and controllers; the work is to close product, security, and test gaps without replacing the framework authentication layer.

## 2. Non-goals

- Email verification: not part of this feature. The pre-existing flow is disabled for now: the Fortify feature is off, the `verified` middleware is removed from app routes, `User` no longer implements `MustVerifyEmail`, and the verification UI/routes/tests are removed. Re-enabling it belongs to the separate feature.
- Custom roles, permissions, role middleware, and Policies (Feature 3).
- Assigning a database-backed job-seeker/employer/admin role (Feature 3).
- Employer onboarding, company profiles, jobs, applications, and dashboards beyond the existing placeholder dashboard.
- Replacing Fortify, adding Breeze/Jetstream, or introducing another authentication package.
- Social login, SSO, MFA expansion, or passkey feature work. Existing 2FA/passkey controls remain installed but are out of scope.
- Admin user-management screens, audit logging, API tokens, and Sanctum.
- New database migrations, packages, fonts, images, or UI libraries.
- Rewriting the public homepage or changing the approved palette.

## 3. Current-state evidence

- `config/fortify.php`: `web` guard, `users` password broker, lowercase usernames, `/dashboard` home, registration + password reset + 2FA + passkeys enabled; email verification intentionally disabled.
- `app/Providers/FortifyServiceProvider.php`: Fortify Inertia views, custom create/reset actions, and named limiters for login, 2FA, and passkeys.
- `app/Actions/Fortify/CreateNewUser.php`: validates name/email/password and maps only those fields into `User::create()`.
- `app/Actions/Fortify/ResetUserPassword.php`: validates password rules and persists the new password.
- `app/Models/User.php`: fillable name/email/password; password and secret fields hidden; `email_verified_at` and `password` casts.
- `routes/web.php`: `home` is public; `dashboard` is behind `web` and `auth`; the former `verified` gate is removed.
- `routes/settings.php`: profile, security, delete, and appearance routes use `auth`; security also uses password confirmation.
- `php artisan route:list -v`: 46 routes; Fortify supplies the auth routes. No admin, employer, candidate, company, job, or application routes exist.
- `app/Http/Controllers/Controller.php` is empty and does not use `AuthorizesRequests`. No Policies exist.
- `resources/js/pages/auth/*`: login, register, forgot-password, reset-password, confirm-password, and two-factor pages already exist with Inertia `Form`, field errors, and processing states. The verification page was removed with the disabled feature.
- `app/Http/Middleware/HandleInertiaRequests.php` currently shares the full authenticated user model.
- `database/migrations/0001_01_01_000000_create_users_table.php`: unique `users.email`; `password_reset_tokens.email` primary; `sessions.id` primary, `user_id` indexed, `last_activity` indexed.
- Existing suite at spec time: 42 tests / 157 assertions. Final implementation suite after disabling verification: 61 tests / 316 assertions, all passing.

## 4. Assumptions and explicit deferrals

- **A1 — Verification is disabled.** Fortify's email-verification feature is off, the `verified` middleware is removed from app routes, `User` no longer implements `MustVerifyEmail`, and the verification UI/routes/tests are removed. The `email_verified_at` column, cast, factory state, and profile-email-change reset remain dormant data plumbing for the follow-up. Re-enabling verification belongs to the separate feature.
- **A2 — Roles are deferred.** No role table, role column, default job-seeker assignment, or admin flag is introduced. Registration creates a plain user. Feature 3 will seed the custom roles and assign the candidate capability plus the first administrator.
- **A3 — First-admin provisioning is deferred.** No public admin registration route or privileged request field will be added. The developer-only provisioning path is documented as a Feature 3 responsibility because an “administrator” cannot be meaningfully represented before the custom role schema exists.
- **A4 — 2FA/passkeys are retained, not extended.** They affect the login regression surface but are not Feature 2 acceptance criteria.
- **A5 — No new schema, dependencies, or routes** are required for the core Feature 2 slice.

## 5. User flows and acceptance criteria

### F1 — Registration

A guest visits `/register`, submits name, email, password, and confirmation. Valid input creates a user, authenticates the session, regenerates the session ID, lowercases the email, hashes the password, and redirects to the intended URL or `/dashboard`.

Acceptance criteria:

- The registration page renders responsively with labelled fields and a processing state.
- Duplicate email, invalid email, missing name, weak password, and mismatched confirmation return server-side field errors.
- Extra payload fields such as `role`, `role_id`, `is_admin`, `permissions`, `account_status`, or `email_verified_at` do not set privileged state.
- No admin self-registration route exists.
- Registration copy identifies the initial job-seeker path without claiming a role has been assigned.

### F2 — Login

A guest visits `/login`, submits email and password. Valid credentials establish a session and redirect to the intended protected URL or `/dashboard`. Invalid credentials return the generic authentication error.

Acceptance criteria:

- Invalid credentials do not authenticate the user and do not reveal whether the email exists.
- Login is limited to five attempts per minute per normalized email + IP.
- The session identifier changes after a successful login.
- “Remember me” remains functional.
- Existing 2FA challenge behavior is not regressed.
- User-supplied `redirect`/`url` fields cannot redirect to an external host.

### F3 — Logout

An authenticated user posts to `/logout`. The guard logs the user out, the session is invalidated, the CSRF token is regenerated, and the response redirects to the public home page.

Acceptance criteria:

- The user is a guest after logout.
- The old session is no longer authenticated.
- A subsequent protected request redirects to login.
- No open redirect is introduced.

### F4 — Password reset

A guest requests a reset link at `/forgot-password`; a valid account receives a notification. The reset page accepts a valid token and a new password, then redirects to login with a status message.

Acceptance criteria:

- The request and reset screens render.
- A valid token resets the password; the old password no longer works.
- Invalid/expired tokens and mismatched/short passwords return useful errors.
- The reset action does not authenticate the browser or expose the password.
- Existing reset-link behavior is preserved unless a separate approval adds generic responses or reset-request throttling (see Risks).

### F5 — Protected access and intended redirects

A guest visits `/dashboard` or a protected settings page and is redirected to login. After a successful login, the user is returned to the originally requested URL when it is safe and local; otherwise the redirect falls back to `/dashboard`.

Acceptance criteria:

- Guests cannot read protected pages.
- The intended URL is restored after login.
- The fallback is the named dashboard route, not a hard-coded external URL.
- Authenticated users visiting guest-only login/register/reset pages are redirected to the dashboard by the existing `guest` middleware.

### F6 — Admin provisioning

No administrator can be created through public registration. The developer-only path is documented as deferred to Feature 3, which will use a trusted command/seeder and the custom role schema. Feature 2 must not introduce a temporary `is_admin` shortcut.

### Acceptance summary

1. Registration, login, logout, and password reset work end to end.
2. Validation is server-authoritative and field errors are displayed.
3. Guests are blocked from protected routes; intended redirects work.
4. Session fixation, logout invalidation, and safe redirect behavior are covered by tests.
5. Privileged fields cannot be injected through public payloads.
6. No public admin self-registration exists.
7. Auth pages are responsive, keyboard-accessible, and expose useful processing/error states.
8. Existing 2FA/passkey behavior is not broken; email verification is disabled and outside this feature.
9. No new role, permission, or admin mechanism is introduced.
10. Backend tests, PHPStan, Pint, TypeScript, and the production build pass.

## 6. Routes, middleware, and authorization boundary

| Method    | URI                       | Name                              | Middleware                           | Owner                     | Notes                               |
| --------- | ------------------------- | --------------------------------- | ------------------------------------ | ------------------------- | ----------------------------------- |
| GET       | `/register`               | `register`                        | `web`, `guest:web`                   | Fortify                   | Registration form                   |
| POST      | `/register`               | `register.store`                  | `web`, `guest:web`                   | Fortify + `CreateNewUser` | Creates normal user                 |
| GET       | `/login`                  | `login`                           | `web`, `guest:web`                   | Fortify                   | Login form                          |
| POST      | `/login`                  | `login.store`                     | `web`, `guest:web`, `throttle:login` | Fortify                   | Session login                       |
| POST      | `/logout`                 | `logout`                          | `web`, `auth:web`                    | Fortify                   | Session logout                      |
| GET       | `/forgot-password`        | `password.request`                | `web`, `guest:web`                   | Fortify                   | Reset request form                  |
| POST      | `/forgot-password`        | `password.email`                  | `web`, `guest:web`                   | Fortify                   | Sends reset notification            |
| GET       | `/reset-password/{token}` | `password.reset`                  | `web`, `guest:web`                   | Fortify                   | Reset form                          |
| POST      | `/reset-password`         | `password.update`                 | `web`, `guest:web`                   | Fortify                   | Resets password                     |
| GET       | `/dashboard`              | `dashboard`                       | `web`, `auth`                        | App                       | Existing protected page             |
| GET/PATCH | `/settings/profile`       | `profile.edit` / `profile.update` | `web`, `auth`                        | App                       | Existing protected settings         |
| GET       | `/settings/security`      | `security.edit`                   | `web`, `auth`, `password.confirm`    | App                       | Existing; 2FA/passkeys out of scope |

- All state-changing routes remain under the `web` middleware group, so CSRF protection and session cookies are unchanged.
- No custom role/permission middleware is added. Feature 2 has no role checks; the only boundaries are guest, auth, password confirmation, and rate limiting.
- Email-verification routes are not registered while the feature is disabled; verification is owned by the separate follow-up.
- No new route is proposed. If reset-request hardening is approved later, it must not create a duplicate Fortify route.

## 7. Policy actions

No new Policy is required or appropriate in this slice.

- Registration, login, and password reset are intentionally guest-accessible.
- Logout operates only on the current session.
- No domain model is read or mutated through a protected resource route.
- `App\Http\Controllers\Controller` remains without `AuthorizesRequests`; Feature 3 establishes the Policy/authorization pattern.
- Admin provisioning will need a protected command or seeded role assignment in Feature 3, not a public Policy bypass.

## 8. Form Requests and validation

| Flow                     | Validation location                                                                 | Rules                                                                                                                              |
| ------------------------ | ----------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------- |
| Registration             | `CreateNewUser` action using `ProfileValidationRules` and `PasswordValidationRules` | Name required/string/max 255; email required/email/max 255/unique; password required/confirmed + environment default password rule |
| Login                    | Fortify `LoginRequest`                                                              | Email required/string; password required/string; remember sometimes                                                                |
| Forgot password          | Fortify `SendPasswordResetLinkRequest`                                              | Email required/email; Fortify lowercases it before lookup                                                                          |
| Reset password           | Fortify `NewPasswordController` + `ResetUserPassword` action                        | Token required; email required/email; password required + environment default + confirmed                                          |
| Protected profile update | Existing `ProfileUpdateRequest`                                                     | Out of scope except as a regression boundary                                                                                       |

- The vendor-owned Fortify routes are an intentional exception to introducing new app-owned Form Requests. Login and forgot-password already use Fortify Form Requests. Registration and reset-password use the Fortify action extension points because the `CreatesNewUsers` / `ResetsUserPasswords` contracts receive arrays. Both actions validate and explicitly map fields; no request payload reaches `create()` or `forceFill()` unvalidated.
- Password policy comes from `AppServiceProvider`: production requires 12 characters with mixed case, letters, numbers, symbols, and uncompromised passwords; non-production keeps the framework default. Do not hard-code a different policy in React.
- Privileged fields are not fillable and must be ignored even when present in a request. Tests must cover this explicitly.
- Error messages remain generic for failed login. Registration duplicate-email errors are allowed because uniqueness is a registration requirement.

## 9. Data model, migrations, foreign keys, and indexes

No migration is required for Feature 2.

Existing schema:

- `users`: `id` primary; `name`; `email` unique; `email_verified_at` nullable (retained dormant for the verification follow-up); `password`; `remember_token`; timestamps.
- `password_reset_tokens`: `email` primary; `token`; `created_at`.
- `sessions`: `id` primary; `user_id` nullable indexed; `ip_address`; `user_agent`; `payload`; `last_activity` indexed.
- `passkeys` and two-factor columns remain as installed; not changed by this feature.

Index/foreign-key review:

- `users.email` unique index already supports login and registration lookups.
- `password_reset_tokens.email` primary key supports reset-token lookup.
- `sessions.user_id` and `last_activity` indexes already exist.
- No new foreign key is appropriate: the default Laravel session table intentionally does not enforce a foreign key on `user_id`.
- No role, permission, role-assignment, or admin columns are added. Feature 3 owns that schema.
- No data migration or backfill is needed.

## 10. Query, payload, pagination, and eager-loading plan

- Authentication performs one indexed user lookup by normalized email; password verification is handled by the guard.
- Password reset performs one indexed token-table lookup and one user lookup.
- There are no list endpoints in this feature, so pagination is not applicable.
- No relationships are loaded; no N+1 risk is introduced.
- Shared Inertia auth data should be shaped explicitly rather than serializing the whole model:
    - Guest: `auth.user = null`.
    - Authenticated: only `id`, `name`, and `email` (plus fields explicitly required by the UI). `email_verified_at` is not shared while verification is disabled.
    - Exclude password hashes, 2FA secrets, recovery codes, remember tokens, and future role/permission graphs.
- Update TypeScript `Auth`/`User` types so `user` is nullable for guests and remove the loose `[key: string]: unknown` index signature. Protected pages must narrow or guard the user rather than assuming a non-null session.
- Feature 3 may extend the payload with an explicit permission list; it must not expose the entire role graph.

## 11. Service / Repository decision

No new Service or Repository is justified.

- Fortify already owns the multi-step authentication pipeline, password broker, notifications, session regeneration, and rate limiting.
- `CreateNewUser` and `ResetUserPassword` are the correct existing extension points.
- A wrapper service would duplicate framework behavior and add a layer with no reuse.
- A repository is unnecessary because authentication uses single indexed provider lookups, not a reusable complex query.
- First-admin provisioning and role assignment will be designed with Feature 3, where the custom schema and reusable authorization service exist.

## 12. Inertia props and React plan

Existing pages remain:

| Page                        | Props                             | Notes                                                 |
| --------------------------- | --------------------------------- | ----------------------------------------------------- |
| `auth/login`                | `canResetPassword`, `status`      | Email/password, remember, passkey control, reset link |
| `auth/register`             | `passwordRules`                   | Name/email/password/confirmation                      |
| `auth/forgot-password`      | `status`                          | Email request                                         |
| `auth/reset-password`       | `email`, `token`, `passwordRules` | Read-only email, password/confirmation                |
| `auth/confirm-password`     | none                              | Out of scope; keep working                            |
| `auth/two-factor-challenge` | none                              | Out of scope; keep working                            |

The `auth/verify-email` page and its route helpers were removed with the disabled feature.

Planned UI work:

- Keep Inertia `Form`, Wayfinder actions, shadcn/ui inputs/buttons, `InputError`, `PasswordInput`, and `Spinner`; do not add Axios or a new form library.
- Add `aria-invalid` and `aria-describedby` wiring for invalid fields, with stable error IDs.
- Give `InputError` an appropriate alert/live-region role so field errors are announced.
- Add `role="status"`/`aria-live="polite"` to success/status messages.
- Remove the keyboard trap on the password-visibility toggle, or otherwise make it keyboard reachable and announce its state.
- Use visible focus styles, labels, `autocomplete`, and native form submission.
- Keep buttons disabled while `processing`; preserve the spinner and prevent double submission.
- Make the registration copy clearly describe the initial job-seeker path without introducing a role selector.
- Preserve the existing responsive AuthSimple layout; no new fonts, images, or UI libraries.
- Manual keyboard, screen-reader-announcement, 200% zoom, and mobile checks are required because the repository has no frontend unit/E2E runner.

## 13. Empty, loading, and error states

- Empty states: not applicable; these are single-record authentication forms.
- Loading states: processing spinner, disabled submit control, and no duplicate submission.
- Validation errors: field-level messages, associated to inputs, preserved after an error.
- Authentication errors: generic login failure, no account-existence leak.
- Password reset: clear token/email/password errors; success status after submission.
- Logout: no partial authenticated state; redirect to the public home page.
- Network/server failure: Inertia’s form error behavior remains the fallback; no custom client-side retry loop.

## 14. Test matrix

Use the existing PHPUnit-style feature tests under `tests/Feature/Auth/` and `tests/Feature/`. Add tests for the gaps rather than replacing the suite. Email verification is disabled; the regression suite asserts its routes are absent, registration sends no verification email, and unverified users can access protected pages.

| Area                             | Cases                                                                                                                                     | Expected evidence                                                                  |
| -------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------- |
| Registration happy path          | Render; valid payload; lowercased email; hashed password; authenticated session; intended/dashboard redirect                              | User persisted; session regenerated; response redirect                             |
| Registration validation          | Duplicate email; invalid email; missing/too-long name; short/weak password; password mismatch                                             | 302 with field errors; no user created                                             |
| Registration privilege tampering | Extra `role`, `role_id`, `is_admin`, `permissions`, `account_status`, `email_verified_at` fields                                          | Only name/email/password persist; no privileged state                              |
| Login happy path                 | Valid credentials; remember me; intended URL; session regeneration                                                                        | Authenticated; safe local redirect                                                 |
| Login failure                    | Wrong password; unknown email; generic error; rate limit after five attempts                                                              | Guest remains; no email-existence leak; 429                                        |
| Guest-only routes                | Authenticated user opens login/register/forgot/reset                                                                                      | Redirected by `guest:web`; no auth mutation                                        |
| Logout                           | Authenticated POST; session invalidation; CSRF regeneration; protected request afterward                                                  | Guest; redirect home; protected route redirects login                              |
| Safe redirects                   | External `redirect`/`url` payload; protected intended URL                                                                                 | No external redirect; local intended URL works                                     |
| Password reset                   | Request screen; known email notification; valid token; old password invalid; invalid/expired token; mismatch/weak password                | Notification sent; password changed; errors shown                                  |
| Protected boundary               | Guest dashboard; guest settings; authenticated user dashboard                                                                             | Guest redirect login; authenticated user allowed where existing middleware permits |
| Shared auth props                | Guest `auth.user`; authenticated field allow-list; secret fields absent                                                                   | Inertia assertions                                                                 |
| Regression                       | 2FA challenge login; passkey login coexistence; verification-disabled regression                                                          | Existing tests remain green                                                        |
| Verification disabled            | Routes absent; registration sends no verification email; unverified user accesses dashboard/profile; payload excludes `email_verified_at` | `EmailVerificationDisabledTest` passes                                             |
| Frontend checks                  | `npm run types:check`, `npm run build`                                                                                                    | Typecheck/build pass                                                               |
| Manual accessibility             | Keyboard-only, error announcement, status announcement, 200% zoom, mobile                                                                 | Documented QA result                                                               |

Applicable authorization cases:

- **Guest:** blocked from protected routes.
- **Authenticated user:** can access their own session/settings; cannot access guest-only auth screens.
- **Role/owner/non-owner:** not applicable until Features 3 and the first domain resources. Do not invent a role test.

## 15. Security and performance risks

- **Open redirects:** Fortify uses `redirect()->intended()` for login/register and a fixed redirect for logout. Do not accept a redirect URL from request input. Add a regression test.
- **Session fixation:** Fortify regenerates the session after login/register and invalidates it on logout. Verify with tests; do not bypass by custom login code.
- **CSRF:** All auth routes stay in the `web` middleware group. Do not exclude them from CSRF verification.
- **Privileged-field injection:** No `is_admin`, role, permission, approval, or verification field is fillable. Tests must attempt them anyway.
- **Password handling:** Passwords are hashed by the model cast; never log or serialize them. Fortify’s hidden attributes must remain.
- **Account enumeration:** The app binds a generic failed-reset response so unknown emails receive the same status as known emails in HTML and JSON. Timing differences from the actual mail send remain a residual risk.
- **Reset-email abuse:** The bound reset-request Form Request applies a 5/minute email+IP limiter and returns 429 with `Retry-After`; it does not duplicate or shadow Fortify routes.
- **Production cookies:** `SESSION_SECURE_COOKIE` must be enabled behind HTTPS; `HttpOnly` is true and `SameSite=Lax` by default. Do not record environment values in source control.
- **Shared props leakage:** The shared payload is now an explicit allow-list, preventing future role/permission attributes from leaking automatically.
- **Performance:** All auth lookups are single indexed queries. No pagination, counts, aggregation, queue, or cache is justified. Password hashing dominates request cost; rate limits protect the login path.
- **Verification disabled:** Unverified users are not gated from dashboard/settings while verification is off. The `email_verified_at` column, cast, factory state, and profile-email-change reset remain dormant for the follow-up feature.

## 16. Implementation sequence

1. Approve or amend A1–A5 and the open decisions below.
2. Add backend feature tests for registration, login, logout, reset, intended redirects, session regeneration, payload tampering, and shared auth props. Run them against the current code and record any real defects.
3. Fix only the defects those tests expose; keep Fortify controllers and routes in place.
4. Shape the shared `auth.user` payload and update TypeScript `Auth`/`User` types; guard protected pages against a null user.
5. Apply accessible error/status/password-toggle improvements to the auth pages using existing components.
6. Update registration copy for the initial job-seeker path and document the disabled verification and deferred admin work.
7. Run the targeted tests, full suite, static checks, typecheck, and production build.
8. Manually verify keyboard navigation, error/status announcements, mobile layout, and 200% zoom; record the result.
9. Review the diff and stop; do not start Feature 3.

## 17. Commands to verify

```text
php artisan test --filter='RegistrationTest|AuthenticationTest|PasswordResetTest|DashboardTest'
php artisan test
composer lint:check
composer types:check
npm run types:check
npm run build
php artisan route:list -v
git diff --check
```

Manual smoke test:

```text
php artisan serve
npm run dev
```

Then verify register, login, logout, forgot/reset password, guest redirect, intended redirect, mobile layout, keyboard focus, error announcements, and 200% zoom in a real browser.

`npm run check` is not a gate for this feature yet: it currently reports formatting issues in six pre-existing documentation files outside this spec. Do not auto-fix them without approval.

## 18. Approved decisions

All five recommendations below were approved and implemented. Email verification is disabled for now and remains a separate follow-up; roles and first-admin provisioning remain deferred to Feature 3.

1. **Verification boundary:** Disable the pre-existing email-verification flow (Fortify feature off, `verified` gate, UI/routes/tests removed) and keep `email_verified_at` dormant for the separate feature? Approved: yes.
2. **Role/admin deferral:** Defer default job-seeker role assignment and first-admin provisioning to Feature 3, while guaranteeing no public admin self-registration? Recommended: yes; no role schema exists yet.
3. **Reset hardening:** Add generic unknown-email responses and a dedicated password-reset request limiter in this feature, or record them as accepted residual risks for a follow-up? Recommended: add the generic response and limiter if a clean Fortify-extension point can be verified without duplicating routes.
4. **Shared auth payload:** Approve narrowing `auth.user` to an explicit field allow-list and making the TypeScript user nullable for guests? Recommended: yes.
5. **Registration copy:** Approve job-seeker-oriented registration copy even though no role is assigned until Feature 3? Recommended: yes.
