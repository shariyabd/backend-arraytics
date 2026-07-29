# Domain Discovery

## Address Book Management System

**Version:** 1.0
**Role:** Domain-Driven Design (DDD) Expert
**Source Documents:** [PRD.md](PRD.md), [03-REQUIRMENTS-ANALYSIS.md](03-REQUIRMENTS-ANALYSIS.md)
**Scope:** Business-concept discovery only. No database, API, framework, or implementation detail.

---

## 1. Business Modules (Bounded Contexts)

The domain is small and cohesive. Two bounded contexts are identified.

### BC-1 — Identity & Access
Responsible for establishing *who* an actor is and whether they are permitted to act.
- Concepts: credentials, authentication, access session, ownership identity.
- Boundary: knows about the acting user's identity; does not know about contact business data beyond the ownership link.

### BC-2 — Address Book (Contact Management)
The core domain. Responsible for the lifecycle and querying of contact records.
- Concepts: contact, ownership stamp, search, filtering, listing.
- Boundary: consumes an authenticated identity from BC-1 but owns all contact business rules.

> The core domain is **BC-2 (Address Book)**. **BC-1 (Identity & Access)** is a supporting context — necessary, but not where the product's business value lives.

---

## 2. Business Entities

Entities have identity and a lifecycle.

### E-1 — Contact
The central entity. Represents a person recorded in the address book.
- **Identity:** a unique contact identifier (conceptual, not a DB column).
- **Attributes (business-level):** name, phone, email, website, gender, age, nationality.
- **Ownership:** carries a reference to the User who created it (the *owner*).
- **Lifecycle:** Created → (Viewed / Updated) → Deleted.

### E-2 — User
An authenticated actor who owns and manages contacts.
- **Identity:** a unique user identity.
- **Business role:** creator/owner of contacts; the subject of authentication.
- **Note:** In the current scope only pre-provisioned users exist (via seeding); self-registration is not a domain concern (see Assumptions).

### E-3 — Access Token *(supporting, BC-1)*
Represents proof of an authenticated session for a User.
- **Lifecycle:** Issued → Presented (on each request) → Revoked/Expired.
- Treated as an entity because it has identity and a lifecycle, though it is a supporting concept.

---

## 3. Value Objects

Value objects are defined by their attributes, are immutable, and have no independent identity.

| Value Object | Description | Business Constraints |
|--------------|-------------|----------------------|
| **PersonName** | The contact's display name. | Required, non-empty text. |
| **PhoneNumber** | Contact phone. | Required; must conform to a valid phone format. |
| **EmailAddress** | Contact email (and User login identifier). | Required; must be a well-formed email. |
| **WebsiteUrl** | Contact website. | Must be a valid URL (required-ness pending clarification — see Assumptions). |
| **Gender** | Contact gender classification. | One of an allowed set (e.g., Male / Female / Other — exact set pending clarification). |
| **Age** | Contact age. | Whole number within an allowed range (bounds pending clarification). |
| **Nationality** | Contact's nationality. | Required; free-text or controlled list pending clarification. |
| **Credentials** | Email + secret used to authenticate a User. | Must match a known User to succeed. |
| **OwnershipStamp** | The link recording *which User created a Contact*. | Assigned at creation from the authenticated User; immutable thereafter; never client-supplied. |
| **SearchCriteria** | A keyword applied across name, email, phone. | Partial, case-insensitive matching. |
| **FilterCriteria** | Combination of gender, nationality, and age range (min/max). | Multiple filters combine (AND). |
| **PageRequest** | A request for a slice of results. | Page number + page size (default configurable). |

---

## 4. Aggregates

### AGG-1 — Contact Aggregate *(core, BC-2)*
- **Aggregate Root:** Contact.
- **Contained value objects:** PersonName, PhoneNumber, EmailAddress, WebsiteUrl, Gender, Age, Nationality, OwnershipStamp.
- **Invariants:**
  - A Contact must always carry a valid OwnershipStamp referencing an existing User.
  - The OwnershipStamp is set once (at creation) and never changes.
  - All required value objects must be valid for the Contact to exist.
- **Consistency boundary:** A single Contact is created, modified, and deleted as one unit.

### AGG-2 — User Aggregate *(supporting, BC-1)*
- **Aggregate Root:** User.
- **Associated concepts:** Credentials, issued Access Tokens.
- **Invariants:**
  - A User's identity is the sole source of a Contact's ownership.
  - Access Tokens are only valid while associated with an active User.

