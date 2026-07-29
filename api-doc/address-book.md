# API Handover — Address Book (Contact) Module

**Module:** B — Address Book (Contact Management) · **Type:** Core context
**Base path:** `/api/v1` · **Auth:** Bearer token (Laravel Sanctum)
**Status:** Implemented, tested (48 passing), Pint clean.

This document is the implementation-accurate contract for the Contact API. It is complete enough to consume or re-implement the module without further clarification.

---

## 1. Overview

A secure, paginated, searchable REST API for managing address-book contacts. Every contact is owned by the authenticated user who created it (`created_by`), stamped server-side and never accepted from the client. Responses are JSON only and always use the uniform envelope.

- All endpoints require a valid bearer token (`auth:sanctum`). There is no public contact endpoint.
- **Visibility scope:** see-all — any authenticated user may read/update/delete any contact. `created_by` is audit metadata, not an access boundary. (No `403` path in this version.)

---

## 2. Authentication & Authorization

| Requirement | Detail |
|-------------|--------|
| Scheme | `Authorization: Bearer <token>` |
| Token source | `POST /api/v1/login` (Auth module) returns `data.token`. |
| Guard | `auth:sanctum` on every contact route. |
| Missing/invalid token | `401 Unauthenticated.` |
| Ownership | `created_by` is set from the token identity on create; immutable on update; never a request field. |

---

## 3. Uniform Response Envelope

**Success**
```json
{ "success": true, "message": "string", "data": <payload>|null }
```

**Error**
```json
{ "success": false, "message": "string", "errors": { "field": ["message"] } | null }
```

All requests should send `Accept: application/json`. Write endpoints send `Content-Type: application/json`.

---

## 4. Endpoints

| Operation | Method & Path | Success | Route name |
|-----------|---------------|---------|------------|
| List | `GET /api/v1/contacts` | 200 | `api.v1.contacts.index` |
| Create | `POST /api/v1/contacts` | 201 | `api.v1.contacts.store` |
| Show | `GET /api/v1/contacts/{contact}` | 200 | `api.v1.contacts.show` |
| Update | `PUT\|PATCH /api/v1/contacts/{contact}` | 200 | `api.v1.contacts.update` |
| Delete | `DELETE /api/v1/contacts/{contact}` | 200 | `api.v1.contacts.destroy` |

`{contact}` is the integer contact `id`.

---

## 5. Data Model — Contact fields

| Field | Type | Notes |
|-------|------|-------|
| id | integer | Primary key (read-only). |
| name | string | |
| phone | string | |
| email | string | |
| website | string\|null | Optional. |
| gender | string | One of `Male`, `Female`, `Other`. |
| age | integer | |
| nationality | string | Free-text. |
| created_by | integer | Owning `UserId` (read-only, audit metadata). |
| created_at | string | ISO-8601 timestamp. |

---

## 6. Create — `POST /api/v1/contacts`

### Request body

| Field | Type | Rules |
|-------|------|-------|
| name | string | required, max 255 |
| phone | string | required, max 30, matches `^\+?[0-9\s\-()]{7,}$` |
| email | string | required, valid email, max 255 |
| website | string\|null | optional; valid URL, max 255 (when present) |
| gender | string | required, in `Male\|Female\|Other` |
| age | integer | required, 1–150 |
| nationality | string | required, max 255 |

> `created_by` is **not** accepted. Any client-supplied value is ignored and overwritten with the authenticated user's id.

**Example**
```json
{
  "name": "Ada Lovelace",
  "phone": "+1234567890",
  "email": "ada@example.com",
  "website": "https://ada.example.com",
  "gender": "Female",
  "age": 36,
  "nationality": "United Kingdom"
}
```

**Response — 201**
```json
{
  "success": true,
  "message": "Contact created.",
  "data": {
    "id": 1,
    "name": "Ada Lovelace",
    "phone": "+1234567890",
    "email": "ada@example.com",
    "website": "https://ada.example.com",
    "gender": "Female",
    "age": 36,
    "nationality": "United Kingdom",
    "created_by": 7,
    "created_at": "2026-07-29T16:57:37+00:00"
  }
}
```

