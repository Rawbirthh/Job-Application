# Project Memory

> Maintained by `/remember save`. Concise project facts and latest work handoff; not a transcript or secret store.

## Project profile

- Stack: Laravel 13.38, React 19.3, TypeScript 5.9 strict, Inertia 3, Tailwind CSS 4, shadcn/ui vendored components.
- Product: job portal for candidates, employers, and platform administrators per `project-overview.md`.
- Database target: unresolved. `AGENTS.md` baseline is MySQL; local runtime and test suite use SQLite.
- Authentication: session-based Laravel Fortify. Registration, login/logout, password reset, 2FA, passkeys enabled; email verification disabled.
- Authorization: custom dynamic DB-backed role/permission system (Features 3–14). Roles: `platform_admin`, `employer`, `job_seeker`. Permissions include `admin.access`, `users.*`, `roles.*`, `permissions.*`, catalogue permissions, `employers.view`/`employers.manage`, and `jobs.moderate`. Policies, middleware, services, and admin management UI in place.
- Tests: Pest 5 on PHPUnit 13; `php artisan test`. Latest full suite: **517 passed, 3594 assertions** (includes Feature 29 tests).
- Frontend conventions: Inertia page props, Wayfinder actions/routes, named routes, vendored shadcn/ui, pagination component, split/admin layouts.

## Features implemented (latest first)

- **Feature 29 — Saved searches and job alerts** (IMPLEMENTED, verified): Candidates can save public-job-search filter sets with optional name + alert preference (daily/weekly digest). Rerun saved searches via `/candidate/saved-searches`. Scheduled command `saved-searches:dispatch-alerts {daily|weekly}` produces in-app notifications for newly published matching jobs with duplicate prevention via `saved_search_alerts` ledger. No new permission slugs; `role:job_seeker` route boundary + `SavedSearchPolicy` ownership checks. Query logic extracted into `JobSearchService` reused by `PublicJobController` (zero behavior change, `PublicJobListTest` green). Requires scheduler runner (`schedule:work`/cron) in production for alerts; graceful no-op without it.
- **Feature 28 — Company hiring-team members and scoped permissions** (committed `7c09de7`): owner/recruiter/reviewer membership, last-owner protection, cross-company IDOR prevention.
- Features 1–27 also implemented (see `docs/specs/` and prior memory entries).

## Current task / latest handoff

- Status: **Feature 29 implemented, tested, and verified; changes are UNCOMMITTED in the working tree on `main`.** Feature 28 committed (`7c09de7`).
- Branch: `main` (Feature 29 working-tree changes not yet committed).
- Spec: `docs/specs/feature-29-saved-searches-and-job-alerts.md` (status IMPLEMENTED).
- Key files (Feature 29):
    - Backend: `database/migrations/2026_11_11_000001_create_saved_searches_and_alerts_tables.php`, `app/Enums/AlertFrequency.php`, `app/Models/SavedSearch.php`, `app/Models/SavedSearchAlert.php`, `app/Services/JobSearchService.php`, `app/Policies/SavedSearchPolicy.php`, `app/Http/Controllers/Candidate/SavedSearchController.php`, `app/Http/Requests/Candidate/StoreSavedSearchRequest.php`, `app/Http/Requests/Candidate/UpdateSavedSearchRequest.php`, `app/Notifications/SavedSearchMatchedNotification.php`, `app/Console/Commands/DispatchSavedSearchAlertsCommand.php`, updates to `app/Models/User.php`, `app/Http/Controllers/PublicJobController.php`, `app/Services/NotificationService.php`, `routes/web.php`, `routes/console.php`, `README.md` (scheduler note).
    - Frontend: `resources/js/types/saved-search.ts`, `resources/js/components/saved-search/save-search-dialog.tsx`, `resources/js/pages/candidate/saved-searches/index.tsx`, updates to `resources/js/pages/jobs/index.tsx` (save button + toast), `resources/js/lib/navigation.ts` (nav item), `resources/js/types/index.ts` (barrel).
    - Tests: `tests/Feature/Candidate/SavedSearchTest.php` (12 tests), `tests/Feature/Candidate/SavedSearchAlertsTest.php` (9 tests).
- Tests/checks actually run for Feature 29:
    - `php artisan test` — **517 passed, 3594 assertions** (full suite, no regressions).
    - `php artisan test --filter=SavedSearch` — 21 passed (both test files).
    - `vendor\bin\pint --test` — passed.
    - `php vendor/bin/phpstan analyse --memory-limit=512M --no-progress` — passed, 0 errors.
    - `npm run types:check` — passed.
    - `npm run check` — passed.
    - `npm run build` — passed.
- Local testing notes (not bugs): CORS error when mixing `localhost` vs `127.0.0.1` — `APP_URL` and browser must use the same host; resolved by clearing config cache and using consistent host. UI card padding fixed on saved-searches page (`px-4` on `CardHeader`/`CardFooter`).

## Durable notes

- Preserve custom authorization architecture and last-administrator safeguards.
- Keep route permission middleware and Policy checks authoritative on the server.
- Rebuild frontend assets (`npm run build`) after modifying controller actions or route definitions used by Wayfinder.
- Feature 6–9 deferred delete protections active via `restrictOnDelete` FKs on `job_postings`.
- Company members scoped per company; never grant platform-admin permissions.
- Never store `.env` values, credentials, tokens, private keys, or personal data here.

## Next recommended step

Commit the Feature 29 working-tree changes on `main`, then proceed to Feature 30 (Report a job or employer) per `project-overview.md`.
