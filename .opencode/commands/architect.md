---
description: Architect a feature, save a decision/spec, and wait for approval before coding
---

Load the `feature-architecture` skill. Feature request: $ARGUMENTS

Act like a senior engineer using the JSM `/architect` approach. Collaboratively inspect the current repository and surface only decisions that materially affect design. Do not assume a business domain, role model, package, or dependency not present in the repository.

Create or update a concise spec under `docs/specs/` with a descriptive feature slug. Include: goal/non-goals; user flows and acceptance criteria; routes/middleware and custom role/permission checks; Policy actions; Form Requests and validation; data model/migration/foreign keys/indexes; query/pagination/eager-load plan; Service/Repository decision with reasons; Inertia page props and React forms/components; accessibility/empty/loading/error states; test matrix (happy path, validation, applicable guest/role/owner/non-owner cases, regression cases); security/performance risks; implementation sequence; and commands to verify.

If truly blocking requirements are missing, list the smallest set of focused questions in the spec/output. Otherwise use explicit assumptions and label them. Do not write application code, create migrations, install packages, or run destructive commands. Present the spec and a short list of important decisions, then STOP and wait for the user's approval or edits. Do not proceed to implementation in the same response.
