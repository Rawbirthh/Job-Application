# Feature 04 — Shared application shell and role-aware dashboard routing

**Metadata:**

- Feature ID: 04
- Title: Shared application shell and role-aware dashboard routing
- Spec Type: Feature specification
- Source: `project-overview.md` § Feature 4
- Dependencies: Feature 2 (Authentication), Feature 3 (Custom roles, permissions, middleware, Policies)
- Status: **APPROVED**
- Created: 2026-10-29
- Updated: 2026-10-29

---

## Goal

Provide consistent layouts and landing pages for authenticated personas. Implement a role/permission-aware navigation menu that hides inaccessible links and role-specific landing destinations, ensuring users reach appropriate dashboards based on their confirmed permissions.

## Non-goals

- Full analytics, applicant statistics, or feature-specific management tables (those belong to later features).
- User profile editing or account settings beyond what Feature 2 provides.
- Real-time notifications or messaging (Feature 25).

## Actors and flows

**Primary actors:**

- Platform administrator (role `platform_admin`): Should land on admin dashboard with admin-specific navigation.
- Employer (role `employer`): Should land on employer dashboard with employer-specific navigation.
- Job seeker (role `job_seeker`): Should land on candidate dashboard with candidate-specific navigation.
- Unauthenticated user: Already redirected to login by route middleware.

**Key flows:**

1. Authenticated user visits `/dashboard` → server confirms permission → renders role-specific page.
2. Authenticated user navigates via sidebar/header → permission-aware nav reflects available actions.
3. Authenticated user with no recognized role or missing role → safe fallback/error state.
4. Authenticated user attempts direct route to forbidden page (e.g., `/admin` without `admin.access`) → 403 Forbidden.

---

## Scope and acceptance criteria

**In scope:**

- Shared responsive app layout with sidebar/header structure using existing shadcn/ui components.
- Role/permission-aware navigation menu that hides unavailable links but never replaces server authorization.
- Role-specific landing destination logic based on server-confirmed permissions.
- Breadcrumbs and page headings where consistent with starter kit patterns.
- Flash messages and user menu from shared Inertia props.
- Accessible mobile navigation using existing Sheet component.
- Empty dashboard content for each persona (placeholders acceptable).

**Acceptance criteria (checkable statements):**

- Signed-in user with `admin.access` permission lands on `/admin` dashboard or equivalent role-specific page.
- Signed-in user with `employer` role lands on employer dashboard.
- Signed-in user with `job_seeker` role lands on candidate/job seeker dashboard.
- Navigation menu shows only links the current user can access based on permissions (e.g., `/admin/users` only if `admin.access` + `users.view`).
- User cannot bypass server authorization by directly visiting a forbidden route; 403 response is returned.
- Mobile navigation is keyboard accessible and responsive at phone widths.
- Breadcrumbs display correctly for app pages.
- All navigation uses named routes to prevent brittle hardcoded paths.
- Frontend type checks pass (`npm run types:check`).
- Frontend build succeeds (`npm run build`).

---

## Existing code and conventions inspected

**Backend routing and auth:**

- `routes/web.php`: Current structure has `/` (home, public), `/dashboard` (generic auth required), and `/admin/*` routes.
- `routes/admin.php`: Admin routes already protected by `permission:admin.access` middleware.
- `app/Http/Middleware/HandleInertiaRequests.php`: Shared props include `auth.user` (id, name, email only) but **do not include roles/permissions**.
- `app/Http/Middleware/EnsureUserHasPermission.php`: Middleware for permission checks on routes (Feature 3).
- `app/Policies/UserPolicy.php`: Example Policy pattern established (Feature 3).

**Frontend layout and components:**

- `resources/js/app.tsx`: Inertia app with layout routing — `PublicLayout` for `home`, `AuthLayout` for `auth/*`, settings layout for `settings/*`, default `AppLayout` for others.
- `resources/js/layouts/app-layout.tsx`: Basic AppLayout wrapper; delegates to `AppSidebarLayout`.
- `resources/js/layouts/app/app-sidebar-layout.tsx`: Responsive layout with sidebar, header, breadcrumbs, content.
- `resources/js/components/app-sidebar.tsx`: Sidebar with `mainNavItems` (currently hardcoded Dashboard only) and footer links.
- `resources/js/components/nav-main.tsx`: Navigation menu renderer; accepts items array.
- `resources/js/components/app-header.tsx`: Header with responsive mobile sheet navigation.
- `resources/js/types/`: Authorization types already defined in `types/authorization.ts` (Role, Permission, etc., though not currently shared as Inertia props).

