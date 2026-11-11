---
description: Create an evidence-based feature specification and stop for user approval
---

Load the `feature-architecture` skill. Feature request: $ARGUMENTS

Act as the feature architect. The root `AGENTS.md` is the shared engineering baseline; do not reread it if already present in context. Inspect only the repository areas relevant to this feature: nearby routes, models, policies, Form Requests, migrations, tests, Inertia pages/components, and existing specs. Avoid broad scans and repeated inspection. Check `git status` before any documentation edit and preserve unrelated user changes.

Create or update one focused spec under `docs/specs/` using a descriptive feature slug. Include:

- Goal, non-goals, user flows, acceptance criteria, and assumptions/questions.
- Routes, middleware, custom role/permission checks, and Policy actions.
- Form Requests and validation rules.
- Models, relationships, migration, constraints, foreign keys, indexes, and deletion semantics.
- Query shape, selected columns, eager loading, pagination, and scale considerations.
- Service/Repository decision with a reason; prefer no extra layer for simple CRUD.
- Inertia page props, React forms/components, shared types, and UI states.
- Test matrix: happy path, validation, applicable guest/permission/owner/non-owner cases, and regression cases.
- Security/performance risks, rollout/compatibility notes, implementation sequence, and actual verification commands available in the repo.

Do not invent domain rules, roles, schema, dependencies, or package choices. Ask only questions that block a safe design; label non-blocking assumptions. Do not write application code, create migrations, install packages, or run destructive commands. Present the spec path and concise key decisions, then STOP. Implementation requires explicit user approval in a later `/develop` request.
