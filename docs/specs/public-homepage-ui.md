# Public Homepage UI

- **Feature slug:** `public-homepage-ui`
- **Status:** Approved and implemented — 2026-10-26
- **Date:** 2026-10-26
- **Scope type:** Public UI foundation; this is not a numbered roadmap feature
- **Relationship to roadmap:** Replaces the starter-kit `welcome` page. It does **not** implement Feature 4 (authenticated app shell), Feature 16 (public job listing), or any role/permission model.

## Evidence inspected

- `routes/web.php` — `/` currently renders `welcome`; `/dashboard` is the only authenticated app page.
- `resources/js/pages/welcome.tsx` — current Laravel starter page.
- `resources/js/app.tsx` — name-based layout resolution; `welcome` currently has no layout.
- `resources/css/app.css` - Tailwind v4 tokens; originally the neutral shadcn palette, later amended to the approved teal/emerald palette; Instrument Sans 400/500/600 only.
- `resources/js/components/ui/` — existing shadcn primitives, including Button, Badge, Sheet, Separator, Collapsible, Card.
- `resources/js/layouts/` — existing auth and authenticated app layouts.
- `docs/architecture/baseline.md` — no roles, Policies, job schema, or business tables exist yet.
- `project-overview.md` — product is a job portal for candidates, employers, and platform administrators.

---

## 1. Goal

Replace the Laravel starter welcome page with a polished, responsive, accessible public homepage for the job portal that:

1. Speaks primarily to job seekers and secondarily to employers.
2. Communicates the product promise without inventing live jobs, companies, testimonials, or statistics.
3. Establishes a reusable **public layout** for this page and future public pages.
4. Uses the existing design system, React/Inertia conventions, and authentication routes.
5. Stays independent of the not-yet-built job, company, role, and application schema.

The homepage is a **pre-launch marketing/home page** in this slice. Live search, categories, and featured jobs are deferred until the corresponding data features exist.

## 2. Non-goals

- Job search results, job listing, job detail, filtering, saved jobs, applications.
- Database-backed categories, featured jobs, company logos, or live counts.
- Employer onboarding, company profiles, job posting, or employer dashboards.
- Role/permission checks, admin navigation, or persona-specific content.
- Authentication changes, registration changes, social login, MFA, or passkeys.
- New npm/Composer dependencies, new fonts, an icon library, a carousel, an animation library, or external images.
- A waitlist/email-capture endpoint, newsletter, analytics, or tracking pixels.
- Fake testimonials, fake company logos, fabricated user counts, or placeholder jobs presented as real.
- SSR changes or public HTTP caching of personalized Inertia responses.
- Implementing Feature 4 or Feature 16 inside this spec.

---

## 3. Design direction

### Concept: “Deep emerald + teal”

A calm, human, editorial homepage rather than a dense job-board dashboard. The page should feel confident and spacious, with strong typography and a single teal accent used sparingly. The existing semantic shadcn token system remains the base; the palette below is an application-wide direction, not a separate homepage skin.

> **Palette amendment (2026-10-26):** the original warm terracotta direction was superseded by the user's approved teal / deep-emerald / soft-fog palette. The layout, copy, and accessibility rules below are unchanged. The implemented values now live in `resources/css/app.css`.

- **Tone:** clear, grounded, optimistic. No hype, no “dream job” clichés.
- **Product name/logo:** keep the existing `config('app.name')` value and `AppLogoIcon`; no new brand name or logo asset in this slice.
- **Imagery:** CSS-only texture and layered UI cards. No stock photos or external images.
- **Density:** generous whitespace; one idea per section.
- **Motion:** restrained. Hover/focus transitions and mobile-sheet transitions only. No auto-playing carousel, parallax, or scroll-jacking.

### Implemented palette tokens

The palette is applied through the existing semantic tokens in `resources/css/app.css`. Core tokens carry the 60-30-10 direction; homepage-scoped tokens remain for section surfaces and decorative accents.