---

## 7. List — `GET /api/v1/contacts`

### Query parameters

| Param | Type | Rules | Default |
|-------|------|-------|---------|
| search | string | max 255; partial, case-insensitive match on name, email, phone | — |
| gender | string | in `Male\|Female\|Other` | — |
| nationality | string | max 255; exact match | — |
| min_age | integer | 1–150 | — |
| max_age | integer | 1–150, `>= min_age` | — |
| per_page | integer | 1–100 | 15 |
| page | integer | `>= 1` | 1 |

Filters and search are combinable (ANDed) and applied **before** pagination.

**Response — 200**
```json
{
  "success": true,
  "message": "Contacts retrieved.",
  "data": {
    "data": [ { "id": 1, "name": "Ada Lovelace", "...": "..." } ],
    "meta": {
      "current_page": 1,
      "per_page": 15,
      "total": 50,
      "last_page": 4
    }
  }
}
```

`data.meta` follows the shared `PaginationMeta` contract.

---

## 8. Show — `GET /api/v1/contacts/{contact}`

**Response — 200**
```json
{ "success": true, "message": "Contact retrieved.", "data": { "id": 1, "...": "..." } }
```
Missing id → `404`.

---

## 9. Update — `PUT|PATCH /api/v1/contacts/{contact}`

Partial updates supported: send only the fields to change. Each field uses the same rules as Create but is optional (`sometimes`); when present it must still be valid (e.g. `name` cannot be sent empty).

`created_by` and `created_at` are never changed.

**Example**
```json
{ "name": "Ada King", "age": 37 }
```

**Response — 200**
```json
{ "success": true, "message": "Contact updated.", "data": { "id": 1, "name": "Ada King", "...": "..." } }
```

---

## 10. Delete — `DELETE /api/v1/contacts/{contact}`

**Response — 200**
```json
{ "success": true, "message": "Contact deleted.", "data": null }
```
Missing id → `404`.

---

## 11. Error Responses

| Condition | Status | message | errors |
|-----------|--------|---------|--------|
| No/invalid token | 401 | `Unauthenticated.` | null |
| Validation failure | 422 | `The given data was invalid.` | field → messages |
| Contact not found | 404 | `Resource not found.` | null |
| Unexpected failure | 500 | `Server error.` (internals hidden in production) | null |

**422 example**
```json
{
  "success": false,
  "message": "The given data was invalid.",
  "errors": {
    "email": ["The email field must be a valid email address."],
    "gender": ["The selected gender is invalid."]
  }
}
```

---

## 12. Business Rules

- **BR-B1:** All endpoints require authentication (bearer token).
- **BR-B2:** `created_by = auth()->id()`, set only in the service on create; never client-supplied.
- **BR-B3:** `created_by` is immutable on update.
- **BR-B4:** Search matches name/email/phone (partial, case-insensitive); filters combinable (AND); applied before pagination.
- **BR-B5:** Responses expose only the fields in §5; nothing sensitive or internal.
- **BR-B6:** Missing contact → 404; validation failure → 422 with field messages.
- **BR-B7:** Visibility is see-all (OQ-1); `created_by` is audit metadata, not an access boundary.

---

## 13. Module-Specific Standards

- Layered flow: Route → Form Request → Controller → Service → Model → Resource. Controllers are thin; all business logic and ownership stamping live in `ContactService`.
- Output always via `ContactResource` / `ContactCollection` through the shared `ApiResponse` envelope; failures via the central exception handler.
- Configurable pagination: `config/contacts.php` → `default_per_page` (15), `max_per_page` (100).
- Persistence: `contacts` table with indexes on `name`, `email`, `phone`; seeder produces ~50 contacts referencing valid users.

### Resolved open questions (defaults, isolated in validation/config/service)

| ID | Decision |
|----|----------|
| OQ-1 | See-all visibility (no 403). |
| OQ-3 | Age range 1–150. |
| OQ-4 | Gender: `Male`, `Female`, `Other`. |
| OQ-5 | Nationality: free-text (max 255). |
| OQ-6 | Page size: default 15, max 100. |
| OQ-11 | Website: optional. |
