# Project Memory

> Maintained by `/remember save`. Concise project facts and latest work handoff; not a transcript or secret store.

## Project profile

- Stack: Laravel 13.38, React 19.3, TypeScript 5.9 strict, Inertia 3, Tailwind CSS 4, shadcn/ui vendored components.
- Product: job portal for candidates, employers, and platform administrators per `project-overview.md`; business schema is not built yet.
- Database target: unresolved. `AGENTS.md` baseline is MySQL; the local runtime and test suite use SQLite. Confirm before business migrations.
- Authentication: session-based Laravel Fortify. Registration, login/logout, password reset, 2FA, and passkeys are enabled. Email verification is intentionally disabled pending a separate feature.
- Authorization: custom/dynamic role and permission system is planned but not implemented. No role tables, Policies, admin middleware, or base-controller `AuthorizesRequests` yet. Roles and first-admin provisioning belong to Feature 3.
- Tests: Pest 5 runner with PHPUnit 13; `php artisan test`. Latest full suite: 61 passed, 316 assertions. No frontend unit or E2E runner configured.
- Frontend conventions: Inertia page props, Wayfinder actions, named routes, vendored shadcn/ui. Keep validation and authorization authoritative on the server.

## Confirmed architecture decisions

- Follow `AGENTS.md`; keep Services/Repositories only when complexity, reuse, or query shape justifies them. Feature 2 added neither.
- `GET /` is named `home`, uses `web` only, and renders Inertia `home` through `PublicLayout`.
- Public homepage copy/layout is approved and implemented. Live job search, categories, and featured roles are deferred to Feature 16.
- Global palette is teal / deep emerald / soft fog (60-30-10), applied through semantic tokens, not a homepage-only skin.
    - Canvas: `#f8fafc` light, `#0b1220` dark.
    - Structural sidebar: `#0f172a` light, `#0d1a2a` dark.
    - Primary: `#0d9488` with `#021716` text light; `#2dd4bf` with `#042f2e` text dark.
    - Teal is reserved for interaction, focus, icons, and small accents; large surfaces use fog/emerald.
- Homepage tokens `--home-surface`, `--home-surface-strong`, `--home-accent`, and `--home-accent-soft` remain scoped in `resources/css/app.css`.
- Protected app routes currently use `auth` only: dashboard and all settings pages. The `verified` middleware gate was removed while email verification is disabled.
- Email verification is disabled end-to-end: Fortify feature off, verification routes/UI/tests removed, `User` no longer implements `MustVerifyEmail`, and `mustVerifyEmail`/verification prompts removed.
- Dormant verification data is retained: `users.email_verified_at`, its cast, the factory `unverified()` state, and the profile email-change reset. Do not drop the column; the separate verification feature will need it.
- Inertia shares only `auth.user` `id`, `name`, and `email`; password, 2FA, remember token, and `email_verified_at` are not exposed.
- Password reset: generic unknown-email response in HTML and JSON, plus a 5/minute email+IP throttle returning 429 with `Retry-After`.
- No new dependencies, fonts, images, or UI libraries were added.

## Current task / latest handoff

- Status: Feature 2 authentication/account lifecycle implemented and verified; email verification removed from the active flow per user instruction. All changes remain uncommitted; no commit or push made.
- Summary: Completed Fortify auth hardening, narrowed shared auth props, added reset-request throttling and generic reset responses, improved auth form accessibility, added regression coverage, and disabled/deleted the pre-existing email-verification flow.
- Prior homepage/palette work from an earlier feature remains uncommitted in the same working tree and is not part of Feature 2.
- Key Feature 2 files:
    - Backend: `app/Http/Middleware/HandleInertiaRequests.php`, `app/Providers/FortifyServiceProvider.php`, `app/Http/Requests/Auth/SendPasswordResetLinkRequest.php`, `app/Http/Responses/Auth/GenericFailedPasswordResetLinkRequestResponse.php`, `config/fortify.php`, `routes/web.php`, `routes/settings.php`, `app/Models/User.php`, `app/Http/Controllers/Settings/ProfileController.php`.
    - Frontend: `resources/js/types/auth.ts`, `resources/js/components/input-error.tsx`, `resources/js/components/password-input.tsx`, `resources/js/pages/auth/{login,register,forgot-password,reset-password}.tsx`, `resources/js/pages/settings/profile.tsx`.
    - Tests: `tests/Feature/Auth/{AuthenticationTest,RegistrationTest,PasswordResetTest,EmailVerificationDisabledTest}.php`, `tests/Feature/DashboardTest.php`, `tests/Feature/Settings/ProfileUpdateTest.php`.
    - Docs: `docs/specs/authentication-account-lifecycle.md` (Approved — implemented), `docs/architecture/baseline.md` (auth state updated; still an audit snapshot).
- Removed files:
    - `resources/js/pages/auth/verify-email.tsx`
    - `tests/Feature/Auth/EmailVerificationTest.php`
    - `tests/Feature/Auth/VerificationNotificationTest.php`
    - The deleted scaffolding is recoverable from initial commit `09b3a75` when verification is implemented later.
- Verification observed on the final tree:
    - `php artisan test`: 61 passed, 316 assertions.
    - `EmailVerificationDisabledTest`: 4 passed, 33 assertions.
    - `composer lint:check`: passed (Pint).
    - `composer types:check`: passed, 0 errors.
    - `npm run types:check`: passed.
    - `npm run build`: passed; verification route helpers removed.
    - `php artisan route:list -v`: 43 routes; no `verification.*` or `email/verify` routes; dashboard/settings require `auth`.
    - `git diff --check`: clean.
    - `npm run check`: still fails only on 6 pre-existing docs (five under `.opencode/` plus `project-overview.md`).
- Blockers/open questions:
    - Manual browser QA still unverified for both auth and homepage: keyboard navigation, screen-reader announcements, reduced motion, 200% zoom, and mobile layout.
    - Database target unresolved: SQLite locally/tests vs MySQL baseline in `AGENTS.md`.
    - `npm run check` docs failures require approval before fixing.
    - `docs/architecture/baseline.md` remains an audit snapshot; current route and test counts above supersede its older figures.
    - `resources/laravel-opencode-agentic-starter-routed.zip` is an unrelated pre-existing deletion; leave untouched.
- Next recommended step: Review the Feature 2 auth flow in a real browser (register without verification, login/logout, password reset, unverified access, profile), then commit if approved. Do not start email verification automatically; the user will implement it separately.

## Durable notes

- Preserve the approved homepage layout, copy, and `home` route unless the user explicitly changes them.
- Keep the palette in semantic tokens; do not hard-code palette hex values in page components.
- Keep email verification disabled until the dedicated feature. Re-enable Fortify's feature, `verified` middleware, `MustVerifyEmail`, verification UI/tests, and profile prompts together.
- Keep the shared `auth.user` payload to the explicit allow-list; do not serialize the full user model.
- Do not add roles, permissions, `is_admin`, or first-admin provisioning before Feature 3 designs the custom schema.
- Wayfinder-generated route/action files are gitignored and regenerated during the build.
- Do not auto-fix the six unrelated `npm run check` documentation failures without approval.
- Never store `.env` values, credentials, tokens, private keys, or personal data here.
