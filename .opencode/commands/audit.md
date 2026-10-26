---
description: Audit the actual project stack and conventions before feature work
---

Load the `feature-architecture` skill. Inspect the repository without changing application code. Identify the actual PHP/Laravel, Node/package manager, React, Inertia, TypeScript, Tailwind, and shadcn versions; authentication setup; custom role/permission model and middleware; routes; model/policy/form-request conventions; migrations and indexes; test framework and available scripts; formatting/static analysis/lint/build commands; and existing UI patterns.

Do not assume a package, role name, schema, or product domain. Do not install dependencies. Write a concise factual baseline to `docs/architecture/baseline.md` (create the directory/file if needed), including evidence paths, unknowns, and recommendations. Preserve existing documentation. Report what was inspected and stop without implementing a feature.
