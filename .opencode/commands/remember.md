---
description: Save or restore a durable project and current-work handoff in memory.md
---

Use the JSM-style `/remember save` and `/remember restore` workflow. Action and optional detail: $ARGUMENTS

If the first argument is `save`:
1. Inspect the current conversation context, `git status`, relevant `git diff`/recent history, relevant specs, and test results when available. Do not assume a command passed unless observed.
2. Read the existing root `memory.md` first so durable decisions are preserved.
3. Update the single root `memory.md` with concise confirmed project facts, decisions, current feature state, files changed, tests/checks actually run, blockers, and the next concrete action. Keep durable project context and latest feature handoff in their existing sections. Remove stale facts and compress low-value details.
4. Never store credentials, `.env` values, tokens, private keys, personal data, hidden reasoning, or speculative implementation details. Do not modify application code just to save memory.
5. Report that `memory.md` was updated and summarize its key sections.

If the first argument is `restore`:
1. Read root `memory.md` in full.
2. Verify important claims against the current repository before treating them as current: inspect referenced files/specs, current `git status`, and relevant test/config evidence. Flag stale or contradictory notes rather than silently trusting them.
3. Present a concise restored-context summary: project constraints, last task and status, files/tests, blockers, and next step.
4. STOP after the summary and wait for the user's next instruction. Do not automatically continue unfinished work.

If the first argument is missing or is neither `save` nor `restore`, explain the two valid forms and ask which action to perform. This command restores a saved summary, not a deleted transcript or the previous model's hidden reasoning.
