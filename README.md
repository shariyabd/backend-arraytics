# Address Book Management System — Backend API

A decoupled **Laravel 12 REST API** for managing address-book contacts. JSON only (no Blade/views), consumed by a separate React SPA. Token authentication via Laravel Sanctum; one core entity (`Contact`) owned by the authenticated user who created it.

> This README documents the **backend**. The frontend has its own setup guide. Docker is optional and documented at the end (the non-Docker steps below are the primary, always-supported path).

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
│   ├── Models/                                  # User, Contact
│   └── Support/ApiResponse.php                  # uniform response envelope
├── bootstrap/app.php                            # middleware, routing, exception → envelope
├── config/contacts.php                          # pagination defaults (per_page)
├── database/
│   ├── factories/                               # User, Contact factories
│   ├── migrations/                              # users, contacts, tokens, ...
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
Keep `DB_CONNECTION=sqlite` in `.env` and create the file:

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

Tests run against an in-memory SQLite database (no configuration needed):

```bash
php artisan test --compact          # full suite
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
| Auth | `POST /login` (public, throttled), `GET /me`, `POST /logout` (protected) |
| Address Book | `GET/POST /contacts`, `GET/PUT/PATCH/DELETE /contacts/{id}` (all protected) |

Full request/response/validation/error contracts per module:

- [api-doc/auth.md](api-doc/auth.md)
- [api-doc/address-book.md](api-doc/address-book.md)

Inspect live routes with `php artisan route:list --path=api --except-vendor`.

---

## 8. Docker (Optional — not yet provided)

Docker is a bonus. A `docker-compose.yml` (backend + frontend + MySQL, single-command startup) is **planned** and will be added once the frontend integration is complete. Until then, use the non-Docker steps in §4, which remain the fully supported path.

---

## 9. Frontend

The React SPA lives in the sibling `frontend/` directory and is documented separately. It consumes this API using the base URL and the token flow described in §5 and `api-doc/`.
