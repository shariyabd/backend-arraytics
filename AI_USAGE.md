# AI Usage — Backend (Laravel API)

How AI tooling was used to plan, build, test, and document the **backend** of the Address Book Management System — and, honestly, where it helped, where it was wrong, and how its output was validated. The frontend has its own companion report (`../frontend/AI_USAGE.md`).

---

## 1. Tools & environment

| Tool | Role |
|------|------|
| **Claude Code** (Claude Opus 4.8) | Primary coding agent — terminal/IDE pair-programmer running an agentic loop (read context → plan → edit → run tests/linter → self-correct). |
| **Laravel Boost** (MCP server, `laravel/boost`) | Gave the agent version-accurate Laravel 12 docs search, schema/route inspection, and tinker access, so generated code targeted the installed package versions instead of training-data memory. |
| **Project skills** (`.claude/skills/laravel-best-practices/`) | Curated rule-packs (validation, eloquent, security, testing, …) auto-activated when the agent worked in those domains. |
| **`CLAUDE.md` + `docs/` + `.claude/prompt/`** | The agent's persistent context: architecture rules, guardrails, phase prompts, and design docs (see §2). |
| **Pint + PHPUnit** | The non-negotiable validation harness — every AI change had to pass both before being accepted. |

---

## 2. Development strategy: docs-first, phase-driven

The project was **not** built by prompting "build me an address book". It followed a deliberate agentic-engineering pipeline: each phase has a reusable prompt in [`.claude/prompt/`](.claude/prompt/) that produces a persistent design artifact in [`docs/`](docs/), and later phases consume the earlier artifacts as their source of truth.

| Phase | Prompt | Artifact produced | Purpose |
|-------|--------|-------------------|---------|
| 1 | `01-product-brief.md` | `docs/01-Product-Brief.md` | Scope, features, acceptance criteria from the assignment |
| 2 | `02-requirements-and-domain.md` | `docs/02-Requirements-and-Domain.md` | Clarified requirements, domain vocabulary, **open questions** |
| 3 | `03-data-model.md` | `docs/03-Data-Model.md` | Entities, invariants, `address_book` schema, ownership rule |
| 4 | `04-architecture.md` | `docs/04-Architecture.md` | Layered architecture, shared contracts (envelope, errors) |
| 5 | `05-guardrails.md` | `docs/05-Guardrails.md` | Folder structure, coding standards, AI-agent do/don't rules |
| 6 | `06-golden-module.md` | `docs/06-Golden-Module.md` + the **Auth module** | Reference implementation every later module copies |
| 7 | `07-api-handover.md` | `api-doc/*.md` | Implementation-accurate API contracts for the frontend |

Milestones followed the same order: **design docs → guardrails → Golden Module (Auth) → Address Book module by analogy → hardening & tests → API handover docs → Docker → READMEs.**

Two techniques did the heavy lifting:

- **Golden Module / build-by-analogy.** The Auth slice was built first to production quality, then the Contact module was generated with the instruction to *mirror the golden module exactly* (same layers, namespaces, envelope, test structure). This is why the two modules are structurally symmetric — and why the generated code landed consistent on the first pass.
- **Guardrails as executable constraints.** `CLAUDE.md` encodes the one architecture rule (`Route → Form Request → Controller → Service → Model → Resource`), a do-NOT list (no logic in controllers, never trust `created_by`, no raw models in responses), and the verification commands the agent must run before calling anything done.

---

## 3. Where AI was used across the lifecycle

| Stage | How AI was used |
|-------|-----------------|
| Planning & requirements | Summarizing the assignment into the product brief; extracting requirements and surfacing ambiguities as tracked **open questions** (OQ-1 visibility, OQ-3 age range, OQ-4 gender set, OQ-11 website optionality) instead of silently guessing. |
| Architecture | Drafting the layered architecture + shared contracts docs; the human approved the layer boundaries and the uniform response envelope before any module code. |
| Scaffolding | `php artisan make:*` for model, migration, requests, resources, controller, service, factory, seeder, tests — then filling implementations. |
| Implementation | The full Contact module (validation, CRUD service with search/filter/pagination, ownership stamping, resources) written by the agent against the golden module + module contract. |
| Debugging | Test-failure output fed back into the loop; Boost's docs search used to resolve Laravel-12-specific behavior rather than guessing from memory. |
| Testing | Feature + unit suite written by the agent (happy paths, every validation rule, auth boundaries, IDOR/owner-scoping, pagination, combined filters, wildcard search input). Current suite: **72 tests / 253 assertions, all passing.** |
| Refactoring | Pint-driven style normalization; consolidation passes (e.g. constants for enums/ranges, `config/contacts.php` for pagination) so decisions live in one place. |
| Documentation | `api-doc/` handover contracts, `docs/` design record, README, DOCKER.md, CLAUDE.md — drafted by the agent, reconciled against the docs-of-record by the human (see §5, second catch). |
| Review | A final audit pass against the assignment text (route protection, `created_by` provenance, validation coverage) before considering the module done. |

