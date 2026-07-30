# Phase 5 — Guardrails

## The rules we follow (operational manual for AI-assisted development)

**Purpose:** The concrete, actionable rules for building this project safely in parallel with AI agents. This is *how* we build — structure, standards, imperatives, and checklists. For *what* we decided and *why* (technology choices, ADRs, envelope rationale), see [04-Architecture.md](04-Architecture.md).

**Workflow docs:** [01-Product-Brief.md](01-Product-Brief.md) · [02-Requirements-and-Domain.md](02-Requirements-and-Domain.md) · [03-Data-Model.md](03-Data-Model.md) · [04-Architecture.md](04-Architecture.md) · **05-Guardrails.md** (this file) · [06-Golden-Module.md](06-Golden-Module.md) · plus [api-doc/](../api-doc/).

---

## 1. CLAUDE.md precedence

The repo's [../CLAUDE.md](../CLAUDE.md) is the always-loaded instruction file for AI agents, in two parts:

1. **Laravel Boost guidelines** (auto-generated) — framework mechanics: PHP rules, testing, Pint, Artisan. **Preserved as-is; do not overwrite.**
2. **Project section** (added) — the layered architecture rule, ownership rule, guardrails, and the pre-finalize checklist.

**Precedence:** Boost rules govern framework mechanics; the project section governs architecture and business boundaries. On conflict over architecture/boundaries, **the project section wins.** Extend project rules by editing only the project section.

---

## 2. Folder structure & file placement

The layered flow — **Route → Form Request → Controller → Service → Model → API Resource** — maps directly onto the folder layout. Directories are created on demand as features are built; this is the approved intended layout, not a mandate to scaffold empty folders.

```
app/Http/Controllers/Api/V1/{Auth,Contact}/   thin controllers, versioned
app/Http/Requests/Api/V1/{Auth,Contact}/      Form Request validation
app/Http/Resources/Api/V1/                     API Resources (output shaping)
app/Services/{Auth,Contact}/                   business logic + ownership stamping
app/Models/                                    User, Contact
app/Support/ApiResponse.php                    uniform { success, message, data } envelope
bootstrap/app.php                              routing + central exception → envelope mapping
config/contacts.php                            pagination defaults
database/{factories,migrations,seeders}/       Contact factory/seeder (~50 records)
routes/api.php                                 /api/v1 route group (auth:sanctum protected)
tests/{Feature,Unit}/Api/V1/                   endpoint + service tests
docs/                                          design & engineering docs (source of truth)
```

**Where does a file go?**

| If you are writing… | It belongs in… |
|---------------------|----------------|
| A route definition | `routes/api.php` |
| Input validation | `app/Http/Requests/Api/V1/{Auth,Contact}/` |
| Request coordination (thin) | `app/Http/Controllers/Api/V1/{Auth,Contact}/` |
| Business logic / ownership stamping | `app/Services/{Auth,Contact}/` |
| Data access / relationships | `app/Models/` |
| Response shaping | `app/Http/Resources/Api/V1/` |
| The response envelope | `app/Support/ApiResponse.php` |
| Pagination / module config | `config/contacts.php` |
| Test data builders | `database/factories/` |
| Endpoint + service tests | `tests/{Feature,Unit}/Api/V1/` |

---

## 3. Coding standards

### PHP 8.3
- Explicit return types and parameter type hints on **every** method.
- Constructor property promotion; curly braces on **all** control structures (even single-line).
- Enum keys in TitleCase; PHPDoc (with array-shape types) preferred over inline comments.
- Descriptive names: `assignOwnerFromAuthenticatedUser()`, not `setUser()`.

### Laravel conventions
- Use `php artisan make:*` generators with `--no-interaction`; new models get factories + seeders.
- Casts via the `casts()` method, not the `$casts` property.
- Middleware / exceptions / providers registered in `bootstrap/app.php` — **not** a legacy `Kernel.php`.
- Named routes + `route()` for URL generation; RESTful naming.

### API & data
- Versioned routes under `/api/v1`.
- Every response passes through an **API Resource** into the envelope — success `{ success, message, data }`, error `{ success, message, errors }`.
- Models declare `$fillable` (mass-assignment protection) and `$hidden` (secrets/tokens).
- Apply search/filters **before** pagination; eager-load to avoid N+1; select only required columns.

### Formatting
- Run `vendor/bin/pint --dirty --format agent` before finalizing any PHP change. Never run `--test` to "fix".

---

