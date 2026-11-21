# Running This Project With Docker (Local Development)

This is the containerized path for local development on Windows + WSL2 / Docker
Desktop. It runs: **MariaDB 11** (matching XAMPP), **the Laravel app** (`php
artisan serve` through a PHP CLI image), **the queue worker**, **Reverb** for
real-time, and optionally **phpMyAdmin** in a `tools` profile so you can browse
the Docker database the same way you use `http://localhost/phpmyadmin/`.

Your host `.env` stays untouched and keeps pointing at XAMPP (`127.0.0.1:3309`);
the compose services **override** `DB_*` (and `REVERB_HOST`) so the container
talks to the container database instead. Production is a separate, future task
and is intentionally not covered here.

## What you get

| Service      | Port (host)      | What it is                                                                                      |
| ------------ | ---------------- | ----------------------------------------------------------------------------------------------- |
| `app`        | `127.0.0.1:8000` | Laravel app via `php artisan serve` (code bind-mounted from the project folder, edits are live) |
| `mysql`      | `3310`           | MariaDB 11 with database `jobapplication`, seeded by you via `migrate`/`db:seed`                |
| `queue`      | —                | `queue:listen` — required for queued mail (verification links) etc.                             |
| `reverb`     | `8080`           | WebSocket server for real-time messaging                                                        |
| `phpmyadmin` | `8081`           | Optional (`tools` profile). Like XAMPP's phpMyAdmin for the DOCKER database                     |

## Prerequisites

- Docker Desktop running with the **WSL2** backend enabled (you already have this).
- The project cloned anywhere on your WSL path (fast) or `C:\...` (slower bind-mount; it works, edits are just slower to propagate).
- Your host currently has XAMPP running or not - **doesn't matter**, Docker's MariaDB is separate (`3310` on the host maps to `3306` inside).
- Stop the host's `php artisan serve` on port `8000` first, or pick a different port by changing the `app` service's `ports:` line (compose + `.env` accordingly).

## First run (do these once in order)

1. Build the image:

    ```bash
    docker compose build
    ```

2. Install PHP dependencies **inside** the container (creates `vendor/` on the host, shared through the bind mount):

    ```bash
    docker compose run --rm app composer install
    ```

3. Create all tables in the Docker database and seed it (authorization catalogue + one test job seeker):

    ```bash
    docker compose run --rm app php artisan migrate
    docker compose run --rm app php artisan db:seed
    or if you don't wanna store all seed and you want the app to run just command this
    docker compose run --rm app php artisan db:seed --class=AuthorizationSeeder
    ```

4. Create a platform administrator (no public route exists):

    ```bash
    docker compose run --rm app php artisan auth:assign-role your-email@example.com platform_admin
    ```

5. Build the frontend once (or leave Vite running on the host, see below):

    ```bash
    npm run build
    ```

6. Start everything:

    ```bash
    docker compose up
    ```

    Leave it running; logs from all services stream into one terminal. Ctrl+C stops all of them.

7. Open `http://127.0.0.1:8000`. Register an account, open `/employer/onboarding`,
   click **Resend verification email**, then find the signed link either in your
   Mailtrap inbox (if you configured SMTP in `.env`) or via the host log at
   `storage/logs/laravel.log`.

8. Optional: phpMyAdmin for the Docker database:

    ```bash
    docker compose --profile tools up
    ```

    → `http://localhost:8081` (user `root`, password `secret` — the `DOCKER_DB_ROOT_PASSWORD` value; anything you set there works)

## Everyday commands

```bash
docker compose up              # start everything (ctrl+c to stop)
docker compose up -d           # start in the background
docker compose down            # stop and remove containers (data kept in the named volume)
docker compose down -v         # ALSO delete the database volume (destructive — wipes the Docker database)
docker compose ps              # status
docker compose logs -f app     # follow one service's logs
docker compose exec app php artisan db:seed   # run commands in a RUNNING app container
docker compose run --rm app php artisan test  # tests run on SQLite in the container, fine
docker compose restart app     # re-read .env changes (e.g. after switching MAIL_MAILER)
```

