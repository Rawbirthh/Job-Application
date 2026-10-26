# Architecture Baseline

> Audited on 2026-10-26 from the repository working tree. This document records
> observed state only. No application code, dependencies, migrations, or existing
> documentation were changed. Evidence paths are relative to the repository root.

## Summary

- The application is the Laravel 13 React starter kit (`composer.json` package
  name: `laravel/react-starter-kit`) with the OpenCode agent guidance layered on
  top.
- Authentication is session-based through Laravel Fortify. Password reset,
  two-factor authentication, and passkeys are enabled. Email verification is
  intentionally disabled pending a separate feature.
- There is no custom role/permission model, no Policy, no admin middleware, and
  no business-domain schema yet. The roadmap in `project-overview.md` assigns
  that foundation to a later feature; it is not implemented.
- The local runtime uses SQLite. MySQL is supported by configuration, but MySQL
  connectivity was not configured or verified. This differs from the MySQL
  baseline stated in `AGENTS.md`.
- Backend tests, PHPStan, Pint, frontend typecheck, and the production build
  passed. `npm run check` failed on pre-existing Markdown formatting issues.

## Versions and runtime

| Area               | Observed version/state                                                                                                                                                                                            | Evidence                                                                                 |
| ------------------ | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------- |
| PHP                | 8.5.12 local and CI 8.5. `composer.json` requires `^8.3`, but locked PHPUnit 13.4.3 requires PHP `>=8.4.1`, so dev/test effectively needs PHP 8.4+.                                                               | `php -v`, `composer.json`, `composer show phpunit/phpunit`                               |
| Laravel            | 13.38.0                                                                                                                                                                                                           | `composer.lock`, `php artisan --version`                                                 |
| Composer           | 2.9.3                                                                                                                                                                                                             | `composer --version`                                                                     |
| Node               | 24.13.0 local; CI uses Node 22                                                                                                                                                                                    | `node --version`, `.github/workflows/tests.yml`                                          |
| npm                | 11.6.2; `package-lock.json` lockfile v3                                                                                                                                                                           | `npm --version`, `package-lock.json`                                                     |
| React              | 19.3.1; `react-dom` 19.3.1; types 19.3.0                                                                                                                                                                          | `npm ls --depth=0`                                                                       |
| Inertia            | Server adapter 3.5.2; React client 3.9.1; `@inertiajs/vite` 3.9.1                                                                                                                                                 | `composer.lock`, `npm ls --depth=0`                                                      |
| TypeScript         | 5.9.3, `strict: true`, `noImplicitAny: true`, `noEmit: true`                                                                                                                                                      | `tsconfig.json`                                                                          |
| Tailwind CSS       | 4.3.3; `@tailwindcss/vite` 4.3.3; `tw-animate-css` 1.4.0                                                                                                                                                          | `npm ls --depth=0`, `resources/css/app.css`                                              |
| shadcn/ui          | No shadcn npm package or CLI version is installed. `components.json` records style `new-york`, base color `neutral`, CSS variables, and Lucide icons. Components are vendored under `resources/js/components/ui`. | `components.json`, `package-lock.json`                                                   |
| Vite toolchain     | `vite-plus` 1.4.0; `vite` is aliased to `@voidzero-dev/vite-plus-core` 1.4.0; `laravel-vite-plugin` 3.2.0; `@vitejs/plugin-react` 6.1.2; React Compiler Babel plugin 1.0.0                                        | `package.json`, `vite.config.ts`, `npm ls --depth=0`                                     |
| Wayfinder          | Server package 0.1.23; Vite plugin 0.1.11; form variants enabled                                                                                                                                                  | `composer.lock`, `package.json`, `vite.config.ts`                                        |
| Fortify / passkeys | Fortify 1.41.0; `@laravel/passkeys` 0.2.0                                                                                                                                                                         | `composer.lock`, `npm ls --depth=0`                                                      |
| Tests              | Pest 5.3.3 runner with PHPUnit 13.4.3; Larastan 3.13.2; Pint 1.32.1                                                                                                                                               | `composer.lock`, `composer show`                                                         |
| Database           | Local runtime is SQLite; all five migrations are applied in batch 1. MySQL is configured as an available connection but is not the local default.                                                                 | `php artisan about`, `php artisan migrate:status`, `config/database.php`, `.env.example` |

## Package manager and build tooling

