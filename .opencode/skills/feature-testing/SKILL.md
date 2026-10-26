---
name: feature-testing
description: Write meaningful Laravel and React regression, feature, authorization, validation, and unit tests, then verify changes
compatibility: opencode
---

## Choose the test runner

Inspect `composer.json`, `phpunit.xml`, `tests/Pest.php`, existing test files, and `package.json` before writing tests. For a new Laravel project, Pest is the recommended default where compatible because its syntax is concise and it is built on PHPUnit. Use the actual installed versions; do not pin, install, or upgrade dependencies without approval. If the project uses PHPUnit, use PHPUnit style.

Use `php artisan test` as the common Laravel test entry point when available. Run the narrowest relevant tests first, then the broader suite when practical. For frontend tests, use only the test framework already configured unless the user approves adding one.

## Design tests from behavior

- Write tests that would fail if the requested behavior regressed; avoid simply asserting private implementation details.
- New protected feature: test guest behavior, allowed action, denied/incorrect permissions, validation errors, correct persistence/response, and relevant ownership cases. Do not force cases irrelevant to the feature.
- Bug fix: add a regression test reproducing the failure before/around the fix when possible.
- Business logic: test important branches, boundary values, invalid states, and transactional failure behavior as applicable.
- Database behavior: assert persisted changes, constraints, filters, ordering, pagination, relation behavior, and soft-delete/restore semantics where relevant.
- Security: verify unauthorized direct requests, ownership/tenant isolation, mass assignment boundaries, and sensitive response fields where applicable.
- Frontend: cover interaction and state transitions that matter; include error/empty/loading/processing behavior for meaningful interactive components.

## Execution and truthful reporting

1. Run the new/modified tests.
2. Fix implementation defects rather than suppressing a failing test.
3. Run related suite/static checks when practical.
4. Report exact commands and actual outcomes. If blocked by missing DB/service/env, state the blocker and provide the command the developer can run locally.
5. Never replace a failing test with a weaker assertion merely to get a green test suite. Never state tests passed if they were not run.
