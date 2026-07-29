# 02 — Requirements & Domain

**Phase 2 — Understanding the problem:** requirements clarification + domain vocabulary.
**Related docs:** [01-Product-Brief.md](01-Product-Brief.md) · [03-Data-Model.md](03-Data-Model.md) · [04-Architecture.md](04-Architecture.md) · [05-Guardrails.md](05-Guardrails.md) · [06-Golden-Module.md](06-Golden-Module.md) · [api-doc/](../api-doc/)

---

## 1. Purpose

Turn the product brief into precise, buildable rules and a shared domain vocabulary. The *what* and *why* live in [01-Product-Brief.md](01-Product-Brief.md); the full data model lives in [03-Data-Model.md](03-Data-Model.md). This doc captures the business rules, resolved open questions, and ubiquitous language that bridge them.

**System in one line:** a decoupled Laravel 12 REST API (JSON only, no Blade/views) for an Address Book, consumed by a separate React SPA. One core entity — `Contact` (table `contacts`) — with Sanctum token auth and no registration.

---

## 2. Module Breakdown

Business capabilities, grouped. (Not architecture — see [04-Architecture.md](04-Architecture.md).)

- **Authentication** — public login (email + password), Sanctum token issuance, logout (revoke); bearer auth on all protected endpoints; invalid/missing/expired/revoked token → 401. Login is throttled 6/min.
- **Ownership** — `created_by` stamped server-side from the authenticated user on create; never client-supplied; immutable thereafter (audit metadata only, not an access boundary).
- **Contact CRUD** — create (201), list (paginated), view single (404 if missing), update (editable fields only), delete (404 if missing).
- **Search / Filter / Pagination** — keyword search across name, email, phone (partial, case-insensitive); filters on gender, nationality, age range (min/max), combinable (ANDed); server-side pagination with metadata.
- **Validation** — Form Request classes; failures return 422 with field-level messages before any persistence.
- **API Response & Errors** — uniform envelope; success `{success, message, data}`, error `{success, message, errors}`; standard HTTP status codes; no stack traces/secrets in production responses.
- **Data & Seeding** — MySQL `contacts` table, indexes on searched columns; seeder produces ~50 contacts + ≥1 test user with valid `created_by`.

---

## 3. Business Rules

- **BR-1 — Ownership assignment:** `created_by` is always the authenticated user's ID, stamped server-side; never client-supplied.
- **BR-2 — Ownership immutability:** `created_by` cannot change after creation.
- **BR-3 — Authenticated access:** every contact operation requires a valid token; only login is public.
- **BR-4 — Validation gate:** a request failing validation is rejected (422) before any persistence.
- **BR-5 — Data exposure limits:** responses expose only required fields; sensitive attributes (e.g. password, token) are hidden.
- **BR-6 — Combinable filters:** search and multiple filters may be applied together and are ANDed.
- **BR-7 — See-all visibility:** any authenticated user may read/update/delete any contact; `created_by` is audit metadata, not an access boundary (no 403 path). See OQ-1.

---

## 4. Assumptions

Stated once. Each was validated during build (resolutions in §5 where an open question was attached).

- **A-1 — Single role:** one user role only; no admin/user distinction, no roles/permissions system, no elevated actor.
- **A-2 — See-all access:** users access all contacts regardless of `created_by` (resolved — see OQ-1 / BR-7).
- **A-3 — No self-registration:** users are pre-provisioned via the seeder only; account creation is out of scope.
- **A-4 — Hard deletes:** removal is permanent; no soft-delete / recoverable state.
- **A-5 — Token lifetime:** Sanctum default TTL; no custom expiry policy (resolved — see OQ-10).
- **A-6 — Timestamps:** `updated_at` is maintained by Laravel timestamps alongside `created_at`.
- **A-7 — Phone format:** stored/validated as a permissive string (no E.164 / country requirement).
- **A-8 — No uniqueness:** `email` and `phone` are not unique on contacts (resolved — see OQ-7).
- **A-9 — HTTPS:** all production traffic is served over HTTPS.

---

## 5. Open Questions — Resolved

These were surfaced during requirements analysis and **decided during build**. The decided value is authoritative.

| ID | Question | Resolution |
|----|----------|------------|
| **OQ-1** | Can a user read/update/delete only their own contacts, or all? | **See-all.** Any authenticated user may read/update/delete any contact; `created_by` is audit metadata, not an access boundary (no 403 path). |
| **OQ-2** | Is a logout / token-revocation endpoint required? | **Provided.** Logout revokes the current token → subsequent requests get 401. |
| **OQ-3** | What is the allowed age range? | **Integer 1–150.** |
| **OQ-4** | What are the allowed gender values? | **Closed set: `Male | Female | Other`.** |
| **OQ-5** | Is nationality free text or a controlled list? | **Free text, max 255.** |
| **OQ-6** | What is the default / maximum page size? | **Default 15, max 100.** |
| **OQ-7** | Should any contact fields be unique (email/phone)? | **No uniqueness** — email and phone may repeat. |
| **OQ-8** | Is registration ever needed? | **No** — login-only; users are seeded. |
| **OQ-9** | Are soft deletes required? | **No** — hard deletes only. |
| **OQ-10** | What token lifetime / expiry policy? | **Sanctum default TTL**; no custom policy. |
| **OQ-11** | Is `website` required or optional? | **Optional** (nullable URL). |
| **OQ-12** | Which API documentation format is the deliverable? | **Markdown**, module-wise under [api-doc/](../api-doc/). |

---

## 6. Bounded Contexts