---

## 4. How AI output was validated (never accepted blindly)

1. **Mechanical gates:** every PHP change ran `vendor/bin/pint --dirty` and the focused test filter; a change that broke either was rejected back into the loop.
2. **Spec reconciliation:** generated docs and code were checked against `docs/` (the source of truth), not against "what the code currently does" — this caught the auth-surface documentation error described in §5.
3. **Adversarial tests for security claims:** the claims that matter (spoofed `created_by` ignored; cross-user access returns 404; wildcard search stays owner-scoped) are each pinned by a dedicated test rather than trusted.
4. **Scope fencing:** while building the Contact module the agent was explicitly told not to modify the Auth module or shared contracts — keeping diffs reviewable.

---

## 5. Where AI was wrong / suboptimal — and how it was caught

**Catch #1 — Form Request `authorize()` left at the stub default.**
`php artisan make:request` scaffolds `authorize(): bool { return false; }`. Shipped as-is, **every** contact request would 403 before validation ran. An agent that only fills in `rules()` ships this bug.
*How it was caught:* (a) knowing these routes are already guarded by `auth:sanctum`, per-request authorization should be `true`; (b) the feature tests assert `201`/`200` and would have failed with `403` on first run. All contact Form Requests were corrected to `return true`, keeping the route guard as the single access-control point.

**Catch #2 — generated documentation drifted from the spec.**
The first draft of `api-doc/auth.md` presented `login`, `me`, and `logout` as equally mandated. Verifying against `docs/01-Product-Brief.md` showed only `login` is a written requirement; `logout` is implied (revoked token → 401, tracked as OQ-2) and `me` is a golden-module convenience. Lesson: **reconcile generated docs against the source-of-truth docs, not against the code's current behavior.**

---

## 6. Prompting techniques that worked

- **"Read the reference first, build strictly by analogy, escalate what you can't infer."** The single most effective instruction pattern in this project (see §2). It produced first-pass-consistent code and zero unauthorized architectural decisions.
- **Phase prompts that name their inputs.** Every prompt in `.claude/prompt/` starts by listing the exact files to read (e.g. Phase 6: "Read docs/01, docs/04, docs/05, then implement…"), which keeps the agent grounded in project truth instead of generic Laravel knowledge.
- **Encoding review checklists into the prompt.** "Before finalizing: run Pint, run the focused tests, confirm the layered flow was respected" turns quality gates into part of the task definition instead of an afterthought.

---

## 7. Human decisions (where judgment was manual)

| Decision | Made by human because… |
|----------|------------------------|
| **OQ-1: owner-only visibility** (users see only their own contacts; others' resolve to 404) | Changes the product's meaning; the assignment is silent on it. Surfaced by the agent as a blocking question, decided by the human, then encoded in `ContactService` + tests. |
| Age range 1–150, gender set `Male/Female/Other`, website optional, page size 15/100 | Low-impact defaults — but implemented as *isolated, flagged* constants/config so a different answer changes one place. |
| Sanctum over Passport/JWT; MySQL primary with SQLite quick-start | Stack fit for the assignment's size and explicit Sanctum suggestion. |
| Layer boundaries and the uniform envelope | Architecture is a human sign-off, not an agent default. |

---

## 8. Lessons learned & impact

- **Docs-first pays for itself.** The ~1 day spent producing phase docs made the implementation phases near-linear: the agent rarely asked questions it could answer from the docs, and rework was minimal.
- **A golden module beats a style guide.** Concrete reference code constrains an agent far more effectively than prose rules — the Contact module needed no structural corrections.
- **Green tests ≠ verified claims.** The two catches in §5 both came from checking against a source of truth (guard semantics, spec text), not from test output alone. Validation has to include *reading*.
- **Productivity:** the backend (module code + 72 tests + the full documentation set) fits comfortably in the assignment's 3-day window with roughly a day of that going to design docs and review — a trade that produced fewer bugs, not more overhead.

---

## 9. Guardrails applied to the AI (summary)

- Every AI-written PHP change gated by `vendor/bin/pint` + the test suite before acceptance.
- Agent scoped per task; forbidden from touching other modules or shared contracts mid-task.
- Ownership (`created_by`) verified **by test** to be stamped server-side only.
- Architectural questions escalated to the human instead of guessed (OQ process).
