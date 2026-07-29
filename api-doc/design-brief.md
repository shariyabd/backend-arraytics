# UI Design Brief — Address Book Management System

**Audience:** an AI/design agent tasked with designing the frontend UI **before** implementation.
**Purpose:** describe *what* to build and *why*, derived from the backend's actual behavior. This is a product + interaction spec — it is **not** the API reference. Endpoint payloads/schemas will be shared separately (`api-doc/auth.md`, `api-doc/address-book.md`) for the implementation phase.

> Design against this document. Where it constrains data, validation, states, or flows, treat those as fixed — they mirror what the backend enforces. Visual/layout/aesthetic decisions are yours.

---

## 1. Product in one paragraph

A single-page web app for managing an **address book**. An authenticated user logs in, then lands on a searchable, filterable, paginated list of contacts. They can create, view, edit, and delete contacts. Each contact captures identity + demographic details (name, phone, email, website, gender, age, nationality). It is a small, focused CRUD tool — not a CRM. The backend is a decoupled JSON REST API; this UI is a separate React SPA that talks to it with a bearer token.

**Primary goal:** let a user find and manage contacts quickly, with clear validation and predictable feedback.

---

## 2. Users, roles & access model

- **One user role.** No admin/user distinction, no permissions system. Every logged-in user has the same capabilities.
- **No self-service registration.** Users are pre-provisioned. The app has **no sign-up screen** — only login. (A seeded account exists: `test@example.com` / `password`.)
- **See-all visibility.** Any authenticated user can view, edit, and delete **any** contact. Ownership (`created_by`) is recorded as metadata but is **not** an access boundary — do not design "my contacts vs. others" separation or per-row permission states.
- **Everything except login requires authentication.** Unauthenticated access to any contact screen must route the user to login.

---

## 3. Screen inventory

Design these screens. Each entry lists purpose, key content, actions, and required states.

### 3.1 Login
- **Purpose:** authenticate and obtain a session token.
- **Fields:** email, password.
- **Actions:** Submit ("Log in").
- **States:**
  - *Default / editing.*
  - *Submitting* (disable submit, show progress).
  - *Invalid credentials* — show a single, non-specific error ("These credentials do not match our records."). Do **not** reveal whether email or password was wrong.
  - *Field validation* — email required & valid format; password required.
  - *Rate-limited* — after too many rapid attempts the server blocks further tries for ~1 minute; show a "too many attempts, try again shortly" message and disable submit.
- **On success:** store the token, redirect to the Contacts list.
- **Note:** no "forgot password," no "create account," no "remember me" backend support — omit them or mark clearly as non-functional.

### 3.2 Contacts List (home, after login)
- **Purpose:** the primary workspace — browse, search, filter, paginate, and jump into actions.
- **Content:** a table/list of contacts showing at least: name, phone, email, gender, age, nationality (website optional to show). Each row has **Edit** and **Delete** actions; row or a **View** action opens details.
- **Controls:**
  - **Search box** — one field that matches **name, email, or phone** (partial, case-insensitive).
  - **Filters** — gender (Male/Female/Other), nationality (text), age range (min age / max age). Filters and search are **combinable** (all applied together, AND logic).
  - **Pagination** — page navigation with page size; server returns `current_page`, `per_page`, `total`, `last_page`. Default page size is 15; the user may request 1–100.
  - **"Add contact"** — opens the create form.
- **States:**
  - *Loading* (initial and on any search/filter/page change).
  - *Populated* (rows + pagination).
  - *Empty — no contacts at all* (first-run friendly, prompt to add one).
  - *Empty — no results for the current search/filters* (offer "clear filters").
  - *Error* (list failed to load — retry affordance).
- **UX notes:** search/filter should feel server-driven (results reflect the whole dataset, not just the current page). Reflect active filters visibly and make them clearable. Preserve filters when paginating.

### 3.3 Contact Details (View)
- **Purpose:** read-only view of one contact's full record.
- **Content:** all fields — name, phone, email, website (linkable if present, may be empty), gender, age, nationality, and (optional) created date.
- **Actions:** Edit, Delete, Back to list.
- **States:** loading; loaded; **not found** (the contact was deleted or the id is invalid → show a "contact not found" state, not a crash).

### 3.4 Create Contact
- **Purpose:** add a new contact.
- **Form fields:** name, phone, email, website, gender, age, nationality (see §4 for rules). **Do not** include any "owner"/"created by" field — it is set by the server and must never be a form input.
- **Actions:** Save (create), Cancel.
- **States:** editing; per-field validation errors (server returns field-level messages on 422 — surface them inline); submitting; success (toast + return to list or details); auth-expired (→ login).

### 3.5 Edit Contact
- **Purpose:** update an existing contact.
- **Same form as Create**, pre-filled. Supports partial edits (changing a subset of fields is valid).
- **Owner and created date are immutable** — never editable; do not render owner as an input.
- **States:** loading the record; editing; validation errors; submitting; success; not-found (record gone).

### 3.6 Delete Contact (confirmation)
- **Purpose:** remove a contact safely. Deletes are permanent (no soft-delete/restore).
- **Pattern:** confirmation dialog before deleting.
- **States:** confirming; deleting; success (remove from list + toast); already-gone (treat "not found" gracefully — refresh the list).

### 3.7 Global: Session expiry / logout
- **Logout action** available in the app chrome (e.g., header menu) — revokes the token and returns to login.
- **Auto-handling:** if any request comes back unauthenticated (expired/revoked/missing token), clear the session and redirect to login with a brief "please log in again" message.

