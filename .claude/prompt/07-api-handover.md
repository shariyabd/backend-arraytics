# Phase 7 — API Handover

You are the Engineering Manager.

Read `docs/04-Architecture.md`, `docs/05-Guardrails.md`, and `docs/06-Golden-Module.md`. Produce module-wise API handover documentation inside `api-doc/`, complete enough for an AI engineer to implement (or consume) each module without further clarification.

Each module gets its own `.md` file (e.g. `api-doc/auth.md`, `api-doc/address-book.md`) containing:

- API overview
- Endpoints
- Request payloads
- Response schemas
- Parameters and data types
- Validation rules
- Authentication / authorization requirements
- Error responses
- Business rules
- Module-specific standards

Rules:

- Follow the project's API standards and the Golden Module conventions.
- The API reference is the implementation contract — keep it accurate to what is built.
- Do **not** duplicate design rationale here; that lives in `docs/`.