The domain is small and cohesive — two contexts.

- **BC-1 — Identity & Access** *(supporting).* Establishes *who* an actor is and whether they may act. Concepts: credentials, authentication, access session, ownership identity. Knows the acting user's identity; does not know contact business data beyond the ownership link.
- **BC-2 — Address Book** *(core domain).* Owns the lifecycle and querying of contact records. Concepts: contact, ownership stamp, search, filtering, listing. Consumes an authenticated identity from BC-1 but owns all contact business rules.

The product's value lives in **BC-2**; BC-1 is necessary support.

---

## 7. Domain Model Vocabulary

Concept-level only. The physical schema, columns, casts, and indexes are in [03-Data-Model.md](03-Data-Model.md).

### Entities (identity + lifecycle)

- **Contact** *(BC-2, core).* A person recorded in the address book. Attributes: name, phone, email, website, gender, age, nationality; carries an ownership reference to its creating User. Lifecycle: Created → (Viewed / Updated) → Deleted.
- **User** *(BC-1).* An authenticated actor who creates and manages contacts; the subject of authentication. Pre-provisioned via seeding only.
- **Access Token** *(BC-1, supporting).* Proof of an authenticated session for a User. Lifecycle: Issued → Presented (each request) → Revoked/Expired.

### Value Objects (attribute-defined, immutable, no identity)

- **PersonName** — required, non-empty.
- **PhoneNumber** — required; permissive string format.
- **EmailAddress** — required; well-formed email (also the User login identifier).
- **WebsiteUrl** — optional; valid URL when present.
- **Gender** — one of `Male | Female | Other`.
- **Age** — integer 1–150.
- **Nationality** — free text, max 255.
- **OwnershipStamp** — the link recording which User created a Contact; assigned server-side at creation, immutable, never client-supplied.
- **SearchCriteria** — keyword matched partially/case-insensitively across name, email, phone.
- **FilterCriteria** — gender, nationality, age range (min/max); combinable (AND).
- **PageRequest** — page number + size (default 15, max 100).

### Aggregates & Invariants

- **Contact Aggregate** *(root: Contact).* Invariants: always carries a valid OwnershipStamp referencing an existing User; the stamp is set once and never changes; all required value objects must be valid for the Contact to exist. Created, modified, and deleted as one unit.
- **User Aggregate** *(root: User).* Associated: Credentials, issued Access Tokens. Invariants: the User's identity is the sole source of a Contact's ownership; Access Tokens are valid only while associated with an active User.

The two aggregates reference each other **only by identity** (Contact → User via OwnershipStamp), never by embedding — preserving aggregate boundaries.

---

## 8. Business Actors

| Actor | Type | Role |
|-------|------|------|
| **Authenticated User** | Primary (human, via SPA) | Logs in, then creates, views, searches, filters, updates, deletes contacts. The only actor able to perform contact operations. |
| **Guest / Anonymous** | Primary (human) | Un-authenticated visitor; may only attempt login. No access to contact data. |
| **Authentication Guardian** | Secondary (system) | Verifies presented tokens on each protected operation; grants or denies. |
| **Seeder / Provisioner** | Secondary (system) | Populates ~50 sample contacts + ≥1 test user for first-run readiness. |

Single user role only — no Administrator or privileged actor (A-1).

---

## 9. Key Workflows

- **Authenticate (login).** Guest submits credentials → system validates against a known User → on success issues an Access Token and returns it (Guest becomes Authenticated User); on failure denies access. Login throttled 6/min. Logout revokes the current token → later requests get 401.
- **Create contact.** Authenticated User supplies details → system validates → OwnershipStamp applied automatically from the acting User → Contact added → returns resource + 201.
- **List / search / filter.** Authenticated User requests the list, optionally with SearchCriteria, FilterCriteria, and a PageRequest → system applies search + filters, returns a paginated slice with metadata.
- **View single.** Authenticated User requests one Contact by id → returns full details, or 404 if it does not exist.
- **Update.** Authenticated User submits changed details → system validates → editable attributes updated, OwnershipStamp unchanged → returns updated resource, or 404.
- **Delete.** Authenticated User requests deletion by id → system removes it and confirms, or 404.

---

## 10. Ubiquitous Language

| Term | Definition |
|------|------------|
| **Address Book** | The core domain: the collection of all Contact records. |
| **Contact** | A recorded person — name, phone, email, website, gender, age, nationality. |
| **User** | An authenticated actor who creates and manages Contacts. |
| **Guest** | An un-authenticated visitor able only to attempt login. |
| **Owner** | The User who created a given Contact. Ownership never transfers (and, under see-all, does not gate access). |
| **OwnershipStamp** (`created_by`) | The immutable link recording which User created a Contact; assigned server-side, never client-supplied. |
| **Credentials** | The email + secret a User presents to authenticate. |
| **Access Token** | Proof of an authenticated session, presented on each protected action. |
| **Authenticate / Login** | Proving identity to obtain an Access Token. |
| **Logout** | Revoking the current Access Token (subsequent requests → 401). |
| **Search Criteria** | A keyword matched partially/case-insensitively against name, email, phone. |
| **Filter Criteria** | Constraints on gender, nationality, age range — combinable. |
| **Page Request** | A request for a slice of the list (page number + size; default 15, max 100). |
| **Listing** | A paginated set of Contacts returned with navigation metadata. |
| **Envelope** | The uniform response shape: `{success, message, data}` on success, `{success, message, errors}` on error. |
| **Provisioning / Seeding** | Populating the system with an initial test User and ~50 sample Contacts. |
