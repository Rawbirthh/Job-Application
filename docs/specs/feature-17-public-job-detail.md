---
Feature ID: 17
Title: Public job detail page
Status: APPROVED
Type: Feature Implementation
Source: project-overview.md (Phase 3 — Public discovery and candidate profile)
Dependencies: Feature 16 (Public job listing with server-side pagination)
Created: 2026-11-03
Approved: 2026-11-03
---

# Feature 17 Specification: Public job detail page

## Objective

Show the complete public job description for a currently visible listing, accessible to both guests and authenticated users. Provide a clear call-to-action appropriate to the viewer's authentication status.

## Goals & Non-Goals

### Goals

- Provide a public `/jobs/{job}` detail page accessible without authentication.
- Display comprehensive job information: title, company profile, full description, responsibilities, requirements, workplace arrangement, employment type, experience level, location, published date, application deadline, and disclosed salary.
- Enforce strict visibility rules: only `published` jobs from approved active companies are viewable.
- Handle non-public records (draft, rejected, closed, non-existent) consistently without leaking private data.
- Provide contextual call-to-action: guests see sign-in/register path; authenticated job seekers see application flow entry (Feature 21 implementation).
- Responsive, accessible layout using existing shadcn/ui components and PublicLayout.

### Non-Goals

- Application submission functionality (Feature 21).
- Saved jobs bookmarking (Feature 20).
- Related jobs or recommendations (deferred).
- Edit/admin actions (those belong to employer/admin contexts).
- Internal moderation notes, rejection reasons, or private company fields.

## Dependencies Verified

**Prerequisite features implemented:**

- Feature 16: Public job listing with search, filters, and pagination; `PublicJobController` established; `jobs.index` route exists; `Job::published()` scope and company eligibility query patterns confirmed.
- Feature 5–9: Job categories, employment types, workplace arrangements, experience levels, and pay periods are configured catalogue tables with active/inactive state.
- Feature 12: Company profiles with public fields (`name`, `slug`, `logo_path`, `overview`, `website`, `location`, `industry`, `size_band`); `is_active` boolean.
- Feature 14: Job moderation lifecycle with `status` column (`draft`, `pending_review`, `published`, `rejected`, `closed`); `rejection_reason` migration added.

**Schema inspection:**

- `job_postings` table columns: `id`, `company_id`, `title`, `description`, `responsibilities`, `requirements`, `category_id`, `employment_type_id`, `workplace_arrangement_id`, `experience_level_id`, `salary_min`, `salary_max`, `salary_currency`, `salary_negotiable`, `pay_period_id`, `location`, `application_deadline`, `status`, `rejection_reason`, `created_at`, `updated_at`.
- Foreign keys with `restrictOnDelete` to catalogues and `companies`.
- No slug on `job_postings`; ID-based routing is appropriate.

## Existing Code & Conventions Inspected

- **Controller**: `PublicJobController` (`app/Http/Controllers/PublicJobController.php`) handles public job listing with visibility scoping.
- **Model**: `Job` (`app/Models/Job.php`) with `published()`, `draft()`, `closed()`, `rejected()`, `pendingReview()`, `forOwner()`, `forCompany()` scopes; relationships to `company`, `category`, `employmentType`, `workplaceArrangement`, `experienceLevel`, `payPeriod`.
- **Layout**: `PublicLayout` (`resources/js/layouts/public-layout.tsx`) with `SiteHeader`, `SiteFooter`, skip-to-content link, accessible main landmark.
- **Routes**: Public `GET /jobs` route exists at `routes/web.php:13`; employer job routes under `employer.jobs.*` namespace require authentication and role middleware.
- **Frontend**: `resources/js/pages/jobs/index.tsx` provides typed `Job` interface with all relevant fields and navigates to `/jobs/${job.id}` via "View Role" button.
- **Pagination & UI**: Existing shadcn/ui components (Badge, Button, Card implicitly used); responsive grid layout; accessible labels and empty states.
- **Auth context**: `HandleInertiaRequests` middleware shares `auth.user` (with roles/permissions) to Inertia pages.

## Routes & Middleware

**New route:**

```php
Route::get('/jobs/{job}', [PublicJobController::class, 'show'])->name('jobs.show');
```

- **Middleware**: Public route (no authentication required); uses Laravel's implicit route-model binding for `Job` model.
- **Route parameter**: `{job}` resolves to `Job` model instance by ID; 404 if not found or non-numeric ID.

## Authorization & Visibility Scoping

**No authorization required to view the route**, but visibility is enforced in the controller:

