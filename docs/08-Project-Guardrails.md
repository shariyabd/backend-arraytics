# Project Engineering Foundation & Guardrails

## Address Book Management System — Backend

**Version:** 1.0
**Role:** Lead Software Engineer
**Source Documents:** [06-Architecture.md](06-Architecture.md), [07-Technical-Decisions.md](07-Technical-Decisions.md)
**Purpose:** Prepare the project for **parallel AI-assisted development** with protected architectural boundaries and clear guardrails. No business features are implemented here.

---

## 1. CLAUDE.md

The repository's [../CLAUDE.md](../CLAUDE.md) is the always-loaded instruction file for AI agents. It has two parts:

1. **Laravel Boost Guidelines** (auto-generated) — framework mechanics, PHP rules, testing, Pint, Artisan usage. **Preserved as-is.**
2. **Project section** (added) — the layered architecture rule, ownership rule, guardrails, and the pre-finalize checklist.

**Precedence:** Boost rules govern framework mechanics; the project section governs architecture and business boundaries. On conflict for architecture/boundaries, the project section wins.

**Every agent must, before acting:**
- Respect the layered flow: `Route → Form Request → Controller → Service → Model → API Resource/DTO`.
- Never place business logic in controllers or accept `created_by` from the client.
- Run Pint + focused tests before finalizing (see §3 / §4).

> Do not overwrite the Boost block. Extend project rules by editing the project section only.

---

## 2. Project Folder Structure

Standard Laravel 12 structure, organized so each layer and module has an obvious home. **Directories are created on demand as features are built** (per "no new base folders without approval" — this is the *approved intended layout*, not a mandate to scaffold empty folders now).

```
backend/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   └── Api/
│   │   │       └── V1/              # thin controllers, versioned
│   │   ├── Requests/
│   │   │   └── V1/                  # Form Request validation classes
│   │   ├── Resources/
│   │   │   └── V1/                  # API Resources (output shaping)
│   │   └── Middleware/              # auth guard, throttle (as needed)
│   │
│   ├── Services/                    # business logic (the heart)
│   │
│   ├── Models/                      # Eloquent aggregates (Contact, User)
│   │
│   ├── DTOs/                        # optional: non-model data shapes
│   │
│   ├── Exceptions/                  # domain/application exceptions
│   │
│   └── Providers/
│
├── bootstrap/
│   ├── app.php                      # middleware, exceptions, routing (L12)
│   └── providers.php                # service providers (L12)
│
├── config/
│
├── database/
│   ├── factories/                   # model factories (tests + seeding)
│   ├── migrations/                  # schema (users, contacts, tokens)
│   └── seeders/                     # ~50 contacts + test user
│
├── routes/
│   ├── api.php                      # versioned API routes (/api/v1)
│   └── console.php
│
├── tests/
│   ├── Feature/                     # endpoint tests (contract-level)
│   │   └── Api/V1/
│   └── Unit/                        # service logic tests
│
└── docs/                            # design + engineering docs (this folder)
```

**Placement rules (where does a file go?):**

| If you are writing… | It belongs in… |
|---------------------|----------------|
| A route definition | `routes/api.php` |
| Input validation | `app/Http/Requests/V1/` |
| Request coordination (thin) | `app/Http/Controllers/Api/V1/` |
| Business logic / ownership stamping | `app/Services/` |
| Data access / relationships | `app/Models/` |
| Response shaping | `app/Http/Resources/V1/` |
| A non-model data shape | `app/DTOs/` |
| A custom error type | `app/Exceptions/` |
| Test data builders | `database/factories/` |

---

## 3. Coding Standards

Derived from the Technical Decisions (§4) and Laravel Boost rules.

### 3.1 PHP
- PHP 8.3; explicit return types and parameter type hints on **every** method.
- Constructor property promotion; curly braces on **all** control structures (even single-line).
- Enum keys in TitleCase; PHPDoc (with array-shape types) preferred over inline comments.
- Descriptive names: `assignOwnerFromAuthenticatedUser()`, not `setUser()`.

### 3.2 Laravel Conventions
- Use `php artisan make:*` generators with `--no-interaction`; models get factories + seeders.
- Casts via the `casts()` method, not the `$casts` property.
- Middleware/exceptions/providers registered in `bootstrap/app.php` — **not** a legacy `Kernel.php`.
- Named routes + `route()` for URL generation; RESTful route naming.

### 3.3 API & Data
- Versioned routes under `/api/v1`.
- Every response passes through an **API Resource** into the envelope `{ success, message, data }` (or `{ success, message, errors }`).
- Models declare `$fillable` (mass-assignment protection) and `$hidden` (secrets/tokens).
- Apply search/filters **before** pagination; eager-load to avoid N+1; select only required columns.

### 3.4 Formatting
- Run `vendor/bin/pint --dirty --format agent` before finalizing any PHP change. Never run `--test` to "fix".

---

## 4. Development Rules

1. **Thin controllers, fat services.** No business logic outside services.
2. **Ownership is sacred.** `created_by = auth()->id()` set only in the service, only on create; immutable on update; never a client input.
3. **Validate at the edge.** All input validation in Form Requests → 422 with field messages.
4. **Uniform I/O.** Form Requests standardize input; Resources standardize output; the error handler standardizes failures.
5. **No layer-skipping.** Controllers never touch models/DB directly; models never call services.
6. **Test what you build.** Feature test every endpoint (happy, validation, auth, not-found, search/filter/pagination); unit test service logic. Use factories.
7. **Run before done.** `pint --dirty` + focused test filter, then full suite before completion.
8. **Ask before scope creep.** No new dependencies, base folders, or doc files without approval.
9. **Security defaults.** No secrets in logs; no stack traces in production responses; login throttled.
10. **Honor open questions.** Do not silently resolve OQ-1 or validation bounds — implement them as the docs/answers dictate, flag if unclear.