| Token                   | Light     | Dark      | Use                                                |
| ----------------------- | --------- | --------- | -------------------------------------------------- |
| `--home-surface`        | `#f1f5f9` | `#0f1b2b` | Fog page/alternating-section background            |
| `--home-surface-strong` | `#e2e8f0` | `#152538` | Hero art panel, subtle inset areas                 |
| `--home-accent`         | `#0d9488` | `#2dd4bf` | Decorative dot, step numbers, underline, icon wash |
| `--home-accent-soft`    | `#ccfbf1` | `#0f3b38` | Icon containers and soft highlights                |

The global 60-30-10 direction is: 60% soft-fog canvas (`--background`), 30% deep-emerald structure (`--sidebar` and inverted bands), 10% teal accent (`--primary`, `--ring`, `--home-accent`). The authenticated sidebar is a deep-emerald rail in both themes; the light-mode main content is the soft-fog inset.

Rules:

- Solid primary actions use `--primary` / `--primary-foreground`: teal `#0d9488` with near-black emerald `#021716` in light mode, and teal `#2dd4bf` with `#042f2e` in dark mode. These pairs meet WCAG AA for normal text.
- The teal accent is reserved for interactive elements, focus rings, icons, and small highlights; do not use it for large surfaces.
- All body copy uses `--foreground` / `--muted-foreground`.
- Borders use existing `--border`; inputs use `--input`.
- No hard-coded palette hex values in page components; use semantic utilities.

### Typography

Existing font only: Instrument Sans 400, 500, 600.

- H1: `clamp(2.75rem, 6vw, 5rem)`, weight 600, line-height `0.98`, tracking `-0.045em`.
- Section H2: `2rem–2.75rem`, weight 600, tracking `-0.03em`.
- Eyebrow: 12px, weight 500, uppercase, tracking `0.18em`.
- Body: 16–18px, line-height 1.7, muted foreground.
- Buttons/labels: 14px, weight 500.
- Avoid weight 700/800; those font weights are not loaded.

### Layout

- Content width: `max-w-6xl` (1152px), gutters `px-5 sm:px-6 lg:px-8`.
- Header height: 72px.
- Section rhythm: `py-20 md:py-28`; hero `pt-16 pb-20 md:pt-24 md:pb-28`.
- Marketing cards: `rounded-2xl` / `rounded-3xl`; controls keep the existing shadcn radius.
- Breakpoints: existing Tailwind defaults. Verify at 320, 375, 768, 1024, and 1440px.
- Sticky header offset: add `scroll-mt-24` or equivalent to anchor targets.

### Page wireframe

```text
┌──────────────────────────────────────────────────────────────────────┐
│ [logo] [How it works] [For employers] [FAQ]      [Log in] [Sign up] │
├──────────────────────────────────────────────────────────────────────┤
│                                                                      │
│  [eyebrow]                                       ┌────────────────┐  │
│  Find work that fits                             │  layered UI    │  │
│  the life you want.                              │  art: profile, │  │
│                                                  │  saved role,   │  │
│  [supporting copy]                               │  next step     │  │
│  [Create profile] [See how it works]             └────────────────┘  │
│  Already have an account? Log in                                     │
│                                                                      │
├──────────────────────────────────────────────────────────────────────┤
│  WHY [NAME]        Less searching. More deciding.                   │
│  [clarity card]    [profile card]    [progress card]                 │
├──────────────────────────────────────────────────────────────────────┤
│  HOW IT WORKS      01 profile → 02 discover → 03 apply/track        │
├──────────────────────────────────────────────────────────────────────┤
│  FOR EMPLOYERS     dark band, honest pre-launch copy, account CTA    │
├──────────────────────────────────────────────────────────────────────┤
│  FAQ               native details/summary accordion                 │
├──────────────────────────────────────────────────────────────────────┤
│  Footer: brand, Explore links, Account links, copyright              │
└──────────────────────────────────────────────────────────────────────┘
```

