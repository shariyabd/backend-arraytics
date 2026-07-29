# API Handover — Authentication Module

**Module:** A — Identity & Access (Auth) · **Type:** Supporting (Golden Module)
**Base path:** `/api/v1` · **Auth:** Bearer token (Laravel Sanctum)
**Status:** Implemented, tested (33 passing), Pint clean.

This document is the implementation-accurate contract for the Auth API. It is the reference (Golden Module) other modules mirror. Complete enough to consume or re-implement without further clarification.

---

## 1. Overview

Token-based authentication for the Address Book API. `register` and `login` are the public endpoints: both issue a Sanctum personal access token. Protected endpoints require that token. This module exposes only the authenticated identity (`UserId`) to other modules — no internal details.

- Registration is public and self-service; accounts may also be pre-seeded.
- Both `register` and `login` return the same `{ user, token, token_type }` shape, so the client can treat a fresh registration as an immediate login.
- Tokens use Sanctum defaults (no custom TTL).

---

## 2. Endpoints

| Operation | Method & Path | Access | Success | Route name |
|-----------|---------------|--------|---------|------------|
| Register | `POST /api/v1/register` | Public (throttled) | 201 | `api.v1.register` |
| Login | `POST /api/v1/login` | Public (throttled) | 200 | `api.v1.login` |
| Current user | `GET /api/v1/me` | Protected | 200 | `api.v1.me` |
| Logout | `POST /api/v1/logout` | Protected | 200 | `api.v1.logout` |

**Throttle:** the public endpoints (`register`, `login`) are each rate-limited to **6 requests/minute** per client; the 7th returns `429`.

---

## 3. Uniform Response Envelope

**Success**
```json
{ "success": true, "message": "string", "data": <payload>|null }
```

**Error**
```json
{ "success": false, "message": "string", "errors": { "field": ["message"] } | null }
```

Send `Accept: application/json`; write endpoints send `Content-Type: application/json`.

---

## 4. Data Model — User (exposed fields)

| Field | Type | Notes |
|-------|------|-------|
| id | integer | The `UserId` used as `created_by` reference elsewhere. |
| name | string | |
| email | string | |

> Sensitive attributes (`password`, `remember_token`, tokens) are never exposed.

---

## 5. Register — `POST /api/v1/register`

Public, throttled. Creates a user, then issues a token so the client is logged in immediately.

### Request body

| Field | Type | Rules |
|-------|------|-------|
| name | string | required, max 255 |
| email | string | required, valid email, max 255, **unique** (not already registered) |
| password | string | required, min 8, must match `password_confirmation` |
| password_confirmation | string | required, must equal `password` |
| device_name | string | optional; max 255 — labels the issued token (defaults to the user agent, then `api-token`) |

**Example**
```json
{ "name": "Jane Doe", "email": "jane@example.com", "password": "password123", "password_confirmation": "password123", "device_name": "web" }
```

**Response — 201**
```json
{
  "success": true,
  "message": "Registered successfully.",
  "data": {
    "user": { "id": 2, "name": "Jane Doe", "email": "jane@example.com" },
    "token": "2|plainTextTokenValue...",
    "token_type": "Bearer"
  }
}
```

Use `data.token` as `Authorization: Bearer <token>` immediately — no separate login call is required.

**Duplicate email / weak password / mismatch — 422**
```json
{
  "success": false,
  "message": "The given data was invalid.",
  "errors": {
    "email": ["The email has already been taken."],
    "password": ["The password field confirmation does not match."]
  }
}
```

---

## 6. Login — `POST /api/v1/login`

### Request body

| Field | Type | Rules |
|-------|------|-------|
| email | string | required, valid email, max 255 |
| password | string | required |
| device_name | string | optional; max 255 — labels the issued token (defaults to the user agent, then `api-token`) |

**Example**
```json
{ "email": "test@example.com", "password": "password", "device_name": "web" }
```

**Response — 200**
```json
{
  "success": true,
  "message": "Logged in successfully.",
  "data": {
    "user": { "id": 1, "name": "Test User", "email": "test@example.com" },
    "token": "1|plainTextTokenValue...",
    "token_type": "Bearer"
  }
}
```

Use the returned `data.token` as `Authorization: Bearer <token>` on protected endpoints.

**Invalid credentials — 422** (unknown email or wrong password, without revealing which):
```json
{
  "success": false,
  "message": "The given data was invalid.",
  "errors": { "email": ["These credentials do not match our records."] }
}
```

---

## 7. Current User — `GET /api/v1/me`

Requires `Authorization: Bearer <token>`.

**Response — 200**
```json
{
  "success": true,
  "message": "Authenticated user retrieved.",
  "data": { "id": 1, "name": "Test User", "email": "test@example.com" }
}
```

---

## 8. Logout — `POST /api/v1/logout`

Requires `Authorization: Bearer <token>`. Revokes the token backing the current request.

**Response — 200**
```json
{ "success": true, "message": "Logged out successfully.", "data": null }
```

After logout the same token is rejected with `401` on subsequent requests.

---

## 9. Error Responses

| Condition | Status | message | errors |
|-----------|--------|---------|--------|
| Invalid credentials (login) | 422 | `The given data was invalid.` | `email` → messages |
| Validation failure (missing/malformed/duplicate email/password mismatch) | 422 | `The given data was invalid.` | field → messages |
| No/invalid/revoked token on protected route | 401 | `Unauthenticated.` | null |
| Too many attempts (register/login) | 429 | throttle message | null |
| Unexpected failure | 500 | `Server error.` (internals hidden in production) | null |

---

## 10. Module-Specific Standards

- Layered flow: Route → Form Request → Controller → Service → Model → Resource. `AuthController` is thin; account creation, credential verification, and token lifecycle live in `AuthService`.
- Output via `UserResource` through the shared `ApiResponse` envelope; failures via the central exception handler.
- Security: passwords hashed via the model's `hashed` cast; sensitive fields hidden; register/login throttled; no secrets or tokens logged.
- Cross-module capability: exposes the authenticated `UserId` (identity by reference) — consumed by the Address Book module for ownership stamping. No other internal is shared.