- npm is the usable package manager: `package-lock.json` exists, `npm ls` works,
  and `composer setup` uses `npm install`.
- `package.json` has no `packageManager` field. `.npmrc` sets
  `ignore-scripts=true`.
- `pnpm-workspace.yaml` exists, but pnpm is not installed and there is no
  `pnpm-lock.yaml`. Treat pnpm as unused/ambiguous until the team confirms it.
- CI installs dependencies through `composer setup`, which runs `npm install`
  rather than `npm ci`. This is not a reproducible lockfile-only install.

## Authentication

- Guard and provider: `web` session guard with the Eloquent `App\Models\User`
  provider; password broker is `users` (`config/auth.php`).
- Fortify is registered in `bootstrap/providers.php` and configured in
  `config/fortify.php`. Views are enabled, the home path is `/dashboard`, and
  the route prefix is empty.
- Enabled Fortify features: registration, password reset, email verification,
  two-factor authentication (confirmation and password confirmation required),
  and passkeys (password confirmation required).
- `App\Models\User` implements `MustVerifyEmail` and `PasskeyUser`; it uses
  `PasskeyAuthenticatable` and `TwoFactorAuthenticatable`. Fillable fields are
  `name`, `email`, and `password`; hidden fields are password, 2FA secrets and
  recovery codes, and remember token.
- Registration is handled by `App\Actions\Fortify\CreateNewUser`, which creates
  only name, email, and password. It does not assign a role, permission, or
  account status.
- Rate limits: login 5/minute per email and IP; two-factor 5/minute per login
  session; passkeys 10/minute per credential/session and IP
  (`app/Providers/FortifyServiceProvider.php`).
- Inertia shared data includes the full authenticated user object plus the app
  name and sidebar state (`app/Http/Middleware/HandleInertiaRequests.php`). The
  model's hidden fields prevent secret attributes from being serialized, but
  future role/permission attributes should be shaped explicitly.
- There is no API authentication layer, no Sanctum dependency, and no
  `routes/api.php`.

### Routes

`php artisan route:list -v` reports 46 routes total. The application-owned routes
are:

| Route                                                        | Middleware                                   | Handler                                   |
| ------------------------------------------------------------ | -------------------------------------------- | ----------------------------------------- |
| `GET /` (`home`)                                             | `web`                                        | `Route::inertia('welcome')`               |
| `GET /dashboard` (`dashboard`)                               | `web`, `auth`, `verified`                    | `Route::inertia('dashboard')`             |
| `GET /settings/profile` (`profile.edit`)                     | `web`, `auth`                                | `ProfileController@edit`                  |
| `PATCH /settings/profile` (`profile.update`)                 | `web`, `auth`                                | `ProfileController@update`                |
| `DELETE /settings/profile` (`profile.destroy`)               | `web`, `auth`, `verified`                    | `ProfileController@destroy`               |
| `GET /settings/security` (`security.edit`)                   | `web`, `auth`, `verified`, `RequirePassword` | `SecurityController@edit`                 |
| `PUT /settings/password` (`user-password.update`)            | `web`, `auth`, `verified`, `throttle:6,1`    | `SecurityController@update`               |
| `GET /settings/appearance` (`appearance.edit`)               | `web`, `auth`, `verified`                    | `Route::inertia('settings/appearance')`   |
| `GET /.well-known/passkey-endpoints` (`well-known.passkeys`) | `web`                                        | JSON response pointing at `security.edit` |

Fortify and `laravel/passkeys` auto-register login, registration, logout,
password reset, email verification, password confirmation, two-factor, and
passkey routes (`php artisan route:list -v`). There are no admin, employer,
candidate, job, company, or application routes yet.

### Authorization

- No custom role/permission schema, middleware alias, Gate, or Policy exists.
  Searches for role/permission/Policy/authorize across `app/`, `routes/`,
  `database/`, and `tests/` returned no matches.
- `app/Http/Controllers/Controller.php` is an empty abstract class. It does not
  use Laravel's `AuthorizesRequests` trait, so `$this->authorize()` is not
  available in controllers until that trait is added or authorization is done
  through `Gate`.
- The settings Form Requests in `app/Http/Requests/Settings/` define validation
  rules only and omit `authorize()`. Laravel's Form Request passes authorization
  when the method is absent
  (`vendor/laravel/framework/src/Illuminate/Foundation/Http/FormRequest.php:347`).
  Current requests therefore rely on route `auth`/`verified` middleware and the
  fact that they operate on the authenticated user's own settings.