Future data slots (not rendered in this slice):

- A real search form and “Featured roles” section will be added when Feature 16 exists.
- A category browser will be added when Feature 5/16 data exists.
- These must not be simulated with fake records in production.

---

## 4. User flows

### Guest

1. Opens `/`.
2. Reads the value proposition and sees two working actions: **Create your profile** → `/register`, **See how it works** → `#how-it-works`.
3. Can open **Log in** → `/login`.
4. Can navigate to **For employers** and **FAQ** in-page anchors.
5. On mobile, opens the menu sheet, navigates, and the sheet closes after a link is selected.
6. Never encounters a search box, jobs section, or CTA that looks functional but leads nowhere.

### Authenticated user

1. Opens `/` (public page; no redirect).
2. Header shows **Dashboard** → `/dashboard` instead of Log in/Create account.
3. No role-specific label is shown because no role model exists yet.
4. Server-side access to `/dashboard` remains protected by existing `auth`/`verified` middleware.

### Keyboard/reduced-motion user

1. First Tab reveals a **Skip to content** link.
2. Header, CTA, FAQ, and footer links have visible focus rings and logical tab order.
3. Mobile menu is focus-trapped, closes on Escape, and restores focus to the trigger.
4. FAQ items open/close via keyboard.
5. `prefers-reduced-motion` removes non-essential transforms/transitions.

---

## 5. Acceptance criteria

- `GET /` returns 200 for guests and authenticated users.
- The response renders the Inertia component `home`.
- The route name remains `home`; existing `route('home')` usage continues to work.
- The page is not wrapped in the authenticated sidebar `AppLayout`.
- Guest header shows Log in/Create account; authenticated header shows Dashboard.
- All visible links resolve to existing routes or in-page anchors; no dead search/jobs links.
- No jobs, companies, categories, counts, or testimonials are fabricated.
- No new database query is introduced by the page.
- No authorization check is added or relaxed.
- Layout works at 320px through 1440px without horizontal scrolling.
- Light and dark modes both meet contrast expectations for text, controls, and focus indicators.
- Keyboard navigation, mobile sheet focus behavior, FAQ operation, and reduced-motion behavior are manually verified.
- Existing authentication and dashboard behavior is unchanged.
- `npm run types:check`, `npm run build`, `composer lint:check`, `composer types:check`, and `php artisan test` pass (or failures are reported accurately).

---

## 6. Routes and access boundary

| Route            | Name        | Handler                       | Middleware                | Notes                                |
| ---------------- | ----------- | ----------------------------- | ------------------------- | ------------------------------------ |
| `GET /`          | `home`      | `Route::inertia('/', 'home')` | `web`                     | Public; replaces `welcome`           |
| `GET /login`     | Fortify     | existing                      | `web`, `guest`            | Linked from header/hero              |
| `GET /register`  | Fortify     | existing                      | `web`, `guest`            | Linked from header/hero/employer CTA |
| `GET /dashboard` | `dashboard` | existing                      | `web`, `auth`, `verified` | Linked only for authenticated users  |

Implementation notes:

- Keep the route name `home` to avoid breaking `resources/js/layouts/auth/auth-simple-layout.tsx` and `tests/Feature/ExampleTest.php`.
- Update the Inertia layout resolver in `resources/js/app.tsx`:
    - replace the `welcome` case with `home`;
    - return `PublicLayout` for `home`;
    - do not let `home` fall through to `AppLayout`.
- Use native `<a href="#...">` for same-page anchors; use Inertia `<Link>` for application routes.
- Do **not** add a `/jobs` route in this slice. The future job search route belongs to Feature 16.

### Custom role/permission checks

None. The page is public and read-only. The custom role/permission system does not exist in the repository yet, and the homepage must not hard-code `admin`, `employer`, or `candidate` roles. The only conditional is authenticated vs guest, based on the existing shared `auth.user` prop. This is presentation only, not authorization.

