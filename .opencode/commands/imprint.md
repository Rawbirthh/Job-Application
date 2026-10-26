---
description: Capture and audit reusable UI conventions for consistent React and shadcn components
---

Load the `ui-consistency` skill. Target component/path or action: $ARGUMENTS

Use the JSM `/imprint` approach. If a component or path is supplied, inspect its implementation, related styles, tokens, responsive behavior, accessibility, and surrounding screens; otherwise inspect the most recently changed UI. If the argument is `audit`, scan existing UI patterns and identify repetition/inconsistency before proposing a baseline.

Update `docs/ui-registry.md` with only patterns evidenced in the code or explicitly approved by the user: layout, spacing, typography, colors/tokens, radius/shadows, component anatomy, responsive rules, empty/loading/error states, and accessibility conventions. Prefer existing Tailwind/shadcn patterns. Do not invent product branding or perform broad UI refactors just to make the registry consistent. If changes to application code are needed, describe the change and scope first; ask for approval before a broad redesign. Report captured patterns and any conflicts found.
