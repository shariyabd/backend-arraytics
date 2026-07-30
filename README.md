# Address Book Management System — Backend API

A decoupled **Laravel 12 REST API** for managing address-book contacts. JSON only (no Blade/views), consumed by a separate React SPA. Token authentication via Laravel Sanctum; one core entity (`Contact`, stored in the `address_book` table) owned by the authenticated user who created it.

> This README documents the **backend**. The frontend has its own setup guide. Docker is optional and documented at the end (the non-Docker steps below are the primary, always-supported path).

> **Folder layout assumption:** the docs and `docker-compose.yml` expect the two repos checked out as **sibling folders named `backend` and `frontend`** (e.g. `git clone <backend-url> backend`). If your checkout folders are named differently, either rename them or adjust the `../frontend` paths in `docker-compose.yml` and the cross-links.

---

## 1. Tech Stack & Versions

| Tool | Version | Notes |
|------|---------|-------|
| PHP | **8.3** (min 8.2) | CLI + extensions: `pdo`, `pdo_mysql` (or `pdo_sqlite`), `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json` |
| Composer | **2.x** | Dependency manager |
| Laravel | **12** | Framework |
| Laravel Sanctum | 4 | Token auth |
| MySQL | **8.x** | Primary database (SQLite supported for quick start) |
| PHPUnit | 11 | Testing |
| Node.js | 20+ | **Optional** — only for the bundled `composer dev`/`composer setup` convenience scripts; not required to run the API |

---

## 2. Architecture

Every request flows through a strict, one-directional layered pipeline:

```
Route → Form Request → Controller → Service → Model → API Resource
```

- **Controllers are thin** — validate via Form Request, call one Service method, return a Resource.
- **All business logic and ownership stamping live in Services.** `created_by` is set only in the service from `auth()->id()` — never accepted from the client.
- **Output always goes through an API Resource** into the uniform envelope `{ success, message, data }`.
- **Errors are centralized** in `bootstrap/app.php` and rendered through the same envelope.

### Folder map (key paths)

```
backend/
├── app/
│   ├── Http/
│   │   ├── Controllers/Api/V1/{Auth,Contact}/   # thin controllers, versioned
│   │   ├── Requests/Api/V1/{Auth,Contact}/      # Form Request validation
│   │   └── Resources/Api/V1/                    # API Resources (output shaping)
│   ├── Services/{Auth,Contact}/                 # business logic (the heart)
│   ├── Models/                                  # User, Contact (maps to `address_book`)
│   └── Support/ApiResponse.php                  # uniform response envelope
├── bootstrap/app.php                            # middleware, routing, exception → envelope
├── config/contacts.php                          # pagination defaults (per_page)
├── config/cors.php                              # allowed origins (env FRONTEND_URL)
├── database/
│   ├── factories/                               # User, Contact factories
│   ├── migrations/                              # users, address_book, tokens, ...
│   └── seeders/                                 # DatabaseSeeder → ContactSeeder (~50)
├── routes/api.php                               # /api/v1 routes
├── tests/{Feature,Unit}/Api/V1/                 # endpoint + service tests
├── api-doc/                                     # module-wise API handover docs
└── docs/                                         # design & engineering docs
```

---

## 3. Prerequisites

Install and confirm:

```bash
php -v         # 8.3.x (>= 8.2)
composer -V    # 2.x
mysql --version # 8.x  (skip if using the SQLite quick start)
```

Ensure the PHP extensions listed in §1 are enabled (`php -m` to check).

---

## 4. Setup (Non-Docker) — the primary path

### 4.1 Install dependencies

```bash
cd backend
composer install
```

### 4.2 Create and key the environment file

```bash
cp .env.example .env
php artisan key:generate
```

### 4.3 Configure the database

**Option A — MySQL (recommended, matches production).**
Create a database, then set these keys in `.env`:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=address_book
DB_USERNAME=root
DB_PASSWORD=your_password
```

```bash
mysql -u root -p -e "CREATE DATABASE address_book CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

