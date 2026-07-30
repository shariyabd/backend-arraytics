# Data Model

## Address Book Management System — Phase 3: The Data Blueprint

**Scope:** Business and data modeling only. Implementation-independent — no Laravel, no API design, no repositories/services.
**Workflow:** [01-Product-Brief.md](01-Product-Brief.md) · [02-Requirements-and-Domain.md](02-Requirements-and-Domain.md) · **03-Data-Model.md** · [04-Architecture.md](04-Architecture.md) · [05-Guardrails.md](05-Guardrails.md) · [06-Golden-Module.md](06-Golden-Module.md) · [api-doc/](../api-doc/)

> Open questions and their resolutions are tracked in [02-Requirements-and-Domain.md](02-Requirements-and-Domain.md).

---

## 1. Domain Model

### 1.1 Entities

Entities have a distinct identity and a lifecycle.

#### E-1 — Contact *(core, Address Book context)*
A person recorded in the address book.

| Property | Value Object / Type | Notes |
|----------|--------------------|-------|
| Identity | ContactId | Distinct, immutable identity. |
| name | PersonName | Required. |
| phone | PhoneNumber | Required, valid phone format. |
| email | EmailAddress | Required, valid email. |
| website | WebsiteUrl | Optional (nullable) valid URL. |
| gender | Gender | One of an allowed set. |
| age | Age | Whole number within allowed range. |
| nationality | Nationality | Free text. |
| owner | OwnershipStamp → UserId | Set at creation, immutable. |
| createdAt | Timestamp | When the Contact was recorded. |

**Lifecycle:** Recorded → (Viewed / Revised)\* → Removed.

#### E-2 — User *(supporting, Identity & Access context)*
An authenticated actor that owns and manages Contacts. Identity `UserId`; `email` (unique login identifier); `secret` (credential, never exposed).
**Lifecycle:** Provisioned → Authenticated (issues Access Tokens) → …

#### E-3 — Access Token *(supporting, Identity & Access context)*
Proof of an authenticated session for a User. Identity `TokenId`; `owner` (`UserId`); `state` (Issued / Revoked / Expired).
**Lifecycle:** Issued → Presented (per action) → Revoked/Expired.

### 1.2 Aggregates

| Aggregate | Root | Members | Context |
|-----------|------|---------|---------|
| **Contact Aggregate** | Contact | Its value objects (PersonName, PhoneNumber, EmailAddress, WebsiteUrl, Gender, Age, Nationality, OwnershipStamp) | Address Book (core) |
| **User Aggregate** | User | Credentials, issued Access Tokens | Identity & Access (supporting) |

Aggregates reference each other **by identity only** (Contact holds a `UserId` via OwnershipStamp; it never embeds the User).

### 1.3 Value Objects

Immutable, identity-less, defined by their attributes and validity constraints.

| Value Object | Business Constraint |
|--------------|---------------------|
| **Gender** | Member of allowed set: Male / Female / Other. |
| **Age** | Whole number, 1–150. |
| **OwnershipStamp** | Holds a valid UserId; assigned at creation; immutable; never client-supplied. |
| **Credentials** | Email + secret; must match a known User. |

PersonName, PhoneNumber, EmailAddress, WebsiteUrl, and Nationality carry the format constraints listed for their fields in §1.1 / §2.1.

### 1.4 Relationships (Domain)

```
User (1) ───owns──▶ (0..*) Contact
     │
     └──issues──▶ (0..*) Access Token
```

- A **User owns zero or more Contacts**; every **Contact has exactly one owner** (mandatory).
- A **User has zero or more Access Tokens**; every Token belongs to exactly one User.
- Ownership is **immutable** and **never transfers**.
- The relationship crosses aggregate boundaries and is expressed **by identity reference only**.

---

## 2. Data Model

Conceptual data model (structure and relationships), independent of any particular database technology.

### 2.1 Tables

#### `users`
| Attribute | Type | Constraints |
|-----------|------|-------------|
| id | Identifier | Primary identity. |
| name | Text | Display name of the account holder. |
| email | Text | **Unique**; login identifier. |
| password | Secret | Stored non-reversibly; never exposed. |
| created_at | Timestamp | |
| updated_at | Timestamp | |

