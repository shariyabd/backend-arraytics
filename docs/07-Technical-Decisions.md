# Technical Decisions

## Address Book Management System — Backend

**Version:** 1.0
**Role:** Chief Software Architect
**Source Documents:** [06-Architecture.md](06-Architecture.md), [05-DOMAIN-DATA-MODEL.md](05-DOMAIN-DATA-MODEL.md), [PRD.md](PRD.md)
**Scope:** Finalized technical decisions. No code. Optimized for maintainability and AI-assisted development.

---

## 1. Technology Stack

The stack realizes the service-pattern layered architecture (Request → Form Request → Controller → Service → Model → API Resource/DTO) on a proven, well-documented foundation.

| Layer / Concern | Technology | Version |
|-----------------|-----------|---------|
| Language | PHP | 8.3 |
| Framework | Laravel | 12 (latest stable) |
| API style | RESTful JSON API | — |
| Authentication | Laravel Sanctum (token-based) | v4 (Laravel 12 compatible) |
| Database | MySQL | 8.0+ |
| ORM | Eloquent | (bundled with Laravel 12) |
| Validation | Form Request classes | (Laravel) |
| Output shaping | Eloquent API Resources | (Laravel) |
| Testing | PHPUnit | 11 |
| Code style | Laravel Pint | v1 |
| Local environment | Laravel Sail (Docker) | v1 |
| Log/diagnostics | Laravel Pail + standard logging | v1 |
| Seeding / fake data | Database Seeders + Faker | (bundled) |
| API documentation | Markdown + Postman collection | — |

> Rationale for the stack as a whole: the PRD mandates Laravel + MySQL + Sanctum, and the project is already scaffolded on **PHP 8.3 / Laravel 12 / PHPUnit 11 / Pint / Sail** (per project guidelines). Adopting the framework's first-party tools maximizes documentation coverage — which directly benefits AI-assisted development — and keeps the dependency surface minimal.

---

## 2. Architecture Decision Records (ADR)

Each decision follows: **Decision → Reason → Alternative Considered → Trade-offs**.

### ADR-001 — Framework: Laravel 12
- **Decision:** Build the backend on Laravel 12 (PHP 8.3).
- **Reason:** Mandated by the PRD; first-party support for everything the project needs (routing, Eloquent, Form Requests, Resources, Sanctum, seeding, testing). Largest, best-documented PHP ecosystem, which strengthens AI-assisted development.
- **Alternative Considered:** Symfony (API Platform); a lighter micro-framework (Lumen/Slim).
- **Trade-offs:** Laravel carries more built-in weight than a micro-framework, but for a small CRUD API that weight is convenience, not overhead. Ties the project to Laravel's conventions and release cadence.

### ADR-002 — Architecture: Service-Pattern Layered Monolith
- **Decision:** Use the layered pipeline Route → Form Request → Controller → Service → Model → API Resource/DTO within a single deployable.
- **Reason:** Matches the approved [06-Architecture.md](06-Architecture.md). Keeps controllers thin, concentrates business logic in testable services, and gives AI agents an unambiguous "where does this go" map.
- **Alternative Considered:** Fat controllers (logic in controllers); full Hexagonal/DDD with repositories + interfaces; microservices.
- **Trade-offs:** Adds a service layer that a trivial CRUD app could skip — a small amount of extra structure in exchange for testability and reuse. Rejected microservices as unjustified for one core entity.

### ADR-003 — Authentication: Laravel Sanctum (API tokens)
- **Decision:** Token-based auth via Sanctum; login is the only public endpoint; all contact endpoints require a bearer token.
- **Reason:** Mandated by the PRD; Sanctum is Laravel's first-party, lightweight token solution ideal for an SPA + API. Simpler than full OAuth for a single trusted client.
- **Alternative Considered:** Laravel Passport (OAuth2); JWT via a third-party package; session/cookie auth.
- **Trade-offs:** Sanctum offers fewer features than Passport (no OAuth grants/scopes out of the box), but the project needs none of those. Tokens must be transmitted and stored securely by the client.

### ADR-004 — Database: MySQL 8 with Eloquent
- **Decision:** MySQL 8.0+ as the datastore; Eloquent as the ORM; indexes on `name`, `email`, `phone`.
- **Reason:** Mandated by the PRD. Eloquent gives clean model/aggregate mapping, relationships, mass-assignment protection, and hidden attributes with minimal code. Indexes satisfy the search performance requirement.
- **Alternative Considered:** PostgreSQL; raw query builder / raw SQL; SQLite for production.
- **Trade-offs:** Eloquent can invite N+1 queries if used carelessly — mitigated by eager loading and the performance standards below. SQLite is used only for fast tests, not production.