- Only `published` jobs are viewable.
- Job's company must exist, be active (`is_active = true`), and have an approved owner (`owner.status = 'approved'`).
- If a job does not meet these criteria (draft, rejected, closed, company inactive/suspended, or non-existent), return **404** to avoid leaking existence/state of private records. Do not return distinct error messages that reveal internal states to unauthorized viewers.

**Controller implementation pattern:**

```php
public function show(Job $job): Response
{
    // Visibility check
    if (
        $job->status !== 'published' ||
        !$job->company ||
        !$job->company->is_active ||
        $job->company->owner->status !== EmployerStatus::Approved->value
    ) {
        abort(404);
    }

    // Eager load relationships
    $job->load([
        'company:id,name,slug,logo_path,overview,website,location,industry,size_band',
        'category:id,name',
        'employmentType:id,name',
        'workplaceArrangement:id,name',
        'experienceLevel:id,name',
        'payPeriod:id,name',
    ]);

    return Inertia::render('jobs/show', [
        'job' => [
            'id' => $job->id,
            'title' => $job->title,
            'description' => $job->description,
            'responsibilities' => $job->responsibilities,
            'requirements' => $job->requirements,
            'location' => $job->location,
            'salary_min' => $job->salary_min,
            'salary_max' => $job->salary_max,
            'salary_currency' => $job->salary_currency,
            'salary_negotiable' => $job->salary_negotiable,
            'application_deadline' => $job->application_deadline?->toIso8601String(),
            'published_at' => $job->created_at?->toIso8601String(),
            'company' => $job->company ? [
                'id' => $job->company->id,
                'name' => $job->company->name,
                'slug' => $job->company->slug,
                'logo_url' => $job->company->logo_url,
                'overview' => $job->company->overview,
                'website' => $job->company->website,
                'location' => $job->company->location,
                'industry' => $job->company->industry,
                'size_band' => $job->company->size_band,
            ] : null,
            'category' => $job->category ? ['id' => $job->category->id, 'name' => $job->category->name] : null,
            'employment_type' => $job->employmentType ? ['id' => $job->employmentType->id, 'name' => $job->employmentType->name] : null,
            'workplace_arrangement' => $job->workplaceArrangement ? ['id' => $job->workplaceArrangement->id, 'name' => $job->workplaceArrangement->name] : null,
            'experience_level' => $job->experienceLevel ? ['id' => $job->experienceLevel->id, 'name' => $job->experienceLevel->name] : null,
            'pay_period' => $job->payPeriod ? ['id' => $job->payPeriod->id, 'name' => $job->payPeriod->name] : null,
        ],
    ]);
}
```

**Fields excluded from public view:**

- Internal moderation fields: `rejection_reason`.
- Any application counts or applicant data.
- Company owner identity (`owner_user_id`).
- Timestamps other than `published_at` (use `created_at` as publication timestamp; `updated_at` is internal).

## Query Shape & Performance

- Use implicit route-model binding for `Job` lookup by ID.
- Eager load relationships in one query to avoid N+1: `company`, `category`, `employmentType`, `workplaceArrangement`, `experienceLevel`, `payPeriod`.
- Select only public fields on relationships (see controller pattern above).
- No pagination required for a single-record detail page.

## React / Inertia Page & Components

**Page Component**: `resources/js/pages/jobs/show.tsx`

**TypeScript Props Interface:**

```typescript
interface JobDetail {
    id: number;
    title: string;
    description: string;
    responsibilities: string | null;
    requirements: string | null;
    location: string | null;
    salary_min: string | null;
    salary_max: string | null;
    salary_currency: string | null;
    salary_negotiable: boolean;
    application_deadline: string | null;
    published_at: string | null;
    company: {
        id: number;
        name: string;
        slug: string;
        logo_url: string | null;
        overview: string | null;
        website: string | null;
        location: string | null;
        industry: string | null;
        size_band: string | null;
    } | null;
    category: { id: number; name: string } | null;
    employment_type: { id: number; name: string } | null;
    workplace_arrangement: { id: number; name: string } | null;
    experience_level: { id: number; name: string } | null;
    pay_period: { id: number; name: string } | null;
}

interface Props {
    job: JobDetail;
}
```

**UI Layout & Sections:**

1. **Breadcrumb / Back Navigation**: Link back to `/jobs` listing.
2. **Job Header**:
    - Job title (h1).
    - Company logo and name.
    - Location, employment type, workplace arrangement, experience level badges.
    - Published date and application deadline if present.
