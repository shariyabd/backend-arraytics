# Architecture — How We Build It

## Address Book Management System — Backend

**Phase 4 — "How we build it."**
This document defines the architecture style, the layered request flow, module boundaries, the shared kernel contracts, cross-cutting concerns, the technology stack, and the key decisions behind them.

**Related workflow docs:** [01-Product-Brief.md](01-Product-Brief.md) · [02-Requirements-and-Domain.md](02-Requirements-and-Domain.md) · [03-Data-Model.md](03-Data-Model.md) · [05-Guardrails.md](05-Guardrails.md) · [06-Golden-Module.md](06-Golden-Module.md)
**Concrete per-endpoint contracts:** [api-doc/auth.md](../api-doc/auth.md) · [api-doc/address-book.md](../api-doc/address-book.md)

---

## 1. Architecture Style & Rationale

The backend is a single deployable organized as a **layered service architecture** — a linear request pipeline where each layer has exactly one responsibility. Business logic lives in **services**, not controllers.

This style fits a small, single-entity JSON API:

| Driver | Why layered service fits |
|--------|--------------------------|
| One core entity (`Contact`) | A single, well-understood pipeline is enough — no bounded contexts, no microservices. |
| Thin, testable logic | Business logic sits in services, so it is reusable and unit-testable in isolation. |
| Clear ownership of change | Each layer does one job; a developer (or AI agent) always knows where a change belongs. |
| Consistent I/O | Form Requests standardize input; API Resources standardize output. |
| Ownership safety | The service is the single place that stamps `created_by` from the authenticated user — the client can never set it. |

The added service layer is the one piece a trivial CRUD app could skip; we keep it for testability and reuse.

---

## 2. The Layered Flow

Data flows **down** the pipeline; results flow back **up**. A layer only talks to the layer directly below it. All endpoints live under `/api/v1`.

```
   HTTP Request  (/api/v1/...)
        │
        ▼
 ┌──────────────────┐
 │  Route           │  maps URL + verb → controller action; applies auth guard
 └────────┬─────────┘
          ▼
 ┌──────────────────┐
 │  Form Request    │  validates & authorizes input; rejects bad data (422)
 └────────┬─────────┘
          ▼
 ┌──────────────────┐
 │  Controller      │  thin: takes validated data, calls one service, returns a response
 └────────┬─────────┘
          ▼
 ┌──────────────────┐
 │  Service         │  business logic, orchestration, ownership stamping, transactions
 └────────┬─────────┘
          ▼
 ┌──────────────────┐
 │  Model           │  data access & persistence (the aggregate)
 └────────┬─────────┘
          ▼
 ┌──────────────────┐
 │  API Resource    │  shapes the model into the uniform JSON envelope
 └────────┬─────────┘
          ▼
   HTTP Response  (uniform envelope)
```

---

## 3. Layer Responsibilities

**Route (delivery entry)** — Maps URL + verb to a controller action; applies the Sanctum auth guard to all protected endpoints (everything except login). No logic beyond routing and middleware.

**Form Request (validation & authorization)** — Validates all incoming fields against business rules; returns **422** with field-level messages on failure. Never accepts `created_by` from the client — it is not a validatable input.

**Controller (thin coordinator)** — Receives validated data, calls exactly one service method, wraps the result in an API Resource, and returns it with the correct status code. No business logic, no direct data access.

**Service (business logic)** — The heart of the app: implements the workflows (create, update, delete, list/search/filter). **Stamps `created_by` from `auth()->id()`** on create and never changes it on update; enforces invariants; owns transactions. HTTP-independent and reusable.

**Model (persistence / aggregate)** — Represents the aggregate (`Contact`, `User`), owns data access and relationships, enforces mass-assignment protection (`$fillable`) and hidden attributes (`$hidden`). Persistence concerns only.

**API Resource (output shaping)** — Transforms a model into the uniform envelope, exposing only required fields and hiding anything internal or sensitive. The single place output structure is decided.

---

## 4. Modules & Boundaries

Even with one pipeline, files are grouped by feature so work stays isolated. Each module is a full vertical slice through the same layers, so Auth and Address Book can be built in parallel without touching each other's files.

