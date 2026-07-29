# Module Contracts

## Address Book Management System — Backend

**Version:** 1.0
**Role:** Principal Software Architect
**Source Documents:** [../06-Architecture.md](../06-Architecture.md), Golden Module (Auth), [../08-Project-Guardrails.md](../08-Project-Guardrails.md)
**Scope:** The stable, public contracts each module exposes to the outside (other modules and API clients). Implementation details are hidden. **No implementation code.**

---

## Index

| Module | Type | Contract |
|--------|------|----------|
| A — Identity & Access (Auth) | Supporting (Golden Module) | [Module-A-Auth.md](Module-A-Auth.md) |
| B — Address Book | Core | [Module-B-Address-Book.md](Module-B-Address-Book.md) |
| C — Platform / Shared Kernel | Foundation | [Module-C-Shared-Kernel.md](Module-C-Shared-Kernel.md) |

Each module file defines exactly six things: **Public Operations, Request DTOs, Response DTOs, Published Events, Consumed Events, Error Contracts.**

---

## Conventions (binding on every module)

Derived from the Golden Module and the guardrails.

- **Transport envelope** — every response uses the shared shape (defined in Module C):
  - Success: `{ "success": true, "message": string, "data": <payload> }`
  - Error: `{ "success": false, "message": string, "errors": object|null }`
- **Versioning** — all HTTP operations live under `/api/v1`.
- **Identity by reference** — modules exchange a User only as a `UserId`, never the full object. Identity is resolved from the bearer token, never from request input.
- **Ownership is never a client input** — `created_by`/owner fields are absent from every Request DTO by contract.
- **DTO stability** — Request/Response DTOs are additive-only: new optional fields may be added; existing fields are never removed or repurposed without a new API version.
- **Events are conceptual/in-process** — event names are the stable contract; payloads carry identities and primitives only.
- **Notation** — `?` = optional; `Enum{…}` = allowed values; `[]` = list. These describe *shapes*, not classes.

---

## Contract Dependency Summary

```
        ┌──────────────────────────────┐
        │  Module C — Shared Kernel     │  envelope, pagination, error taxonomy
        └───────────────┬──────────────┘
              used by    │    used by
        ┌───────────────┴───────────────┐
        │                               │
┌───────▼────────┐   currentUserId()  ┌─▼───────────────┐
│ Module A — Auth │───────────────────▶│ Module B —       │
│ (Golden)        │   (identity only)  │ Address Book     │
└─────────────────┘                    └──────────────────┘

Rules: B → A only via currentUserId(); A never depends on B;
       both depend on C; no cycles; identity crosses boundaries as UserId only.
```

---

### Note

These are the **stable public contracts** — the surface other modules and clients may rely on. Everything else (services, models, query construction, token internals) is implementation detail and may change freely so long as these contracts hold. Contracts evolve **additively**; a breaking change requires a new API version and an updated ADR in [../07-Technical-Decisions.md](../07-Technical-Decisions.md).
