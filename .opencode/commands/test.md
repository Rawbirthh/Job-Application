---
description: Add and run tests with the configured testing specialist
agent: build
---

Load the `feature-testing` skill. Test target: $ARGUMENTS

MANDATORY: invoke `test-engineer` for the test-design/implementation pass. The primary agent must verify the proposed tests and run the relevant checks. If the specialist cannot be invoked, explain why and complete the task directly if safe.

Inspect existing test conventions and available commands before acting. If target is omitted, use the current diff and latest feature. Write or update meaningful tests that would fail on a regression. Cover success behavior, validation, persistence, and relevant authorization/role/ownership/guest cases; include a regression test for bug fixes. Do not assume every feature has an admin role or owner concept. Prefer Pest-style tests for a new project if Pest is installed and compatible; otherwise follow existing PHPUnit/Pest conventions. Do not install dependencies without approval.

Run the targeted test(s) and, where time and project setup allow, the broader suite. Report exact commands, passed/failed counts or output summaries, and blockers. Fix failing tests only when the test failure is caused by the target implementation; never weaken a meaningful test to hide a defect. Do not claim success if the command was not run.
