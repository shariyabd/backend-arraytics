# Domain & Data Model

## Address Book Management System

**Version:** 1.0
**Role:** Domain-Driven Design (DDD) Architect
**Source Documents:** [PRD.md](PRD.md), [03-REQUIRMENTS-ANALYSIS.md](03-REQUIRMENTS-ANALYSIS.md), [04-DOMAIN-DISCOVERY.md](04-DOMAIN-DISCOVERY.md)
**Scope:** Business and data modeling only. Implementation-independent — no Laravel, no API design, no repositories/services.

---

## 1. Domain Model

### 1.1 Entities

Entities have a distinct identity and a lifecycle.

#### E-1 — Contact *(core, Address Book context)*
The central entity: a person recorded in the address book.

| Property | Value Object / Type | Notes |
|----------|--------------------|-------|
| Identity | ContactId | Distinct, immutable identity. |
| name | PersonName | Required. |
| phone | PhoneNumber | Required, valid phone format. |
| email | EmailAddress | Required, valid email. |
| website | WebsiteUrl | Required-ness pending clarification (AS-7). |
| gender | Gender | One of an allowed set. |
| age | Age | Whole number within allowed range. |
| nationality | Nationality | Required. |
| owner | OwnershipStamp → UserId | Set at creation, immutable. |
| createdAt | Timestamp | When the Contact was recorded. |

**Lifecycle:** Recorded → (Viewed / Revised)\* → Removed.

#### E-2 — User *(supporting, Identity & Access context)*
An authenticated actor that owns and manages Contacts.

| Property | Value Object / Type | Notes |
|----------|--------------------|-------|
| Identity | UserId | Distinct, immutable identity. |
| email | EmailAddress | Login identifier. |
| secret | (credential) | Verification material; never exposed. |

**Lifecycle:** Provisioned → Authenticated (issues Access Tokens) → …

#### E-3 — Access Token *(supporting, Identity & Access context)*
Proof of an authenticated session for a User.

| Property | Type | Notes |
|----------|------|-------|
| Identity | TokenId | Distinct identity. |
| owner | UserId | The authenticated User. |
| state | Issued / Revoked / Expired | Session lifecycle. |

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
| **PersonName** | Non-empty text. |
| **PhoneNumber** | Valid phone format. |
| **EmailAddress** | Well-formed email. |
| **WebsiteUrl** | Valid URL. |
| **Gender** | Member of allowed set (e.g., Male / Female / Other — AS-4). |
| **Age** | Whole number within allowed range (AS-5). |
| **Nationality** | Non-empty; free-text or controlled list (AS-6). |
| **OwnershipStamp** | Holds a valid UserId; assigned at creation; immutable; never client-supplied. |
| **Credentials** | Email + secret; must match a known User. |
| **SearchCriteria** | Keyword matched (partial, case-insensitive) across name, email, phone. |
| **FilterCriteria** | Gender + Nationality + AgeRange (min/max); combinable (AND). |
| **PageRequest** | Page number + page size (default configurable). |

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

### 2.1 Main Data Entities

#### `users`
| Attribute | Type (conceptual) | Constraints |
|-----------|-------------------|-------------|
| id | Identifier | Primary identity. |
| name | Text | Display name of the account holder. |
| email | Text | Unique; login identifier. |
| password | Secret | Stored non-reversibly; never exposed. |
| created_at | Timestamp | |
| updated_at | Timestamp | |

#### `contacts` (Address Book records)
| Attribute | Type (conceptual) | Constraints |
|-----------|-------------------|-------------|
| id | Identifier | Primary identity. |
| name | Text | Required. |
| phone | Text | Required; valid phone format. |
| email | Text | Required; valid email. |
| website | Text | Valid URL (required-ness per AS-7). |
| gender | Enumerated text | One of allowed values. |
| age | Whole number | Within allowed range. |
| nationality | Text | Required. |
| created_by | Identifier → users.id | Required; the owner. Immutable. |
| created_at | Timestamp | |
| updated_at | Timestamp | |

#### `access_tokens` *(supporting; session material)*
| Attribute | Type (conceptual) | Constraints |
|-----------|-------------------|-------------|
| id | Identifier | Primary identity. |
| user_id | Identifier → users.id | Owning User. |
| token | Secret | Hashed; never exposed. |
| state | Enumerated | Active / Revoked / Expired. |
| created_at | Timestamp | |

> `access_tokens` is included for completeness of the ownership/session picture. It is a supporting concept and is intentionally described at a high level only.

### 2.2 Entity Relationships

| Relationship | Cardinality | Optionality |
|--------------|-------------|-------------|
| users → contacts (via `created_by`) | 1 : 0..* | A user may own no contacts; a contact must have exactly one owner. |
| users → access_tokens (via `user_id`) | 1 : 0..* | A user may have no active tokens; a token must belong to one user. |

