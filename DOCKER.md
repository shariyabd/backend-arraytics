# Docker Guide — Address Book Management System

Run the entire stack — **MySQL + Laravel API + React SPA** — with a single command. Docker is **optional**; the apps also run natively (see each app's `README.md`).

---

## Prerequisites

- Docker **installed and running**: Docker Engine 24+ (or Docker Desktop) and Docker Compose v2 (`docker compose`, not the legacy `docker-compose`). On macOS/Windows, launch Docker Desktop and wait for it to report "running" before continuing.
- Ports **5173** and **8000** free on the host (MySQL is internal-only by default)

Verify:

```bash
docker --version          # 24+
docker compose version    # v2
docker info               # fails if the Docker daemon isn't running — start Docker Desktop / dockerd first
```

---

## Quick start (one command)

Check out both repos as sibling folders — this backend repo and the frontend repo as `../frontend`:

```
any-parent-folder/
├── backend/                  # this repo
│   ├── docker-compose.yml    # ← run the command below from here
│   ├── Dockerfile
│   └── ...
└── frontend/                 # the React SPA repo
    ├── Dockerfile
    ├── nginx.conf
    └── ...
```

Then, from this **backend** directory (which holds `docker-compose.yml`):

```bash
docker compose up --build
```

That's it. Compose will:

1. Start **MySQL 8** and wait until it is healthy.
2. Build and start the **backend**, which waits for MySQL, generates the app key, runs migrations, and seeds ~50 demo contacts **on a fresh database only**.
3. Build and start the **frontend** (nginx serving the production SPA bundle).

| Service  | URL                              | Notes                          |
|----------|----------------------------------|--------------------------------|
| Frontend | http://localhost:5173            | React SPA                      |
| Backend  | http://localhost:8000/api/v1     | JSON REST API                  |
| MySQL    | internal only                    | db `address_book`, user `root` (defaults — see § Configuration) — not published to the host by default (uncomment the `ports` block in `docker-compose.yml` to expose it) |

**Login:** `test@example.com` / `password`

Stop with `Ctrl+C`, then `docker compose down`. To also wipe the database volume:

```bash
docker compose down -v
```

---

## Architecture

```
┌─────────────┐      /api/*  proxied      ┌─────────────┐        ┌──────────┐
│  frontend   │ ─────────────────────────▶│   backend   │ ─────▶ │  mysql   │
│ nginx :80   │   (same-origin, no CORS)  │ serve :8000 │        │  :3306   │
│  → host 5173│                           │  → host 8000│        │          │
└─────────────┘                           └─────────────┘        └──────────┘
        ▲
   browser :5173
```

- The **frontend image** is a two-stage build: `node:24-alpine` compiles the Vite bundle, then `nginx:alpine` serves the static `dist/`.
- nginx serves the SPA (history-mode fallback to `index.html`) **and** proxies `/api/*` to the `backend` service. Because the browser only ever talks to `localhost:5173`, requests are **same-origin — no CORS is involved** in the Docker setup. The SPA's `VITE_API_BASE_URL` defaults to `/api/v1`, which the proxy forwards.
- The **backend image** runs `php artisan serve` behind an entrypoint that waits for MySQL, generates `APP_KEY`, migrates, and conditionally seeds.
- **MySQL** persists to the named volume `db_data`.

---

## Files

| File                          | Purpose                                                        |
|-------------------------------|----------------------------------------------------------------|
| `docker-compose.yml`             | Orchestrates the three services (in the backend repo)          |
| `Dockerfile`                     | PHP 8.3 CLI + extensions + Composer, runs `artisan serve`      |
| `docker-entrypoint.sh`           | Waits for DB, keygen, `config:cache`, `migrate`, conditional `db:seed` |
| `.dockerignore`                  | Keeps `vendor/`, `.env`, sqlite, logs out of the build context |
| `../frontend/Dockerfile`         | Multi-stage: Vite build → nginx serve                          |
| `../frontend/nginx.conf`         | SPA fallback + `/api` reverse proxy to backend                 |
| `../frontend/.dockerignore`      | Keeps `node_modules/`, `dist/`, `.env` out of the build context|

---

## Configuration

The stack works with **zero configuration** — every knob has a sensible default. To customize, you never edit `docker-compose.yml`: each knob is a `${VAR:-default}` that can be overridden from the shell or from a `.env` file in this directory.

**Overridable (all optional):**

| Variable             | Default        | Meaning                                             |
|----------------------|----------------|-----------------------------------------------------|
| `DOCKER_DB_DATABASE` | `address_book` | MySQL database name (created on first boot)         |
| `DOCKER_DB_PASSWORD` | `secret`       | MySQL root password (demo credential)               |
| `BACKEND_PORT`       | `8000`         | Host port for the API (also updates `APP_URL`)      |
| `FRONTEND_PORT`      | `5173`         | Host port for the SPA (also updates the CORS `FRONTEND_URL`) |

```bash
# one-off, via the shell
BACKEND_PORT=8001 FRONTEND_PORT=5174 docker compose up --build

# or persistently — add lines to the .env in this directory
echo "DOCKER_DB_PASSWORD=my-own-secret" >> .env
```

> **Why the `DOCKER_` prefix?** Compose reads this directory's `.env` for `${...}` substitution — the same file Laravel uses for *native* (non-Docker) runs. Prefixed names keep the two worlds separate: your local `DB_DATABASE`/`DB_PASSWORD` (which describe your host MySQL) can never leak into the containers, and vice versa.

**Fixed inside the compose network (not meant to be changed):**

| Variable        | Value                   | Meaning                                     |
|-----------------|-------------------------|---------------------------------------------|
| `DB_HOST`       | `mysql`                 | Service name on the compose network         |
| `DB_USERNAME`   | `root`                  | The MySQL image's built-in superuser        |
| `APP_DEBUG`     | `false`                 | No stack traces leaked in responses         |

The frontend's API base URL is a **build arg** (`VITE_API_BASE_URL`, baked at build time), because Vite inlines env vars into the bundle. To point the SPA at an external API instead of the proxy, rebuild with `--build-arg VITE_API_BASE_URL=https://api.example.com/api/v1`.

---

## Common operations

```bash
# Rebuild after code changes
docker compose up --build

# Run in the background
docker compose up -d --build

# Tail logs
docker compose logs -f backend

# Run the backend test suite inside the container
docker compose exec backend php artisan test

# Open a shell / tinker
docker compose exec backend bash
docker compose exec backend php artisan tinker

# Re-seed from scratch (drops all data)
docker compose exec backend php artisan migrate:fresh --seed

# Full reset including the DB volume
docker compose down -v && docker compose up --build
```

---

## Troubleshooting

| Symptom | Cause / fix |
|---------|-------------|
| `port is already allocated` | Something already uses 5173/8000. Stop it, or remap without touching the yml: `BACKEND_PORT=8001 FRONTEND_PORT=5174 docker compose up`. |
| Backend can't authenticate to MySQL after changing `DOCKER_DB_PASSWORD`/`DOCKER_DB_DATABASE` | The `db_data` volume was initialized with the *old* credentials — the MySQL image only applies them on first boot. Reset: `docker compose down -v && docker compose up --build`. |
| Backend restarts / "Waiting for MySQL…" loops | MySQL is still initializing on first boot; the entrypoint retries automatically. Give it ~20–30s. |
| SPA loads but API calls 502 | Backend not up yet or crashed — check `docker compose logs backend`. |
| Seed data missing | Seeding runs only when `address_book` is empty. Force it: `docker compose exec backend php artisan migrate:fresh --seed`. |
| Code changes not reflected | Images are built, not bind-mounted. Rerun `docker compose up --build`. |

---

## Publishing images (optional bonus)

```bash
docker compose build
docker tag arraytics-backend  <registry>/address-book-api:latest
docker tag arraytics-frontend <registry>/address-book-spa:latest
docker push <registry>/address-book-api:latest
docker push <registry>/address-book-spa:latest
```
