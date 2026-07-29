# Design & Engineering Docs

The design record for the **Address Book Management System** backend — a decoupled Laravel 12 REST API consumed by a React SPA. These docs trace the project from idea to implementation using a lean, agentic-engineering workflow: each phase is driven by a prompt in [`.claude/prompt/`](../.claude/prompt/) and produces one document here.

## The workflow

| Phase | Document | Purpose | Prompt |
|-------|----------|---------|--------|
| 1 | [01-Product-Brief.md](01-Product-Brief.md) | What we're building and why — vision, scope, features, acceptance criteria | [`01-product-brief.md`](../.claude/prompt/01-product-brief.md) |
| 2 | [02-Requirements-and-Domain.md](02-Requirements-and-Domain.md) | Requirements clarified + domain vocabulary; open questions and their resolutions | [`02-requirements-and-domain.md`](../.claude/prompt/02-requirements-and-domain.md) |
| 3 | [03-Data-Model.md](03-Data-Model.md) | Entities, aggregates, invariants, tables, ownership rule | [`03-data-model.md`](../.claude/prompt/03-data-model.md) |
| 4 | [04-Architecture.md](04-Architecture.md) | Layered architecture + technology decisions + shared contracts | [`04-architecture.md`](../.claude/prompt/04-architecture.md) |
| 5 | [05-Guardrails.md](05-Guardrails.md) | Folder structure, coding standards, and AI-agent rules | [`05-guardrails.md`](../.claude/prompt/05-guardrails.md) |
| 6 | [06-Golden-Module.md](06-Golden-Module.md) | The Auth module as the reference implementation to copy | [`06-golden-module.md`](../.claude/prompt/06-golden-module.md) |

**Progression:** idea → requirements & domain → data model → architecture → guardrails → reference implementation → build the remaining modules by analogy.

## API reference

The concrete, implementation-accurate API contracts (endpoints, payloads, validation, errors) live in [`../api-doc/`](../api-doc/):

- [auth.md](../api-doc/auth.md) — authentication endpoints
- [address-book.md](../api-doc/address-book.md) — contact CRUD, search, filter, pagination
- [design-brief.md](../api-doc/design-brief.md) — UI design brief for the frontend agent

Day-to-day engineering rules for AI-assisted development are summarized in [`../CLAUDE.md`](../CLAUDE.md), which layers on top of [05-Guardrails.md](05-Guardrails.md).
