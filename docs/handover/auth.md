# Implementation Handover — Auth Module

**Module:** Identity & Access (Auth) · **Type:** Supporting context
**Status:** ✅ Implemented — this is the **Golden Module** (reference for all others).
**Owner doc:** [../Golden-Module.md](../Golden-Module.md) · **Contract:** [../Module Contracts/Module-A-Auth.md](../Module%20Contracts/Module-A-Auth.md)
**Standards:** [../06-Architecture.md](../06-Architecture.md), [../08-Project-Guardrails.md](../08-Project-Guardrails.md), [../../CLAUDE.md](../../CLAUDE.md)

> This handover documents the already-built module so its patterns can be understood and replicated. No new work is required here unless a listed open question changes behavior.

---

## Module Goal

Establish *who* the actor is and gate access to the rest of the API. Provide token-based authentication (login), expose the current identity, and allow token revocation (logout) — while setting the reference pattern every other module copies.

---

## Responsibilities

- Verify email/password credentials and issue a Sanctum bearer token.
- Expose the authenticated user (`me`) and revoke the current token (`logout`).
- Guard all protected endpoints; reject missing/invalid/expired/revoked tokens.
- Publish the authenticated `UserId` for other modules (identity by reference).
- Throttle the login endpoint against brute force.
- **Not** responsible for: contact data, registration, roles/permissions.

---

## Dependencies

| Depends on | For |
|-----------|-----|
| Shared Kernel — `ApiResponse` ([../../app/Support/ApiResponse.php](../../app/Support/ApiResponse.php)) | Uniform success/error envelope. |
| Shared Kernel — central exception handler ([../../bootstrap/app.php](../../bootstrap/app.php)) | Error → envelope mapping. |
| Laravel Sanctum | Token issuance/verification (`HasApiTokens` on `User`). |
| `User` model + `UserFactory` | Identity persistence and test data. |

Consumed by: **Address Book module** (via the authenticated `UserId`). Auth depends on no business module.

---

## Public Contracts

Full detail: [../Module Contracts/Module-A-Auth.md](../Module%20Contracts/Module-A-Auth.md).

- `POST /api/v1/login` — public, throttled (6/min). Body: `{ email, password, device_name? }`. Returns `{ user, token, token_type }`.
- `GET /api/v1/me` — protected. Returns `UserResource`.
- `POST /api/v1/logout` — protected. Revokes the current token; returns `data: null`.
- Cross-module capability: `currentUserId()` (authenticated identity, by reference).
- Errors: 422 (validation/bad credentials), 401 (unauthenticated), 429 (throttle).

---

## Business Rules

- **BR-A1:** Login is the only public endpoint; all others require a valid bearer token.
- **BR-A2:** Credentials must match an existing user; failures reveal neither which field was wrong nor whether the email exists.
- **BR-A3:** Sensitive attributes (password, remember_token, raw token) are never returned.
- **BR-A4:** Passwords and tokens are never logged.
- **BR-A5:** Logout revokes only the token backing the current request.

---

## Acceptance Criteria

- [x] A user with valid credentials receives a bearer token and their profile.
- [x] Invalid credentials return 422 without leaking which field failed.
- [x] Missing/invalid/revoked token on a protected route returns 401.
- [x] `me` returns the authenticated user; `logout` revokes the current token.
- [x] Login is rate-limited (429 after the limit).
- [x] All responses use the uniform envelope; no sensitive fields exposed.

---

## Definition of Done

- [x] Layered flow respected (Route → Form Request → Controller → Service → Model → Resource).
- [x] Business logic only in `AuthService`; controller thin.
- [x] Validation in `LoginRequest`; no ownership/privileged field accepted.
- [x] Output via `UserResource` + `ApiResponse`; errors centralized.
- [x] Feature + unit tests written and passing (**19 tests, 52 assertions**).
- [x] `vendor/bin/pint --dirty` clean.
- [x] No secrets in logs/responses; production hides internals.

---

## Open Questions Affecting This Module

- **OQ-2:** Is a dedicated logout endpoint required? (Implemented; confirm it stays.)
- **OQ-10:** Token lifetime/expiry policy (currently Sanctum default: no expiry).
- **C-2:** Is login rate limiting mandatory or recommended? (Implemented as mandatory.)