---

## 5. Module Structure

Two feature modules plus a shared foundation. Each module is a **full vertical slice** through all layers, enabling parallel development.

### Module A — Auth (supporting)
| Layer | Component |
|-------|-----------|
| Route | `POST /api/v1/login` (public, throttled); `POST /api/v1/logout` (protected, pending OQ-2) |
| Form Request | `LoginRequest` |
| Controller | `AuthController` (thin) |
| Service | `AuthService` — verify credentials, issue/revoke tokens |
| Model | `User` |
| Resource | `UserResource` (safe fields only) |

### Module B — Address Book (core)
| Layer | Component |
|-------|-----------|
| Route | `apiResource('contacts')` under `/api/v1`, auth-protected |
| Form Request | `StoreContactRequest`, `UpdateContactRequest` |
| Controller | `ContactController` (thin) |
| Service | `ContactService` — CRUD, ownership stamping, search/filter/pagination |
| Model | `Contact` (belongsTo User) |
| Resource | `ContactResource`, `ContactCollection` |

### Shared Foundation
- Response envelope helper/trait, base exception handling, pagination formatting, base classes.
- Depended on by both modules; depends on nothing.

**Parallelization rule:** Agent 1 builds Module A, Agent 2 builds Module B, against the agreed **Shared** contracts. They must not edit each other's files. Integration = "do the contracts (envelope, identity) match."

---

## 6. Git Strategy

### Branching
- `main` — always releasable; protected.
- `feature/<module>-<short-desc>` — e.g., `feature/addressbook-crud`, `feature/auth-login`.
- One branch per module/feature to keep parallel work isolated; short-lived.

### Commits
- Small, focused, imperative subject: `Add ContactService with ownership stamping`.
- Do **not** commit or push unless the user asks. If on `main`, branch first.
- Commit only after Pint + relevant tests pass.
- Sign-off footer on commits:
  `Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>`

### Pull Requests
- One PR per module/feature into `main`; describe scope, layers touched, and tests added.
- Require: Pint clean, tests green, layered-architecture respected, no client-supplied `created_by`.
- PR body footer: `🤖 Generated with [Claude Code](https://claude.com/claude-code)`.

### Hygiene
- No interactive git flags (`-i`). Use `gh` for GitHub operations.
- `.env`, secrets, and vendor artifacts stay ignored; never commit tokens or seed credentials.

---

## 7. Hook Configuration

Recommended automation to enforce guardrails mechanically (config lives in `.claude/settings.json`; agents cannot self-enforce timing — hooks must). **Documented here as the intended policy; apply via the `update-config` skill or user approval.**

| Hook | Trigger | Action | Purpose |
|------|---------|--------|---------|
| **Post-edit format** | After editing any `*.php` | `vendor/bin/pint --dirty --format agent` | Guarantee style compliance automatically. |
| **Pre-commit tests** | Before commit | `php artisan test --compact` (or focused) | Block commits that break tests. |
| **Boundary check** | After edit to `Controllers/*` | Warn if the file references `DB::`/`Model::` query builders directly | Protect the thin-controller rule. |
| **Ownership check** | After edit touching contact create/update | Warn if `created_by` appears in a Form Request `rules()` | Prevent client-supplied ownership. |
| **Secret guard** | Before commit | Scan staged diff for tokens/passwords | Prevent secret leakage. |

> Hooks are advisory guardrails; they must not block legitimate work. Start with format + test hooks; add boundary/ownership warnings as the codebase grows.

---

## 8. AI Agent Rules

Rules that govern how AI agents work in this repository.

### 8.1 Always
- Read the relevant `docs/` file(s) before implementing a phase.
- Follow the layered flow and place files per the §2 table.
- Use `php artisan make:*` generators; add factories with new models.
- Write tests alongside features; run focused tests + Pint before claiming done.
- State honestly when something is skipped, failing, or uncertain.

### 8.2 Never
- Put business logic in controllers, or query models from controllers.
- Accept, validate, or set `created_by` from client input.
- Return raw models; always use a Resource/DTO.
- Add dependencies, base folders, or documentation files without approval.
- Implement business features when the task is scaffolding/foundation only.
- Silently resolve an open question (OQ-1, validation bounds) — implement per docs or flag it.

### 8.3 Parallel Development Protocol
- Each agent owns **one module/vertical slice**; do not edit another module's files.
- Build against agreed **Shared** contracts (response envelope, identity from Auth).
- Reference the User only by identity (`UserId`/`auth()->id()`), never by embedding.
- Surface contract changes explicitly — a shared-contract change affects every agent.

### 8.4 Definition of Done (per feature)
- [ ] Layered flow respected; controller thin; logic in service.
- [ ] Ownership stamped only in service; not client-supplied.
- [ ] Validation in Form Request; 422 with field messages.
- [ ] Output via Resource in the uniform envelope.
- [ ] Feature + unit tests written and passing (happy, validation, auth, not-found, search/filter/pagination where relevant).
- [ ] `vendor/bin/pint --dirty` clean.
- [ ] No secrets in logs/responses; production hides internals.

---

### Note

This foundation prepares the project for safe, parallel, AI-assisted development. It defines *how* to build (structure, standards, rules, guardrails) without building any business feature. Implementation phases must operate within these boundaries; changing a boundary requires updating this document and, where architectural, an ADR in [07-Technical-Decisions.md](07-Technical-Decisions.md).
