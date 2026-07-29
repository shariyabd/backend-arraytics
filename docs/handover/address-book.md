# Implementation Handover — Address Book Module

**Module:** Address Book (Contact Management) · **Type:** Core context
**Status:** ⏳ To be built.
**Contract:** [../Module Contracts/Module-B-Address-Book.md](../Module%20Contracts/Module-B-Address-Book.md)
**Reference:** [../Golden-Module.md](../Golden-Module.md) — build by analogy to Auth.
**Standards:** [../06-Architecture.md](../06-Architecture.md), [../08-Project-Guardrails.md](../08-Project-Guardrails.md), [../../CLAUDE.md](../../CLAUDE.md)

> This handover lets one engineer build the module independently. Follow the Golden Module patterns exactly; do not invent new conventions.

---

## Module Goal

Provide a secure, paginated, searchable REST API for managing address-book contacts. Every contact is owned by the authenticated user who created it, and ownership is enforced server-side.

---

## Responsibilities

- Full CRUD for the `Contact` resource: create, list, view, update, delete.
- Server-side **search** (name, email, phone), **filtering** (gender, nationality, age range), and **pagination**.
- Stamp `created_by` from the authenticated identity on create; keep it immutable on update.
- Enforce contact validation rules via Form Requests.
- Return all data through Resources in the uniform envelope.
- **Not** responsible for: authentication (consumes it from Auth), user management.

---

## Dependencies

| Depends on | For |
|-----------|-----|
| **Auth module** | Authenticated `UserId` (identity by reference) for ownership + guarding. |
| Shared Kernel — `ApiResponse` ([../../app/Support/ApiResponse.php](../../app/Support/ApiResponse.php)) | Uniform envelope. |
| Shared Kernel — central exception handler ([../../bootstrap/app.php](../../bootstrap/app.php)) | Error → envelope mapping (404/422/401/403/500). |
| Sanctum `auth:sanctum` guard | Protecting all endpoints. |
| A `Contact` model, migration, factory, seeder | Persistence + ~50 seed records referencing valid users. |

Must **not** modify the Auth module or the Shared Kernel contracts.

---

## Public Contracts

Full detail: [../Module Contracts/Module-B-Address-Book.md](../Module%20Contracts/Module-B-Address-Book.md).

| Operation | Method & Path | Access |
|-----------|---------------|--------|
| index | `GET /api/v1/contacts` | Protected |
| store | `POST /api/v1/contacts` | Protected |
| show | `GET /api/v1/contacts/{id}` | Protected |
| update | `PUT/PATCH /api/v1/contacts/{id}` | Protected |
| destroy | `DELETE /api/v1/contacts/{id}` | Protected |

- **Request DTOs:** `StoreContactRequest`, `UpdateContactRequest`, `ListContactsQuery` — `created_by` is **never** an accepted field.
- **Response DTOs:** `ContactResource`, `ContactCollection` + shared `PaginationMeta`.
- **Published events:** `ContactCreated`, `ContactUpdated`, `ContactDeleted` (payload: `{ contactId, ownerId }`).

---

## Business Rules

- **BR-B1:** All contact endpoints require authentication (bearer token).
- **BR-B2:** `created_by = auth()->id()`, set **only in the service** on create; never accepted or overridable from client input.
- **BR-B3:** `created_by` is immutable — never changed on update.
- **BR-B4:** Search matches name, email, phone (partial, case-insensitive); filters (gender, nationality, min_age, max_age) are combinable (AND); filters/search applied **before** pagination.
- **BR-B5:** Responses expose only the defined contact fields; nothing sensitive or internal.
- **BR-B6:** Missing contact → 404; validation failure → 422 with field messages.
- **BR-B7 (pending OQ-1):** Visibility scope — own-only vs. see-all. Until decided, implement as a single service-layer policy point; if own-only, accessing another user's contact returns 403.

### Validation (pending confirmation — see open questions)
| Field | Rule |
|-------|------|
| name | required, string, max 255 |
| phone | required, valid phone format |
| email | required, valid email |
| website | valid URL (required? — OQ-11) |
| gender | required, in allowed set (OQ-4) |
| age | required, integer, within range (OQ-3) |
| nationality | required (free-text? — OQ-5) |

---

## Acceptance Criteria

- [ ] Authenticated user can create a contact; `created_by` is set from the token, ignoring any client-supplied owner.
- [ ] Listing returns paginated results with `meta` (current_page, per_page, total, last_page).
- [ ] Search by name/email/phone returns correct partial, case-insensitive matches.
- [ ] Filters (gender, nationality, age range) work individually and combined.
- [ ] Viewing a missing contact returns 404.
- [ ] Update changes editable fields only; `created_by` and `created_at` unchanged.
- [ ] Delete removes the contact and returns a success message; deleting a missing contact returns 404.
- [ ] All validation rules enforced via Form Requests (422 + field messages).
- [ ] Unauthenticated requests to any contact endpoint return 401.
- [ ] A seeder creates ~50 realistic contacts referencing valid users.
- [ ] Responses use the uniform envelope and expose only defined fields.

---

## Definition of Done

- [ ] Layered flow respected (Route → Form Request → Controller → Service → Model → Resource).
- [ ] Business logic + ownership stamping only in the service; controller thin.
- [ ] Validation in Form Requests; `created_by` not among accepted inputs.
- [ ] Output via `ContactResource`/`ContactCollection` + `ApiResponse`; errors centralized.
- [ ] Migration with indexes on `name`, `email`, `phone`; factory + seeder (~50 records).
- [ ] Eager-load owner where needed; no N+1; select only required columns.
- [ ] Feature tests (happy, validation, auth, not-found, search/filter/pagination) + unit tests for the service — all passing.
- [ ] `vendor/bin/pint --dirty` clean; full suite green.
- [ ] No modification to Auth or Shared Kernel contracts.

---

## Open Questions To Resolve Before/During Build

- **OQ-1 (High):** Own-only vs. see-all contact visibility → sets BR-B7 and the 403 behavior.
- **OQ-3:** Allowed age range (min/max).
- **OQ-4:** Exact allowed gender values (enum vs. free string).
- **OQ-5:** Nationality — free-text or controlled list.
- **OQ-6:** Default and maximum page size.
- **OQ-11:** Is `website` required or optional?

> If an open question is unresolved at build time, implement the most reasonable default, isolate it behind the service/validation layer, and flag it — do not hard-code a silent assumption elsewhere.