### ADR-005 — Validation: Form Request Classes
- **Decision:** All input validation lives in dedicated Form Request classes; failures return 422 with field-level messages.
- **Reason:** Mandated by the PRD and the architecture; keeps controllers thin, makes rules reusable and unit-testable, and centralizes the create/update rule sets.
- **Alternative Considered:** Inline `$request->validate()` in controllers; a separate validation service.
- **Trade-offs:** More classes to maintain, but each is small and single-purpose — a net win for clarity and AI navigability.

### ADR-006 — Output: Eloquent API Resources (uniform envelope)
- **Decision:** Shape every response through API Resources into the standard `{ success, message, data }` / `{ success, message, errors }` envelope. Never return raw models.
- **Reason:** Guarantees consistent responses (PRD §14), hides sensitive/internal fields, and returns only required fields (performance requirement).
- **Alternative Considered:** Returning models/arrays directly; Fractal or a third-party transformer.
- **Trade-offs:** A thin transformation layer per resource; negligible cost for large consistency and security gains. DTOs may supplement Resources where a non-model shape is needed.

### ADR-007 — Ownership Enforced in the Service Layer
- **Decision:** `created_by` is set exclusively in the service from `auth()->id()` on create, and never changed on update; it is never a validatable input.
- **Reason:** Enforces the core business rule (INV-2/INV-3) in one place, eliminating any path for a client to set or override ownership.
- **Alternative Considered:** Setting `created_by` in the controller; a model `creating` event/observer.
- **Trade-offs:** The service becomes the single source of truth for ownership (intended). A model observer was rejected to keep ownership logic explicit and visible rather than hidden in lifecycle hooks.

### ADR-008 — Error Handling: Centralized Exception Rendering
- **Decision:** Centralize exception-to-response mapping so all errors emit the uniform envelope with correct status codes; hide stack traces/internals in production.
- **Reason:** Satisfies PRD §16/§21; prevents leaking sensitive detail; keeps controllers free of try/catch noise.
- **Alternative Considered:** Per-controller try/catch; letting the framework's default error pages render.
- **Trade-offs:** One central place to maintain the mapping; requires discipline to route domain errors through it.

### ADR-009 — Testing: PHPUnit (feature-first) with Factories & SQLite
- **Decision:** PHPUnit 11 as the test framework; feature tests for endpoints, unit tests for services; model factories for data; SQLite in-memory for the test suite.
- **Reason:** Project standard (PHPUnit, not Pest). Feature tests best verify the API contract (auth, validation, CRUD, search/filter/pagination); SQLite keeps the suite fast.
- **Alternative Considered:** Pest; testing only via manual/Postman; testing against MySQL exclusively.
- **Trade-offs:** SQLite ≠ MySQL for a few edge behaviors (collation, some functions); mitigated by keeping DB-specific logic minimal and optionally running a MySQL pass in CI.

### ADR-010 — Code Style: Laravel Pint
- **Decision:** Enforce style with Pint (Laravel preset); run `pint --dirty` before finalizing changes.
- **Reason:** Project standard; consistent formatting reduces diff noise and helps AI-generated code match the codebase.
- **Alternative Considered:** PHP-CS-Fixer directly; no enforced style.
- **Trade-offs:** Everyone must run Pint; trivial cost for consistency.

### ADR-011 — Local Environment: Laravel Sail (Docker)
- **Decision:** Provide Sail for a reproducible PHP 8.3 + MySQL environment.
- **Reason:** One-command, consistent dev/CI environment; lowers onboarding friction and "works on my machine" issues.
- **Alternative Considered:** Native local PHP/MySQL; Valet; a custom Docker Compose.
- **Trade-offs:** Requires Docker; some overhead on constrained machines, offset by reproducibility.

### ADR-012 — API Documentation: Markdown + Postman
- **Decision:** Document the API via Markdown plus a Postman collection covering auth flow, headers, payloads, query params, and response/error examples.
- **Reason:** Satisfies PRD §20 with low tooling overhead; Postman doubles as a manual test harness.
- **Alternative Considered:** OpenAPI/Swagger with generated UI; auto-generated docs from annotations.
- **Trade-offs:** Manual docs can drift from code; acceptable at this scale. OpenAPI can be adopted later without rework.

