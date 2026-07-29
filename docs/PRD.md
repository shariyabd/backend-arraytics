# Product Requirements Document (PRD)

## Backend — Address Book Management System (Laravel REST API)

**Version:** 1.0
**Project:** Address Book Management System
**Backend Framework:** Laravel 12 (or latest stable)
**Architecture:** RESTful API
**Database:** MySQL
**Authentication:** Laravel Sanctum (Token-based)

---

# 1. Product Overview

The backend provides a secure, scalable, and maintainable REST API for managing Address Book records.

The backend is responsible for:

* Authentication
* Authorization
* Business logic
* Validation
* CRUD operations
* Searching
* Filtering
* Pagination
* Data persistence

The backend should expose only REST APIs. It does not render any frontend views.

---

# 2. Goals

The API should provide:

* Secure authentication
* Complete CRUD functionality
* Server-side validation
* Consistent API responses
* Search & filtering
* Pagination
* Proper error handling
* Clean architecture
* Easily maintainable codebase

---

# 3. Non Goals

The backend will NOT:

* Render Blade templates
* Contain frontend logic
* Accept `created_by` from client input
* Expose unnecessary database fields
* Mix business logic inside controllers

---

# 4. Architecture

Recommended layered architecture:

```
Client (React SPA)
        │
 REST API (Routes)
        │
 Controllers
        │
 Form Requests
        │
 Service Layer (optional but preferred)
        │
 Eloquent Models
        │
 Database
```

Responsibilities:

* **Routes** → Endpoint definitions
* **Controllers** → Handle HTTP requests/responses
* **Form Requests** → Validation & authorization
* **Services** → Business logic
* **Models** → Database interaction
* **Resources** → API response transformation

---

# 5. Authentication

Authentication should use **Laravel Sanctum** with token-based authentication.

### Public Endpoint

* Login

### Protected Endpoints

Require a valid bearer token for all address book operations.

Behavior:

* Invalid token → 401 Unauthorized
* Missing token → 401 Unauthorized
* Expired/revoked token → 401 Unauthorized

---

# 6. User Authentication Flow

1. User submits email/password.
2. Credentials are validated.
3. Token is generated.
4. Token returned to client.
5. Client includes bearer token on every request.
6. Middleware authenticates request.
7. Authenticated user becomes available via `Auth::user()`.

---

# 7. Authorization Rules

All CRUD operations require authentication.

`created_by` must always be assigned from the authenticated user:

```
created_by = auth()->id();
```

The client must never be allowed to send or override this value.

---

# 8. Address Book Entity

The Address Book resource contains:

| Field       | Type                 |
| ----------- | -------------------- |
| id          | Integer              |
| name        | String               |
| phone       | String               |
| email       | String               |
| website     | String               |
| gender      | Enum/String          |
| age         | Integer              |
| nationality | String               |
| created_by  | Integer (FK/User ID) |
| created_at  | Timestamp            |

---

# 9. CRUD Functional Requirements

## Create Contact

Accepts:

* Name
* Phone
* Email
* Website
* Gender
* Age
* Nationality

Automatically sets:

* created_by

Returns:

* Created resource
* Success message
* HTTP 201

---

## Read Contacts

Returns paginated list.

Supports:

* Search
* Filters
* Sorting (optional)
* Pagination

---

## View Single Contact

Returns complete details of one contact.

404 if record does not exist.

---

## Update Contact

Updates editable fields only.

`created_by` remains unchanged.

Returns updated resource.

---

## Delete Contact

Deletes selected record.

Returns success message.

404 if record no longer exists.

---

# 10. Validation Rules

Validation should be implemented using **Form Request** classes.

| Field       | Validation                       |
| ----------- | -------------------------------- |
| Name        | Required, string                 |
| Phone       | Required, valid phone format     |
| Email       | Required, valid email            |
| Website     | Required, valid URL              |
| Gender      | Required, allowed values         |
| Age         | Required, integer, allowed range |
| Nationality | Required                         |