**Option B — SQLite (zero-config quick start).**
`.env.example` defaults to MySQL — edit `.env`: comment out the MySQL `DB_*` block and uncomment the SQLite lines (`DB_CONNECTION=sqlite`, `DB_DATABASE=database/database.sqlite`), then create the file:

```bash
touch database/database.sqlite
```

### 4.4 Run migrations and seed data

```bash
php artisan migrate --seed
```

This creates the schema and seeds **1 test user + ~50 realistic contacts** (each `created_by` references a valid user).

### 4.5 Start the API

```bash
php artisan serve
```

The API is now at **http://localhost:8000** (base path `/api/v1`).

### 4.6 CORS (cross-origin) for the SPA

The API restricts browser origins in [config/cors.php](config/cors.php) to the value of `FRONTEND_URL` (default `http://localhost:5173`). If you run the SPA on a different origin, set it in `.env`:

```dotenv
FRONTEND_URL=http://localhost:5173
```

> Not needed when using Docker or the SPA's Vite dev proxy — in those setups requests are same-origin.

---

## 5. Seeded Credentials

Use these to obtain a token via the login endpoint:

| Field | Value |
|-------|-------|
| Email | `test@example.com` |
| Password | `password` |

```bash
curl -X POST http://localhost:8000/api/v1/login \
  -H "Accept: application/json" -H "Content-Type: application/json" \
  -d '{"email":"test@example.com","password":"password"}'
```

Copy `data.token` from the response and send it as `Authorization: Bearer <token>` on protected endpoints, e.g.:

```bash
curl http://localhost:8000/api/v1/contacts \
  -H "Accept: application/json" -H "Authorization: Bearer <token>"
```

---

## 6. Running Tests

