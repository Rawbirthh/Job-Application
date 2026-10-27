---
description: Implement an approved feature with specialist routing, tests, verification, and a compact report
agent: build
---

Load the `feature-architecture` skill and the relevant `laravel-backend`, `inertia-react-typescript`, `feature-testing`, and `security-review` skills. Requested implementation: $ARGUMENTS

First inspect `git status`, the relevant spec in `docs/specs/` if present, and the surrounding code. Never overwrite unrelated user changes. If the request refers to an architect spec, confirm it was approved in the conversation; if no approval is present, stop and ask for approval instead of coding.

Follow `AGENTS.md`. Plan briefly, then implement the feature end-to-end in dependency order without inventing missing product requirements. MANDATORY SPECIALIST ROUTING (do not merely mention the specialists; invoke them through OpenCode's subagent/task mechanism when the matching work exists):

- Laravel/PHP, routes, controllers, Form Requests, Policies, Eloquent, migrations, schema, or backend security changes: delegate the backend slice to `backend-engineer`.
- React, Inertia, TypeScript, Tailwind, shadcn/ui, browser states, or frontend accessibility changes: delegate the frontend slice to `frontend-engineer`.
- Meaningful feature behavior, persistence, authorization, validation, or any bug fix: after the initial implementation draft, delegate test design/creation to `test-engineer`, including a regression test for a bug fix.
- If the task crosses backend and frontend, route each distinct slice to its matching specialist. Give each subagent a bounded task and relevant file/spec context. Avoid concurrent edits to the same files; have the primary agent coordinate integration.
- Do not call irrelevant specialists for documentation-only, trivial, or strictly one-layer changes. If a matching configured specialist was not invoked, explicitly report why in the final summary.
- Keep subagent depth shallow. The primary Build agent remains the orchestrator and is responsible for integrating work, verifying results, running checks, reviewing the final diff, and reporting truthfully.

MANDATORY COMPLETION GATE: implement; add/update tests for meaningful/critical behavior (a bug fix should normally include a regression test); run targeted tests and relevant typecheck/lint/format/build commands that actually exist in the project; fix failures caused by the change and rerun; inspect the final diff; check relevant security and query-performance risks. Do not install/update dependencies, modify unrelated files, or perform destructive/production actions without approval. If a check cannot run, report why.

End with a compact report: task type; agent(s); layers used and why; files changed; tests/checks run with exact outcomes; unverified items/blockers; next step if any. Use the full security checklist only if the change is security-sensitive. Do not claim unrun checks passed.
