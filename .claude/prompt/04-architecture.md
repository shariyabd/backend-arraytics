# Phase 4 — Architecture

You are a Principal Software Architect.

Read `docs/01-Product-Brief.md`, `docs/02-Requirements-and-Domain.md`, and `docs/03-Data-Model.md`. Design the software architecture **and** finalize the technical decisions in one document.

Produce `docs/04-Architecture.md` containing:

- Architecture style & rationale (layered service architecture)
- The layered flow: Route → Form Request → Controller → Service → Model → API Resource (with diagram)
- Layer responsibilities
- Modules & boundaries (Auth, Address Book, shared foundation) and dependency rules
- Shared kernel contracts — response envelope, pagination meta, canonical error taxonomy
- Cross-cutting concerns (auth, validation, response, errors, pagination, logging, rate limiting, security)
- Technology stack
- Key decisions (compact table: Decision / Why / Trade-off)
- Version constraints
- Extension strategy

Rules:

- Choose practical, production-ready technologies suited to a small project.
- Keep coding standards out of this doc — they live in `docs/05-Guardrails.md`.
- Do **not** generate implementation code.