- New resource writes should implement explicit request-level or controller
  Policy authorization rather than inheriting this settings pattern.
- `project-overview.md` describes planned platform administrator, employer, and
  job-seeker audiences, but no corresponding roles, permissions, assignments,
  or protected resources exist in code. Those names are product requirements,
  not current architecture.

## Database schema and indexes

All migrations were applied to the local SQLite database. The schema is still the
starter-kit schema.

- `users`: `id` primary key; `name`; `email` unique; nullable
  `email_verified_at`; `password`; `remember_token`; timestamps. The 2FA
  migration adds nullable `two_factor_secret`, `two_factor_recovery_codes`, and
  `two_factor_confirmed_at`. No role, status, soft-delete, or avatar column.
- `password_reset_tokens`: `email` primary key; `token`; nullable `created_at`.
- `sessions`: string `id` primary key; nullable indexed `user_id`; `ip_address`;
  `user_agent`; `payload`; indexed `last_activity`.
- `passkeys`: `id` primary key; `user_id` foreign key with cascade delete;
  `name`; unique `credential_id`; JSON `credential`; nullable `last_used_at`;
  timestamps; explicit `user_id` index. On MySQL the foreign key already creates
  an index, so the explicit index is likely redundant there; it is useful on
  SQLite.
- `cache`: string `key` primary key; `value`; indexed `expiration`.
- `cache_locks`: string `key` primary key; `owner`; indexed `expiration`.
- `jobs`: `id` primary key; indexed `queue`; payload; attempts; timestamps.
- `job_batches`: string `id` primary key; batch counters and timestamps.
- `failed_jobs`: `id` primary key; unique `uuid`; composite index on
  `connection`, `queue`, `failed_at`.

No business tables exist for companies, jobs, applications, roles, permissions,
notifications, or audit records. Account deletion is a hard delete of the
`users` row; there is no soft-delete policy in the current schema.

## Backend conventions

- Controllers are thin: `Inertia::render(...)` for pages, redirects with
  `to_route()`/`back()` for writes, and `Inertia::flash('toast', ...)` for
  success feedback (`app/Http/Controllers/Settings/`).
- `ProfileController@update` uses `fill($request->validated())` and resets email
  verification when the email changes. `ProfileController@destroy` logs the user
  out, deletes the row, invalidates the session, and regenerates the CSRF token.
- `SecurityController@edit` selects only passkey columns and maps them to a
  small Inertia payload. The passkey list uses `->get()` without an explicit
  limit; it is bounded in practice by a user's passkeys, but it is not a reusable
  paginated-list pattern.
- Form Requests live under `app/Http/Requests/` and use shared validation traits
  in `app/Concerns/` (`ProfileValidationRules`, `PasswordValidationRules`).
- Models use Laravel 13 PHP attributes for fillable/hidden fields and a `casts()`
  method (`app/Models/User.php`). There are no custom relationships beyond those
  supplied by Fortify traits.
- There are no Services, Repositories, API Resources, DTOs, Events, Listeners,
  Jobs, or Observers in the application code.
- Simple pages use `Route::inertia()`; writes use named routes and redirects.
- No pagination, `withCount()`, `chunkById()`, transactions, or explicit
  eager-loading examples exist yet.

## Frontend and UI conventions

- Entry point: `resources/js/app.tsx` uses `createInertiaApp`, Inertia v3 layout
  resolution, React strict mode, a shared `TooltipProvider`, and Sonner toasts.
- Layout resolution is name-based:
    - `welcome` has no layout.
    - `auth/*` uses `AuthLayout` (currently `auth-simple-layout`; split and card
      layouts exist but are unused).
    - `settings/*` uses `[AppLayout, SettingsLayout]`.
    - Everything else uses `AppLayout`, which defaults to the collapsible inset
      sidebar shell (`app-sidebar-layout`). A header-based shell exists but is not
      the default.
- Pages declare static `.layout` metadata for breadcrumbs/titles where needed
  (`dashboard.tsx`, settings pages).
- Forms use the Inertia v3 `<Form>` component with Wayfinder-generated
  `.form()` actions, not `useForm()`. They consume render props for `processing`
  and `errors`, show field errors through `InputError`, disable submit buttons
  while processing, and use `Spinner`/`PasswordInput` helpers. Destructive
  actions use a confirmation `Dialog` (`components/delete-user.tsx`).
