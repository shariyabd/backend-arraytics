# API Handover — Authentication Module

**Module:** A — Identity & Access (Auth) · **Type:** Supporting (Golden Module)
**Base path:** `/api/v1` · **Auth:** Bearer token (Laravel Sanctum)
**Status:** Implemented, tested (19 passing), Pint clean.

This document is the implementation-accurate contract for the Auth API. It is the reference (Golden Module) other modules mirror. Complete enough to consume or re-implement without further clarification.

---

## 1. Overview

Token-based authentication for the Address Book API. `login` is the only public endpoint; it verifies credentials and issues a Sanctum personal access token. Protected endpoints require that token. This module exposes only the authenticated identity (`UserId`) to other modules — no internal details.

- No registration endpoint (users exist via the seeder).
- Tokens use Sanctum defaults (no custom TTL).

---

## 2. Endpoints

| Operation | Method & Path | Access | Success | Route name |
|-----------|---------------|--------|---------|------------|
| Login | `POST /api/v1/login` | Public (throttled) | 200 | `api.v1.login` |
| Current user | `GET /api/v1/me` | Protected | 200 | `api.v1.me` |
| Logout | `POST /api/v1/logout` | Protected | 200 | `api.v1.logout` |

**Throttle:** login is rate-limited to **6 requests/minute** per client; the 7th returns `429`.

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

## 5. Login — `POST /api/v1/login`

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

## 6. Current User — `GET /api/v1/me`

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

## 7. Logout — `POST /api/v1/logout`

Requires `Authorization: Bearer <token>`. Revokes the token backing the current request.

**Response — 200**
```json
{ "success": true, "message": "Logged out successfully.", "data": null }
```

After logout the same token is rejected with `401` on subsequent requests.

---

## 8. Error Responses

| Condition | Status | message | errors |
|-----------|--------|---------|--------|
| Invalid credentials | 422 | `The given data was invalid.` | `email` → messages |
| Validation failure (missing/malformed fields) | 422 | `The given data was invalid.` | field → messages |
| No/invalid/revoked token on protected route | 401 | `Unauthenticated.` | null |
| Too many login attempts | 429 | throttle message | null |
| Unexpected failure | 500 | `Server error.` (internals hidden in production) | null |

---

## 9. Module-Specific Standards

- Layered flow: Route → Form Request → Controller → Service → Model → Resource. `AuthController` is thin; credential verification and token lifecycle live in `AuthService`.
- Output via `UserResource` through the shared `ApiResponse` envelope; failures via the central exception handler.
- Security: passwords hashed; sensitive fields hidden; login throttled; no secrets or tokens logged.
- Cross-module capability: exposes the authenticated `UserId` (identity by reference) — consumed by the Address Book module for ownership stamping. No other internal is shared.
