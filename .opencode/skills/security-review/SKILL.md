---
name: security-review
description: Review Laravel/Inertia changes for authorization, validation, mass assignment, data exposure, destructive behavior, and database integrity
compatibility: opencode
---

## Threat-model relevant changes

Assess untrusted input, direct URL access, session authentication, CSRF/session behavior, custom role/permission escalation, object ownership/tenant boundaries, mass assignment, file upload/path traversal, sensitive information disclosure, SQL/query safety, unsafe external calls, SSRF where applicable, race conditions, transactions, and destructive database operations. Only investigate attack surfaces relevant to the actual feature.

## Mandatory gate for protected business/resource features

1. Are the correct authentication/role/permission middleware applied to the direct route and route group?
2. Are Policies implemented and called for model/resource actions before protected operations? Is ownership/tenant scope enforced where applicable?
3. Is all user input validated through the intended Form Request and all allowed fields explicitly controlled?
4. Are list responses paginated or bounded/streamed with documented reason?
5. Are relationships loaded efficiently without N+1 queries, including loop/serialization behavior?
6. Are sensitive columns excluded from queries and Inertia/page responses?
7. Is `$fillable` declared on mass-assigned models, and is raw request payload avoided?
8. Do FK constraints/indexes and migration deletion/nullable rules preserve data integrity without redundant indexes?

Also review CSRF/session-auth assumptions, role-granting controls, transaction/event ordering, logs, secrets, and dependency/production changes when relevant. A check should be marked `not applicable` only with a reason; do not rubber-stamp all checks.

## Output

Prioritize evidence-based findings:
- 🔴 Critical: exploitable authorization/data exposure, destructive corruption/loss, or severe correctness failure.
- 🟡 Warning: likely vulnerability, data integrity risk, missing significant test, or serious performance issue.
- 🔵 Suggestion: lower-risk hardening/maintainability.

Include a path and line or a precise code reference wherever possible. Distinguish observed problems from unverified risks. Never change files in a review-only task. End with `✅ What was done correctly`.