#### `contacts` (Address Book records)
| Attribute | Type | Constraints |
|-----------|------|-------------|
| id | Identifier | Primary identity. |
| name | string (max 255) | Required. Indexed. |
| phone | string (max 30) | Required; pattern `^\+?[0-9\s\-()]{7,}$`. Indexed. |
| email | string (max 255) | Required; valid email. Indexed. No uniqueness. |
| website | string (max 255) | **Nullable**; optional valid URL. |
| gender | string | One of `Male` \| `Female` \| `Other`. |
| age | unsigned small integer | 1–150. |
| nationality | string (max 255) | Free text. |
| created_by | Identifier → `users.id` | Required; the owner. Immutable; cascade on delete. |
| created_at | Timestamp | |
| updated_at | Timestamp | |

- **Indexes:** `name`, `email`, `phone`.
- **No uniqueness** on contact `email` or `phone` — two contacts may share them.

#### `personal_access_tokens` *(Sanctum; supporting session material)*
| Attribute | Type | Constraints |
|-----------|------|-------------|
| id | Identifier | Primary identity. |
| tokenable_id / tokenable_type | Morph → `users.id` | Owning User. |
| token | Secret | Hashed; never exposed. |
| created_at | Timestamp | |

> `personal_access_tokens` is Laravel Sanctum's table, included for completeness of the ownership/session picture. It is a supporting concept described at a high level only.

### 2.2 Entity Relationships

| Relationship | Cardinality | Optionality |
|--------------|-------------|-------------|
| users → contacts (via `created_by`) | 1 : 0..* | A user may own no contacts; a contact must have exactly one owner. |
| users → personal_access_tokens | 1 : 0..* | A user may have no active tokens; a token must belong to one user. |

### 2.3 Ownership

- Every `contacts` row carries `created_by`, a **mandatory** reference to the owning `users` row.
- `created_by` is **assigned once at creation** from the authenticated identity and is **immutable** — it is never updated and never accepted from client input.
- On owner deletion, contacts **cascade** (are removed with the user).

### 2.4 High-Level ER Diagram (text format)

```
        users (PK id, email unique)
          │ 1
          ├── owns (created_by, cascade) ──▶ 0..* contacts (idx: name, email, phone)
          │
          └── issues ──▶ 0..* personal_access_tokens (tokenable → users.id)

Legend:  PK = primary identity   FK = identity reference   1 : 0..* = one owner to many owned
```

---

## 3. Aggregate Boundaries

**AGG-1 — Contact Aggregate *(core)*.** Root: Contact. Inside the boundary: all Contact value objects (name, phone, email, website, gender, age, nationality) and the OwnershipStamp. A Contact is recorded, revised, and removed atomically as a whole. The owner is referenced by `UserId` only — the User aggregate is *outside* this boundary; nothing may reach into a Contact's internals except through the Contact root.

**AGG-2 — User Aggregate *(supporting)*.** Root: User. Inside the boundary: Credentials and issued Access Tokens. Authentication state and credential changes are consistent within the User. Contacts reference the User by identity but are *not* part of this aggregate.

### Boundary Principles
1. **One transaction, one aggregate.** A single unit of change touches a single aggregate instance.
2. **Reference across boundaries by identity, never by containment.** Contact ↔ User is a `UserId` link.
3. **Invariants are enforced within a boundary.** Cross-aggregate rules (e.g., "owner must exist") are enforced at the moment of association, not by merging the aggregates.

---

## 4. Business Invariants

### Contact Aggregate
- **INV-1:** A Contact must always have a valid OwnershipStamp referencing an existing User.
- **INV-2:** OwnershipStamp (`created_by`) is set exactly once, at creation, and is immutable thereafter.
- **INV-3:** OwnershipStamp is derived from the authenticated identity — never supplied or overridden by the client.
- **INV-4:** A Contact cannot exist unless all required value objects are valid (name, phone, email, gender, age, nationality); website is optional.
- **INV-5:** Gender must be a member of the allowed set (`Male` / `Female` / `Other`).
- **INV-6:** Age must be a whole number within 1–150.
- **INV-7:** EmailAddress, PhoneNumber, and WebsiteUrl must each satisfy their format constraint whenever present.

### User Aggregate
- **INV-8:** A User's email is unique across users.
- **INV-9:** A User's secret is stored non-reversibly and is never exposed in any read of the entity.
- **INV-10:** An Access Token is valid only while associated with an existing User and in an active state; otherwise it grants no access.

### Cross-Aggregate / Ownership
- **INV-11:** Ownership never transfers — once a Contact is owned by a User, that link does not change.
- **INV-12:** Every Contact references exactly one owner; ownerless Contacts are invalid.

---

### Note on Boundaries

This document models the domain and its data structure only. It intentionally omits framework choices, API/endpoint contracts, and repository/service definitions — those are derived in [04-Architecture.md](04-Architecture.md) while preserving these aggregate boundaries and invariants.