- Wayfinder-generated files live in `resources/js/routes/`,
  `resources/js/actions/`, and `resources/js/wayfinder/`; they are gitignored and
  regenerated by the Vite plugin during build.
- shadcn/ui primitives are vendored in `resources/js/components/ui/`: alert,
  avatar, badge, breadcrumb, button, card, checkbox, collapsible, dialog,
  dropdown menu, icon, input, input-otp, label, navigation menu, select,
  separator, sheet, sidebar, skeleton, sonner, spinner, toggle, toggle group,
  tooltip, and a custom placeholder pattern.
- There is no Table, Pagination, EmptyState, Tabs, Textarea, Switch, Popover,
  Command, or Calendar primitive. There is no server-driven list page pattern
  yet.
- Styling is Tailwind v4 CSS-first: tokens and light/dark variables are defined
  in `resources/css/app.css`; there is no `tailwind.config.js`. The base radius
  is `0.625rem`, the sans font is Instrument Sans, and the palette uses OKLCH
  values. shadcn styling is `new-york`, neutral, CSS variables, Lucide icons.
- Generated UI files use double quotes and no semicolons, unlike the
  application code's single-quote/semicolon style. `vite.config.ts` excludes
  `resources/js/components/ui/*` from formatting and linting.
- Accessibility patterns include labels tied to inputs, `sr-only` headings on
  settings pages, an `aria-label` on the settings navigation, focus handling on
  password errors, and a mobile navigation sheet.

## Commands, checks, and observed results

| Command                        | Result                                                                                                                                                                                                                                                                                                    |
| ------------------------------ | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `php artisan test`             | Passed: 40 tests, 138 assertions, about 40 seconds.                                                                                                                                                                                                                                                       |
| `composer lint:check`          | Passed (Pint, Laravel preset).                                                                                                                                                                                                                                                                            |
| `composer types:check`         | Passed (PHPStan/Larastan level 7, zero errors).                                                                                                                                                                                                                                                           |
| `npm run types:check`          | Passed (`tsc --noEmit`).                                                                                                                                                                                                                                                                                  |
| `npm run build`                | Passed; produced `public/build`. Warning: optional `fontaine` package is missing, so optimized font fallbacks are disabled.                                                                                                                                                                               |
| `npm run check`                | Failed on formatting issues in 8 pre-existing files: `.opencode/agents/code-reviewer.md`, `.opencode/commands/develop.md`, `.opencode/commands/recover.md`, `.opencode/commands/remember.md`, `.opencode/skills/security-review/SKILL.md`, `docs/ui-registry.md`, `memory.md`, and `project-overview.md`. |
| `php artisan migrate:status`   | All five migrations show `Ran` in batch 1.                                                                                                                                                                                                                                                                |
| `php artisan about`            | Reports SQLite, database cache/queue/session, local environment, debug enabled.                                                                                                                                                                                                                           |
| `composer check-platform-reqs` | Passed on PHP 8.5.12.                                                                                                                                                                                                                                                                                     |

Command definitions:

- PHP formatting: `composer lint` (fix) and `composer lint:check` (check);
  `pint.json` uses the Laravel preset.
- PHP static analysis: `composer types:check` -> `phpstan analyse`;
  `phpstan.neon` covers `app/`, `bootstrap/app.php`, `config/`, `database/`, and
  `routes/` at level 7.
- PHP tests: `php artisan test`; `composer test` also clears config, runs Pint
  check, PHPStan, and the test suite.
- Frontend format/lint: `npm run check` (`vp check`) and
  `npm run check:fix`; rules live in `vite.config.ts`.
- Frontend types: `npm run types:check`.
- Frontend build: `npm run build`; SSR variant: `npm run build:ssr`.
- Development: `npm run dev` (Vite Plus) or `composer dev`/`php artisan dev`,
  which runs the PHP server, queue listener, and Vite.
- Aggregate CI command: `composer ci:check` runs `npm run check`,
  `npm run types:check`, and `php artisan test`. It was not run as a whole; the
  observed `npm run check` failure would stop it.
- `composer setup` installs dependencies, creates `.env` if missing, generates a
  key, migrates, installs npm packages, and builds. It was not run because it
  installs dependencies.