**Current dashboard:**

- `resources/js/pages/dashboard.tsx`: Renders placeholder layout (three stat cards and a main area).
- `tests/Feature/DashboardTest.php`: Tests that authenticated users can visit `/dashboard`; does not test role-aware routing.

**Tailwind and shadcn/ui:**

- Global palette: teal / deep emerald / soft fog (60-30-10) per memory.md.
- Sidebar, Sheet, NavigationMenu, Avatar, Button, and other components already installed.

---

## Detailed design decisions

### 1. Inertia props enhancement

**Decision:** Extend `HandleInertiaRequests.share()` to include user roles and permissions.

**Rationale:**

- Navigation menu conditionally renders based on permissions; frontend must know what the user can access.
- Server controls which roles/permissions are assigned; frontend only reads them.
- Avoid duplicate authorization logic; server decides accessibility, frontend implements UI.

**Implementation approach:**

- Add `auth.roles` and `auth.permissions` arrays to shared props (role slugs and permission slugs).
- Example: `auth.roles = ['employer']`, `auth.permissions = ['jobs.create', 'jobs.view']`.
- Helper method in Inertia middleware or service to fetch user's roles/permissions efficiently.

### 2. Dashboard routing strategy

**Decision:** Implement a server-side redirect from `/dashboard` to the appropriate role-specific page.

**Rationale:**

- Server is the authority on user permissions; client cannot determine which role to trust.
- Avoid race conditions or multiple dashboard types on one route.
- Clear, predictable routing.

**Routes:**

- `/dashboard` (generic, currently exists) → controller determines user role(s) and redirects to role-specific dashboard.
- `/admin/dashboard` (admin only, protected by `permission:admin.access`) → renders admin dashboard page.
- `/employer/dashboard` (employer only, protected by employer role check) → renders employer dashboard page.
- `/candidate/dashboard` (job seeker only, protected by job_seeker role check) → renders candidate dashboard page.

**Controller logic:**

- New `DashboardRedirectController` or update existing dashboard route handler.
- Fetch user's roles/permissions.
- If `admin.access` permission exists → redirect to `/admin/dashboard`.
- Else if `employer` role exists → redirect to `/employer/dashboard`.
- Else if `job_seeker` role exists → redirect to `/candidate/dashboard`.
- Else → render error page or fallback.

### 3. Role-aware navigation menu

**Decision:** Build a permission-based navigation structure; dynamically filter items based on user permissions.

**Rationale:**

