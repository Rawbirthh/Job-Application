---
description: Implement an approved feature with adaptive delegation, focused tests, and verified completion
agent: build
---

Implementation target: $ARGUMENTS

## 1. Approval and scope gate

1. Inspect `git status --short` and the requested/approved spec in `docs/specs/` (or the exact files named by the user). Never overwrite unrelated user changes.
2. If the request refers to an `/architect` spec, confirm the user approved that spec in the conversation. If approval is absent or the spec materially changed, stop and request approval.
3. Use the repository's root `AGENTS.md` as the shared engineering baseline. Do not reload unrelated skills. Follow all security, architecture, scalability, and testing rules in it.
4. Read only the relevant spec sections and inspect the closest existing implementation, tests, routes, models, and UI patterns. Do not perform a broad repository audit unless the feature requires it or no baseline exists.
5. Produce a concise implementation plan and identify dependencies, file ownership, test scope, and risk before editing.

## 2. Adaptive execution policy

The primary Build agent owns the feature from start to finish and implements directly by default. Delegate only when a specialist provides meaningful independent expertise or parallelism. Do not invoke an agent merely because its layer exists.

Use these routing rules:

- **Primary agent only (default):** small CRUD, localized bug fixes, simple one-layer changes, documentation, small UI changes, and tasks where delegation/context handoff would cost more than doing the work.
- **Backend specialist:** delegate a bounded backend slice when domain logic, complex Eloquent/query design, migrations/data integrity, integrations, or authorization is substantial enough to benefit from focused work.
- **Frontend specialist:** delegate a bounded frontend slice when UI flows/components are substantial, interaction/accessibility states are complex, or frontend work can proceed independently.
- **Backend + frontend in parallel:** only when both slices are substantial, their interfaces can be specified up front, and they do not edit the same files. Define the contract (routes, props, types, validation/errors) before dispatch. Otherwise work sequentially.
- **Test specialist:** delegate test design/implementation only for complex business rules, broad edge-case matrices, security/authorization-sensitive behavior, or a substantial regression suite. For ordinary features and fixes, the primary agent writes/updates the tests directly. Tests are still required for meaningful/critical behavior regardless of delegation.
- **Code reviewer:** invoke the independent read-only `code-reviewer` only for critical/high-risk changes: authentication/authorization or tenant isolation; sensitive data exposure; destructive/data migrations; payment/security boundaries; concurrency/transaction integrity; broad architectural refactors; or a high-impact change where independent review materially reduces risk. Do not invoke it for routine low-risk features. The user can always run `/review` for an independent review on demand.

### Delegation discipline

- Keep delegation one level deep. The primary agent remains accountable for integration, tests, final diff, and truthful reporting.
- Give each specialist only: the approved spec excerpt, exact task/acceptance criteria, relevant paths, interface contract, constraints, and expected return format. Do not send the whole project history or unrelated skills.
- Specialists should not independently rediscover the entire repository. Ask them to inspect only files necessary for their bounded task.
- Avoid overlapping edits. Assign clear file ownership; if overlap is unavoidable, sequence the work.
- Do not delegate sequential work that depends on unfinished output. Parallelize only independent slices.
- Do not ask a specialist to repeat checks the primary agent will run at the final gate. Specialists may run focused checks for their own work and report exact outcomes; the primary agent performs the integrated final verification once.
- If a specialist is unavailable or delegation is not justified, continue directly when safe and report the reason briefly.

## 3. Skill loading policy

Load skills just in time, only when their content adds detail beyond `AGENTS.md`:

- Backend implementation: `laravel-backend`.
- React/Inertia/UI implementation: `inertia-react-typescript`; also `ui-consistency` only when modifying or establishing reusable visual patterns.
- Test design: `feature-testing` when writing a meaningful test suite or delegating to `test-engineer`.
- Security-sensitive implementation/review: `security-review` for the relevant threat surface.

Do not load `feature-architecture` during normal development when an approved spec already exists. Do not load all skills preemptively. A delegated specialist loads only the skill(s) relevant to its bounded assignment.

## 4. Implementation and quality gates

Follow the approved spec and `AGENTS.md`. Implement in dependency order appropriate to the codebase. Keep scope focused; do not add packages, abstractions, or unrelated refactors without approval.

For every meaningful feature or bug fix:

1. Add/update automated tests for changed behavior. Bug fixes should normally include a regression test. Cover happy path, validation, persistence, and applicable authorization/guest/role/ownership cases; do not force irrelevant cases.
2. Run the narrowest relevant tests first.
3. Run relevant existing typecheck, lint, formatter, static analysis, and build commands when configured and affected.
4. Fix failures caused by the change and rerun the affected checks.
5. Inspect `git diff` and `git status`; confirm no unrelated edits, secrets, generated artifacts, or accidental changes.
6. Review applicable authorization, validation, data exposure, query performance, migration/index, transaction, and UI-state risks. Use the full security checklist for security-sensitive changes.
7. For critical/high-risk changes, invoke `code-reviewer` after implementation and tests, then reconcile findings against repository evidence. Resolve critical findings and material warnings before reporting completion.

Do not claim a command passed unless it was actually run and its result inspected. If a check cannot run, report the exact command and blocker. Do not weaken tests to obtain a green result.

## 5. Completion report

Keep the report compact:

- Feature/spec and risk level.
- Execution mode: primary-only or specialists used, with a short reason.
- Layers/files changed.
- Tests/checks run with exact commands and outcomes.
- Independent review: performed or not required, with reason.
- Manual verification status (never imply the agent performed the user's browser check).
- Remaining blockers/unverified items and next action.

Stop after reporting. Do not update `memory.md` automatically; the user controls the checkpoint with `/remember save`.
