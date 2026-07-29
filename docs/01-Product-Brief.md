# 01 — Product Brief

**Phase 1 — What & Why.** The lean seed for the Address Book project. Downstream detail lives in
[02-Requirements-and-Domain.md](02-Requirements-and-Domain.md),
[03-Data-Model.md](03-Data-Model.md),
[04-Architecture.md](04-Architecture.md),
[05-Guardrails.md](05-Guardrails.md), and
[06-Golden-Module.md](06-Golden-Module.md).
API reference: [api-doc/auth.md](../api-doc/auth.md), [api-doc/address-book.md](../api-doc/address-book.md), [api-doc/design-brief.md](../api-doc/design-brief.md).

---

## 1. Vision — the idea

Build an **Address Book Management System** as two decoupled applications:

- A **Laravel 12 REST API** — JSON only, no Blade, no views. It owns authentication, business logic, validation, and persistence.
- A **React SPA** (separate project) that consumes the API over token-based auth.

The backend exposes one core entity — a **Contact** (table `contacts`) — and nothing more. Keeping the API decoupled and JSON-only lets the frontend evolve independently and keeps the backend a clean, testable service.

This brief covers the **backend** scope.

---

## 2. Goals & scope

**In scope**

- Token-based authentication via **Laravel Sanctum**.
- Full **Contact CRUD** with server-side validation.
- **Search, filtering, and pagination** on the contact list.
- A **consistent JSON response envelope** across every endpoint.
- Graceful **error handling** via a central exception handler.
- A **seeder** producing ~50 realistic contacts and a test user.
- Clean, layered, maintainable architecture.

**Out of scope**

- Rendering Blade templates or any frontend views/logic.
- User self-registration (users are created via seeder only).
- Accepting `created_by` from client input.
- Exposing unnecessary DB fields or mixing business logic into controllers.

---

## 3. Tech stack

| Layer | Choice |
| --- | --- |
| Backend framework | Laravel 12 (PHP 8.3) |
| API style | RESTful, versioned under `/api/v1`, JSON only |
| Authentication | Laravel Sanctum (bearer tokens) |
| Database | MySQL |
| Frontend (separate) | React SPA |

---

## 4. Feature requirements

### Authentication

- `POST /api/v1/login` — public, **throttled 6/min**. Validates email/password, returns a Sanctum bearer token.
- `GET /api/v1/me` — protected, returns the authenticated user.
- `POST /api/v1/logout` — protected, revokes the current token.
- **No registration** — users are provisioned by the seeder.
- Sanctum default token TTL. Invalid / missing / revoked token → `401`.

### Contact CRUD (all protected)

- **Create** — accepts the contact fields; `created_by` is stamped server-side; returns the created resource with `201`.
- **List** — paginated, with search + filters (see below).
- **Show** — full details of one contact; `404` if not found.
- **Update** — editable fields only; `created_by` never changes.
- **Delete** — removes the record, returns success; `404` if already gone.

### Validation (Form Request classes)

| Field | Rules |
| --- | --- |
| `name` | required, string, max 255 |
| `phone` | required, string, max 30, pattern `^\+?[0-9\s\-()]{7,}$` |
| `email` | required, valid email, max 255 |
| `website` | **optional**, nullable, valid URL, max 255 |
| `gender` | required, one of `Male` \| `Female` \| `Other` |
| `age` | required, integer, `1–150` |
| `nationality` | required, free text, max 255 |
| `created_by` | server-set from `auth()->id()`, immutable — never client input |

### Search, filter & pagination

- **Search** — single keyword across `name`, `email`, `phone` (partial match, case-insensitive).
- **Filters** — `gender`, `nationality`, and age range (`min_age` / `max_age`); combinable.
- **Pagination** — server-side; default **15** per page, max **100**. Filters applied before pagination.

### Seeding

- ~50 realistic contacts plus at least one test user.
- All seeded contacts reference a valid user for `created_by`.

---

## 5. API behavior expectations

**Uniform response envelope**

Success:

```json
{
  "success": true,
  "message": "Contact created successfully.",
  "data": { }
}
```

Error:

```json
{
  "success": false,
  "message": "Validation failed.",
  "errors": {
    "email": ["The email field is required."]
  }
}
```

Every endpoint returns this shape — success carries `data`, errors carry `errors`.

**HTTP status mapping**

| Situation | Status |
| --- | --- |
| Success | 200 |
| Created | 201 |
| Validation error | 422 |
| Unauthorized (bad/missing/revoked token) | 401 |
| Forbidden | 403 |
| Not found | 404 |
| Server error | 500 |

**Error handling**

- A **central exception handler in `bootstrap/app.php`** maps validation failures, auth errors, missing resources, DB exceptions, and unexpected errors into the error envelope.
- Never leak stack traces or sensitive details in production.

---

## 6. Non-functional requirements

**Security**

- Sanctum auth; all contact endpoints require a valid bearer token.
- Mass-assignment protection; sensitive model attributes hidden.
- Rate limiting on the login endpoint (6/min).
- Never log passwords, tokens, or sensitive user data.

**Performance**

- Always paginate; apply filters before pagination.
- Avoid N+1 queries; return only required fields via API Resources.
- Index commonly searched columns (`name`, `email`, `phone`).

**Maintainability**

- Layered flow: **Route → Form Request → Controller → Service → Model → API Resource.**
- **Controllers stay thin**; business logic and ownership stamping (`created_by`) live in **Services**.
- Output always shaped through an **API Resource** — never a raw model.
- RESTful route naming, dependency injection, consistent conventions.
- See [04-Architecture.md](04-Architecture.md) and [05-Guardrails.md](05-Guardrails.md) for the enforced rules.

---

## 7. Acceptance criteria

- [ ] User can authenticate at `POST /api/v1/login` and receive a valid token; `/me` and `/logout` work under auth.
- [ ] The login endpoint is throttled at 6/min; there is no registration endpoint.
- [ ] All contact CRUD endpoints require authentication.
- [ ] `created_by` is always derived from the authenticated user and is immutable.
- [ ] All validation rules are enforced via Form Requests (including the `website` optional case).
- [ ] Search (name/email/phone), filters (gender/nationality/age range), and pagination (default 15, max 100) work correctly.
- [ ] Every response follows the `{ success, message, data }` / `{ success, message, errors }` envelope.
- [ ] Correct HTTP status codes are returned (201 create, 422 validation, 401 auth, 404 missing, etc.).
- [ ] Seeder populates ~50 realistic contacts and a test user.
- [ ] Errors are handled gracefully via the central handler; no stack traces in production.
- [ ] Code follows the layered architecture and Laravel conventions.