Frontend development: keep `npm run dev` **on the host** (fastest, HMR works,
no Docker involved) or rely on a one-off `npm run build` after every edit.
The browser talks to Vite at `localhost:5173` — the Laravel app doesn't need
Vite inside Docker.

Real-time messaging: the browser connects to `127.0.0.1:8080` (mapped to the
container's Reverb). The **app container broadcasts by service name** (`REVERB_HOST=reverb`),
which is why `compose.yaml` overrides it — do not "fix" it back to `127.0.0.1` or
broadcasts from inside the container will chase a dead address.

## If something breaks

| Symptom                                                    | Fix                                                                                                                                                                                                                                                                                                                                                                                                                                                             |
| ---------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `port is already allocated` (8000/3310/8080)               | Something else uses the port - stop the host's `php artisan serve` / XAMPP / other container, or change the `ports:` mapping.                                                                                                                                                                                                                                                                                                                                   |
| App returns 500 `Connection refused` with Host `127.0.0.1` | `php artisan serve` passes only a whitelist of env vars (`APP_ENV`, `PATH`, `LARAVEL_SAIL`, `XDEBUG_*`…) to its web worker — so `.env` wins over compose's `environment:` and the app targets the host's `127.0.0.1:3309`, which doesn't exist in the container. The `app` service runs serve with `--no-reload` (uses all env vars as-is and also disables the `.env` watcher). If you hit this, rebuild/restart (`docker compose down && docker compose up`). |
| App returns 500 + logs show SQL/access errors              | The database wasn't migrated in the **Docker** database — run `docker compose run --rm app php artisan migrate` (remember: XAMPP's DB and Docker's DB are separate).                                                                                                                                                                                                                                                                                            |
| `419 Page Expired`                                         | `.env` `APP_URL` and your browser host must match — keep both on `127.0.0.1:8000` (or both on `localhost`).                                                                                                                                                                                                                                                                                                                                                     |
| Verification mail never arrives                            | The `queue` service isn't running — `docker compose logs queue` shows it; it starts with `docker compose up`.                                                                                                                                                                                                                                                                                                                                                   |
| Real-time doesn't work                                     | `reverb` service must be up (`docker compose ps`); the app broadcasts to `reverb:8080`, your browser receives from `127.0.0.1:8080` — both must be healthy.                                                                                                                                                                                                                                                                                                     |
| Edits don't show up                                        | Blade/PHP edits are instant (bind mount); Vite assets require `npm run dev` (host) or a fresh `npm run build`; Wayfinder regenerates on `npm run build`.                                                                                                                                                                                                                                                                                                        |
| Console shows `[::1]:5173 ... ERR_CONNECTION_REFUSED`      | A stale dev artifact `public/hot` points Vite at a dev server that isn't running. Start `npm run dev` if you want HMR, or delete `public/hot` locally to serve the compiled bundle (the hot file overrides `manifest.json`).                                                                                                                                                                                                                                    |
| Site is blank / manifest missing                           | `public/build` is missing — run `npm run build` once before `docker compose up`.                                                                                                                                                                                                                                                                                                                                                                                |
| Windows filesystem slowness                                | The project lives under `C:\...` — bind mounts from WSL2-mounted drives are slower than from the WSL native filesystem; consider cloning under `~/code/` inside WSL for speed.                                                                                                                                                                                                                                                                                  |

## What is committed vs local

`Dockerfile`, `compose.yaml`, `.dockerignore`, and this doc are committed to the
repo. Nothing here contains credentials — the database password inside Docker
defaults to `secret` and can be overridden by exporting `DOCKER_DB_ROOT_PASSWORD`
before running `docker compose`. Your real `.env` (Mailtrap credentials, XAMPP
passwords, etc.) is **not** baked into any image and is ignored via `.dockerignore`.