---

## 4. Data dictionary & validation (mirror client-side)

The UI's client-side validation should mirror the server. These are the exact server rules — replicate them for instant feedback, but the server remains the source of truth.

| Field | Type | Required | Rules to mirror |
|-------|------|----------|-----------------|
| name | text | Yes | max 255 chars |
| phone | text | Yes | max 30 chars; digits with optional leading `+`, spaces, dashes, parentheses; at least 7 characters (pattern: `^\+?[0-9\s\-()]{7,}$`) |
| email | email | Yes | valid email; max 255 |
| website | url | **No (optional)** | when provided, must be a valid URL; max 255. Empty is allowed. |
| gender | select | Yes | one of exactly `Male`, `Female`, `Other` (render as a dropdown/segmented control, not free text) |
| age | number | Yes | integer between **1 and 150** |
| nationality | text | Yes | free text; max 255 (a plain text input; not a fixed country list) |

Notes for forms:
- `gender` is a closed set of three values — use a control that can't produce anything else.
- `age` is a whole number in [1, 150] — constrain the input.
- `website` is the only optional field.
- On submit failure the server returns **per-field error messages** — design inline error slots for every field.

---

## 5. Search, filter & pagination — interaction spec

| Capability | Behavior to design for |
|-----------|------------------------|
| Search | Single query string; matches **name, email, phone**; partial and case-insensitive. |
| Filter: gender | Choose one of Male/Female/Other (plus "any"). |
| Filter: nationality | Text match. |
| Filter: age range | `min_age` and/or `max_age`; `max_age` cannot be less than `min_age` (validate this pairing in the UI). |
| Combinability | Search + all filters apply together (AND). Provide a clear "active filters" summary and a "clear all." |
| Pagination | Server-side. Show current page, total count, and total pages. Page size default 15, selectable within 1–100. |
| Persistence | Keep search/filter/page in sync (e.g., URL query params) so results are shareable/refresh-safe. |

Design for the fact that results are **whole-dataset** server responses: changing search/filter resets to page 1; the counts in pagination reflect the filtered total, not just the loaded rows.

---

## 6. Global behaviors & feedback

**Response shape (for designing feedback):** every server response carries a boolean success flag, a human-readable `message`, and either a `data` payload or a field-keyed `errors` object. Design your toasts/banners to use the `message`, and your inline field errors to use `errors`.

**Status → UX mapping:**

| Situation | Design response |
|-----------|-----------------|
| Success (create) | Success toast; go to list/details. |
| Success (update/delete) | Success toast; reflect change in the list. |
| Not authenticated / token expired | Clear session → redirect to Login with a notice. |
| Validation failure | Inline field errors from the `errors` object; keep the user on the form with their input intact. |
| Not found (view/edit/delete a missing contact) | Graceful "not found" state; offer back-to-list. |
| Rate limited (login) | "Too many attempts" message; temporarily disable submit. |
| Server error | Non-technical error banner ("Something went wrong, please try again."); never show stack traces. |

**Cross-cutting UX requirements:**
- Every list/detail/form load has explicit **loading**, **empty**, and **error** states — no blank screens.
- Destructive actions (delete) always confirm first.
- Preserve user input on validation errors.
- Auth-gate the whole app: only Login is reachable while logged out.

---

## 7. Business rules that shape the UI

- **BR-1 — Ownership is invisible input.** Never show an owner/creator picker in create/edit forms. The server assigns it. You may *display* the creator on the details view (read-only) if useful, but it is not editable.
- **BR-2 — Owner is immutable.** Editing a contact never changes who created it.
- **BR-3 — See-all.** No per-record ownership gating in the UI.
- **BR-4 — Login-only auth.** No registration, password reset, or profile management screens.
- **BR-5 — Hard delete.** No trash/restore flow.
- **BR-6 — Minimal, safe fields.** Only the fields in §4 exist on a contact; don't invent extra fields (tags, notes, avatars, addresses) — the backend won't store them.

---

## 8. Out of scope (do not design)

- Sign-up / registration, password reset, email verification.
- Roles, permissions, sharing, or team features.
- Soft delete / archive / restore, bulk actions (unless additive and clearly optional).
- Sorting is **optional** and not backed by a defined contract yet — you may design a sort affordance, but treat it as a nice-to-have that may be deferred.
- User profile/settings screens (beyond logout).
- Any field not listed in §4.

---

## 9. What to deliver from the design phase

For the implementation phase to proceed smoothly, the design output should cover:

1. **Screen designs / wireframes** for every screen in §3, including their loading/empty/error/validation states.
2. **The contact form** with all fields from §4 and inline error placement.
3. **The list experience**: table/cards, search, filter panel, active-filter summary, pagination, and both empty states.
4. **Auth flow**: login, session-expiry redirect, logout entry point.
5. **A component inventory** (buttons, inputs, selects, table/list, dialog/confirm, toast/banner, pagination, empty-state, error-state) and the interaction states for each.
6. **Navigation / information architecture** (how the user moves between list ↔ details ↔ create/edit, and where global actions live).

---

## 10. Handoff note

Once the UI is designed, the concrete API contracts — endpoints, request payloads, response schemas, exact validation messages, and error bodies — will be provided in [auth.md](auth.md) and [address-book.md](address-book.md) for implementation. Nothing in those will contradict this brief; they add the wire-level detail. Design so that wiring to a token-based JSON API with the response shape in §6 is straightforward.
