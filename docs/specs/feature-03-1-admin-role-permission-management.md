# Feature 3.1 — Admin Role and Permission Management

**Metadata:**

- Feature ID: 3.1
- Title: Admin Role and Permission Management
- Spec Type: Feature specification
- Source: Extension of Feature 3 (Custom authorization foundation)
- Dependencies: Feature 3 (implemented), Feature 4 (implemented)
- Status: **APPROVED**
- Created: 2026-10-29
- Updated: 2026-10-29

---

## Goal

Provide authorized administrators with a UI to dynamically manage the role and permission catalogue without requiring code changes or database seeders. Enable CRUD operations for roles and permissions, and allow administrators to assign multiple permissions to each role through a many-to-many relationship.

## Non-goals

- Direct user-to-permission assignments (permissions remain role-based only).
- Company-scoped roles or permissions (Feature 28).
- Permission inheritance or role hierarchies.
- Bulk permission import/export.
- API endpoints for role/permission management (Inertia-only for now).
- Automatic permission generation from code or routes.

---

## Actors and flows

**Primary actors:**

- Platform administrator (role `platform_admin` with permission `admin.access` + new permissions for role/permission management).

**Key flows:**

1. Admin navigates to `/admin/roles` → views paginated list of roles with permissions count → can create/edit/delete roles.
2. Admin navigates to `/admin/permissions` → views paginated list of permissions → can create/edit/delete permissions.
3. Admin edits a role → sees available permissions → can assign/remove permissions to/from the role.
4. Admin attempts to delete a system role/permission → operation is blocked with error message.
5. Admin attempts to remove the last permission from the last admin role → operation is blocked to prevent lockout.

---

## Scope and acceptance criteria

**In scope:**

- Admin CRUD for roles (name, slug, description, is_system flag).
- Admin CRUD for permissions (name, slug, description, is_system flag).
- Admin UI to assign/remove permissions to/from roles.
- Protection of system roles and permissions (cannot delete or change `is_system` flag).
- Last-administrator protection when modifying role permissions.
- Responsive split-layout UI: left side = form, right side = data table.
- Server-side pagination, search, and authorization for all endpoints.

**Acceptance criteria (checkable statements):**

- Admin with `roles.manage` permission can create, view, edit, and delete non-system roles.
- Admin with `permissions.manage` permission can create, view, edit, and delete non-system permissions.
- Admin with `roles.manage` permission can assign/remove permissions to/from roles.
- System roles (`is_system = true`) cannot be deleted; attempting to delete returns 403 or validation error.
- System permissions (`is_system = true`) cannot be deleted; attempting to delete returns 403 or validation error.
- Attempting to remove `admin.access` permission from `platform_admin` role when it's the only role with that permission returns validation error.
- Role slug and permission slug are unique; duplicate slugs are rejected with validation error.
- Role slug and permission slug follow kebab-case pattern; invalid formats are rejected.
- Non-admin users cannot access role/permission management routes (403).
- All list endpoints are paginated (20 per page) and searchable by name/slug.
- Frontend type checks pass (`npm run types:check`).
- Frontend build succeeds (`npm run build`).

---

## Security considerations

### 1. System definition protection

Existing `ProtectsSystemDefinition` trait in `Role` and `Permission` models must be enforced:

- `is_system = true` records cannot be deleted.
- `is_system` flag cannot be changed from `true` to `false`.
- `slug` field on system records cannot be changed.

### 2. Last-administrator protection

When modifying role-permission assignments, ensure at least one user retains `admin.access` permission:

- Cannot remove `admin.access` from `platform_admin` role if no other role has it.
- Cannot delete the last role that has `admin.access` permission.
- Use existing `RoleAssignmentService` patterns or extend with new methods.

### 3. Authorization requirements

New permissions to seed:

- `roles.view` — View list of roles.
- `roles.manage` — Create, update, delete roles; assign permissions to roles.
- `permissions.view` — View list of permissions.
- `permissions.manage` — Create, update, delete permissions.

These permissions are granted to `platform_admin` role only.

### 4. Input validation

- Role slug: required, kebab-case, unique, max 100 characters.
- Role name: required, string, max 100 characters.
- Role description: nullable, string, max 500 characters.
- Permission slug: required, kebab-case with dots allowed (e.g., `users.view`), unique, max 100 characters.
- Permission name: required, string, max 100 characters.
- Permission description: nullable, string, max 500 characters.
- Permission IDs for role assignment: array of existing permission IDs.

### 5. Route protection

All routes under `/admin/roles` and `/admin/permissions` require:

- `auth` middleware.
- `permission:admin.access` middleware.
- Specific permission middleware for each action (e.g., `permission:roles.view` for index).