3. **Company Information Card** (sidebar or section):
    - Company logo, name, location, industry, size band.
    - Company overview (first paragraph or truncated).
    - Website link (external, `rel="noopener noreferrer"`).
4. **Job Details** (main content area):
    - **Description** section (rich text preserved; use `whitespace-pre-wrap` or convert newlines to `<br>` if plain text).
    - **Responsibilities** section (if present).
    - **Requirements** section (if present).
    - **Compensation** section: display salary range, currency, pay period; "Salary negotiable" if applicable and no range provided.
    - **Application Deadline** (if present, with visual emphasis if approaching).
5. **Call-to-Action**:
    - **Guest users**: Prominent button "Sign in to apply" linking to `/login` with intended return to this job page, and secondary link "Register" to `/register`.
    - **Authenticated job seekers**: Prominent button "Apply for this role" (link to `/jobs/{job}/apply` placeholder; Feature 21 will implement application submission). Disabled state if deadline passed.
    - **Authenticated employers/admins**: No apply button; optionally show "You are viewing as [role]" note or hide CTA section.

**Accessibility & Responsive Design:**

- Semantic HTML: `<h1>` for job title, `<h2>` for section headings, proper heading hierarchy.
- Accessible external link pattern for company website (icon + screen reader text).
- Responsive layout: single-column on mobile, two-column (main + sidebar) on desktop using Tailwind responsive utilities.
- Focus states on interactive elements.
- `<Head>` with page title (job title + company name) and meta description (truncated job description).

**Empty/Error States:**

- Non-existent/non-public job returns 404 from controller; Inertia default error page handles this.
- Gracefully handle missing optional fields (responsibilities, requirements, salary, deadline, company overview) without breaking layout.

## Form Requests & Validation

- **None required** for this read-only feature.
- Route-model binding handles ID validation; non-numeric or non-existent IDs result in 404.

## Models, Relationships & Queries

- **No new migrations or model changes required.**
- Reuse existing `Job` model and relationships.
- Reuse `Job::published()` scope pattern and company eligibility check from Feature 16.

## Services & Repositories

- **Not needed.** Simple read-only controller action with explicit visibility check and Inertia response.
- Keep controller method thin; business logic is straightforward eligibility check.

## Tests & Edge Cases

**Feature Tests** (`tests/Feature/PublicJobDetailTest.php`):

1. **Guest access**:
    - Guest can view a published job from an approved active company.
    - Response includes job title, description, company information, category, employment type, etc.
    - CTA shows "Sign in to apply" button.
2. **Authenticated job seeker access**:
    - Authenticated user with `job_seeker` role can view the same job.
    - CTA shows "Apply for this role" button (link to `/jobs/{job}/apply` placeholder).
3. **Visibility rules**:
    - Draft job returns 404.
    - Pending review job returns 404.
    - Rejected job returns 404.
    - Closed job returns 404.
    - Published job from inactive company returns 404.
    - Published job from company with suspended/unapproved owner returns 404.
4. **Non-existent job**:
    - Invalid job ID (non-numeric) returns 404.
    - Non-existent job ID returns 404.
5. **Private field exclusion**:
    - Response does not include `rejection_reason`.
    - Response does not include `owner_user_id`.
    - Response does not include internal timestamps other than `published_at`.
6. **Optional field handling**:
    - Job without responsibilities renders without error.
    - Job without requirements renders without error.
    - Job without salary displays "Salary negotiable" if flag is true, or hides salary section.
    - Job without deadline renders without deadline section.
7. **Eager loading verification**:
    - Inspect query count to ensure relationships are eager loaded (use `\DB::enableQueryLog()` or similar in test setup if needed for assertion; otherwise verify by inspection).

**Test Execution:**

- Run `php artisan test --filter=PublicJobDetailTest` to verify behavior.
- Run full suite `php artisan test` to ensure no regressions.

## Security Checks

- **IDOR Prevention**: Visibility enforced server-side; non-public jobs return 404 regardless of URL manipulation.
- **Information Disclosure**: Private fields (`rejection_reason`, owner identity) excluded from response.
- **XSS Prevention**: All user-generated content (job description, responsibilities, requirements, company overview) is rendered as text or sanitized HTML if rich-text editor is used (current implementation appears to be plain text; use `whitespace-pre-wrap` CSS class to preserve formatting without executing scripts).
- **External Link Safety**: Company website links use `rel="noopener noreferrer"` to prevent `window.opener` access.
- **Rate Limiting**: Public route; consider adding throttle middleware (e.g., `throttle:60,1`) if abuse is observed; defer until Feature 30 (reporting) or analytics indicate need.

## Implementation Steps

