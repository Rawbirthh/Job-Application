---
Feature ID: 16
Title: Public job listing with server-side pagination
Status: APPROVED (Implemented & Verified)
Type: Feature Implementation
Source: project-overview.md (Phase 3)
Dependencies: Feature 5–9, Feature 14
Created: 2026-11-02
---

# Feature 16 Specification: Public job listing with server-side pagination

## Objective

Let anyone (guests and authenticated users) browse currently published, eligible job listings with server-side pagination, search by keyword, and filters (category, employment type, workplace arrangement, experience level).

## Goals & Non-Goals

### Goals

- Provide a public `/jobs` index page accessible without authentication.
- Implement robust server-side search and filtering by category, employment type, workplace arrangement, and experience level.
- Enforce strict visibility rules: only `published` jobs belonging to approved and non-suspended companies are returned.
- Optimize query performance with targeted eager loading, selected columns, and server-side pagination preserving query parameters.
- Provide a responsive, accessible React + Inertia UI using shadcn/ui primitives.

### Non-Goals

- Detailed job view page (Feature 17).
- Application submission (Feature 21).
- Saved jobs bookmarking (Feature 20).

## Existing Code & Conventions Inspected

- **Models**: `Job` (`app/Models/Job.php`), `Company` (`app/Models/Company.php`), `JobCategory`, `EmploymentType`, `WorkplaceArrangement`, `ExperienceLevel`.
- **Controllers**: `JobController` (`app/Http/Controllers/Employer/JobController.php`) for employer drafts/management.
- **Frontend**: Inertia pages under `resources/js/Pages/`, using React, TypeScript, and Tailwind CSS.

## Routes & Middleware

- **Route**: `GET /jobs` -> `PublicJobController@index` (or `JobController@publicIndex`).
- **Middleware**: Public route (no authentication middleware required; optional session/auth middleware for header state via `HandleInertiaRequests`).

## Authorization & Visibility Scoping

- No authorization required to view the public list.
- **Database Query Scoping**:
    - `where('status', 'published')`
    - Where company exists, company status is approved (`CompanyStatus::Approved` or equivalent string/enum), and company owner/account is not suspended.

## Query Shape & Pagination

- Eager load relationships: `company:id,name,slug,logo_path`, `category:id,name,slug`, `employmentType:id,name,slug`, `workplaceArrangement:id,name,slug`, `experienceLevel:id,name,slug`.
- Select columns on `jobs`: `id`, `company_id`, `job_category_id`, `employment_type_id`, `workplace_arrangement_id`, `experience_level_id`, `title`, `slug`, `location`, `is_remote`, `min_salary`, `max_salary`, `salary_currency`, `pay_period`, `published_at`, `created_at`.
- Pagination: Laravel `paginate(15)->withQueryString()`.

## React / Inertia Page & Components

- **Page Component**: `resources/js/Pages/Jobs/Index.tsx`
- **Props**:
    - `jobs`: Paginated resource collection (`links`, `data`, `meta`).
    - `filters`: Current search/filter parameters (`search`, `category`, `employment_type`, `workplace_arrangement`, `experience_level`).
    - `categories`, `employmentTypes`, `workplaceArrangements`, `experienceLevels`: Filter options lists.
- **UI Elements**:
    - Search input with debounce or submit.
    - Filter dropdowns / sidebar.
    - Job cards displaying title, company name + logo, location, badges for workplace arrangement and employment type, salary summary, and published date.
    - Empty state when no jobs match filters.
    - Accessible pagination controls.

## Tests & Verification

- **Feature Tests** (`tests/Feature/PublicJobListTest.php`):
    - Guest can access `/jobs`.
    - Only published jobs are listed (drafts, pending, closed, rejected are excluded).
    - Jobs from suspended or unapproved companies are excluded.
    - Search query filters by title/location.
    - Category and other filters restrict results correctly.
    - Pagination works and preserves query string parameters.

## Implementation Steps

1. Create `PublicJobController` (or add public index method) with query filters, eager loading, and pagination.
2. Register public `GET /jobs` route in `routes/web.php`.
3. Create React Inertia page `resources/js/Pages/Jobs/Index.tsx` with search, filters, job cards, and pagination.
4. Add feature test suite `tests/Feature/PublicJobListTest.php`.
5. Run tests and frontend build to verify.
