# AI Usage

This documents how AI tooling was used to build the **backend** of the Address Book Management System, and — honestly — where it helped and where it needed correction.

**Tool:** Claude Code (Claude Opus 4.8) used as a pair-programmer in the terminal/IDE.

---

## 1. Where AI was used

| Area | How AI was used |
|------|-----------------|
| Reading the spec | Summarizing the design docs (PRD, architecture, module contracts, Golden Module) and reconciling contradictions between them before any code was written. |
| Scaffolding | Generating files via `php artisan make:*` (model, migration, requests, resources, controller, service, seeder, tests), then filling in the implementation. |
| Address Book module | Writing the `Contact` model, migration, Form Requests, `ContactService` (CRUD + search/filter/pagination + ownership stamping), controller, resources, factory, and seeder. |
| Tests | Writing the feature + unit tests (happy paths, validation, auth, not-found, search/filter/pagination) — 29 module tests, full suite 48 green. |
| Documentation | Producing the module-wise API handover docs (`api-doc/auth.md`, `api-doc/address-book.md`), this README, and the agent-context additions to `CLAUDE.md`. |

AI did **not** make the architectural decisions unilaterally: the visibility scope (OQ-1) and the `website` requiredness (OQ-11) were surfaced as explicit questions and decided by a human before implementation.

---

## 2. An approach that worked well

**"Read the reference implementation first, then build strictly by analogy — and escalate genuinely architectural gaps instead of guessing."**

Concretely, before writing a line of the module, the AI was directed to read the **Golden Module** (the already-built Auth slice), the **Module-B contract**, and the **guardrails**, and to mirror the exact file layout, namespaces, envelope, and layer responsibilities. The one High-impact open question that couldn't be inferred (OQ-1 — see-all vs. own-only visibility) was raised as a direct question rather than silently resolved.

Why it worked: the module landed consistent with the existing codebase on the first pass — same response envelope, same test structure, same naming — with **48 tests passing and Pint clean**, and no rework caused by an unauthorized architectural assumption. Lower-impact unknowns (age range, gender set, page size) were implemented as **isolated, flagged defaults** (constants on the model, a `config/` file) so they can change in one place.

---

## 3. Where AI was wrong / suboptimal — and how it was caught

**Incorrect output: Form Request `authorize()` left at the stub default.**

`php artisan make:request` scaffolds every Form Request with:

```php
public function authorize(): bool
{
    return false;
}
```

If shipped as generated, **every** create/update/list request would have been rejected with `403 Forbidden` before validation ever ran — the endpoints would appear "broken" despite correct routing and logic. An AI that only fills in `rules()` and leaves the rest of the stub untouched will ship this bug.

**How it was caught:** knowing that (a) these routes are already protected by the `auth:sanctum` guard at the route layer, so per-request authorization there should simply be `true`, and (b) the feature tests assert `201`/`200`, which would have surfaced as `403` on the first run. All three contact Form Requests were changed to `return true`, and the auth guard remains the single access-control point.

**A second, subtler catch (documentation).** The first draft of `api-doc/auth.md` presented `login`, `me`, and `logout` as the authentication surface without distinguishing what the spec actually mandates. Verifying against `PRD.md`/`Idea.md` showed that **only `login` is a written requirement**; `logout` is merely *implied* by "revoked token → 401" (tracked as open question OQ-2) and `me` is a Golden-Module convenience addition not named in the spec at all. The lesson reinforced: **reconcile generated documentation against the source-of-truth docs**, not against the code's current behavior alone.

---

## 4. Guardrails applied to the AI

- Every AI-written PHP change was run through `vendor/bin/pint` and the focused test suite before being considered done.
- The AI was scoped to the Address Book module and explicitly told **not** to modify the Auth module or shared-kernel contracts.
- Ownership (`created_by`) was verified — by test — to be stamped only server-side and to ignore any client-supplied value.
