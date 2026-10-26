---
name: inertia-react-typescript
description: Build typed React pages, forms, navigation, pagination, and reusable components with Inertia, Tailwind, and shadcn/ui
compatibility: opencode
---

## Inspect first

Read `AGENTS.md`; inspect actual versions and folder conventions under `resources/js`, shared `types`, layouts, shared pagination, `package.json`, Tailwind config, shadcn components, and any existing page/form examples.

## Data and navigation

- Treat Inertia props as server-owned page data. Do not use Axios/fetch in `useEffect` to load the main page model.
- Use Inertia `router.get()` for server filters/sorting and appropriate router methods/useForm for writes. Preserve query strings/state intentionally, and keep browser-visible URL in sync with server filter state.
- `useState` is for ephemeral UI state such as modals, tabs, dropdowns, or current input text, not a second source of truth for server-owned rows/authorization.
- Use effects only for synchronization tasks such as subscriptions or timers; prefer event handlers for event-driven work.

## Types and forms

- Define typed props interfaces for page/components and typed form data. Avoid `any`; use `unknown` and narrow data from untyped boundaries.
- Use shared types where the project already has a canonical location; do not create duplicate competing domain types.
- Use Inertia `useForm()` for normal forms; display field-level errors, disable submit while processing, prevent duplicate submission, and handle success/failure feedback.
- Ensure a semantic `<form onSubmit>` and button types are correct; do not implement submit twice through click and submit handlers.

## Lists and states

- Every list page should use backend pagination and the shared Pagination component when present, include useful empty state, and present loading/error states where the interaction supports them.
- Filtering, sorting, and counts belong on the server for paginated data. Do not slice a page client-side to simulate server pagination.
- Keep Inertia payload and client components aligned. Do not expose sensitive values or attempt to secure a route through conditional rendering.

## shadcn and visual quality

- Reuse installed shadcn/ui primitives and the current design tokens. Do not install a second UI kit or replace app-wide tokens without approval.
- Ensure keyboard focus, semantic labels, accessible names/descriptions, sensible dialog behavior, responsive layouts, and contrast are addressed.
- Keep variants/styles consistent with `docs/ui-registry.md` when it has been populated. Update the registry through `/imprint` after notable or reusable UI patterns are established.
- Avoid huge components; extract reusable components only when repeated behavior or readability warrants it.

## Verification

Use scripts actually present in `package.json` (typecheck, lint, format, build). Add UI/unit/integration coverage where the project has a test framework and the behavior warrants it. Report any checks that were unavailable.