---

## Backend implementation steps

### Routes

**File: `routes/admin.php`**

```php
Route::middleware(['auth', 'permission:admin.access'])->group(function (): void {
    // Roles
    Route::prefix('roles')->name('roles.')->middleware(['permission:roles.view'])->group(function (): void {
        Route::get('/', [RoleController::class, 'index'])->name('index');

        Route::middleware(['permission:roles.manage'])->group(function (): void {
            Route::post('/', [RoleController::class, 'store'])->name('store');
            Route::get('{role}/edit', [RoleController::class, 'edit'])->name('edit');
            Route::put('{role}', [RoleController::class, 'update'])->name('update');
            Route::delete('{role}', [RoleController::class, 'destroy'])->name('destroy');
        });
    });

    // Permissions
    Route::prefix('permissions')->name('permissions.')->middleware(['permission:permissions.view'])->group(function (): void {
        Route::get('/', [PermissionController::class, 'index'])->name('index');

        Route::middleware(['permission:permissions.manage'])->group(function (): void {
            Route::post('/', [PermissionController::class, 'store'])->name('store');
            Route::get('{permission}/edit', [PermissionController::class, 'edit'])->name('edit');
            Route::put('{permission}', [PermissionController::class, 'update'])->name('update');
            Route::delete('{permission}', [PermissionController::class, 'destroy'])->name('destroy');
        });
    });
});
```

### Controllers

**New: `app/Http/Controllers/Admin/RoleController.php`**

- `index()` — paginated list with search, eager load permissions count.
- `store()` — validate, create role, sync permissions if provided.
- `edit()` — return role with assigned permission IDs for form.
- `update()` — validate, update role, sync permissions, check last-admin protection.
- `destroy()` — check system flag, delete role.

**New: `app/Http/Controllers/Admin/PermissionController.php`**

- `index()` — paginated list with search.
- `store()` — validate, create permission.
- `edit()` — return permission for form.
- `update()` — validate, update permission (slug immutable if system).
- `destroy()` — check system flag, check if permission is assigned to any role, delete permission.

### Form Requests

**New: `app/Http/Requests/Admin/StoreRoleRequest.php`**

- Validation rules for role creation.

**New: `app/Http/Requests/Admin/UpdateRoleRequest.php`**

- Validation rules for role update, including permission sync.

**New: `app/Http/Requests/Admin/StorePermissionRequest.php`**

- Validation rules for permission creation.

**New: `app/Http/Requests/Admin/UpdatePermissionRequest.php`**

- Validation rules for permission update.

### Models

**Existing: `app/Models/Role.php`**

- Already has `permissions()` relationship.
- Already uses `ProtectsSystemDefinition` trait.

**Existing: `app/Models/Permission.php`**

- Already has `roles()` relationship.
- Already uses `ProtectsSystemDefinition` trait.

### Service layer

**Extend: `app/Services/Authorization/RoleAssignmentService.php`**

- Add method to sync permissions to a role with last-admin check.
- Add method to validate permission removal doesn't break admin access.

### Seeder update

**File: `database/seeders/AuthorizationSeeder.php`**

- Add new permissions: `roles.view`, `roles.manage`, `permissions.view`, `permissions.manage`.
- Assign new permissions to `platform_admin` role.

---

## Frontend implementation steps

### Inertia pages

**New: `resources/js/pages/admin/roles/index.tsx`**

- Split layout: left = create/edit form, right = paginated roles table.
- Table columns: Name, Slug, Permissions Count, System, Actions.
- Search input for filtering by name/slug.
- Form fields: Name, Slug, Description, Permissions (multi-select).
- Create/Update/Delete actions with loading states.
- System role indicator badge.

**New: `resources/js/pages/admin/permissions/index.tsx`**

- Split layout: left = create/edit form, right = paginated permissions table.
- Table columns: Name, Slug, Roles Count, System, Actions.
- Search input for filtering by name/slug.
- Form fields: Name, Slug, Description.
- Create/Update/Delete actions with loading states.
- System permission indicator badge.

### Components

**New: `resources/js/components/admin/split-layout-form.tsx`**

- Reusable split layout component with form on left and data table on right.
- Responsive: stacks on mobile, side-by-side on desktop.

**Update: `resources/js/lib/navigation.ts`**

- Add navigation items for `/admin/roles` and `/admin/permissions` with required permissions.

### Types

**Update: `resources/js/types/authorization.ts`**

- Add `RoleWithPermissions` type for role list items.
- Add `PermissionWithRoles` type for permission list items.

---

## Tests and verification

### Backend tests

