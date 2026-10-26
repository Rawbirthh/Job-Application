---
name: feature-architecture
description: Plan Laravel and Inertia features with explicit routes, authorization, schema, data flow, tests, and approval gates
compatibility: opencode
---

## Purpose

Produce implementable, evidence-based designs without inventing the product domain. Inspect the real repository and use the user's feature description as the source of requirements.

## Process

1. Read `AGENTS.md`; inspect related routes, models, Policies, Form Requests, migrations, components, tests, and existing docs.
2. Clarify the observable goal and acceptance criteria. Ask only about requirements that materially block design; label non-blocking assumptions.
3. Map actors/user flows, route names and middleware, custom dynamic roles/permissions, model actions/Policies, validation, schema and data ownership, query/indices/pagination, service/repository decisions, Inertia props, UI states, and tests.
4. Consider authorization bypass, IDOR/ownership, mass assignment, sensitive fields, race conditions, duplicate submissions, transactions, background work, deletion/retention semantics, and scale risks as applicable.
5. Make the plan concrete and minimal. Do not add repositories, services, packages, queues, caches, or abstractions without a reason.
6. For `/architect`, write/update a spec in `docs/specs/` and stop for explicit approval; never implement app code in that command.

## Spec template

- Goal / non-goals
- User-visible behavior and acceptance criteria
- Assumptions / questions
- Routes and access boundary
- Policy actions and dynamic role/permission resolution
- Form Requests and validation
- Models, relationships, migration, constraints, and indexes
- Query/data payload shape, eager loading, and pagination
- Service/Repository decision with rationale
- React/Inertia page/form/component plan
- Relevant tests and security cases
- Risks, rollout/compatibility notes, and verification commands
- Ordered implementation plan

A spec is not approval. Stop at the approval gate.