---

## 7. Policy actions

None. There is no protected model or resource action on this page. Creating a Policy for a static marketing page would be meaningless ceremony. `/dashboard` remains protected by existing middleware; no Policy changes are required.

If future homepage data is added:

- Public featured jobs still need server-side visibility scoping, not a Policy on the homepage.
- Personalized dashboard-like content would require its own Policy/authorization design in a later approved feature.

---

## 8. Form Requests and validation

None. The homepage has no write endpoint and no user input in this slice.

- The CTA and auth buttons are links, not forms.
- Do not add a fake newsletter/search form without a real server handler.
- When Feature 16 adds search, use a dedicated request/query validation for `q`, `location`, pagination, and allowed filters. Do not trust arbitrary query keys or unbounded values.
- The employer CTA links to registration; employer eligibility remains a later server-controlled onboarding flow.

---

## 9. Data model, migration, foreign keys, indexes

None.

- No migration, table, column, foreign key, index, model, factory, or seeder changes.
- No job/company/category data is needed.
- Do not add placeholder tables or seed fake homepage content.
- Existing `users` schema is untouched.
- Future homepage data will reuse the job/company schema introduced by Features 12–16; do not pre-create it here.

---

## 10. Query, pagination, and eager-loading plan

This slice:

- Zero application-level queries from the homepage.
- The existing shared Inertia middleware may load the authenticated user for signed-in requests. That is existing behavior and must not be expanded.
- No pagination, `get()`, eager loading, or counts.
- No public HTTP caching of the Inertia response because page props include authentication state.

Future integration contract, only after Feature 16:

- Featured roles must be a bounded query (hard limit, e.g. 3).
- Select only public card columns; do not `SELECT *`.
- Eager-load the minimum company/config relationships needed to avoid N+1.
- Never expose moderation notes, applicant counts, private employer fields, or unpublished jobs.
- If categories are shown, cap them (e.g. 6) and compute counts at the database level only if the design actually displays counts.
- Do not query inside React or fetch page data on mount.

---

## 11. Service/Repository decision

No Service and no Repository.

Reasons:

- The page is static and has no business logic.
- There is no multi-step transaction, external integration, or reusable query.
- Adding layers now would create abstractions without evidence.
- When featured jobs arrive, start with a thin `HomeController` plus an Eloquent query scope or a small query object. Extract a Service/Repository only if the query is reused or becomes materially complex.

---

## 12. Inertia page props and React plan

### Props

No new page props in this slice. The page uses existing shared props:

- `name: string` — product name from `config('app.name')`.
- `auth.user: User | null` at runtime — guest vs signed-in header state.
- `sidebarOpen` is ignored by the public layout.

Do not add fake `jobs`, `categories`, `stats`, or `testimonials` props.

Known type drift: `resources/js/types/auth.ts` declares `Auth.user` as non-null even though guests receive `null`. This feature follows the existing runtime guard pattern (`auth.user ? ... : ...`). A global type correction is out of scope and should be handled as a separate, approved cleanup.

### Files

| File                                             | Purpose                                                  |
| ------------------------------------------------ | -------------------------------------------------------- |
| `resources/js/pages/home.tsx`                    | Homepage composition; replaces `welcome.tsx`             |
| `resources/js/layouts/public-layout.tsx`         | Shared public shell for homepage and future public pages |
| `resources/js/components/public/site-header.tsx` | Public header, desktop nav, auth actions, mobile sheet   |
| `resources/js/components/public/site-footer.tsx` | Public footer links and brand line                       |
| `resources/js/components/public/hero-art.tsx`    | Decorative, `aria-hidden` layered-card composition       |
| `resources/js/components/public/faq-list.tsx`    | Accessible native `details/summary` FAQ                  |

