# Address Book Management System

## Tech Stack

- **Backend:** Laravel (REST API)
- **Frontend:** React.js (Single Page Application)
- **Database:** MySQL

---

# Project Overview

Build an **Address Book Management System** as two separate applications:

- A decoupled **Laravel REST API**
- A **React.js Single Page Application (SPA)**

Both applications must communicate through a **token-based authentication system** using APIs.

---

# Database

## Table: `address_book`

| Column | Description |
|---------|-------------|
| `id` | Primary Key |
| `name` | Contact name |
| `phone` | Phone number |
| `email` | Email address |
| `website` | Website URL |
| `gender` | Gender |
| `age` | Age |
| `nationality` | Nationality |
| `created_at` | Timestamp |
| `created_by` | Authenticated user who created the record |

---

# Backend (Laravel API)

## Authentication

- Implement **token-based authentication** using **Laravel Sanctum**.
- Protect **all CRUD endpoints** with authentication.
- `created_by` **must always be derived from the authenticated user**.
- Never accept `created_by` from client requests.

---

## CRUD API

Provide a complete RESTful API for the `address_book` resource.

Operations include:

- Create
- Read
- Update
- Delete

---

## Validation

Use **Laravel Form Requests** for server-side validation.

### Validation Rules

- Email must be a valid email address.
- Phone must follow a valid phone format.
- Age must:
  - be numeric
  - fall within a valid range
- Website must be a valid URL.
- Gender must be one of the allowed values.
- Required fields must not be empty.

---

## Search, Filter & Pagination

### Search

Support server-side searching by:

- Name
- Email
- Phone

### Filters

Support filtering by:

- Gender
- Nationality
- Age Range

### Pagination

Return paginated responses for listing endpoints.

---

## Seeder

Create a database seeder that generates approximately **50 sample records** so the application has data on first run.

---

# Frontend (React SPA)

## Authentication

Create a login screen that:

- Authenticates the user
- Stores the authentication token
- Restricts access to CRUD pages until authenticated

---

## Address Form

Create a submission form with client-side validation that mirrors the backend validation rules.

Requirements:

- Inline validation messages
- Same validation rules as backend
- Create and Update support

---

## Data Table

Display all address book records in a table.

Features:

- Search
- Filters
- Pagination
- Edit action
- Delete action

---

## API Configuration

Handle API communication cleanly.

Requirements:

- Store API Base URL using environment variables
- Automatically attach authentication token using a request interceptor
- Do **not** hardcode URLs or authentication tokens throughout the codebase