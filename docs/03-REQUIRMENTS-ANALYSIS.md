# Requirements Analysis

## Backend — Address Book Management System (Laravel REST API)

**Version:** 1.0
**Role:** Senior Business Analyst
**Source Documents:** [PRD.md](PRD.md), [Idea.md](Idea.md)
**Purpose:** Validate requirements before software design begins. This document does **not** propose system, database, or architecture design.

---

## 1. Requirement Summary

The backend is a **decoupled Laravel 12 REST API** for an Address Book Management System. It exposes JSON APIs only (no Blade/views) and is consumed by a separate React SPA.

Core capabilities:

- **Token-based authentication** via Laravel Sanctum (login is the only public endpoint).
- **CRUD** for a single `address_book` resource.
- **Server-side search, filtering, and pagination** on the listing endpoint.
- **Form Request validation** with structured 422 error responses.
- **Ownership tracking** — every record's `created_by` is derived from the authenticated user and never accepted from the client.
- **Consistent JSON response envelope** (`success`, `message`, `data` / `errors`).
- **Seed data** (~50 records + at least one test user).

The scope is intentionally narrow: one entity, one authenticated actor type, and standard REST semantics.

---

## 2. Module Breakdown

Requirements grouped by business module.

### 2.1 Authentication Module
- Login endpoint (public) accepting email + password.
- Token generation and return on successful login.
- Bearer-token authentication middleware on all protected endpoints.
- Rejection of invalid / missing / expired / revoked tokens with **401**.
- *(Gap)* Logout / token revocation is implied by "revoked token → 401" but **not explicitly specified** as an endpoint.

### 2.2 Authorization / Ownership Module
- All CRUD operations require authentication.
- `created_by = auth()->id()` set automatically on create.
- Client cannot send or override `created_by`.
- `created_by` is immutable on update.

### 2.3 Contact Management Module (CRUD)
- **Create** — accepts name, phone, email, website, gender, age, nationality; auto-sets `created_by`; returns created resource + message + **201**.
- **List (Read)** — paginated collection with search, filters, optional sorting.
- **View single** — full details of one contact; **404** if not found.
- **Update** — editable fields only; `created_by` unchanged; returns updated resource.
- **Delete** — removes record; success message; **404** if already gone.

### 2.4 Search, Filter & Pagination Module
- Keyword search across name, email, phone (partial, case-insensitive where DB collation allows).
- Filters: gender, nationality, age range (min/max), combinable.
- Server-side pagination with metadata (current page, total, per page, last page, data). Configurable default page size.

### 2.5 Validation Module
- Form Request classes enforce field rules (see §11 of PRD).
- Validation failures return **422** with field-specific messages.

### 2.6 API Response & Error Handling Module
- Uniform success/error envelope across all endpoints.
- Graceful handling of validation, auth, missing resource, DB, and unexpected errors.
- Standardized HTTP status codes.
- No stack traces / sensitive details in production responses.

### 2.7 Data & Seeding Module
- MySQL `address_book` table with appropriate types, timestamps, and indexes on searched fields.
- Seeder: ~50 realistic records + ≥1 test user; `created_by` references valid users.

### 2.8 Cross-Cutting: Security, Logging, Performance, Maintainability
- Sanctum auth, mass-assignment protection, hidden sensitive attributes, rate limiting on auth (recommended).
- Log unexpected errors; never log passwords/tokens/sensitive data.
- Pagination, filter-before-paginate, avoid N+1, return only required fields.
- Layered architecture, thin controllers, reusable validation, RESTful naming.

---

## 3. Requirement Classification