| Module | Role | Owns |
|--------|------|------|
| **A — Auth** | Golden / supporting slice | Login, token issuance, session verification |
| **B — Address Book** | Core | Contact CRUD, search, filter, pagination |
| **Shared foundation** | Shared kernel (§6) | Response envelope, pagination meta, error taxonomy, base classes |

**Boundary rules:**
- **B depends on A only via the authenticated `UserId`** — Auth exposes the authenticated identity and nothing else.
- **A never depends on B.**
- **No cycles** between modules or layers.

Module A is the reference implementation to copy by analogy — see [06-Golden-Module.md](06-Golden-Module.md).

---

## 5. Communication & Dependency Rules

1. **One-way flow.** A layer talks only to the layer directly below it; results flow back up.
2. **Controllers never skip layers.** A controller goes through a service; it never touches a model or the database directly.
3. **Services own business logic.** No business rule lives in a controller, Form Request, or model.
4. **Ownership is set only in the service.** `created_by = auth()->id()`, applied in the service — never from client input, never in the controller.
5. **All output goes through an API Resource.** Controllers never return raw models or ad-hoc arrays.
6. **Errors become a uniform response.** Validation, not-found, auth, and unexpected errors are converted centrally into the standard error envelope — no stack traces leak.
7. **The model depends on nothing above it.** It never calls services or controllers.
8. **Shared kernel depends on nothing** and may be used by any layer.

---

## 6. Shared Kernel Contracts

The shared kernel is a **foundation, not an HTTP module** — it exposes no endpoints, only the primitives every module depends on. It is depended upon by all and depends on none.

### 6.1 Response Envelopes

Every response uses one uniform shape, produced through API Resources (see `app/Support/ApiResponse.php`).

**SuccessEnvelope**
```
{ success: true, message: string, data: <payload>|null }
```

**ErrorEnvelope**
```
{ success: false, message: string, errors: object|null }
```

### 6.2 Pagination Meta

Embedded by any paginated response. Request params: `per_page?` (default **15**, max **100**, configured in `config/contacts.php`) and `page?`.

```
PaginationMeta { current_page: integer, per_page: integer, total: integer, last_page: integer }
```

### 6.3 Canonical Error Taxonomy

The single source of truth all modules map their failures onto. Rendering is centralized in `bootstrap/app.php`; internals are hidden in production.

| Category | Status | Standard `message` |
|----------|--------|--------------------|
| Success | 200 | operation-specific |
| Created | 201 | operation-specific |
| Validation error | 422 | `The given data was invalid.` |
| Unauthenticated | 401 | `Unauthenticated.` |
| Forbidden | 403 | `This action is unauthorized.` |
| Not found | 404 | `Resource not found.` |
| Rate limited | 429 | throttle message |
| Server error | 500 | `Server error.` (internals hidden in production) |

> **Note on 403:** this app uses **see-all** visibility — any authenticated user may read/update/delete any contact, and `created_by` is audit metadata, not an access boundary. There is therefore **no 403 path** in practice; 403 remains in the taxonomy for completeness.

---

## 7. Cross-Cutting Concerns

| Concern | Where handled |
|---------|---------------|
| **Authentication** | Route-level Sanctum guard on all protected endpoints; login is public. |
| **Authorization / ownership scope** | Service layer. See-all visibility; `created_by` is audit metadata only. |
| **Input validation** | Form Request layer (422 + field messages). |
| **Response shaping** | API Resource + shared envelope; never a raw model or ad-hoc array. |
| **Error handling** | Central exception handler in `bootstrap/app.php` maps errors → status codes; production hides internals. |
| **Pagination** | Applied in the service after search/filter (default 15, max 100); formatted by the Resource. |
| **Logging** | Around the service; log unexpected errors, never secrets or tokens. |
| **Rate limiting** | Throttle middleware on the login route — **6 requests/min**. |
| **Security** | Mass-assignment protection and hidden attributes in the model; `created_by` never client-supplied; secrets never exposed. |
| **Transactions** | Managed in the service for multi-step operations. |

---

## 8. Technology Stack

The stack realizes the layered flow on a proven, well-documented, first-party foundation, keeping the dependency surface minimal.

