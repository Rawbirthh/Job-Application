# UI Registry

This file is maintained by `/imprint`. Capture only reusable visual patterns grounded in the existing app or an explicit design decision. Do not invent a brand identity before the product requirements provide one.

## Design tokens

- Colors/theme: teal / deep-emerald / soft-fog 60-30-10 palette applied through the semantic tokens in `resources/css/app.css`. Canvas `--background` is `#f8fafc` light / `#0b1220` dark; structural `--sidebar` is `#0f172a` / `#0d1a2a`; teal `--primary` is `#0d9488` / `#2dd4bf`. Solid teal actions pair with near-black emerald text (`#021716` / `#042f2e`) to meet WCAG AA. Keep teal for interaction, focus, icons, and small accents, not large surfaces.
- Homepage tokens: `--home-surface`, `--home-surface-strong`, `--home-accent`, and `--home-accent-soft` provide the fog surfaces and teal highlights for public sections.
- Typography: Instrument Sans 400/500/600. Display headings use weight 600 with tight tracking; do not use weights above 600 until loaded.
- Spacing/radius/shadows: existing Tailwind spacing with `max-w-6xl` public content, `py-20 md:py-28` section rhythm, and `rounded-2xl`/`rounded-3xl` marketing surfaces.
- Focus: public interactions use a visible foreground ring; the employer band uses a teal ring with a sidebar offset; form controls use the semantic `--ring`.

## Layout patterns

- App shell/navigation: authenticated pages use `AppLayout` with a dark-emerald sidebar rail and a soft-fog `SidebarInset` in light mode; public pages use `PublicLayout` with skip link, `SiteHeader`, `<main id="main-content">`, and `SiteFooter`. Use `border-border` inside the light inset, not `border-sidebar-border`.
- Page headers/actions: public sticky header is 72px, backdrop-blurred, with desktop anchor navigation and a mobile `Sheet` menu.
- Responsive breakpoints: use existing Tailwind breakpoints; the public hero is a 7/5 grid from `lg` and stacks below it.
- Public sections: fog `home-surface` alternates with background sections; anchor targets use scroll margin below the sticky header. The employer band uses `bg-sidebar text-sidebar-foreground` with a subtle teal glow; its CTA uses the light structural surface (`bg-sidebar-foreground text-sidebar`) so it reads as a button in both themes.

## Component conventions

- Use existing shadcn/ui components from the repository where appropriate.
- Forms: consistent labels, descriptions, field-level errors, disabled/loading state, and accessible focus behavior.
- Tables/lists: consistent loading, empty, error, pagination, and responsive states.
- Dialogs/sheets: consistent title, description, close behavior, confirmation for destructive actions, and keyboard accessibility.
- FAQ disclosures: native `<details>/<summary>` with a rotating decorative chevron; do not remove the summary focus ring.
- Auth-aware public CTAs: guest actions link to login/register; authenticated actions link to the dashboard. Presentation only; server middleware remains authoritative.

## Reference components

- `resources/js/layouts/public-layout.tsx` — public shell, skip link, and landmarks.
- `resources/js/components/public/site-header.tsx` — responsive public navigation and auth actions.
- `resources/js/components/public/site-footer.tsx` — public footer link structure.
- `resources/js/components/public/faq-list.tsx` — accessible native FAQ disclosure pattern.
- `resources/js/components/public/hero-art.tsx` — decorative, CSS-only homepage art; keep it `aria-hidden` and non-interactive.

## Known inconsistencies / follow-ups

- Manual browser QA for the homepage at 320–1440px, keyboard navigation, dark/light contrast, reduced motion, and 200% zoom has not yet been recorded.
- Manual browser QA for the teal/emerald palette across the homepage, auth screens, dashboard, and settings has not yet been recorded. Contrast was checked from the token values, not in a browser.
- The homepage has no live job search or featured roles; those arrive with Feature 16.
- `npm run check` still reports pre-existing formatting issues in repository documentation outside the files touched by the homepage work.