### ADR-013 — Rate Limiting on Authentication
- **Decision:** Apply Laravel's built-in throttling middleware to the login route.
- **Reason:** PRD §19 (recommended); mitigates brute-force with zero extra dependencies.
- **Alternative Considered:** No throttling; a third-party security package.
- **Trade-offs:** Requires choosing sensible limits; over-tight limits could hinder legitimate retries. (Whether this is mandatory vs. recommended is tracked as C-2.)

---

## 3. External Services

The system is intentionally self-contained; external dependencies are minimal.

| Service | Status | Notes |
|---------|--------|-------|
| MySQL database | **Required** | Primary datastore (self-hosted or managed). |
| SMTP / email provider | **Not required** | No email flows in scope (no registration, no password reset). |
| Third-party auth (OAuth/social) | **Not required** | Sanctum token auth only. |
| Object storage / CDN | **Not required** | No file uploads in scope. |
| External APIs | **None** | No integrations in the current scope. |
| Container runtime (Docker) | **Dev/CI only** | Via Sail; not a production requirement. |

> If features later need email (e.g., password reset) or file attachments, they attach as new modules per the architecture's extension strategy without disturbing existing layers.

---

## 4. Development Standards

Aligned with the project guidelines (CLAUDE.md / Laravel Boost) and the layered architecture.

### 4.1 Structure & Conventions
- Follow the standard Laravel 12 structure; do not introduce new base folders without approval.
- One responsibility per layer: **thin controllers, business logic in services, validation in Form Requests, output in Resources**.
- Descriptive names (`isRegisteredForDiscounts`, not `discount()`); check sibling files for structure before adding new ones.
- Create files via `php artisan make:` generators; create models with useful factories and seeders.

### 4.2 PHP / Code Quality
- Explicit return types and parameter type hints on all methods.
- Constructor property promotion; curly braces on all control structures.
- PHPDoc (with array-shape types) preferred over inline comments.
- Run `vendor/bin/pint --dirty` before finalizing any PHP change.

### 4.3 API & Data
- RESTful route naming; API versioning (e.g., `/api/v1`) for forward compatibility.
- Eloquent API Resources for all responses; never expose raw models.
- Mass-assignment protection (`$fillable`) and hidden sensitive attributes (`$hidden`) on models.
- Apply filters/search **before** pagination; eager-load relationships to avoid N+1; select only required columns.
- Model casts declared via the `casts()` method.

### 4.4 Security
- `created_by` derived only from the authenticated user (service layer); never client-supplied.
- All contact endpoints behind Sanctum auth; login public and throttled.
- Never log passwords, tokens, or sensitive data; hide internals from production error responses.

### 4.5 Testing
- Feature tests for every endpoint (happy path, validation failures, auth failures, not-found, search/filter/pagination).
- Unit tests for service business logic.
- Use factories (and custom states) for test data; `make:test` to scaffold; run the focused test after each change, full suite before completion.
- Do not add verification scripts where tests already prove behavior.

### 4.6 Documentation
- Keep the Postman collection and API Markdown in sync with endpoints.
- Only create documentation files when explicitly requested (per project guidelines).

---

## 5. Version Constraints

| Component | Constraint | Reason |
|-----------|-----------|--------|
| PHP | `^8.3` | Framework baseline; matches installed environment. |
| Laravel Framework | `^12.0` | Mandated; current LTS-track structure (streamlined `bootstrap/app.php`). |
| Laravel Sanctum | `^4.0` | Sanctum release compatible with Laravel 12. |
| MySQL | `>= 8.0` | Modern SQL features, JSON, and performant indexing. |
| PHPUnit | `^11.0` | Project's chosen test framework version. |
| Laravel Pint | `^1.0` | Style enforcement. |
| Laravel Sail | `^1.0` | Local Docker environment. |
| Laravel Pail | `^1.0` | Log tailing during development. |
| Composer | `>= 2.x` | Dependency management. |
| Node.js (tooling only) | Current LTS | Only if asset tooling is needed; backend is API-only. |

**Policy:**
- Pin major versions via Composer constraints; allow patch/minor updates.
- Do not add or change dependencies without approval (per project guidelines).
- Middleware, exceptions, and providers are registered in `bootstrap/app.php` / `bootstrap/providers.php` (Laravel 12 structure) — not in a legacy `Kernel.php`.

---

### Note

These decisions finalize the technology and standards for the backend, consistent with the PRD mandates and the service-pattern architecture. They are practical, production-ready, and chosen to maximize maintainability and AI-assisted development. Implementation phases must adhere to these decisions and the dependency rules in [06-Architecture.md](06-Architecture.md); any deviation requires an updated ADR.