- UI should reflect what the user can actually do.
- Reduces confusion (user doesn't see unavailable actions).
- Server authorization remains the source of truth.

**Navigation structure:**

```
{
  section: 'Platform',
  items: [
    { title: 'Dashboard', href: 'dashboard', icon: LayoutGrid, requiredPermission: null },
    { title: 'Jobs', href: 'employer.jobs.index', icon: Briefcase, requiredPermission: 'jobs.view', requiredRole: 'employer' },
    { title: 'Applications', href: 'employer.applications.index', icon: FileText, requiredPermission: 'applications.view', requiredRole: 'employer' },
    { title: 'Admin Panel', href: 'admin.users.index', icon: Settings, requiredPermission: 'admin.access' },
  ],
}
```

**Frontend implementation:**

- `NavMain` component receives filtered items based on `auth.permissions`.
- `AppSidebar` filters navigation items before passing to `NavMain`.
- Display name, icon, href for allowed items; omit items user cannot access.

### 4. Permission-based routing safeguard

**Decision:** Maintain server-side middleware and Policies; never rely on client-only conditionals for authorization.

**Implementation:**

- Routes protected by `permission` middleware (already in place for `/admin/*`).
- Future role-specific routes follow same pattern: `Route::get('/employer/dashboard', ...)->middleware('permission:employer.dashboard.view')`.
- Client-side navigation hides links but does not grant access; server enforces.

### 5. Flash messages and user menu

**Decision:** Leverage existing Inertia middleware and component patterns.

**Implementation:**

- Flash messages already handled by default Inertia middleware.
- `UserMenuContent` component displays user info and logout.
- `AppHeader` includes user avatar menu.

### 6. Accessibility and responsive design

**Decision:** Use existing shadcn/ui components and maintain mobile-first design.

**Implementation:**

- Sidebar collapses to icons on small screens (already in `Sidebar` component).
- Mobile navigation uses `Sheet` component (already in `AppHeader`).
- Keyboard navigation via semantic HTML and focus management.
- ARIA labels for icons and interactive elements.
- Breadcrumbs are semantic and navigate via Links.

---

## Backend implementation steps

### Routes

**File: `routes/web.php`**

- Update dashboard route to redirect to role-specific dashboard based on user permissions.
- Keep `/dashboard` as a named route for navigation; the controller handles the redirect.

```php
Route::middleware(['auth'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'redirect'])->name('dashboard');

    Route::prefix('admin')->name('admin.')->middleware(['permission:admin.access'])->group(function () {
        Route::get('dashboard', [AdminDashboardController::class, 'show'])->name('dashboard');
    });

    Route::prefix('employer')->name('employer.')->middleware(['permission:employer.dashboard.view'])->group(function () {
        Route::get('dashboard', [EmployerDashboardController::class, 'show'])->name('employer.dashboard');
    });

    Route::prefix('candidate')->name('candidate.')->middleware(['permission:candidate.dashboard.view'])->group(function () {
        Route::get('dashboard', [CandidateDashboardController::class, 'show'])->name('candidate.dashboard');
    });
});
```

### Controllers

**New: `app/Http/Controllers/DashboardController.php`**

- Redirect method that determines user role and redirects to appropriate dashboard.
- Fetch user roles efficiently (single query with eager load).

**New: `app/Http/Controllers/AdminDashboardController.php`**

- Show method returns admin dashboard page with props.

**New: `app/Http/Controllers/EmployerDashboardController.php`**

- Show method returns employer dashboard page with props.

**New: `app/Http/Controllers/CandidateDashboardController.php`**

- Show method returns candidate dashboard page with props.

### Inertia middleware enhancement

**File: `app/Http/Middleware/HandleInertiaRequests.php`**

- Add logic to include `auth.roles` (array of role slugs) and `auth.permissions` (array of permission slugs).
- Use a service method to fetch roles and permissions for the current user.
- Cache or eager load in one query if possible.

---

## Frontend implementation steps

### Inertia types

**File: `resources/js/types/auth.ts`** (update if necessary)

- Ensure `auth.roles` and `auth.permissions` are typed.
- Example:

```typescript
export interface AuthUser {
    id: number;
    name: string;
    email: string;
    roles?: string[]; // role slugs
    permissions?: string[]; // permission slugs
}
```

### Navigation structure

**File: `resources/js/lib/navigation.ts`** (new file)

- Define navigation items with required permissions/roles.
- Export function to filter items based on user permissions.

### Sidebar/header components

**Files: `resources/js/components/app-sidebar.tsx`, `resources/js/components/app-header.tsx`**

- Import filtered navigation items.
- Pass `auth.permissions` and `auth.roles` to filter logic.
- Render only accessible items.

### Dashboard pages

**Files: `resources/js/pages/admin/dashboard.tsx`, `resources/js/pages/employer/dashboard.tsx`, `resources/js/pages/candidate/dashboard.tsx`** (new files)

- Create role-specific dashboard pages.
- Each page receives role-appropriate data via Inertia props.
- Placeholders for content (stat cards, empty states).

### Layout routing

**File: `resources/js/app.tsx`**

- Add layout mapping for new dashboard routes (e.g., `admin/dashboard`, `employer/dashboard`).
- All use `AppLayout` by default.

---

## Middleware and authorization

**File: `bootstrap/app.php` or middleware registration:**

- Ensure `EnsureUserHasPermission` middleware is available for route protection.
- Already implemented in Feature 3.

---

## Form Requests

**Not applicable for this feature.** Navigation and dashboard routing do not accept user input requiring validation.

---

## Models and relationships

**Not applicable for this feature.** Existing Role and Permission models (Feature 3) are reused.

---

## Query shape and performance

**Inertia middleware query:**

- Fetch user roles: `User::find($id)->roles()->pluck('slug')` (or cached).
- Fetch user permissions: `User::find($id)->permissions()->pluck('slug')` (or cached).
- Target: 1–2 queries per request at most (consider eager loading in one query with select).

**No pagination or complex filtering required.**

---

## Service/Repository decisions

**Decision:** Use a lightweight helper function in the Inertia middleware, not a full Service.

**Rationale:**

- Middleware shares data; role/permission fetching is straightforward.
- Avoid over-engineering for a simple lookup.

**Implementation:**

- Helper method in `app/Http/Middleware/HandleInertiaRequests.php` or a simple utility class.

---

## React/Inertia pages

### Dashboard redirect logic

- **Page:** `resources/js/pages/dashboard.tsx` (existing, may be repurposed or kept as a fallback).

### Role-specific dashboard pages

- **Page:** `resources/js/pages/admin/dashboard.tsx` (new)
    - Props: `adminStats` (placeholder object), `recentActivity` (empty array).
    - Shows admin overview and empty states.

- **Page:** `resources/js/pages/employer/dashboard.tsx` (new)
    - Props: `companyStats`, `recentJobs` (empty array).
    - Shows employer overview and empty states.

- **Page:** `resources/js/pages/candidate/dashboard.tsx` (new)
    - Props: `savedJobs`, `applicationStats` (empty array).
    - Shows candidate overview and empty states.

### Navigation and layout

- **Component:** `resources/js/components/app-sidebar.tsx` (update)
    - Dynamically render `NavMain` items based on `auth.permissions`.
    - Use `usePage().props.auth` to access user roles/permissions.

- **Component:** `resources/js/components/app-header.tsx` (update)
    - Apply same permission filtering for mobile navigation.

---

## Accessible and responsive design

**Mobile navigation:**

- Use existing `Sheet` component in header.
- Keyboard accessible via Tab and Enter keys.
- Screen reader labels for menu sections.

**Breadcrumbs:**

- Render for pages where breadcrumbs.length > 1.
- Use semantic `<nav>` element (handled by `Breadcrumbs` component).

**Sidebar:**

- Responsive collapse to icon-only mode on small screens.
- Existing `Sidebar` component handles this.

**Form errors and states:**

- Not applicable (no forms in this feature).

**Empty states:**

- Dashboard pages render empty content with clear messaging.

---

## Tests and verification

### Backend tests

**File: `tests/Feature/DashboardRoutingTest.php`** (new)

Test cases:

1. Authenticated admin user visiting `/dashboard` redirects to `/admin/dashboard`.
2. Authenticated employer user visiting `/dashboard` redirects to `/employer/dashboard`.
3. Authenticated job seeker user visiting `/dashboard` redirects to `/candidate/dashboard`.
4. Unauthenticated user visiting `/dashboard` redirects to login.
5. Admin user can access `/admin/dashboard`; employer cannot (403).
6. Employer user can access `/employer/dashboard`; admin cannot (403).
7. Candidate user can access `/candidate/dashboard`; admin cannot (403).
8. User with no roles/permissions receives a sensible error or fallback.

### Frontend tests

**Manual verification:**

- Type checks pass: `npm run types:check`.
- Build passes: `npm run build`.
- Sidebar displays only accessible items for each role (manual browser inspection or snapshot test).
- Navigation links are functional (Inertia routing works).

### Test command

```bash
php artisan test tests/Feature/DashboardRoutingTest.php
npm run types:check
npm run build
```

---

## Security considerations

1. **Server-side authorization:** All routes protected by middleware; client-side navigation filtering is UI convenience, not a security boundary.
2. **Permission caching:** If caching user permissions, invalidate on role/permission assignment (Feature 3 `RoleAssignmentService` should handle this).
3. **Direct route access:** User cannot bypass middleware by guessing a URL; 403 response is enforced.
4. **Role escalation:** User cannot submit a role ID or permission name from the client; server determines roles via database.

---

## Risks, dependencies, and assumptions

**Risks:**

- If Inertia props include roles/permissions but middleware is misconfigured, stale permissions could be displayed (UI would show inaccessible links).
    - Mitigation: Server middleware enforces authorization; UI display is secondary.
- Navigation structure becomes complex as more features add admin/employer/candidate actions.
    - Mitigation: Use a configuration-driven approach (define navigation in a constant) and keep it maintainable.

**Dependencies:**

- Feature 2 (Authentication): Must be complete; user must be authenticated to reach dashboard.
- Feature 3 (Roles/Permissions): Must be complete; user must have assigned roles for routing to work.

**Assumptions:**

- Every authenticated user has at least one role assigned (enforced by Feature 3's registration/provisioning).
- Navigation items and their required permissions are documented in code (not in database).
- Employer and job seeker roles are distinct from admin role (non-overlapping by design).

---

## Unresolved questions and decisions pending user input

1. **Navigation strategy:** Should the sidebar show all possible navigation items with disabled state, or hide inaccessible items entirely?
    - **Recommendation:** Hide inaccessible items (cleaner UX; no visual clutter).

2. **Dashboard fallback for multi-role users:** If a user has both `employer` and `admin` roles, which dashboard should `/dashboard` route to?
    - **Recommendation:** Prioritize `admin` (admin.access > employer > job_seeker). Can be customized per product decision.

3. **Breadcrumbs for role-specific dashboards:** Should breadcrumbs show the dashboard link?
    - **Recommendation:** Yes, for consistency; breadcrumbs already render only when length > 1.

4. **Flash message support:** Should Feature 4 include logic to display flash messages (e.g., "Role assigned successfully")?
    - **Recommendation:** Use existing Inertia flash support; not specific to Feature 4. Feature 3 already handles it.

---

## Ordered implementation steps

1. **Backend:** Update `HandleInertiaRequests` middleware to include `auth.roles` and `auth.permissions` in shared props.
2. **Backend:** Create `DashboardController.redirect()` to route authenticated users to role-specific dashboards.
3. **Backend:** Create role-specific dashboard controllers (`AdminDashboardController`, `EmployerDashboardController`, `CandidateDashboardController`).
4. **Backend:** Update `routes/web.php` to add new dashboard routes.
5. **Backend:** Write dashboard routing tests.
6. **Frontend:** Update `resources/js/types/auth.ts` to include roles/permissions in `AuthUser` type.
7. **Frontend:** Create navigation structure with permission filtering logic in `resources/js/lib/navigation.ts`.
8. **Frontend:** Update `AppSidebar` and `AppHeader` to use permission-aware navigation.
9. **Frontend:** Create role-specific dashboard pages (`admin/dashboard.tsx`, `employer/dashboard.tsx`, `candidate/dashboard.tsx`).
10. **Frontend:** Update `resources/js/app.tsx` layout routing for new pages.
11. **Frontend:** Run type checks and build; fix any errors.
12. **Integration:** Run full test suite and verify role-aware routing in browser (manual test).

---

## Summary

Feature 4 establishes a consistent, secure application shell with role-aware navigation and landing pages. It leverages existing shadcn/ui components, Inertia props, and the custom authorization system from Feature 3. The implementation is straightforward: extend middleware to share roles/permissions, implement server-side dashboard routing, filter navigation items on the client, and create role-specific dashboard pages with empty content. All authorization decisions remain on the server; the UI is responsive and accessible.

**Key files to create/update:**

- Backend: `DashboardController.php`, `AdminDashboardController.php`, `EmployerDashboardController.php`, `CandidateDashboardController.php`, `routes/web.php`, `HandleInertiaRequests.php`, `tests/Feature/DashboardRoutingTest.php`.
- Frontend: `resources/js/lib/navigation.ts`, `resources/js/pages/admin/dashboard.tsx`, `resources/js/pages/employer/dashboard.tsx`, `resources/js/pages/candidate/dashboard.tsx`, update `app-sidebar.tsx`, `app-header.tsx`, `app.tsx`, `types/auth.ts`.