## 4. Development rules

1. **Thin controllers, fat services.** No business logic outside services.
2. **Ownership is sacred.** `created_by` is stamped **only in the service** from `auth()->id()`, only on create; immutable on update; never a client input.
3. **Validate at the edge.** All input validation in Form Requests → 422 with field messages.
4. **Uniform I/O.** Form Requests standardize input; Resources standardize output; the central handler standardizes failures.
5. **No layer-skipping.** Controllers never touch models/DB directly; models never call services.
6. **Test what you build.** Feature-test every endpoint (happy, validation, auth, not-found, search/filter/pagination); unit-test service logic. Use factories.
7. **Run before done.** `vendor/bin/pint --dirty --format agent`, then `php artisan test --compact` (focused filter first, then full suite).
8. **Ask before scope creep.** No new dependencies, base folders, or doc files without approval.
9. **Security defaults.** No secrets in logs; no stack traces in production responses; login throttled.
10. **Honor resolved decisions.** Implement the resolved open questions (see [04-Architecture.md](04-Architecture.md)) as documented; do not silently change them — flag if unclear.

---

## 5. Module structure

Two feature modules plus a shared foundation. Each module is a **full vertical slice** through all layers, enabling parallel development.

### Module A — Auth (supporting)
| Layer | Component |
|-------|-----------|
| Route | `POST /api/v1/login` (public, throttled); `POST /api/v1/logout` (protected) |
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

### Shared foundation
- Response envelope (`app/Support/ApiResponse.php`), central exception handling, pagination formatting, base classes.
- Depended on by both modules; depends on nothing.

**Parallelization rule:** Agent 1 builds Module A, Agent 2 builds Module B, against the agreed **Shared** contracts. They must not edit each other's files. Integration = "do the contracts (envelope, identity) match."

---

## 6. Git strategy

- **Branching:** `main` is always releasable and protected. Work on `feature/<module>-<short-desc>` (e.g. `feature/addressbook-crud`); one short-lived branch per module.
- **Commits:** small, focused, imperative subject (`Add ContactService with ownership stamping`). Do **not** commit or push unless the user asks; if on `main`, branch first. Commit only after Pint + relevant tests pass. Footer: `Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>`.
- **PRs:** one per module into `main`; describe scope, layers touched, tests added. Require Pint clean, tests green, layered flow respected, no client-supplied `created_by`. Footer: `🤖 Generated with [Claude Code](https://claude.com/claude-code)`.
- **Hygiene:** no interactive git flags (`-i`); use `gh` for GitHub ops. `.env`, secrets, and vendor artifacts stay ignored — never commit tokens or seed credentials.

---

## 7. AI agent rules

### Always
- Read the relevant `docs/` file(s) before implementing a phase.
- Follow the layered flow and place files per the §2 table.
- Use `php artisan make:*` generators; add factories with new models.
- Write tests alongside features; run focused tests + Pint before claiming done.
- State honestly when something is skipped, failing, or uncertain.

### Never
- Put business logic in controllers, or query models from controllers.
- Accept, validate, or set `created_by` from client input.
- Return raw models; always use a Resource.
- Add dependencies, base folders, or documentation files without approval.
- Implement business features when the task is scaffolding/foundation only.
- Silently change a resolved decision — implement per docs or flag it.

### Parallel development protocol
- Each agent owns **one module/vertical slice**; do not edit another module's files.
- Build against the agreed **Shared** contracts (response envelope, identity from Auth).
- Reference the User only by identity (`auth()->id()`), never by embedding.
- Surface contract changes explicitly — a shared-contract change affects every agent.

---

## 8. Before finalizing any change

- [ ] Layered flow respected; controller thin; logic in service.
- [ ] `created_by` stamped only in the service from `auth()->id()`; not client-supplied; immutable on update.
- [ ] Validation in Form Request; 422 with field messages.
- [ ] Output via Resource in the uniform envelope (`{ success, message, data }` / `{ success, message, errors }`).
- [ ] Feature + unit tests written and passing (happy, validation, auth, not-found, search/filter/pagination where relevant).
- [ ] `vendor/bin/pint --dirty --format agent` clean.
- [ ] `php artisan test --compact` green.
- [ ] No secrets in logs/responses; production hides internals.

> Changing a boundary requires updating this document; architectural changes also require an ADR in [04-Architecture.md](04-Architecture.md). See [06-Golden-Module.md](06-Golden-Module.md) for the reference slice to copy by analogy.
