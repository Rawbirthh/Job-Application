---
name: laravel-backend
description: Implement secure, maintainable Laravel routes, controllers, Form Requests, Policies, models, queries, migrations, and business logic
compatibility: opencode
---

## Before coding

Read `AGENTS.md`; inspect the installed Laravel/PHP versions, Composer dependencies, provider registration conventions, existing domain models, routes and middleware, test style, and any custom role/permission implementation. Never assume a package, role name, table schema, or `hasRole()` method.

## Request lifecycle

- Route/group middleware enforces authentication and broad access boundaries.
- Policies express per-model/action authorization; call `$this->authorize()` before protected model work where applicable.
- Use a dedicated Form Request for user input on write endpoints. Validate the actual allowed fields and nested data. Never trust front-end validation or use `$request->all()` for writes.
- Controllers coordinate authorization, validated input, domain operations, and response; delegate only when the logic/query merits a layer.
- For Inertia pages, return only intended props and avoid sensitive columns; writes typically redirect to named routes with appropriate flash messages.

## Eloquent and database

- Declare `$fillable` on models intended for mass assignment; explicitly map validated fields and server-owned fields such as owner IDs.
- Type relationships and casts; add scopes for repeated meaningful filters.
- Avoid N+1 with targeted eager loading. Select only needed fields, ensure eager-loaded relation keys needed for matching are included, and use `withCount()` for counts where appropriate.
- Paginate list endpoints. Do not load unbounded rows into PHP. Use bounded lookups for intentionally small sets and `chunkById()`/streaming for bulk jobs.
- Ensure FK and query-oriented indexes exist without duplicating indexes. Evaluate composite indexes against real filter/order patterns; use `EXPLAIN` for important queries where possible.
- Migrations should be reversible where reasonable, consistent with deployed schema history, and reviewed for locking/backfill risk. Never edit an applied production migration to change production schema.
- Use transactions for logically atomic multi-write operations. Consider post-commit dispatch for events/jobs and failure behavior of external systems.
- Apply SoftDeletes when consistent with lifecycle, audit, retention, privacy, and relationship semantics; do not assume every table should be soft-deleted without review.

## Dynamic authorization

Treat role/permission mapping as domain data, not hardcoded role strings. Trace how roles/permissions are assigned, cached, queried, and checked. Prevent privilege escalation by validating who can grant roles, restricting assignable permissions, and checking the target subject/resource. Do not rely on a hidden button or React condition as security.

## Tests

Write feature tests for protected request flows and data persistence, with tests for guest, unauthorized role/permission, ownership, validation, and happy path only where those cases apply. Add regression tests for bugs. Use Pest style if installed for a new project; otherwise match current PHPUnit/Pest convention. Never claim results without executing the commands.
