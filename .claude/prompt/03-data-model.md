# Phase 3 — Data Model

You are a Domain-Driven Design Architect.

Read `docs/01-Product-Brief.md` and `docs/02-Requirements-and-Domain.md`. Transform the domain into a concrete, implementation-independent data blueprint.

Produce `docs/03-Data-Model.md` containing:

- Domain model — entities, aggregates, value objects, relationships
- Data model — tables (users, contacts, personal_access_tokens) with columns, types, constraints
- Ownership rule — `created_by` mandatory, assigned once, immutable, never from the client
- High-level ER diagram (text)
- Aggregate boundaries
- Business invariants

Rules:

- Keep the model implementation-independent — do **not** discuss Laravel, design APIs, or define services/repositories.
- Do not repeat the open-question matrix; point to `docs/02-Requirements-and-Domain.md` for it.
