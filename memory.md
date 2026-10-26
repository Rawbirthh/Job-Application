# Project Memory

> Maintained by `/remember save`. Keep this concise, factual, and free of secrets. This file stores a useful project/work handoff; it does not contain or recover a full chat transcript.

## Project profile
- Stack: Laravel + React + TypeScript + Inertia.js + MySQL + Tailwind CSS + shadcn/ui.
- Authentication: session-based.
- Authorization: custom/dynamic role and permission implementation; do not assume a third-party package or fixed role names.
- Test convention: prefer Pest for a new project if installed and compatible; otherwise follow the repository's existing PHPUnit/Pest setup.

## Confirmed architecture decisions
- See `AGENTS.md` for the engineering rules and security gate.
- Keep Service and Repository layers only when complexity, reuse, or query shape justifies them.
- Inertia is the default page-data transport; frontend authorization is never authoritative.

## Current task / latest handoff
- Status: No feature task recorded yet.
- Summary: Run `/audit` before the first feature so the actual scaffold, installed versions, testing framework, and app conventions can be verified.
- Files changed: None recorded.
- Tests/checks: None recorded.
- Blockers/open questions: The product domain has not been specified yet.
- Next recommended step: `/audit`, then `/architect <feature>`.

## Durable notes
- Save only confirmed decisions, important file paths, meaningful test results, known issues, and next steps.
- Never store `.env` values, passwords, tokens, private keys, personal data, or speculative claims.
