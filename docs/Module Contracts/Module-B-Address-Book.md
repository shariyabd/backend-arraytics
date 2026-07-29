# Module B — Address Book (Contact Management)

**Type:** Core context
**Owns:** The Contact aggregate and its lifecycle (create, read, update, delete, search, filter, paginate).
**Consumes:** Only the authenticated `UserId` from Module A. Follows every Golden Module convention.
See shared conventions in [README.md](README.md).

---

## 1. Public Operations

| Operation | Method & Path | Access | Purpose |
|-----------|---------------|--------|---------|
| `index` | `GET /api/v1/contacts` | Protected | Paginated list with search, filters, optional sort. |
| `store` | `POST /api/v1/contacts` | Protected | Create a contact; owner stamped from identity. |
| `show` | `GET /api/v1/contacts/{id}` | Protected | Retrieve one contact. |
| `update` | `PUT/PATCH /api/v1/contacts/{id}` | Protected | Update editable fields; owner unchanged. |
| `destroy` | `DELETE /api/v1/contacts/{id}` | Protected | Delete a contact. |

---

## 2. Request DTOs

**StoreContactRequest**
```
{
  name:        string (required, max 255)
  phone:       string (required, valid phone format)
  email:       string (required, valid email)
  website:     string (required*, valid URL)             // *requiredness pending OQ-11
  gender:      Enum{ Male, Female, Other } (required)    // exact set pending OQ-4
  age:         integer (required, within allowed range)  // bounds pending OQ-3
  nationality: string (required)                         // free-text pending OQ-5
}
```
> **By contract, `created_by`/owner is NOT accepted here** — it is stamped server-side from the authenticated identity.

**UpdateContactRequest**
```
{ same editable fields as StoreContactRequest }          // owner is immutable and absent
```

**ListContactsQuery** (query string for `index`)
```
{
  search?:      string,               // matches name, email, phone (partial, case-insensitive)
  gender?:      Enum{ Male, Female, Other },
  nationality?: string,
  min_age?:     integer,
  max_age?:     integer,
  sort?:        string,               // optional; allowed columns TBD
  per_page?:    integer,              // default configurable (see Module C)
  page?:        integer
}
```
Filters are combinable (AND).

---

## 3. Response DTOs

**ContactResource** (single contact)
```
{
  id:          integer,
  name:        string,
  phone:       string,
  email:       string,
  website:     string,
  gender:      string,
  age:         integer,
  nationality: string,
  created_by:  integer,   // UserId (read-only, exposed for reference)
  created_at:  timestamp (ISO-8601)
}
```

**ContactCollection** (list) — a collection of `ContactResource` plus pagination meta:
```
data: [ ContactResource, ... ],
meta: {
  current_page: integer,
  per_page:     integer,
  total:        integer,
  last_page:    integer
}
```
> The pagination `meta` shape is the shared `PaginationMeta` contract (Module C) so all list endpoints match.

---

## 4. Published Events

| Event | Payload | Meaning |
|-------|---------|---------|
| `ContactCreated` | `{ contactId, ownerId }` | A new contact was recorded. |
| `ContactUpdated` | `{ contactId, ownerId }` | An existing contact was revised. |
| `ContactDeleted` | `{ contactId, ownerId }` | A contact was removed. |

---

## 5. Consumed Events

None required for current scope. (May consume `UserLoggedOut`/user-lifecycle events later; not part of this contract version.)

---

## 6. Error Contracts

| Condition | Status | `message` | `errors` |
|-----------|--------|-----------|----------|
| Not authenticated | 401 | `Unauthenticated.` | null |
| Validation failure | 422 | `The given data was invalid.` | field → messages |
| Contact not found | 404 | `Resource not found.` | null |
| Forbidden (own-only scope, if OQ-1 = own-only) | 403 | `This action is unauthorized.` | null |
| Unexpected failure | 500 | `Server error.` | null |

> **OQ-1 dependency:** whether `show`/`update`/`destroy` on another user's contact returns 403 (own-only) or succeeds (see-all) is resolved as a service-layer rule; the error contract above already reserves 403 for the own-only outcome.