| # | Requirement | Module | Type |
|---|-------------|--------|------|
| R1 | Login with email/password returns a token | Auth | Functional |
| R2 | All CRUD endpoints require a valid bearer token | Auth/Authz | Functional + Business Rule |
| R3 | Invalid/missing/expired/revoked token → 401 | Auth | Functional |
| R4 | `created_by` set from `auth()->id()` on create | Authz | Business Rule |
| R5 | Client may never send/override `created_by` | Authz | Business Rule |
| R6 | `created_by` immutable on update | Authz | Business Rule |
| R7 | Create contact returns resource + 201 | CRUD | Functional |
| R8 | List contacts is paginated | CRUD/Pagination | Functional |
| R9 | View single contact; 404 if missing | CRUD | Functional |
| R10 | Update editable fields; return updated resource | CRUD | Functional |
| R11 | Delete contact; 404 if missing | CRUD | Functional |
| R12 | Search across name, email, phone (partial, case-insensitive) | Search | Functional |
| R13 | Filter by gender, nationality, age range; combinable | Filter | Functional |
| R14 | Pagination metadata + configurable page size | Pagination | Functional |
| R15 | Validation via Form Requests | Validation | Functional |
| R16 | Validation failure → 422 with field messages | Validation | Functional + Business Rule |
| R17 | Consistent success/error JSON envelope | Response | Non-functional (Consistency) |
| R18 | Standard HTTP status codes | Response | Non-functional |
| R19 | Graceful error handling; no stack traces in prod | Error Handling | Non-functional (Security/Reliability) |
| R20 | MySQL table with indexes on searched fields | Data | Non-functional (Performance) |
| R21 | Seeder: ~50 records + ≥1 test user | Seeding | Functional |
| R22 | Mass-assignment protection, hidden attributes | Security | Non-functional |
| R23 | Rate limiting on auth endpoints (recommended) | Security | Non-functional |
| R24 | Log errors; never log secrets | Logging | Non-functional (Security) |
| R25 | Avoid N+1, filter-before-paginate, return required fields | Performance | Non-functional |
| R26 | Layered architecture, thin controllers, RESTful naming | Maintainability | Non-functional |
| R27 | API documentation (Postman/OpenAPI/Markdown) | Documentation | Non-functional |

---

## 4. Business Rules

1. **BR-1 — Ownership assignment:** `created_by` is always the authenticated user's ID; never client-supplied.
2. **BR-2 — Ownership immutability:** `created_by` cannot change after creation.
3. **BR-3 — Authenticated access:** Every address-book operation requires a valid token; only login is public.
4. **BR-4 — Validation gate:** A request failing validation is rejected (422) before any persistence.
5. **BR-5 — Data exposure limits:** Responses expose only required fields; sensitive attributes are hidden.
6. **BR-6 — Combinable filters:** Search and multiple filters may be applied together and are ANDed.

> ⚠️ **Ambiguity — Data visibility scope:** The PRD tracks `created_by` but never states whether a user may **read/update/delete only their own contacts** or **all contacts**. This is a critical unresolved business rule (see Open Question OQ-1).

---

## 5. Non-Functional Requirements

| Category | Requirement |
|----------|-------------|
| **Security** | Sanctum token auth; mass-assignment protection; hidden sensitive attributes; recommended rate limiting on auth; no secrets in logs; no stack traces in prod responses. |
| **Performance** | Server-side pagination; filters applied before pagination; indexes on searched columns; avoid N+1; return only required fields. |
| **Consistency** | Uniform success/error response envelope; standardized HTTP status codes across all endpoints. |
| **Reliability** | Graceful handling of validation, auth, not-found, DB, and unexpected errors. |
| **Maintainability** | Layered architecture, thin controllers, reusable Form Requests, RESTful route naming, dependency injection, consistent coding standards. |
| **Observability** | Log unexpected application errors (excluding sensitive data). |
| **Documentation** | Document auth flow, headers, payloads, query params, responses, and error formats. |

---

## 6. Assumptions (Require Confirmation)

- **A-1:** There is a **single user role** (no admin/user distinction, no roles/permissions system).
- **A-2:** Users can access **all** address-book records regardless of `created_by` (i.e., `created_by` is audit metadata, not an access-control boundary). *Pending OQ-1.*
- **A-3:** **Registration is out of scope** — users exist via the seeder only (PRD lists only login as public).
- **A-4:** **Hard deletes** are used (no soft-delete requirement stated).
- **A-5:** Tokens are **long-lived** with no explicit expiry policy beyond Sanctum defaults (PRD mentions "expired" but sets no TTL).
- **A-6:** `updated_at` is maintained by Laravel timestamps even though only `created_at` is listed in the entity table.
- **A-7:** Sorting is **optional / deferred**; if implemented, sortable columns and default order are unspecified.
- **A-8:** Phone is stored/validated as a **string in a permissive format** (no specific country/E.164 requirement given).
- **A-9:** `email` and `phone` uniqueness is **not required** (PRD lists no unique constraint on contacts).
- **A-10:** All API traffic is served over **HTTPS** in production.

---

## 7. Open Questions (Require Clarification)

