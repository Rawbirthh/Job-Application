---
description: Diagnose a bug or stuck implementation and recover safely
agent: build
---

Recovery target: $ARGUMENTS

Use the JSM `/recover` approach. The root `AGENTS.md` is already the shared baseline. Load `feature-architecture` only if the design must be reconsidered, and `feature-testing` only when detailed regression-test design is needed. Inspect the failure, actual error/output, recent diff, relevant files, and test evidence. Categorize the situation before making changes:

- TARGETED FIX: a localized defect with a clear root cause; fix it precisely and add a regression test.
- RESET THE APPROACH: the current patching path is polluted or repeatedly failing; summarize what has been tried and propose a clean, bounded approach. Do not discard user changes or reset Git history without explicit approval.
- RETHINK THE DESIGN: the original architecture/spec is wrong or a prerequisite is missing; explain the incorrect assumption and propose a revised design, using `/architect` for a new approval-gated spec if needed.

Do not make speculative patches. State the evidence, category, smallest safe action, risks, and verification plan. Ask before destructive operations, dependencies changes, or widening scope. The primary agent handles localized recovery by default. Delegate a bounded backend or frontend slice only when diagnosis is complex or the work is substantial and independently actionable. Delegate `test-engineer` only for complex regression design; otherwise write the regression test directly. A regression test remains expected for meaningful bug fixes regardless of delegation. Do not ask the user to call these agents manually for ordinary recovery. The primary agent coordinates, checks the diff, runs the final relevant checks, and reports exact outcomes. If a specialist was not invoked because the issue did not match its scope or delegation failed, say so explicitly.