### 2.3 Ownership

- Every `contacts` row carries `created_by`, a **mandatory** reference to the owning `users` row.
- `created_by` is **assigned once at creation** from the authenticated identity and is **immutable** — it is never updated and never accepted from client input.
- Deleting a User's contacts, or the effect of removing a User on owned contacts (cascade vs. restrict), is **not yet specified** and is recorded as an assumption (AS-D1 below).
- Whether ownership constrains *visibility* (a user sees only their own contacts) or is *audit-only* (all users see all contacts) is the unresolved **OQ-1** — the model supports both; access-scoping is a rule layered on top, not a structural change.

### 2.4 High-Level ER Diagram (text format)

```
┌─────────────────────────┐
│          users          │
├─────────────────────────┤
│ id (PK)                 │
│ name                    │
│ email (unique)          │
│ password (hidden)       │
│ created_at              │
│ updated_at              │
└───────────┬─────────────┘
            │ 1
            │
            │ owns (created_by)          issues (user_id)
            │ 0..*                       │ 0..*
            ▼                            ▼
┌─────────────────────────┐   ┌─────────────────────────┐
│        contacts         │   │      access_tokens      │
├─────────────────────────┤   ├─────────────────────────┤
│ id (PK)                 │   │ id (PK)                 │
│ name                    │   │ user_id (FK → users.id) │
│ phone                   │   │ token (hidden)          │
│ email                   │   │ state                   │
│ website                 │   │ created_at              │
│ gender                  │   └─────────────────────────┘
│ age                     │
│ nationality             │
│ created_by (FK→users.id)│
│ created_at              │
│ updated_at              │
└─────────────────────────┘

Legend:
  PK = primary identity
  FK = identity reference to another entity
  1 : 0..*  = one owner to many owned records
```

---

## 3. Aggregate Boundaries

### AGG-1 — Contact Aggregate *(core)*
- **Root:** Contact.
- **Inside the boundary:** all Contact value objects (name, phone, email, website, gender, age, nationality) and the OwnershipStamp.
- **Consistency unit:** a Contact is recorded, revised, and removed atomically as a whole.
- **External references:** the owner is referenced by `UserId` only — the User aggregate is *outside* this boundary.
- **Rule:** nothing outside may reach into a Contact's internals except through the Contact root.

### AGG-2 — User Aggregate *(supporting)*
- **Root:** User.
- **Inside the boundary:** Credentials and issued Access Tokens (session material tied to the User).
- **Consistency unit:** authentication state and credential changes are consistent within the User.
- **External references:** Contacts reference the User by identity but are *not* part of this aggregate.

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
- **INV-4:** A Contact cannot exist unless all required value objects are valid (name, phone, email, website\*, gender, age, nationality). *(\*website requiredness per AS-7.)*
- **INV-5:** Gender must be a member of the allowed set.
- **INV-6:** Age must be a whole number within the allowed range.
- **INV-7:** EmailAddress, PhoneNumber, and WebsiteUrl must each satisfy their format constraint whenever present.

### User Aggregate
- **INV-8:** A User's email is unique across users.
- **INV-9:** A User's secret is stored non-reversibly and is never exposed in any read of the entity.
- **INV-10:** An Access Token is valid only while associated with an existing User and in an active state; otherwise it grants no access.

### Cross-Aggregate / Ownership
- **INV-11:** Ownership never transfers — once a Contact is owned by a User, that link does not change.
- **INV-12:** Every Contact references exactly one owner; ownerless Contacts are invalid.

---

## 5. Assumptions Carried Into the Model

Unresolved points from earlier phases that shape (but are not resolved by) this model:

- **AS-D1 — Owner deletion behavior:** What happens to a User's Contacts when the User is removed (cascade delete, restrict, or reassign) is unspecified. Modeled as an ordinary mandatory reference until clarified.
- **OQ-1 — Ownership scope:** Whether ownership limits visibility (own-only) or is audit-only (see-all) is unresolved; the structure supports both.
- **AS-4 / AS-5 / AS-6 / AS-7:** Allowed Gender set, Age range bounds, Nationality form (free vs. controlled), and Website requiredness remain unconfirmed and are represented as constraints without fixed values.
- **AS-9 — Contact uniqueness:** No uniqueness constraint is placed on a Contact's email/phone; two Contacts may currently share them.
- **Session concepts** (Access Token entity, logout) are modeled at a high level and depend on confirmation of OQ-2.

---

### Note on Boundaries

This document models the domain and its data structure only. It intentionally omits framework choices, API/endpoint contracts, and repository/service definitions. Later design phases should derive those from this model while preserving its aggregate boundaries and invariants.
