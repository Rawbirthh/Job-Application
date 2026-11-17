# Setting Up This Project From Scratch

How to go from a fresh clone to a running local development instance. Local
development only — deployment is a separate, future task and intentionally
not covered here.

## Prerequisites

- **PHP ≥ 8.3** (developed on 8.5) with the usual Laravel extensions: `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `bcmath`, `curl`, `dom`, `fileinfo`, `intl` — if `php -m` lists those, you are fine.
- **Composer ≥ 2** (developed on 2.9)
- **Node ≥ 22** (developed on 24.x) and **npm ≥ 11**
- **MySQL or MariaDB** running locally (the project is developed against MySQL; the test suite does NOT need MySQL — it uses in-memory SQLite)

## 1. Clone and enter the project

```bash
git clone <repo-url> JobApplication
cd JobApplication
```

## 2. Create the database

```sql
CREATE DATABASE jobapplication CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Any name works — match it in `.env`. If your MySQL uses a custom port, note it (the project author's instance runs on `3309`; stock MySQL is `3306`).

## 3. One-command base setup

```bash
composer setup
```

This runs, in order: `composer install` → copy `.env.example` to `.env` (if missing) → `php artisan key:generate` → `php artisan migrate --force` → `npm install` → `npm run build`. It does **not** create the database or seed it, and it will fail midway if the database from step 2 doesn't exist — so do the database first.

## 4. Configure `.env` for your machine

Required edits (open the file, values depend on your machine):

```dotenv
APP_URL=http://127.0.0.1:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306              # or your MySQL port (author uses 3309)
DB_DATABASE=jobapplication
DB_USERNAME=root
DB_PASSWORD=
```

Notes on the rest of the defaults:

- `QUEUE_CONNECTION=database` — already correct. The queue worker is required for queued jobs (verification mail, etc.). `composer dev` starts it automatically.
- `MAIL_MAILER=log` — verification links are written to `storage/logs/laravel.log`, never delivered. To receive real mail, see the Mailtrap walkthrough in `README.md` ("Email verification (employer onboarding — Feature 38)").
- `REVERB_*` placeholders — fine for local dev; both server and browser read the same values, so messaging works out of the box.
- `SESSION_DRIVER=database`, `CACHE_STORE=database` — work as-is after migrations.
- Keep your browser on the same host everywhere (`127.0.0.1:8000` or `localhost:8000`) to avoid cookie/CORS confusion; if you change `APP_URL`, match the browser to it.

## 5. Seed the database

```bash
php artisan db:seed
```

This seeds the authorization catalogue (roles, permissions) and creates one **Test User** (`test@example.com`, password `password` — standard factory default) with the job-seeker role.

**To create a platform administrator** (no public route exists; provisioning is deliberately a CLI action):

```bash
php artisan auth:assign-role your-email@example.com platform_admin
```

## 6. Run the app

Recommended — everything at once:

```bash
composer dev or php artisan dev
```

This runs (verified via `php artisan dev:list`): the web server on `127.0.0.1:8000`, the Vite dev server, the queue worker (`queue:listen`), and the Reverb WebSocket server for real-time messaging.

Or run them individually in separate terminals:

```bash
php artisan serve           # web app on 127.0.0.1:8000
npm run dev                 # Vite dev server (auto-rebuilds)
php artisan queue:listen    # required for queued jobs (verification mail)
php artisan reverb:start    # real-time WebSocket server
```

**If you only run `php artisan serve`, the app works but nothing is delivered in real time and no queued mail is sent.** Notifications/messages persist, but you'll only see them after a refresh until `queue:listen` and `reverb:start` are running.

## 7. Log in and verify things work

1. Register an account at `/register` — no verification email is sent at registration (by design).
2. Open `/employer/onboarding` — you'll see a "Verify your email" card with a **Resend verification email** button. Click it, then find the signed link in `storage/logs/laravel.log` (or in your Mailtrap inbox if you configured it), and open it — the form unlocks.
3. Queue: mail arrives within seconds of clicking Resend while `queue:listen` runs. Without it, jobs accumulate silently in the `jobs` table (`php artisan tinker --execute="echo DB::table('jobs')->count().PHP_EOL;"`).
4. Real-time: two browser tabs, one on each side of an application conversation, see messages live only with Reverb running.

## 8. Tests and checks

```bash
php artisan test     # Pest suite — runs entirely on in-memory SQLite (no MySQL required)
composer ci:check    # vp check + tsc + pint + phpstan + full test suite (run unpiped)
npm run types:check  # TypeScript
npm run build        # production build + Wayfinder route regeneration
```

## Troubleshooting

| Symptom                                     | Cause / fix                                                                                                                      |
| ------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------- |
| `SQLSTATE[HY000] [2002] Connection refused` | MySQL not running, or wrong `DB_HOST`/`DB_PORT` in `.env`.                                                                       |
| Blank page / stale assets                   | Ran `npm run build` long ago or Vite not running → run `composer dev` or `npm run dev`, hard-refresh.                            |
| `419 Page Expired`                          | Host mismatch (browser on `localhost`, `APP_URL` on `127.0.0.1`). Make them consistent.                                          |
| Verification mail never arrives             | `MAIL_MAILER=log` → link is in `storage/logs/laravel.log`; or queue worker not running (jobs pile up in `jobs` table).           |
| Messages don't arrive live                  | `reverb:start` not running, or broadcast config mismatch — both read `REVERB_*` from the same `.env`, so just restart it.        |
| `composer setup` failed midway              | The MySQL database wasn't created before it, or MySQL wasn't reachable. Create it (step 2), then re-run.                         |
| Port 8000 in use                            | Stop the other process or pick a different port (`php artisan serve --port=8001`) and match `APP_URL`.                           |
| Migrations fail on FK/index on MySQL        | Migrations are written for both MySQL and SQLite with `Schema::hasIndex()` guards — report the exact error if one slips through. |

## Directory orientation (30 seconds)

```
app/                 Laravel backend (models, services, policies, controllers)
routes/web.php       Public + authenticated web routes (employer.* route group)
routes/admin.php     Admin panel routes (admin.employers.*, jobs.*, audit…)
resources/js/pages/  Inertia React pages (auth/, employer/, admin/, public)
resources/js/components/  shadcn/ui + app components
database/            Migrations, factories, seeders
docs/                Specs (docs/specs/), authorization catalogue, this file
phpunit.xml          Test env: SQLite in-memory, sync queue, null broadcast
```