Tests use PHPUnit-style classes under `tests/Feature/` and `tests/Unit/`, even
though Pest is the runner. `tests/Pest.php` binds Feature tests to
`Tests\TestCase` with `RefreshDatabase`; `tests/TestCase.php` provides a
Fortify-feature skip helper. `phpunit.xml` uses SQLite `:memory:`, array
cache/session, sync queue, array mail, and BCRYPT rounds 4. There is no frontend
unit or end-to-end test runner.

## Unknowns and decisions needed

- Database target: the local environment is SQLite while `AGENTS.md` says MySQL.
  Confirm whether MySQL is required before adding schema, indexes, or
  MySQL-specific constraints.
- Role/permission design: no tables, models, middleware, Gates, or Policies
  exist. The schema, permission catalogue, assignment model, and admin
  provisioning path all remain to be designed.
- Authorization plumbing: the base controller does not include
  `AuthorizesRequests`, and current Form Requests do not implement
  `authorize()`. Decide the project-wide pattern before protected resource work.
- Frontend test strategy: no Vitest, Playwright, or other frontend test runner is
  configured.
- SSR: `config/inertia.php` enables SSR, but no SSR entry file was found and
  `npm run build:ssr` was not verified. Decide whether SSR is required.
- shadcn reproducibility: no CLI/package version is pinned, so generated
  component provenance cannot be reproduced exactly from the repository.
- Package manager: pnpm metadata is present but pnpm is not installed; npm is the
  working manager. Confirm and remove or document the unused pnpm file.
- PHP support range: `composer.json` says `^8.3`, but locked test dependencies
  require PHP 8.4.1+. Align the declared constraint with reality.
- README scope: the README documents the agentic starter kit, not application
  installation, database setup, migrations, or the actual test/build commands.
- Type/schema drift: `resources/js/types/auth.ts` includes an optional `avatar`
  field, but the `users` table has no avatar column. Do not rely on it.
- CI reproducibility: CI uses Node 22 while local Node is 24, and setup uses
  `npm install` rather than `npm ci`.

## Recommendations

1. Decide and document the database target (SQLite for local-only development or
   MySQL for the intended deployment) before creating business migrations.
2. For the authorization foundation, design explicit role, permission, and
   assignment tables with foreign keys and uniqueness constraints; add
   `AuthorizesRequests` to the base controller or establish a Gate-based
   equivalent; add middleware aliases and Policies as resources are introduced.
3. Use the existing Inertia v3 `<Form>` + Wayfinder pattern for forms unless the
   team deliberately standardizes on `useForm()`. Keep validation and
   authorization authoritative on the server.
4. Add shared pagination, table, and empty-state primitives before building the
   first server-driven list page; do not paginate or filter large datasets in the
   browser.
5. Add explicit authorization to new Form Requests or controllers; do not copy
   the current settings requests' implicit-authorization pattern to protected
   resources.
6. Resolve the `npm run check` Markdown formatting failures with approval, or
   narrow the check to application code. Do not auto-fix existing documentation
   without review.
7. Update the README with verified install, environment, migration, test,
   development, and build commands, and clarify the package manager.
8. Decide whether SSR is in scope; either add and verify an SSR entry point or
   disable the SSR configuration.
9. Review the `passkeys.user_id` index on the chosen database and remove a
   duplicate only if the target engine already creates an equivalent index.
10. Add frontend tests only with an approved tool choice; the current suite has
    backend coverage only.

## Files inspected

Key evidence: `composer.json`, `composer.lock`, `package.json`,
`package-lock.json`, `pnpm-workspace.yaml`, `.npmrc`, `tsconfig.json`,
`vite.config.ts`, `components.json`, `pint.json`, `phpstan.neon`, `phpunit.xml`,
`bootstrap/app.php`, `bootstrap/providers.php`, `config/auth.php`,
`config/fortify.php`, `config/database.php`, `config/inertia.php`,
`config/session.php`, `routes/web.php`, `routes/settings.php`, `app/Models/`,
`app/Http/Controllers/`, `app/Http/Requests/`, `app/Http/Middleware/`,
`app/Providers/`, `app/Actions/Fortify/`, `database/migrations/`,
`database/factories/`, `database/seeders/`, `tests/`, `resources/js/`,
`resources/css/app.css`, `resources/views/app.blade.php`, `docs/`,
`project-overview.md`, `README.md`, `memory.md`, and the GitHub Actions
workflow.
