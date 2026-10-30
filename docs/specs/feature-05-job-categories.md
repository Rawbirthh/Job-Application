# Feature 5 — Admin Manages Job Categories

**Metadata:**

- Feature ID: 5
- Title: Admin Manages Job Categories
- Spec Type: Feature specification
- Source: `project-overview.md` (Phase 1 — Platform configuration and governance)
- Dependencies: Feature 3 (Custom authorization foundation), Feature 4 (Shared application shell & routing)
- Status: **APPROVED**
- Created: 2026-10-30
- Updated: 2026-10-30

---

## Goal

Provide authorized platform administrators with a secure CRUD interface to manage job categories (e.g. Engineering, Product, Design, Sales, Marketing) used across job postings and public job discovery filters. Ensure categories have unique names/slugs, optional descriptions, active/inactive toggles, and safe handling when referenced by existing jobs.

## Non-goals

- Public or employer creation of job categories.
- Nested or hierarchical categories (flat category structure for MVP).
- Automated AI category suggestions or mapping.
- Public search index optimizations beyond standard pagination and filtering.

---

## Actors and flows

**Primary actors:**

- Platform administrator (`platform_admin` role with permissions `admin.access`, `categories.view`, `categories.manage`).
- Job seekers and employers (read-only consumers of active categories in subsequent features).

**Key flows:**

1. Admin navigates to `/admin/categories` → views paginated list of categories with search and active/inactive status.
2. Admin creates a new category (Name, Slug, Description, Active status) → validation ensures name and slug are unique and well-formed.
3. Admin edits an existing category → updates details or toggles active status.
4. Admin deactivates a category → inactive categories cannot be selected for new job drafts in future features, but remain intact for historical job records.
5. Non-admin users or users lacking `categories.view` / `categories.manage` permissions receive a 403 Forbidden response.

---

## Scope and acceptance criteria

**In scope:**

- Database migration creating `job_categories` table.
- `JobCategory` Eloquent model with mass-assignment protection, casts, and scopes.
- New permission definitions: `categories.view` and `categories.manage` seeded via `AuthorizationSeeder` and assigned to `platform_admin`.
- Form Requests (`StoreJobCategoryRequest`, `UpdateJobCategoryRequest`) with server-side validation.
- `JobCategoryPolicy` enforcing authorization rules.
- `JobCategoryController` handling pagination, search, store, update, toggle/destroy actions.
- Inertia React UI page at `resources/js/pages/admin/categories/index.tsx` using the project's standard admin split-layout pattern.
- Feature tests verifying CRUD operations, uniqueness constraints, pagination, search, permission denial, and deactivation safeguards.

**Acceptance criteria (checkable statements):**

- Authorized admin with `categories.manage` permission can create, update, and toggle active status of job categories.
- Authorized admin with `categories.view` permission can view the paginated and searchable category list.
- Category name and slug are required, unique, and max 100 characters; slug is validated as kebab-case.
- Inactive categories cannot be selected for new records in future features, but historical records referencing them remain valid.
- Unauthorized users or guests cannot access category management endpoints (403 Forbidden).
- Frontend tests/build and backend test suite pass successfully.

---

## Technical design

### 1. Database Schema (`job_categories`)

```schema
- id: bigint primary key
- name: string (max 100, unique)
- slug: string (max 100, unique, indexed)
- description: text nullable (max 500)
- is_active: boolean (default true)
- timestamps
```

### 2. Permissions

Added to `App\Enums\PermissionSlug`:

- `CategoriesView` = `categories.view` ("View job categories")
- `CategoriesManage` = `categories.manage` ("Manage job categories")

Granted by default to `platform_admin` in `AuthorizationSeeder`.

### 3. Routes (`routes/admin.php`)

```php
Route::prefix('categories')->name('categories.')->middleware(['permission:admin.access'])->group(function (): void {
    Route::get('/', [JobCategoryController::class, 'index'])
        ->middleware(['permission:categories.view'])
        ->name('index');

    Route::middleware(['permission:categories.manage'])->group(function (): void {
        Route::post('/', [JobCategoryController::class, 'store'])->name('store');
        Route::put('{category}', [JobCategoryController::class, 'update'])->name('update');
        Route::delete('{category}', [JobCategoryController::class, 'destroy'])->name('destroy');
    });
});
```

### 4. Form Requests

- `StoreJobCategoryRequest`:
    - `name`: `['required', 'string', 'max:100', 'unique:job_categories,name']`
    - `slug`: `['required', 'string', 'max:100', 'alpha_dash', 'unique:job_categories,slug']`
    - `description`: `['nullable', 'string', 'max:500']`
    - `is_active`: `['boolean']`

- `UpdateJobCategoryRequest`:
    - `name`: `['required', 'string', 'max:100', Rule::unique('job_categories', 'name')->ignore($this->route('category'))]`
    - `slug`: `['required', 'string', 'max:100', 'alpha_dash', Rule::unique('job_categories', 'slug')->ignore($this->route('category'))]`
    - `description`: `['nullable', 'string', 'max:500']`
    - `is_active`: `['boolean']`

### 5. Frontend UI

- Path: `resources/js/pages/admin/categories/index.tsx`
- Layout: Split layout matching `admin/roles/index.tsx` and `admin/permissions/index.tsx` (left form for create/edit, right search bar and paginated data table with status badges and action buttons).

---

## Verification plan

1. Run backend migrations and seeders.
2. Execute Pest feature tests covering:
    - Admin CRUD for job categories.
    - Unique name and slug validation.
    - Search and pagination.
    - Permission enforcement (denying users without `categories.view` or `categories.manage`).
    - Active/inactive toggling.
3. Run `php artisan test` and `npm run build`.
