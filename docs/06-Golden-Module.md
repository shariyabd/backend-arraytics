# Golden Module — Authentication (Reference Implementation)

## Address Book Management System — Backend

**Version:** 1.0
**Status:** Implemented, tested, green (28 passing tests).
**Purpose:** The Authentication module is the **reference implementation** every future module must copy. This document maps the built code to the architecture so a new module can be produced by analogy.
**Source Documents:** [04-Architecture.md](04-Architecture.md), [05-Guardrails.md](05-Guardrails.md), [api-doc/auth.md](../api-doc/auth.md)

---

## 1. What the Golden Module Is

A complete vertical slice through every layer of the service-pattern architecture, for the Auth bounded context. It demonstrates — in real, passing code — exactly how the layered flow, ownership rule, uniform envelope, validation, logging, exception handling, and testing fit together.

> **Rule for all future modules:** do not invent new patterns. Mirror the file layout, layer responsibilities, naming, and conventions shown here.

---

## 2. The Layered Flow (as built)

```
POST /api/v1/login
      │
      ▼
routes/api.php ─────────────► throttle + route → controller action
      │
      ▼
LoginRequest ──────────────► validate email/password (422 on failure)
      │
      ▼
AuthController::login ──────► thin: calls AuthService, wraps result
      │
      ▼
AuthService::login ────────► business logic: verify creds, issue token, log
      │
      ▼
User (model, HasApiTokens) ► persistence + token creation
      │
      ▼
UserResource + ApiResponse ► uniform { success, message, data } envelope
```

---

## 3. File Map (copy this structure per module)

| Layer | Golden Module file | Responsibility |
|-------|--------------------|----------------|
| Route | [routes/api.php](../routes/api.php) | `/api/v1` group; public+throttled register/login, protected me/logout. |
| Form Request | [RegisterRequest.php](../app/Http/Requests/Api/V1/Auth/RegisterRequest.php) · [LoginRequest.php](../app/Http/Requests/Api/V1/Auth/LoginRequest.php) | Input validation; no ownership fields accepted. |
| Controller | [app/Http/Controllers/Api/V1/Auth/AuthController.php](../app/Http/Controllers/Api/V1/Auth/AuthController.php) | Thin coordinator; injects service; returns Resource via `ApiResponse`. |
| Service | [app/Services/Auth/AuthService.php](../app/Services/Auth/AuthService.php) | All business logic; token lifecycle; logging. HTTP-independent. |
| Model | [app/Models/User.php](../app/Models/User.php) | Aggregate; `$fillable`, `$hidden`, `HasApiTokens`. |
| Resource | [app/Http/Resources/Api/V1/UserResource.php](../app/Http/Resources/Api/V1/UserResource.php) | Output shaping; exposes only safe fields. |
| Shared (envelope) | [app/Support/ApiResponse.php](../app/Support/ApiResponse.php) | Uniform success/error envelope; reused by every module. |
| Shared (errors) | [bootstrap/app.php](../bootstrap/app.php) | Central exception → envelope mapping (422/401/403/404/500). |
| Feature tests | [AuthenticationTest.php](../tests/Feature/Api/V1/Auth/AuthenticationTest.php) · [RegistrationTest.php](../tests/Feature/Api/V1/Auth/RegistrationTest.php) | Endpoint contract: happy, validation, auth, revocation, throttle. |
| Unit tests | [tests/Unit/Services/Auth/AuthServiceTest.php](../tests/Unit/Services/Auth/AuthServiceTest.php) | Service logic in isolation. |

**Namespace convention:** `App\Http\{Controllers,Requests,Resources}\Api\V1\<Feature>\…`, `App\Services\<Feature>\…`. Tests mirror the same path.

---

## 4. Conventions Established (must be reused)

1. **Thin controller.** No business logic, no queries. Inject the service via constructor property promotion; return `ApiResponse::success(...)`.
2. **Business logic only in the service.** Services receive plain values (not `Request` objects) so they are reusable and unit-testable.
3. **Ownership stamped only in the service** — from the authenticated identity, never accepted as input. (In Auth this is the token owner; in data modules it is `created_by = auth()->id()`.)
4. **Uniform envelope everywhere** — success via `ApiResponse::success`, all failures via the central handler in `bootstrap/app.php`. Never return a raw model/array.
5. **Resource for output** — only safe fields; sensitive attributes hidden.
6. **Validation in a Form Request** — returns 422 with field messages; `authorize()` reflects access policy.
7. **Logging in the service** — log meaningful events (login, logout, failed attempt) and **never** log passwords or tokens.
8. **Versioned routes** — everything under `/api/v1`; named routes (`api.v1.*`).
9. **Tests first-class** — feature tests cover happy/validation/auth/edge; unit tests cover service logic; run Pint + tests before done.

---

## 5. Public Contract (summary)

Full contract: [api-doc/auth.md](../api-doc/auth.md).

- **Operations:** `POST /api/v1/register` (public, throttled 6/min, 201), `POST /api/v1/login` (public, throttled 6/min), `GET /api/v1/me`, `POST /api/v1/logout` (protected).
- **Cross-module capability:** exposes the authenticated `UserId` (identity by reference) for the Address Book module — no other internal is shared.
- **Errors:** 422 (validation/bad credentials), 401 (unauthenticated), 429 (throttle).

---

## 6. How to Build a New Module From This

1. Create the same folders/namespaces for the new feature (`Api/V1/<Feature>`).
2. Write the Form Request(s) — validate inputs; **exclude** ownership fields.
3. Write the Service — put all business logic and ownership stamping here.
4. Write the thin Controller — delegate to the service, return `ApiResponse` + Resource.
5. Write the Resource(s) — expose only required, non-sensitive fields.
6. Register versioned, guarded routes in `routes/api.php`.
7. Reuse the shared `ApiResponse` and the central exception handler — do not add a second envelope.
8. Write feature + unit tests mirroring the Auth test structure.
9. Run `vendor/bin/pint --dirty` and `php artisan test`; both must pass.

---

## 7. Verification Status

- Sanctum installed and wired; `HasApiTokens` on `User`.
- All routes registered under `/api/v1`.
- **Auth slice: 28 tests passing; full suite: 66 tests passing.** Pint clean.
- Login failures return 422 (framework convention); token failures return 401 via the Sanctum guard.

---

### Note

This is a documentation index over real, tested code — the code is the source of truth. If the code and this document ever diverge, update this document. New modules that deviate from these conventions require an updated decision record in [04-Architecture.md](04-Architecture.md).
