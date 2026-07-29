# Module C — Platform / Shared Kernel

**Type:** Foundation (not an HTTP module)
**Owns:** The primitives every other module's contract depends on — response envelope, pagination, and the error taxonomy.
**Dependency rule:** Depended upon by all; depends on none.
See shared conventions in [README.md](README.md).

---

## 1. Public Operations

None (no endpoints). Exposes shared contracts only.

---

## 2. Request DTOs

**PaginationParams** (embedded by any listing operation)
```
{ per_page?: integer (default configurable), page?: integer }
```

---

## 3. Response DTOs

**SuccessEnvelope**
```
{ success: true, message: string, data: <payload>|null }
```

**ErrorEnvelope**
```
{ success: false, message: string, errors: object|null }
```

**PaginationMeta** (embedded by any paginated response)
```
{ current_page: integer, per_page: integer, total: integer, last_page: integer }
```

---

## 4. Published Events

None.

---

## 5. Consumed Events

None.

---

## 6. Error Contracts — Canonical Status Taxonomy

The single source of truth all modules map their failures onto:

| Category | Status | Standard `message` |
|----------|--------|--------------------|
| Success | 200 | operation-specific |
| Created | 201 | operation-specific |
| Validation error | 422 | `The given data was invalid.` |
| Unauthenticated | 401 | `Unauthenticated.` |
| Forbidden | 403 | `This action is unauthorized.` |
| Not found | 404 | `Resource not found.` |
| Rate limited | 429 | throttle message |
| Server error | 500 | `Server error.` (internals hidden in production) |