1. **Backend (delegate to `backend-engineer` if using subagents)**:
    - Add `show` method to `PublicJobController` with visibility check, eager loading, and Inertia response.
    - Register `GET /jobs/{job}` route in `routes/web.php` as `jobs.show`.
2. **Frontend (delegate to `frontend-engineer` if using subagents)**:
    - Create `resources/js/pages/jobs/show.tsx` with typed props, layout, and CTA logic.
    - Reuse existing shadcn/ui components (Badge, Button, Card implicitly, Separator).
    - Implement responsive two-column layout (main content + company sidebar).
    - Conditional CTA based on `auth.user` presence and role.
3. **Tests (delegate to `test-engineer` after implementation)**:
    - Create `tests/Feature/PublicJobDetailTest.php` with visibility, access, and field exclusion tests.
    - Run tests and verify all pass.
4. **Frontend build**:
    - Run `npm run build` to compile TypeScript and ensure no type errors.
5. **Integration verification**:
    - Manual browser test: navigate from `/jobs` listing to a job detail page; verify layout, CTA, and back navigation.
    - Verify 404 behavior for draft/closed/non-existent jobs.
    - Test guest vs. authenticated user CTA differences.

## Risks & Unresolved Questions

**Risks:**

- **Performance**: Single job query with eager loading is efficient; no N+1 risk.
- **Rich Text Handling**: Current schema stores `description`, `responsibilities`, `requirements` as `text` columns; assuming plain text with newlines. If rich HTML is introduced later, sanitization and safe rendering (e.g., DOMPurify) must be added.
- **Application Link**: "Apply for this role" button links to `/jobs/{job}/apply` (placeholder); Feature 21 will implement the application submission form at that route. Until then, clicking may result in 404; consider disabling or showing "Coming soon" tooltip if Feature 21 is not implemented yet.

**Unresolved Questions:**

- **Company public profile page**: Should company name be a link to a dedicated company profile page (e.g., `/companies/{slug}`)? Not in current roadmap; defer to later feature if needed. For now, company name is static text.
- **Application deadline urgency indicator**: Should jobs with deadlines within 7 days show a visual "Apply soon" badge or countdown? Defer as enhancement; current spec shows deadline as static date.
- **Related jobs / "More from this company" section**: Defer to recommendations feature (Feature 34) or separate enhancement.

**Assumptions:**

- Application link (`/jobs/{job}/apply`) will be implemented in Feature 21; until then, consider making the button `disabled` with a tooltip "Application submission coming soon" or omit the button entirely and show "Application feature coming soon" message.
- Company website URLs are validated during company profile creation (Feature 12); assume they are safe external links.
- Job descriptions are plain text; no rich-text editor or HTML sanitization needed yet.

## Acceptance Criteria

- [ ] Guest users can view published jobs from approved active companies at `/jobs/{job}`.
- [ ] Authenticated users (job seekers, employers, admins) can view the same published jobs.
- [ ] Job detail page displays all public fields: title, description, responsibilities, requirements, category, employment type, workplace arrangement, experience level, location, salary (if disclosed), deadline (if set), published date.
- [ ] Company information is displayed: name, logo, location, industry, size band, overview, website link.
- [ ] Call-to-action is contextual:
    - Guests see "Sign in to apply" button linking to `/login`.
    - Authenticated job seekers see "Apply for this role" button linking to `/jobs/{job}/apply`.
    - Employers/admins see no apply button or a role-specific message.
- [ ] Non-public jobs (draft, pending_review, rejected, closed) return 404.
- [ ] Jobs from inactive companies or unapproved/suspended owners return 404.
- [ ] Non-existent job IDs return 404.
- [ ] Private fields (`rejection_reason`, `owner_user_id`, internal timestamps) are excluded from response.
- [ ] Responsive layout works on mobile, tablet, and desktop.
- [ ] Page is accessible: semantic HTML, focus states, external link safety.
- [ ] Feature test suite passes with all visibility and access scenarios covered.
- [ ] Frontend build (`npm run build`) succeeds with no TypeScript errors.

## Next Steps After Approval

Once this specification is approved (status changed to `APPROVED` by the user):

1. Invoke `/develop Feature 17` to implement the backend controller method, frontend page, and tests.
2. Verify implementation with `php artisan test --filter=PublicJobDetailTest` and `npm run build`.
3. Perform manual browser testing to confirm layout and CTA behavior.
4. Update `memory.md` with Feature 17 completion status.
5. Proceed to Feature 18 (Candidate profile management) or Feature 20 (Saved jobs) as user directs.
