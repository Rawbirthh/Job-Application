---
name: ui-consistency
description: Preserve and record design-system consistency for Tailwind, shadcn/ui, React components, and Inertia pages
compatibility: opencode
---

Read `docs/ui-registry.md` and inspect nearby screens/components before introducing a new pattern. Record only existing or user-approved patterns; don't invent a brand or assume a design direction the user hasn't chosen.

Compare hierarchy, spacing, typography, colors/tokens, radii, shadows, icons, form layout, page headers, tables, dialogs, empty/loading/error states, responsive behavior, keyboard access, and focus behavior. Prefer the shared shadcn components and existing Tailwind tokens over one-off styles. If an existing pattern is inconsistent, report the conflict rather than broadening the task into an app-wide redesign.

When asked to imprint a component, update `docs/ui-registry.md` with reusable rules and a file reference. For `audit`, sample the relevant component families and identify actual recurring patterns and inconsistencies. Do not change application source only to make the registry pass. Any broad design-system refactor needs user approval.