Optional: keep small sections inline in `home.tsx` until a second consumer exists. Do not create a generic section factory or a new design-system package for one page.

### Components and primitives

- `Button` for CTAs; `size="lg"` on hero actions.
- `Badge` for the eyebrow/status labels.
- `Sheet` for mobile navigation.
- `Separator` only if it materially improves layout; otherwise use borders.
- `lucide-react` icons already installed.
- Native `<details>` / `<summary>` for FAQ: progressive enhancement, keyboard support, no extra state or dependency. Style the marker and rotate a chevron with `group-open`.
- No `useEffect` data fetching. No client-side filtering or pagination. No new state except whatever Radix Sheet manages internally.
- Use `Link` with `Button asChild` for auth/dashboard routes.
- Use `usePage()` only for shared `name` and `auth`.

### Section specifications

#### Public header

- Sticky, `top-0`, `z-40`, backdrop blur, bottom border.
- Left: existing `AppLogoIcon` in a small rounded square + product name from `name`.
- Desktop nav: **How it works** (`#how-it-works`), **For employers** (`#for-employers`), **FAQ** (`#faq`).
- Guest actions: **Log in** (`login()`), **Create account** (`register()`).
- Authenticated action: **Dashboard** (`dashboard()`). Do not show a role label.
- Mobile: menu button with `aria-label`; Sheet from the right; visible Sheet title; close button; links and auth actions inside; close on navigation.

#### Hero

- Two-column layout on `lg`; single column on smaller screens with art below copy.
- Fog surface with a low-contrast CSS dot/grid texture; no image asset.
- Eyebrow: “A clearer path to work”.
- H1: “Find work that fits the life you want.”
- Supporting copy: “A calmer place to discover roles, understand what they offer, and take the next step — without the noise.”
- Primary CTA: **Create your profile** → `register()`.
- Secondary CTA: **See how it works** → `#how-it-works`.
- Microcopy: “Already have an account? **Log in**”.
- No functional search input in this slice. Search is a future Feature 16 integration; do not render a dead field.
- Hero art: decorative layered cards only, `aria-hidden="true"`, no fake company names or live-looking job rows. Proposed content: “YOUR NEXT MOVE”, “Work, with room for life.”, chips “Clear expectations”, “Room to grow”, “Life outside work”; two floating utility cards (“Profile in progress”, “Saved for later”). These are illustrative product motifs, not records.

#### Why section

- Eyebrow “WHY [NAME]”; H2 “Less searching. More deciding.”
- Three cards, single column on mobile and three columns from `md`:
    1. **Clarity up front** — “Built to show the details that matter before you apply.”
    2. **A profile with purpose** — “Bring your experience, skills, and resume together in one place.”
    3. **Progress you can see** — “A clear view of every application, from first click to final decision.”
- Icon containers use `--home-accent-soft`; icons are Lucide, never the only label.
- Copy describes planned product capability. Product must approve final wording so it does not imply unavailable features are live.

#### How it works

- `id="how-it-works"`.
- H2 “A simpler path from looking to landing.”
- Semantic `<ol>` with three steps:
    1. **Create your profile** — “Add your experience, skills, and resume. Keep it ready for the roles that matter.”
    2. **Find a better fit** — “Explore roles with the context you need to make a confident decision.”
    3. **Apply and keep track** — “Follow every application and see where each conversation stands.”
- Numbered accent markers; horizontal connector on desktop, vertical on mobile.

#### Employer CTA

- `id="for-employers"`.
- Deep-emerald band using `bg-sidebar text-sidebar-foreground` so it stays dark in both themes, with a subtle decorative teal glow. The CTA uses the light structural surface (`bg-sidebar-foreground text-sidebar`) with a teal focus ring and sidebar-colored offset so it remains a visible button in both themes.
- Eyebrow “FOR EMPLOYERS”; H2 “Meet people who are ready to do the work.”
- Honest pre-launch copy: “Employer onboarding is coming soon. Create an account now and be ready to build your company profile, publish roles, and manage applicants.”
- CTA: **Create an account** → `register()`.
- Do not promise a review timeline, pricing, or a live posting flow.
- Rounded large panel; no fake logos or employer testimonials.