**New: `tests/Feature/Authorization/AdminRoleManagementTest.php`**

Test cases:

1. Admin can view roles list.
2. Admin can create a new role.
3. Admin cannot create role with duplicate slug.
4. Admin can update role name/description.
5. Admin can assign permissions to role.
6. Admin can remove permissions from role.
7. Admin cannot remove critical permission from last admin role.
8. Admin cannot delete system role.
9. Non-admin cannot access role management.
10. Role list is paginated and searchable.

**New: `tests/Feature/Authorization/AdminPermissionManagementTest.php`**

Test cases:

1. Admin can view permissions list.
2. Admin can create a new permission.
3. Admin cannot create permission with duplicate slug.
4. Admin can update permission name/description.
5. Admin cannot delete system permission.
6. Admin cannot delete permission assigned to role (must detach first).
7. Non-admin cannot access permission management.
8. Permission list is paginated and searchable.

### Frontend tests

**Manual verification:**

- Type checks pass: `npm run types:check`.
- Build passes: `npm run build`.
- Split layout renders correctly on desktop and mobile.
- Form validation errors display correctly.
- Table pagination and search work.
- System badges display correctly.

### Test command

```bash
php artisan test tests/Feature/Authorization/AdminRoleManagementTest.php
php artisan test tests/Feature/Authorization/AdminPermissionManagementTest.php
npm run types:check
npm run build
```

---

## Risks, dependencies, and assumptions

**Risks:**

- Misconfiguration could allow privilege escalation if permissions are incorrectly assigned.
- Deleting a permission that's referenced in code (hardcoded checks) could break functionality.
    - Mitigation: Only allow deletion of custom permissions; system permissions are protected.

**Dependencies:**

- Feature 3 (Custom roles, permissions, middleware, Policies) must be complete.
- Feature 4 (Shared application shell) must be complete for navigation integration.

**Assumptions:**

- Permission slugs in code (`PermissionSlug::AdminAccess`) remain hardcoded; new permissions are added via database only.
- System permissions are a finite set controlled by developers; custom permissions are for dynamic business rules.
- Admins understand the impact of permission changes on role capabilities.

---

## Unresolved questions and decisions pending user input

1. **Slug immutability for non-system records:** Should admins be allowed to change the slug of a non-system role/permission after creation?
    - **Recommendation:** No, slugs should be immutable to prevent broken references. Admin can create a new record and delete the old one.

2. **Permission assignment to system roles:** Should admins be allowed to add/remove permissions from system roles like `platform_admin`?
    - **Recommendation:** Yes, but with last-admin protection for `admin.access` permission.

3. **Cascade behavior when deleting a permission:** When a permission is deleted, should it be automatically detached from all roles?
    - **Recommendation:** Yes, use `detach()` before deletion.

4. **Permission naming convention:** Should permission slugs follow a specific pattern (e.g., `resource.action`)?
    - **Recommendation:** Yes, recommend `resource.action` pattern in UI help text, but don't enforce it in validation to allow flexibility.

---

## Ordered implementation steps

1. **Backend:** Update `AuthorizationSeeder` with new permissions.
2. **Backend:** Create Form Requests (`StoreRoleRequest`, `UpdateRoleRequest`, `StorePermissionRequest`, `UpdatePermissionRequest`).
3. **Backend:** Create `RoleController` with CRUD actions.
4. **Backend:** Create `PermissionController` with CRUD actions.
5. **Backend:** Update `routes/admin.php` with new routes.
6. **Backend:** Extend `RoleAssignmentService` with permission sync method.
7. **Backend:** Write backend tests.
8. **Frontend:** Update `navigation.ts` with new menu items.
9. **Frontend:** Create `SplitLayoutForm` component.
10. **Frontend:** Create `pages/admin/roles/index.tsx`.
11. **Frontend:** Create `pages/admin/permissions/index.tsx`.
12. **Frontend:** Run type checks and build; fix any errors.
13. **Integration:** Run full test suite and verify in browser.

---

## Summary

Feature 3.1 extends the authorization foundation by enabling administrators to dynamically manage roles and permissions through a secure UI. It enforces system definition protection, last-administrator safeguards, and follows the established patterns from Feature 3. The split-layout UI (form left, table right) provides a clean, responsive experience for managing the authorization catalogue.

**Key files to create/update:**

- Backend: `RoleController.php`, `PermissionController.php`, Form Requests, `routes/admin.php`, `AuthorizationSeeder.php`, `RoleAssignmentService.php`, tests.
- Frontend: `pages/admin/roles/index.tsx`, `pages/admin/permissions/index.tsx`, `SplitLayoutForm` component, `navigation.ts` update, types update.
