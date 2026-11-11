---
description: Create and run focused tests for a feature, bug fix, or current diff
agent: build
---

Test target: $ARGUMENTS

Use the root `AGENTS.md` testing gate. Load `feature-testing` only when its detailed test-design guidance is useful. Inspect the configured test runner and a small number of representative tests before writing anything.

The primary agent handles ordinary focused tests directly. Delegate to `test-engineer` only when the behavior has a complex edge-case matrix, substantial authorization/security boundaries, or a broad regression suite that benefits from independent test design. Do not invoke a subagent just to run a few straightforward assertions.

If no target is supplied, use the current diff and latest feature. Write/update tests that would fail if observable behavior regressed. Cover success, validation, persistence, and applicable authorization/guest/role/ownership cases; include a regression test for bug fixes. Do not assume every feature has an admin role or owner concept. Prefer Pest only if installed and compatible; otherwise follow the existing PHPUnit/Pest convention. Do not install dependencies without approval.

Run targeted tests first and broader relevant checks when practical. Fix implementation defects rather than weakening tests. Report exact commands, outcomes, and blockers. Never claim a check passed unless it was run.