#### FAQ

- `id="faq"`.
- H2 “Questions, answered.”
- Five items max, native `details`:
    1. What is [name]?
    2. How do I get started?
    3. Can I search for jobs today?
    4. I’m hiring. How do I post a role?
    5. Where can I manage my account?
- Answers must be truthful for the current release. For example, job search and employer onboarding should be described as coming in the first release, not as live.
- No `itemprop`/FAQ structured data in this slice.

#### Footer

- Brand mark + short line: “A clearer path to work.”
- Explore column: How it works, For employers, FAQ.
- Account column: Log in, Create account, and Dashboard only for authenticated users.
- Bottom line: `© {currentYear} {name}.`
- No fake social links, legal pages, or newsletter input.

### Responsive behavior

- 320–639px: single column; nav collapses to Sheet; hero art below copy; cards stack; employer band stacks; footer columns stack.
- 640–1023px: two-column card grids where useful; hero remains single column until `lg`.
- 1024px+: hero 7/5 columns; three-card rows; employer band 2 columns.
- Sticky header must not cover anchor headings; use scroll margin.
- No horizontal overflow from rotated/decorative cards; clip art inside its panel.

---

## 13. Accessibility, empty, loading, and error states

### Accessibility

- **Skip link:** first focusable element, “Skip to content”, targets `<main id="main-content">`.
- **Landmarks:** one `<header>`, one `<nav>` with `aria-label`, one `<main>`, one `<footer>`.
- **Headings:** exactly one H1; section H2s; card/step H3s in order.
- **Links/buttons:** descriptive labels; no icon-only controls without `aria-label`; all CTA links have discernible text.
- **Focus:** visible `focus-visible` rings on all interactive elements; focus order follows visual order.
- **Mobile sheet:** Radix focus trap, Escape to close, `aria-labelledby`/`aria-describedby`; trigger restores focus.
- **FAQ:** native summary is keyboard-operable; chevron is decorative; do not remove focus outline.
- **Decorative art:** `aria-hidden="true"` and no focusable children.
- **Contrast:** body and controls meet WCAG AA; accent used only where contrast is verified.
- **Motion:** `prefers-reduced-motion` disables non-essential transforms/transitions; no auto-playing motion.
- **Zoom/text:** content remains usable at 200% zoom; no fixed-height text containers.
- **Touch targets:** at least 44×44px for primary mobile navigation/actions.
- **Language:** existing `<html lang>` is preserved; copy is English for now.

### Empty/loading/error states

- **Loading:** none required. The page is server-rendered through Inertia; no client-side data fetch or skeleton. Inertia’s existing progress bar covers navigation.
- **Empty:** all sections have approved copy. Future featured-jobs/category sections are hidden when their data arrays are empty; do not render fake cards or skeleton placeholders indefinitely.
- **Error:** unhandled route/server errors use the existing Laravel error experience. No page-specific error UI in this slice.
- **No JavaScript:** Inertia requires JS for client navigation, but the initial response is server-rendered HTML; FAQ uses native details and remains operable if client JS fails after load.

---

## 14. Test matrix

### Automated backend

| Case                   | Setup                                          | Expected                                                       |
| ---------------------- | ---------------------------------------------- | -------------------------------------------------------------- |
| Guest homepage         | `GET /`                                        | 200; Inertia `home`; no redirect                               |
| Authenticated homepage | `actingAs(User::factory()->create())`, `GET /` | 200; Inertia `home`; no redirect                               |
| Route regression       | `route('home')`                                | Resolves to `/`; existing `ExampleTest` still passes           |
| Auth regression        | Existing auth tests                            | Login/register/password flows unchanged                        |
| Dashboard regression   | Existing `DashboardTest`                       | Guest redirected; authenticated user sees dashboard            |
| No fake data           | Inertia assertions                             | No `featuredJobs`, `categories`, `stats`, or testimonial props |
| No new authorization   | Route inspection                               | Homepage has only `web` middleware; no role middleware         |