> The two aggregates reference each other **only by identity** (a Contact references a User's identity via OwnershipStamp), never by embedding the other aggregate — preserving aggregate boundaries.

---

## 5. Business Actors

| Actor | Type | Description |
|-------|------|-------------|
| **Authenticated User** | Primary (human, via SPA) | Logs in, then creates, views, searches, filters, updates, and deletes contacts. The only actor able to perform contact operations. |
| **Guest / Anonymous** | Primary (human) | An un-authenticated visitor. Can only attempt to authenticate (login). Has no access to contact data. |
| **System (Authentication Guardian)** | Secondary (system) | Verifies presented tokens on each protected operation and grants or denies access. |
| **System (Seeder / Provisioner)** | Secondary (system) | Populates initial sample contacts and at least one test User for first-run readiness. |

> Scope currently assumes a **single user role** — no Administrator or privileged actor is defined (see Assumptions).

---

## 6. Business Workflows

### WF-1 — Authenticate (Login)
1. Guest submits credentials (email + secret).
2. System validates the credentials against a known User.
3. On success, an Access Token is issued and returned; the Guest becomes an Authenticated User.
4. On failure, access is denied.

### WF-2 — Record a New Contact
1. Authenticated User provides contact details (name, phone, email, website, gender, age, nationality).
2. The system validates the details against business constraints.
3. The OwnershipStamp is applied automatically from the acting User.
4. A new Contact is added to the Address Book.

### WF-3 — Browse / Search / Filter Contacts
1. Authenticated User requests the contact list, optionally providing SearchCriteria, FilterCriteria, and a PageRequest.
2. The system applies search and filters, then returns a paginated slice with listing metadata.

### WF-4 — View a Single Contact
1. Authenticated User requests one Contact by its identity.
2. The system returns the Contact's full details, or reports that it does not exist.

### WF-5 — Revise a Contact
1. Authenticated User submits changed details for an existing Contact.
2. The system validates the changes.
3. Editable attributes are updated; the OwnershipStamp remains unchanged.
4. The revised Contact is returned, or a not-found condition is reported.

### WF-6 — Remove a Contact
1. Authenticated User requests deletion of a Contact by identity.
2. The system removes it and confirms success, or reports that it does not exist.

### WF-7 — Provision Initial Data
1. The Provisioner creates at least one test User.
2. It generates a realistic set (~50) of Contacts, each with a valid OwnershipStamp.

> **Open workflow question:** Whether WF-3/WF-4/WF-5/WF-6 operate over *all* contacts or only the acting User's *own* contacts is unresolved (carried from requirements OQ-1). Listed as an assumption below.

---

## 7. Domain Events

Business-meaningful occurrences (past tense), independent of implementation.

| Event | Trigger (Workflow) | Business Meaning |
|-------|--------------------|------------------|
| **UserAuthenticated** | WF-1 | A User successfully proved their identity and a session began. |
| **AuthenticationFailed** | WF-1 | An authentication attempt was rejected. |
| **ContactRecorded** | WF-2 | A new Contact entered the Address Book under a User's ownership. |
| **ContactRevised** | WF-5 | An existing Contact's details were changed. |
| **ContactRemoved** | WF-6 | A Contact was removed from the Address Book. |
| **ContactViewRequested** *(minor)* | WF-3, WF-4 | A User queried contact data (read activity; may be relevant for logging/audit). |
| **SessionEnded** *(conditional)* | Logout / token revocation | A User's session was terminated. *Only relevant if logout is confirmed in scope (requirements OQ-2).* |
| **InitialDataProvisioned** | WF-7 | The system was seeded with a test User and sample Contacts. |

> These are conceptual events describing *what happened in the business*. No claim is made about publishing, storing, or reacting to them technically.

---

## 8. Ubiquitous Language (Business Terminology)

| Term | Definition |
|------|------------|
| **Address Book** | The core domain: the collection of all Contact records managed by the system. |
| **Contact** | A recorded person, described by name, phone, email, website, gender, age, and nationality. |
| **User** | An authenticated actor who owns and manages Contacts. |
| **Guest** | An un-authenticated visitor able only to attempt login. |
| **Owner** | The User who created a given Contact. Ownership never transfers. |
| **OwnershipStamp** | The immutable link recording which User created a Contact; assigned automatically, never supplied by the client. |
| **Credentials** | The email and secret a User presents to authenticate. |
| **Access Token** | Proof of an authenticated session, presented on each protected action. |
| **Authenticate / Login** | The act of proving identity to obtain an Access Token. |
| **Search Criteria** | A keyword matched (partially, case-insensitively) against name, email, and phone. |
| **Filter Criteria** | Constraints on gender, nationality, and age range, combinable together. |
| **Age Range** | A minimum and/or maximum age used to filter Contacts. |
| **Page Request** | A request for a specific slice of the contact list (page number + size). |
| **Listing** | A paginated set of Contacts returned with navigation metadata. |
| **Revise** | To change the editable details of an existing Contact. |
| **Remove** | To delete a Contact from the Address Book. |
| **Provisioning / Seeding** | Populating the system with an initial test User and sample Contacts. |

---

## 9. Assumptions (Unclear Concepts — Not Guessed)

These business concepts are ambiguous in the source material and are recorded as assumptions rather than resolved:

- **AS-1 — Ownership vs. visibility:** It is unclear whether a User may browse/view/revise/remove *all* Contacts or only those they *own*. Treated here as an open ownership-scope question (requirements OQ-1); the domain model supports either interpretation.
- **AS-2 — Single role:** Assumed one User role only; no Administrator or elevated actor exists.
- **AS-3 — No self-registration:** Users are pre-provisioned; account creation is not part of the domain.
- **AS-4 — Gender set:** The exact allowed Gender values are unconfirmed (assumed Male / Female / Other).
- **AS-5 — Age bounds:** The valid Age range is unspecified.
- **AS-6 — Nationality form:** Whether Nationality is free text or a controlled vocabulary is unconfirmed.
- **AS-7 — Website requiredness:** Whether WebsiteUrl is mandatory or optional is unconfirmed.
- **AS-8 — Session termination:** Whether an explicit logout / SessionEnded concept exists is unconfirmed (requirements OQ-2).
- **AS-9 — Contact uniqueness:** Whether two Contacts may share the same email/phone is unconfirmed; no uniqueness rule is assumed.
- **AS-10 — Removal permanence:** Assumed that removal is permanent (no recoverable/archived state defined).

---

### Note on Boundaries

This document deliberately excludes database schema, API endpoints, response formats, and any Laravel/implementation detail. Those belong to later design phases and should be derived from — and remain consistent with — the ubiquitous language established here.
