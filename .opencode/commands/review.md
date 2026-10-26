---
description: Run an independent specialist review of current changes without editing
agent: build
---

Load the `security-review` skill and the relevant backend/frontend skills. Review scope: $ARGUMENTS

Inspect `git status`, the exact diff, and relevant surrounding files. If no argument is supplied, review the current uncommitted diff and related tests. MANDATORY: invoke the configured `code-reviewer` subagent for an independent read-only pass. If it cannot be invoked, explain the blocker and perform the review yourself while clearly noting that independent delegation did not happen. Reconcile every finding against repository evidence rather than accepting findings blindly.

Review plan alignment; direct-route access and middleware; Policy coverage/controller authorization; Form Request validation; custom role/permission logic; mass assignment; pagination/bounded data; N+1 and selected columns; migrations, FK/indexes and deletion behavior; transactions and side effects; Inertia response exposure; React/TypeScript correctness; UI loading/empty/error/accessibility; and tests/verification. Do not edit files. Report 🔴 Critical, 🟡 Warning, and 🔵 Suggestion findings with file/line evidence when available. Explicitly state what was and was not verified. Always finish with `✅ What was done correctly`.