| ID | Question | Impact |
|----|----------|--------|
| **OQ-1** | Can a user read/update/delete **only their own** contacts, or **all** contacts? | High — defines core authorization rules and query scoping. |
| **OQ-2** | Is a **logout / token-revocation** endpoint required? PRD mentions "revoked token → 401" but defines no revoke action. | Medium — affects endpoint surface. |
| **OQ-3** | What is the **allowed age range** (min/max) for validation? | Medium — validation rule cannot be finalized without bounds. |
| **OQ-4** | What are the exact **allowed gender values** (Male/Female/Other only, or additional)? Enum vs free string? | Medium — validation + filtering. |
| **OQ-5** | Is `nationality` a **free-text string** or a controlled list (e.g., ISO country codes)? | Medium — validation + filter behavior. |
| **OQ-6** | What is the **default and maximum page size**? PRD says "configurable" but gives no values. | Low/Medium — pagination behavior. |
| **OQ-7** | Should any contact fields be **unique** (e.g., email/phone per user or globally)? | Medium — data integrity + validation. |
| **OQ-8** | Is **registration** ever needed, or is login-only permanent? | Low — scope boundary. |
| **OQ-9** | Are **soft deletes** required (recoverable records / audit)? | Low/Medium — data model implications. |
| **OQ-10** | What is the required **token lifetime / expiry** policy? | Low/Medium — security posture. |
| **OQ-11** | Is `website` truly **required**, or optional? (PRD marks it required, which is unusual for an address book.) | Low — validation. |
| **OQ-12** | Which **API documentation format** is the deliverable — Postman, OpenAPI, or Markdown? | Low — deliverable format. |

---

## 8. Conflicts & Inconsistencies Identified

1. **C-1 — Entity vs. timestamps:** The entity table lists only `created_at`, but "Maintainability/best practices" and update flows imply `updated_at`. Assumed present (A-6); confirm.
2. **C-2 — "Rate limiting (recommended)"** is listed under a mandatory "API Security" section yet marked optional. Clarify whether it is in-scope for acceptance.
3. **C-3 — Sorting** appears as "optional" in Read requirements but is not in the acceptance criteria. Treated as deferred (A-7).
4. **C-4 — Website required:** Marking `website` as *required* conflicts with typical real-world address-book data where website is often absent (OQ-11).
5. **C-5 — Authorization granularity:** PRD says "All CRUD operations require authentication" but is silent on per-record ownership enforcement — authentication ≠ authorization (OQ-1 / BR ambiguity).

---

## 9. Final Approved Scope

The following is proposed as the validated, in-scope baseline for design. **Items marked (⧖) are blocked pending clarification** of the corresponding open question.

**In Scope — Confirmed:**
- Sanctum token authentication with a single public **login** endpoint.
- Full CRUD for the `address_book` resource, all protected by authentication.
- `created_by` auto-assigned from the authenticated user and immutable (BR-1, BR-2).
- Server-side search (name, email, phone), filtering (gender, nationality, age range — combinable), and pagination with metadata.
- Form Request validation returning 422 with field-level messages.
- Consistent success/error JSON envelope and standardized HTTP status codes.
- Graceful error handling with production-safe messages.
- MySQL persistence with indexes on searched columns; seeder producing ~50 records + ≥1 test user.
- Security baseline: mass-assignment protection, hidden sensitive attributes, no secrets in logs.
- Layered, maintainable architecture and API documentation deliverable.

**In Scope — Pending Clarification (⧖):**
- Record-level authorization model (own vs. all records) — **OQ-1**.
- Exact validation bounds for age, gender values, nationality format — **OQ-3, OQ-4, OQ-5**.
- Pagination default/max page size — **OQ-6**.
- Rate limiting as mandatory vs. recommended — **C-2**.

**Out of Scope (per PRD Non-Goals & assumptions):**
- Any frontend rendering, Blade templates, or view logic.
- User registration, roles/permissions, or multi-role authorization (unless OQ-8 changes this).
- Soft deletes, sorting UI, and token TTL policy — deferred unless clarified (A-4, A-7, OQ-9, OQ-10).
- Client-supplied `created_by`.

---

### Sign-off

This analysis validates the requirements and surfaces open questions **without** proposing system, database, or architecture design. Design work should begin only after **OQ-1** (authorization scope) and the validation-bound questions (**OQ-3, OQ-4, OQ-5, OQ-6**) are answered, as these directly shape the data model and API contract.