Validation failures should return:

* HTTP 422
* Field-specific error messages

---

# 11. Search

Support keyword search across:

* Name
* Email
* Phone

Search should be performed server-side.

Behavior:

* Partial matches supported
* Case-insensitive where supported by DB collation

---

# 12. Filtering

Supported filters:

### Gender

Examples:

* Male
* Female
* Other

---

### Nationality

Filter by nationality.

---

### Age Range

Support:

* Minimum age
* Maximum age

Multiple filters should be combinable.

---

# 13. Pagination

Server-side pagination.

Response should include:

* Current page
* Total records
* Per page
* Last page
* Data collection

Default page size should be configurable.

---

# 14. API Response Format

Responses should be consistent across all endpoints.

### Success Response

```json
{
  "success": true,
  "message": "Contact created successfully.",
  "data": { ... }
}
```

### Error Response

```json
{
  "success": false,
  "message": "Validation failed.",
  "errors": {
    "email": [
      "The email field is required."
    ]
  }
}
```

---

# 15. HTTP Status Codes

| Operation        | Status |
| ---------------- | ------ |
| Success          | 200    |
| Created          | 201    |
| Validation Error | 422    |
| Unauthorized     | 401    |
| Forbidden        | 403    |
| Not Found        | 404    |
| Server Error     | 500    |

---

# 16. Error Handling

The API should gracefully handle:

* Validation failures
* Invalid authentication
* Missing resources
* Database exceptions
* Unexpected server errors

Avoid exposing stack traces or sensitive implementation details in production.

---

# 17. Database

Use MySQL.

Migration should create the `address_book` table with:

* Appropriate column types
* Timestamps
* Indexes on commonly searched fields (e.g., `name`, `email`, `phone`) to improve query performance

---

# 18. Seeder

Provide seed data for development.

Requirements:

* Create approximately 50 address book records
* Generate realistic sample data
* Create at least one test user for authentication
* Ensure seeded data references valid users for `created_by`

---

# 19. API Security

Requirements:

* Sanctum authentication
* CSRF considerations based on API usage
* Request validation
* Mass assignment protection
* Hidden sensitive model attributes
* Rate limiting on authentication endpoints (recommended)

---

# 20. API Documentation

Document:

* Authentication flow
* Required headers
* Request payloads
* Query parameters
* Response examples
* Error responses

Documentation may be provided using Postman collections, OpenAPI/Swagger, or Markdown.

---

# 21. Logging

Log unexpected application errors for debugging.

Do not log:

* Passwords
* Authentication tokens
* Sensitive user information

---

# 22. Performance Requirements

The API should:

* Use pagination instead of returning all records
* Apply filters before pagination
* Minimize unnecessary database queries
* Avoid N+1 query issues where relationships exist
* Return only required fields in responses

---

# 23. Maintainability

Follow Laravel best practices:

* Thin controllers
* Fat models/services only where appropriate
* Reusable validation
* Clear folder organization
* RESTful route naming
* Dependency injection
* Consistent coding standards

---

# 24. Suggested Folder Structure

```
app/
├── Http/
│   ├── Controllers/
│   ├── Requests/
│   ├── Resources/
│   └── Middleware/
│
├── Models/
│
├── Services/          (optional)
│
├── Policies/          (optional)
│
└── Providers/
```

---

# 25. Functional Acceptance Criteria

The backend is considered complete when:

* User can authenticate and receive a valid token.
* All CRUD endpoints require authentication.
* `created_by` is always derived from the authenticated user.
* All validation rules are enforced via Form Requests.
* Search, filtering, and pagination work correctly.
* Responses follow a consistent JSON structure.
* Proper HTTP status codes are returned.
* Seeder populates the database with approximately 50 realistic records and a test user.
* API handles common errors gracefully.
* Code follows Laravel conventions and is organized for long-term maintainability.