### Automated frontend/static

| Check               | Command                | Expected                                                                      |
| ------------------- | ---------------------- | ----------------------------------------------------------------------------- |
| TypeScript          | `npm run types:check`  | Pass                                                                          |
| Production build    | `npm run build`        | Pass                                                                          |
| PHP format          | `composer lint:check`  | Pass                                                                          |
| PHP static analysis | `composer types:check` | Pass                                                                          |
| Full backend suite  | `php artisan test`     | Pass                                                                          |
| Format/lint         | `npm run check`        | May still fail on pre-existing Markdown files; do not auto-fix unrelated docs |

### Manual visual/accessibility QA

- 320, 375, 768, 1024, 1440px widths; no horizontal scroll.
- Light and dark modes; contrast and focus visibility.
- Keyboard-only pass: skip link, tab order, mobile sheet, FAQ, all CTAs.
- Reduced-motion preference; no unwanted animation.
- Guest and authenticated header states.
- All links: auth routes, dashboard, in-page anchors.
- Browser zoom at 200%; text does not clip.
- No console errors or hydration warnings (SSR is not currently configured, but check dev console).

### Not applicable

- Validation, owner/non-owner, role, permission, and IDOR cases: there is no user input, model, or protected resource on this page. Do not invent tests that imply a feature exists.
- Load/pagination tests: no list or query.
- File upload/download tests: no files.
- Frontend component tests: no frontend test runner is installed; do not add one without approval.

---

## 15. Security and performance risks

### Security

1. **Public route:** no authentication required; confirm no accidental middleware group wraps `/` in `auth`.
2. **No user input/write path:** no CSRF surface, mass assignment, SQL injection, or file handling on this page.
3. **No authorization bypass:** the Dashboard link is presentation only; `/dashboard` remains guarded by existing middleware. Never hide a route only in React.
4. **No role assumptions:** do not add role names or permission labels before the custom role system exists.
5. **Sensitive data:** do not render `auth.user.email` or other private fields. The existing shared auth prop already serializes the authenticated user; the homepage must not broaden or duplicate that exposure.
6. **No unsafe HTML:** do not use `dangerouslySetInnerHTML`.
7. **External resources:** none. Keep the page free of third-party scripts, images, fonts, and tracking.
8. **Future search:** validate and bound query parameters server-side; never pass raw search input into SQL, HTML, or redirects.
9. **Caching:** do not publicly cache the Inertia response because it includes authentication state. If performance work is needed later, cache only server-side data, not the personalized document.

### Performance

1. No database query from the homepage, so scale impact is minimal.
2. No images, videos, carousels, or heavy animation libraries.
3. CSS-only texture and a small set of existing Lucide icons.
4. Reuse existing bundled font; do not add another font or weight.
5. Keep hero art to a small DOM; avoid dozens of decorative nodes.
6. Sticky header uses a translucent background; verify it does not cause expensive repaint on low-end mobile.
7. Future featured-job query must be explicitly limited and eager-loaded; do not turn the homepage into an unbounded listing.
8. Existing `npm run check` may fail on unrelated Markdown; do not “fix” unrelated docs to make the homepage check green.

### Product/content risks

- The page must not imply that job search, applications, employer onboarding, or notifications are live when they are not.
- Do not add fabricated trust signals. It is better to have a smaller, honest page than fake social proof.
- “Create account” is currently a candidate registration flow; employer CTA copy must not imply that registering grants employer capability. Employer capability remains a controlled onboarding flow in Feature 11.

---

## 16. Implementation sequence

