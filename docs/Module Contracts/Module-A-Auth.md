# Module A — Identity & Access (Auth)

**Type:** Supporting context · **Status:** Golden Module (reference implementation)
**Owns:** Authentication and the session/token lifecycle.
**Publishes:** The authenticated identity (`UserId`) that Module B consumes.
See shared conventions in [README.md](README.md).

---

## 1. Public Operations

| Operation | Method & Path | Access | Purpose |
|-----------|---------------|--------|---------|
| `login` | `POST /api/v1/login` | Public (throttled 6/min) | Verify credentials, issue a bearer token. |
| `me` | `GET /api/v1/me` | Protected | Return the current authenticated user. |
| `logout` | `POST /api/v1/logout` | Protected | Revoke the current access token. |

**Internal capability exposed to other modules (not HTTP):**

| Capability | Contract | Consumer |
|-----------|----------|----------|
| `currentUserId()` | Returns the authenticated `UserId` for the request. | Module B (ownership stamping, scoping). |

---

## 2. Request DTOs

**LoginRequest**
```
{
  email:        string (required, valid email, max 255)
  password:     string (required)
  device_name?: string (optional, max 255)   // token label; defaults to user agent
}
```

`me` and `logout` take **no body** — identity comes from the bearer token.

---

## 3. Response DTOs

**AuthTokenResource** (returned by `login`)
```
data: {
  user:       UserResource,
  token:      string,          // plain-text bearer token (shown once)
  token_type: "Bearer"
}
```

**UserResource** (returned by `me`, and nested in login)
```
{
  id:    integer,
  name:  string,
  email: string
}
```
> Sensitive attributes (password, remember_token, raw token records) are **never** exposed by contract.

`logout` returns `data: null` with a success message.

---

## 4. Published Events

| Event | Payload | Meaning |
|-------|---------|---------|
| `UserAuthenticated` | `{ userId }` | A user successfully logged in and a token was issued. |
| `AuthenticationFailed` | `{ email }` | A login attempt was rejected (no token issued). |
| `UserLoggedOut` | `{ userId }` | The current token was revoked. |

---

## 5. Consumed Events

None. Auth is self-contained and depends on no other module's events.

---

## 6. Error Contracts

| Condition | Status | `message` | `errors` |
|-----------|--------|-----------|----------|
| Invalid/missing fields | 422 | `The given data was invalid.` | field → messages |
| Invalid credentials | 422 | `The given data was invalid.` | `{ email: ["These credentials do not match our records."] }` |
| Missing/invalid/expired/revoked token (on protected ops) | 401 | `Unauthenticated.` | null |
| Too many login attempts | 429 | (throttle message) | null |