Tests run against an in-memory SQLite database (no configuration needed). The suite is **72 tests / 253 assertions** and covers auth (login/register/logout, rate limiting), full CRUD, every validation rule (including digit-less phone and `min_age > max_age`), owner-scoping/IDOR (a user cannot read/update/delete another user's contact → `404`), pagination navigation, filters (including `max_age` alone), and wildcard-escaped search:

```bash
php artisan test --compact          # full suite (72 passing)
php artisan test --compact --filter=Contact   # a subset
```

Code style (Laravel Pint):

```bash
vendor/bin/pint          # fix
vendor/bin/pint --test   # check only
```

---

## 7. API Overview

Base path: `/api/v1`. Uniform envelope: `{ success, message, data }` (or `{ success, message, errors }`).

| Module | Endpoints |
|--------|-----------|
| Auth | `POST /register`, `POST /login` (public, throttled), `GET /me`, `POST /logout` (protected) |
| Address Book | `GET/POST /contacts`, `GET/PUT/PATCH/DELETE /contacts/{id}` (all protected) |

Full request/response/validation/error contracts per module:

- [api-doc/auth.md](api-doc/auth.md)
- [api-doc/address-book.md](api-doc/address-book.md)

Inspect live routes with `php artisan route:list --path=api --except-vendor`.

---

## 8. Running the Full System (Backend + Frontend) — two options

The backend and frontend are **two independent git repositories**. You can run the whole system either natively (two terminals, no Docker) or with Docker (one command). Both paths are fully supported — pick one.

### Option A — Native, two separate repos run locally (no Docker required)

1. Clone both repos side by side:
   ```bash
   git clone <backend-repo-url> backend
   git clone <frontend-repo-url> frontend
   ```
2. **Terminal 1 — backend:** follow §4 above (`composer install`, `.env` + key, `php artisan migrate --seed`, `php artisan serve` → http://localhost:8000).
3. **Terminal 2 — frontend:** follow [../frontend/README.md](../frontend/README.md) (`npm install`, `cp .env.example .env`, `npm run dev` → http://localhost:5173).
4. Open **http://localhost:5173** and log in with the seeded credentials (§5). No CORS setup is needed — the frontend's dev proxy makes requests same-origin.

### Option B — Docker (one command)

**Prerequisite:** Docker must be **installed and running** before this step — Docker Engine/Desktop **24+** with Compose v2. Start Docker Desktop (or the Docker daemon) first, then verify:

```bash
docker --version          # 24+
docker compose version    # v2 (the `docker compose` plugin, not legacy docker-compose)
docker info               # errors here mean the daemon isn't running yet
```

A [docker-compose.yml](docker-compose.yml) in this repo runs **MySQL + this API + the React SPA** together. Check out the frontend repo alongside this one as a sibling folder named `frontend` (`../frontend`), so your directory tree looks like this:

```
any-parent-folder/
├── backend/                  # this repo (contains docker-compose.yml, Dockerfile)
│   ├── app/
│   ├── docker-compose.yml    # ← run `docker compose up --build` from here
│   └── ...
└── frontend/                 # the React SPA repo (contains its own Dockerfile)
    ├── src/
    ├── Dockerfile
    └── ...
```

Then, from this **backend** directory:

```bash
docker compose up --build
```

- Frontend: http://localhost:5173
- Backend API: http://localhost:8000/api/v1
- The backend container waits for MySQL, generates the app key, migrates, and seeds ~50 contacts on a fresh database.
- Ports and DB credentials are overridable via environment variables (`BACKEND_PORT`, `FRONTEND_PORT`, `DOCKER_DB_DATABASE`, `DOCKER_DB_PASSWORD`) — no need to edit `docker-compose.yml`. See [DOCKER.md](DOCKER.md) § Configuration.

Full details, architecture, and troubleshooting: **[DOCKER.md](DOCKER.md)**. The non-Docker steps in §4 remain the primary, always-supported path.

---

## 9. Frontend

The React SPA lives in the sibling `frontend/` directory and is documented in **[../frontend/README.md](../frontend/README.md)**. It consumes this API using the base URL and the token flow described in §5 and [api-doc/](api-doc/).

---

## 10. Troubleshooting

| Symptom | Likely cause | Fix |
|---------|--------------|-----|
| `SQLSTATE[HY000] [2002] Connection refused` on migrate | MySQL not running, or wrong `DB_HOST`/`DB_PORT` | Start MySQL; verify the `DB_*` values in `.env` match your server. Or use the SQLite quick start (§4.3 Option B). |
| `SQLSTATE[HY000] [1045] Access denied` | Wrong `DB_USERNAME`/`DB_PASSWORD` | Fix credentials in `.env`; confirm the user can access the `address_book` database. |
| `Unknown database 'address_book'` | Database not created | Run the `CREATE DATABASE` command in §4.3. |
| `php artisan serve` fails: port 8000 in use | Another process owns the port | `php artisan serve --port=8001` (and update the frontend's `VITE_API_TARGET`). |
| Browser CORS error from the SPA | SPA origin not in the allow-list (only applies when the SPA calls the API cross-origin) | Set `FRONTEND_URL` in `.env` to the SPA origin (§4.6). Not needed with the Vite dev proxy or Docker (same-origin). |
| `No application encryption key` | `.env` created without a key | `php artisan key:generate`. |
| Config changes seem ignored | Cached config | `php artisan config:clear`. |
| Tests emit warnings / odd failures | Missing `.env` | `cp .env.example .env && php artisan key:generate` first — tests themselves use in-memory SQLite via `phpunit.xml`, no DB setup needed. |
| Docker: frontend build fails with a context error | Frontend repo not checked out at `../frontend` | See the folder-layout note at the top; clone the frontend as a sibling named `frontend`. |

---

## 11. Assumptions & documented decisions

Ambiguities in the assignment were resolved explicitly and recorded (per module docs and [CLAUDE.md](CLAUDE.md)); the key ones:

- **Visibility is owner-only** — a user can read/update/delete only contacts they created; another user's contact resolves to `404` so existence isn't leaked (OQ-1).
- **Age** must be an integer 1–150 (OQ-3); **gender** ∈ `Male | Female | Other` (OQ-4); **nationality** is free text (OQ-5); **website** is optional (OQ-11).
- **Pagination** defaults to 15 per page, max 100 (`config/contacts.php`).
- Full decision log: [docs/02-Requirements-and-Domain.md](docs/02-Requirements-and-Domain.md) and [api-doc/address-book.md](api-doc/address-book.md).