| Layer / Concern | Technology | Version |
|-----------------|-----------|---------|
| Language | PHP | 8.3 |
| Framework | Laravel | 12 |
| Authentication | Laravel Sanctum (token-based) | 4 |
| Database | MySQL (prod) / SQLite in-memory (tests) | 8.0+ / — |
| ORM | Eloquent | bundled |
| Validation | Form Request classes | bundled |
| Output shaping | Eloquent API Resources | bundled |
| Testing | PHPUnit | 11 |
| Code style | Laravel Pint | 1 |

External services are intentionally minimal: **MySQL is the only required dependency.** No email, object storage, third-party auth, or external APIs are in scope.

---

## 9. Key Decisions

Compact record of the architectural decisions. Most re-affirm product mandates from the Product Brief and Requirements.

| Decision | Why | Trade-off |
|----------|-----|-----------|
| **Framework: Laravel 12** | Mandated; first-party support for everything needed; largest, best-documented PHP ecosystem (aids AI-assisted dev). | More built-in weight than a micro-framework — convenience, not overhead, at this scale. |
| **Layered service pattern** | Thin controllers, testable services, unambiguous "where does this go" map. | Extra layer a trivial CRUD app could skip — accepted for testability and reuse. |
| **Auth: Sanctum** | First-party, lightweight token auth ideal for SPA + API; login is the only public endpoint. | Fewer features than Passport (no OAuth grants/scopes) — none needed here. |
| **DB: MySQL + Eloquent** | Mandated; clean aggregate mapping, relationships, mass-assignment protection, indexes for search. | Eloquent can invite N+1 — mitigated by eager loading. |
| **Validation: Form Requests** | Keeps controllers thin; rules reusable and unit-testable; centralizes create/update rule sets. | More small classes — a net win for clarity. |
| **Output: API Resources** | Uniform envelope, hides internal/sensitive fields, returns only required fields. | A thin transform per resource — negligible cost. |
| **Ownership in service** | `created_by` set only from `auth()->id()`, never client input — one place enforces the core rule. | Service is the single source of truth for ownership (intended); observers rejected to keep it explicit. |
| **Centralized exception rendering** | All errors emit the uniform envelope with correct status codes; no stack traces leak. | One central mapping in `bootstrap/app.php` to maintain. |
| **Testing: PHPUnit + SQLite** | Feature tests verify the API contract; in-memory SQLite keeps the suite fast (**48 tests passing**). | SQLite ≠ MySQL for a few edge behaviors — mitigated by keeping DB-specific logic minimal. |
| **Code style: Pint** | Project standard; consistent formatting reduces diff noise. | Everyone must run Pint — trivial cost. |
| **Rate limiting on login** | Mitigates brute-force with zero extra dependencies (**6/min**). | Requires sensible limits; over-tight limits could hinder legitimate retries. |

For full engineering standards (naming, PHP quality, testing discipline, security do/don'ts), see **[05-Guardrails.md](05-Guardrails.md)**.

---

## 10. Version Constraints

| Component | Constraint | Reason |
|-----------|-----------|--------|
| PHP | `^8.3` | Framework baseline; matches installed environment. |
| Laravel Framework | `^12.0` | Mandated; streamlined `bootstrap/app.php` structure. |
| Laravel Sanctum | `^4.0` | Sanctum release compatible with Laravel 12. |
| MySQL | `>= 8.0` | Modern SQL features, JSON, performant indexing. |
| PHPUnit | `^11.0` | Project's chosen test framework. |
| Laravel Pint | `^1.0` | Style enforcement. |

**Policy:** pin major versions; allow patch/minor updates; do not add or change dependencies without approval. Middleware, exceptions, and providers are registered in `bootstrap/app.php` / `bootstrap/providers.php` (Laravel 12 structure) — not a legacy `Kernel.php`.

---

## 11. Extension Strategy

Add features as **vertical slices** — the architecture grows without restructuring:

1. **New feature** = a new slice (Route → Form Request → Controller → Service → Model → Resource) that leaves existing ones untouched.
2. **New business rule** goes into a service; controllers and models stay stable.
3. **Change output shape** = edit only the Resource.
4. **Reuse logic** (jobs, commands, other endpoints) by calling the service directly, since it is HTTP-independent.
5. **New capabilities** (e.g. email, attachments) attach as new modules without disturbing existing layers.
