# Software Architecture

## Address Book Management System — Backend

**Version:** 2.0
**Role:** Principal Software Architect
**Source Documents:** [PRD.md](PRD.md), [03-REQUIRMENTS-ANALYSIS.md](03-REQUIRMENTS-ANALYSIS.md), [04-DOMAIN-DISCOVERY.md](04-DOMAIN-DISCOVERY.md), [05-DOMAIN-DATA-MODEL.md](05-DOMAIN-DATA-MODEL.md)
**Scope:** A simple, concrete **layered service architecture** for a small project. No code.

---

## 1. Architecture Style

### 1.1 Chosen Style — Layered Service (Service Pattern)

A single backend application organized as a **linear request pipeline** with one clear responsibility per layer:

```
   HTTP Request
        │
        ▼
 ┌──────────────────┐
 │  Route           │   maps URL + verb → controller action, applies auth guard
 └────────┬─────────┘
          ▼
 ┌──────────────────┐
 │  Form Request    │   validates & authorizes input; rejects bad data (422)
 └────────┬─────────┘
          ▼
 ┌──────────────────┐
 │  Controller      │   thin: receives validated data, calls a service, returns a response
 └────────┬─────────┘
          ▼
 ┌──────────────────┐
 │  Service         │   business logic, orchestration, ownership stamping, transactions
 └────────┬─────────┘
          ▼
 ┌──────────────────┐
 │  Model           │   data access & persistence (the aggregate)
 └────────┬─────────┘
          ▼
 ┌──────────────────┐
 │  API Resource /  │   shapes the model into the uniform JSON response
 │  DTO             │
 └────────┬─────────┘
          ▼
   HTTP Response
```

### 1.2 Rationale

| Driver | Why this style |
|--------|----------------|
| Small project, one core entity | A single, well-understood pipeline is enough — no need for bounded-context or microservice complexity. |
| Thin controllers, testable logic | Business logic lives in **services**, not controllers, so it is reusable and unit-testable in isolation. |
| Clear responsibilities | Each layer does exactly one job; a developer (or AI agent) always knows where a change belongs. |
| Consistent I/O | Form Requests standardize input; Resources/DTOs standardize output. |
| Ownership safety | The service is the single place that stamps `created_by` from the authenticated user — the client can never set it. |

---

## 2. Layer Boundaries & Responsibilities

### L1 — Route (Delivery Entry)
- Maps each URL + HTTP verb to a controller action.
- Applies the **authentication guard** to all protected endpoints (everything except login).
- Contains **no logic** beyond routing and middleware assignment.

### L2 — Form Request (Validation & Authorization)
- Validates all incoming fields against business rules (required, formats, allowed values, ranges).
- Performs request-level authorization checks where applicable.
- Returns **422** with field-specific messages on failure — the request never reaches the controller with invalid data.
- **Never** accepts `created_by` from the client (it is not a validatable input; it is set later by the service).

### L3 — Controller (Thin Coordinator)
- Receives the already-validated data from the Form Request.
- Calls exactly one **service** method for the use case.
- Wraps the service result in an **API Resource / DTO** and returns it with the correct status code.
- Holds **no business logic** and performs **no direct data access**.

### L4 — Service (Business Logic)
- The heart of the application. Implements the business workflows (record, revise, remove, list/search/filter a contact).
- **Stamps ownership**: sets `created_by` from the authenticated user's identity on create; keeps it unchanged on update.
- Enforces business invariants and orchestrates any multi-step logic and transactions.
- Talks to **models** for persistence; returns models or plain data to the controller.
- Reusable across controllers, jobs, or commands — not tied to HTTP.

### L5 — Model (Persistence / Aggregate)
- Represents the aggregate (Contact, User) and owns data access.
- Declares relationships (a Contact belongs to its owning User).
- Enforces **mass-assignment protection** and **hides sensitive attributes** (e.g., secrets, tokens).
- Contains only persistence-level concerns and simple accessors — not application workflow.

### L6 — API Resource / DTO (Output Shaping)
- Transforms a model (or service result) into the **uniform response envelope** (`success`, `message`, `data`).
- Exposes **only the required fields**; hides anything internal or sensitive.
- The single place output structure is decided, keeping every endpoint consistent.

---

## 3. Modules (Feature Grouping)

Even with one pipeline, group files by feature so work stays isolated:

| Module | Owns | Pipeline participants |
|--------|------|-----------------------|
| **Auth** | Login, token issuance, session verification | Route → Form Request → Controller → Auth Service → User Model → Resource |
| **Address Book** (core) | Contact CRUD, search, filter, pagination | Route → Form Request → Controller → Contact Service → Contact Model → Resource |
| **Shared** | Response envelope, error/exception handling, base classes, pagination formatting | Used by both modules |

Each module is a **full vertical slice** through the same layers, so two agents can build Auth and Address Book in parallel without touching each other's files.

---

## 4. Communication Rules

1. **One-way flow.** Data moves down the pipeline (Route → … → Model) and results flow back up. A layer only talks to the layer directly below it.
2. **Controllers never skip layers.** A controller must go through a service; it never touches a model or database directly.
3. **Services own business logic.** No business rule lives in a controller, form request, or model.
4. **Ownership is set only in the service.** `created_by` = authenticated user, applied in L4 — never from client input, never in the controller.
5. **All output goes through a Resource/DTO.** Controllers never return raw models or ad-hoc arrays.
6. **Errors become a uniform response.** Validation, not-found, auth, and unexpected errors are converted centrally (Shared) into the standard error envelope — no stack traces leak.

---

## 5. Dependency Rules

```
Route → FormRequest → Controller → Service → Model
                                      │
                                      └──▶ Resource/DTO (for output)
```

| Rule | Statement |
|------|-----------|
| DR-1 | A layer may depend only on the layer directly beneath it (plus Shared). |
| DR-2 | The **Model** depends on nothing above it — it never calls services or controllers. |
| DR-3 | The **Service** depends on models, not on HTTP/controllers — keeping it reusable and unit-testable. |
| DR-4 | **Shared** (envelope, errors, base classes) may be used by any layer and depends on nothing. |
| DR-5 | No cyclic dependencies between modules or layers. |
| DR-6 | The **Auth** module exposes only the authenticated identity to the Address Book module — never internal details. |

---

## 6. Cross-cutting Concerns

| Concern | Where handled |
|---------|---------------|
| **Authentication** | Route-level guard (middleware) on all protected endpoints; login is public. |
| **Authorization / ownership scope** | Service layer (own-only vs. see-all pending OQ-1). |
| **Input validation** | Form Request layer (422 + field messages). |
| **Uniform response** | API Resource / DTO + Shared envelope. |
| **Error handling** | Central Shared handler maps errors → status codes; production hides internals. |
| **Pagination** | Applied in the service (after search/filter), formatted by the Resource. |
| **Logging** | Around the service; log unexpected errors, never secrets/tokens. |
| **Rate limiting** | Middleware on the login route (recommended). |
| **Security** | Mass-assignment protection & hidden attributes in the Model; secrets never exposed via Resource. |
| **Transactions** | Managed in the Service for multi-step operations. |

---

## 7. Extension Strategy

1. **Add a feature** = add a new vertical slice (Route → Form Request → Controller → Service → Model → Resource) without altering existing ones.
2. **New business rule** goes into a **service** — controllers and models stay stable.
3. **Change output shape** = edit only the **Resource/DTO**; nothing else moves.
4. **Reuse logic** (jobs, commands, other endpoints) by calling the **service** directly, since it is HTTP-independent.
5. **Resolve open questions as service logic/config** — ownership scope (OQ-1), validation bounds, and default page size slot into the existing layers without restructuring.

---

## 8. Deployment Overview

```
 ┌──────────────┐    authenticated requests     ┌───────────────────────────┐
 │  Client SPA  │ ─────(bearer token)─────────▶ │       Backend API         │
 │ (separate)   │ ◀────(uniform JSON)────────── │  Route→FormReq→Controller │
 └──────────────┘                                │  →Service→Model→Resource  │
                                                 └─────────────┬─────────────┘
                                                               │ (Model)
                                                               ▼
                                                    ┌────────────────────┐
                                                    │  Database           │
                                                    └────────────────────┘
```

- **Single backend deployable** serving JSON to a separately deployed SPA.
- **One database**, accessed only through models.
- **Stateless requests** — each request carries its bearer token; instances are horizontally scalable behind a load balancer if needed.
- **Environment-separated config** (dev seeding vs. production; production hides error internals).
- **Secured transport (HTTPS)** between client and backend.

---

### Note

This architecture is intentionally simple and matches the request flow **Request → Form Request → Controller → Service → Model → API Resource/DTO**. Business logic is concentrated in **services**; controllers stay thin; input and output are standardized by Form Requests and Resources. Later phases implement these layers while preserving the responsibilities and dependency rules above.
