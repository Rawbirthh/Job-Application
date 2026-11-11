---
description: Run an independent, read-only review of current changes
agent: build
---

Review scope: $ARGUMENTS

This command is an explicit request for independent review, so invoke the configured read-only `code-reviewer` subagent. Load `security-review` and backend/frontend skills only for the portions of the diff that need them. Inspect `git status`, the exact diff, and relevant surrounding files; avoid a broad repository scan. If no argument is supplied, review the current uncommitted diff and related tests.

The reviewer must not edit files. Reconcile every finding against repository evidence rather than accepting findings blindly. Review plan alignment; route middleware and direct access; Policy/controller authorization; Form Request validation; custom role/permission logic; mass assignment; pagination/bounded data; N+1 and selected columns; migrations, FK/indexes and deletion behavior; transactions and side effects; Inertia response exposure; React/TypeScript correctness; UI loading/empty/error/accessibility; and test/verification coverage as applicable.

If the reviewer cannot be invoked, explain the blocker and perform the review directly while clearly stating independent delegation did not happen. Report 🔴 Critical, 🟡 Warning, and 🔵 Suggestion findings with file/line evidence when available. State what was and was not verified. Do not edit files. Always finish with `✅ What was done correctly`.