1. **Approve this spec**, final copy, and the teal/emerald palette direction. Confirm that a pre-launch homepage without live search/featured jobs is acceptable.
2. Apply the approved teal/emerald core and homepage-scoped semantic tokens to `resources/css/app.css`, then verify light/dark values.
3. Create `resources/js/layouts/public-layout.tsx`, `site-header.tsx`, and `site-footer.tsx` using existing primitives.
4. Create `resources/js/pages/home.tsx` and the decorative hero/FAQ components.
5. Replace `resources/js/pages/welcome.tsx`; update `routes/web.php` to render `home`; update `resources/js/app.tsx` layout resolution. Keep the route name `home`.
6. Implement the sections in order: header → hero → why → how it works → employer CTA → FAQ → footer.
7. Add/adjust feature tests for guest and authenticated homepage rendering; update any existing expectations that reference `welcome`.
8. Run automated checks, then manual responsive/keyboard/dark-mode/reduced-motion QA.
9. Update `docs/ui-registry.md` with only the patterns actually implemented (tokens, public layout, FAQ pattern, hero art rule).
10. Stop. Do not wire future search, categories, or featured jobs. Those require Feature 16 and a separate approved increment.

---

## 17. Verification commands

Run after implementation, not during architecture:

```bash
php artisan route:list --name=home
php artisan test --filter=HomePageTest
php artisan test
composer lint:check
composer types:check
npm run types:check
npm run build
npm run check
```

Manual development:

```bash
composer dev
```

or the separately verified equivalents:

```bash
php artisan serve
npm run dev
```

Notes:

- `npm run check` currently has unrelated pre-existing Markdown formatting failures. Report them; do not auto-fix unrelated files without approval.
- No frontend visual-regression runner is installed. Do not claim automated accessibility or visual tests passed; state manual checks separately.
- Do not run `composer setup`, install packages, or change lockfiles for this feature.

---

## 18. Decisions requiring approval

| ID  | Decision                                                                       | Recommendation                                           |
| --- | ------------------------------------------------------------------------------ | -------------------------------------------------------- |
| D1  | `/` is the public candidate-first homepage; employer content is secondary      | Approve as written                                       |
| D2  | This slice is pre-launch: no functional job search or featured jobs yet        | Approve; implement live job search only after Feature 16 |
| D3  | Deep-emerald and teal palette (supersedes the initial warm-editorial proposal) | Approved 2026-10-26; applied through semantic tokens     |
| D4  | Extract a reusable `PublicLayout` now for future public pages                  | Approve; avoids duplicating header/footer later          |
| D5  | No fake jobs, companies, testimonials, counts, or trust logos                  | Approve as a hard rule                                   |
| D6  | Future featured-job/category section is hidden when data is empty              | Approve; no skeleton/fake-data fallback                  |
| D7  | Keep route name `home` and replace `welcome`                                   | Approve; preserves existing links/tests                  |

### Open product questions (non-blocking for this spec)

1. Is `/` definitely the public homepage, or should the first screen be a signed-in dashboard? This spec assumes public `/`.
2. Should the homepage launch before Feature 16 with no job search, or should implementation wait until Feature 16 is available? This spec recommends shipping the public shell now and adding search later.
3. ~~Is the proposed warm accent acceptable?~~ Resolved: the teal / deep-emerald / soft-fog palette was approved on 2026-10-26.
4. Are the draft FAQ answers and planned-capability copy approved for a pre-launch page?

---

## Approval gate

Approved by the user in conversation on 2026-10-26 and implemented in the same change. The pre-launch scope (D2), public `home` route replacement (D7), and the palette amendment (D3) were implemented. The original warm-editorial token proposal was superseded by the approved teal / deep-emerald / soft-fog palette, which is now applied through the core semantic tokens and the homepage-scoped tokens. Live job search, categories, and featured jobs remain deferred to Feature 16 and must be handled by a separate approved increment.
