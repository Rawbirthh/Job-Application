---
description: Save or restore a concise, verified project and feature handoff in memory.md
---

Use the JSM-style `/remember save` and `/remember restore` workflow. Action and optional detail: $ARGUMENTS

## `/remember save`

1. Read the existing root `memory.md` first. Preserve durable project decisions and its established sections.
2. Use the current conversation for feature intent, approval, and user-reported manual verification. Do not treat user-reported checks as commands the agent ran.
3. Inspect `git status --short`, the relevant diff, the active feature spec, and only the test/check evidence relevant to this feature. Do not scan the whole repository or reread unrelated history. Inspect recent Git history only if needed to resolve ambiguity.
4. Update the single root `memory.md` with concise confirmed facts: project profile/architecture; active or latest feature and spec path; status; decisions; changed paths; exact tests/checks actually run and outcomes; user-confirmed manual check (clearly labeled); blockers; next concrete action.
5. Preserve durable rules; replace stale handoff details rather than accumulating a transcript. Keep the file short and useful for a fresh session.
6. Never store credentials, `.env` values, tokens, private keys, personal data, hidden reasoning, or speculative implementation details. Do not modify application code just to save memory.
7. Report what was updated and any evidence that could not be verified.

## `/remember restore`

1. Read root `memory.md` in full.
2. Verify only the referenced active spec, changed paths, relevant test/config evidence, and current `git status --short`. Do not reread the entire codebase. If a claim cannot be verified, label it stale/unverified rather than silently trusting it.
3. Present a concise restored-context summary: durable project constraints; latest feature/spec and approval/status; changed files; tests/checks and exact known outcomes; user-reported manual verification; blockers; next recommended action.
4. STOP and wait for the user's next instruction. Do not automatically continue implementation or invoke `/architect`.

If the first argument is missing or is neither `save` nor `restore`, explain the two valid forms and ask which action to perform. This restores a saved summary, not a deleted transcript or hidden reasoning.
