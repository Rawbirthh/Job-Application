---
Feature ID: 18
Title: Candidate profile management
Status: IMPLEMENTED
Type: Feature Implementation
Source: project-overview.md (Phase 3 — Public discovery and candidate profile)
Dependencies: Feature 2 (Authentication), Feature 3 (Roles/permissions)
Created: 2026-11-03
---

# Feature 18 Specification: Candidate profile management

## Objective

Let an authenticated job seeker create and maintain a private professional profile (headline, summary, location, skills, education entries, and work experience entries) used later by job applications, with server-side validation and strict owner-only access.

## Goals & Non-Goals

### Goals

- Provide an owner-only candidate profile edit flow for users with the `job_seeker` role.
- Persist a one-to-one `candidate_profiles` record per user, plus relational education and work-experience entries.
- Validate all input server-side via a dedicated Form Request, including nested repeated entries.
- Expose only necessary profile fields to Inertia; never expose auth secrets or other users' data.
- Provide clear empty/loading/error states in the React form.
- Keep the data shape ready for Feature 21 (application submission snapshot) and Feature 23 (employer applicant review).

### Non-Goals

- Resume/file upload and management (Feature 19).
- Public candidate pages or employer-facing profile views (Feature 23 will add scoped access).
- Skill catalogue/normalization or search by skill (Feature 34).
- Application submission/history (Features 21–22).
- Company/team collaboration (Feature 28).
- Candidate profile deletion (cascades with account deletion; no standalone delete route).

## Dependencies Verified

- Feature 2: session auth, `auth` middleware, `User` model.
- Feature 3: custom roles/permissions, `role:job_seeker` middleware pattern, Policies.
- Existing conventions: `Company`/`EmployerOnboardingRequest` single-owner models, Form Requests with `*Data()` mapping, thin controllers, Inertia pages, Pest tests.

Schema inspection:

- `users` table: `id`, `name`, `email`, timestamps; employer status added later.
- No existing candidate profile tables. New tables required.

## Existing Code & Conventions Inspected

- **Controller pattern**: `CompanyController` (thin, `authorize`, explicit payload mapping, flash toast, redirect).
- **Form Request pattern**: `StoreCompanyRequest` (trim in `prepareForValidation`, `*Data()` mapping, unique rules).
- **Policy pattern**: `CompanyPolicy` (owner check via `$actor->id === $model->owner...`).
- **Middleware**: candidate routes live under `Route::prefix('candidate')...->middleware(['role:job_seeker'])` in `routes/web.php`.
- **Frontend**: `candidate/dashboard.tsx`, vendored shadcn/ui, Inertia `useForm`, typed props.
- **Tests**: `CompanyTest` (guest redirect, role denial, forged IDs, validation, pagination).

## Assumptions (labelled)

- **A1**: All profile fields (headline, summary, location, skills, education, experience) are optional. Only the authenticated owner may create/edit; no minimum completeness requirement in this feature. (Application submission in Feature 21 may later require a non-empty profile/resume.)
- **A2**: Skills are stored as a JSON array of free-text strings on `candidate_profiles` (max 30, each max 50 chars). No skill catalogue/normalization in this feature.
- **A3**: Education and work experience are stored as separate relational tables (`candidate_educations`, `candidate_experiences`) with a foreign key to `candidate_profiles`, allowing future query/filter by employer views.
- **A4**: Dates are full dates (`date`). `end_date` is nullable; for experience, `is_current = true` implies `end_date` is null. A null end date alone does not imply "current".
- **A5**: Education/experience entries require minimal identity fields (institution; company_name + job_title) but dates and descriptions are optional.
- **A6**: The full submitted list replaces the stored list on update (delete removed rows, insert new rows in submitted order). No per-entry update IDs are exposed.
- **A7**: Profile is created lazily on first edit (find-or-create) in a transaction; no backfill migration for existing users.
- **A8**: Candidate profile routes require the `job_seeker` role. Approved employers retain `job_seeker`, so they may also maintain a candidate profile.
- **A9**: Employer/admin access to a candidate profile is out of scope here; `CandidateProfilePolicy::view` permits only the owner. Feature 23 will extend authorization for employers tied to a relevant application.
- **A10**: No soft deletes; profile and entries cascade-delete with the user account.

## Routes & Access Boundary

Add to the existing candidate group in `routes/web.php`:

```php
Route::prefix('candidate')->name('candidate.')->middleware(['role:job_seeker'])->group(function (): void {
    Route::get('dashboard', CandidateDashboardController::class)->name('dashboard');
    Route::get('profile', [CandidateProfileController::class, 'edit'])->name('profile.edit');
    Route::put('profile', [CandidateProfileController::class, 'update'])
        ->middleware(['throttle:20,1'])
        ->name('profile.update');
});
```

- **Middleware**: `auth` (outer group) + `role:job_seeker`.
- **Named routes**: `candidate.profile.edit`, `candidate.profile.update`.
- **No route model binding / IDs**: owner is derived from `request()->user()`; no user/profile ID is accepted from the URL or body.
- Throttle write endpoint at 20/min.

## Policy Actions & Dynamic Role/Permission Resolution

New `app/Policies/CandidateProfilePolicy.php` (auto-discovered for `CandidateProfile` model):

- `view(User $actor, CandidateProfile $profile): bool` → `$actor->is($profile->user)` (owner only; employer extension deferred to Feature 23).
- `update(User $actor, CandidateProfile $profile): bool` → same owner check.
- `create(User $actor): bool` → `$actor->hasRole(RoleSlug::JobSeeker)` (used when no profile exists yet).

Controller flow resolves/creates the profile first, then calls `$this->authorize('update', $profile)`. Forged `user_id`/profile IDs are never trusted.

No new permissions are required; this feature uses the existing `job_seeker` role. Do not add platform-admin permission checks for candidate self-service.

## Form Requests & Validation

New `app/Http/Requests/Candidate/UpdateCandidateProfileRequest.php`:

- `authorize()` returns `true`; controller performs the Policy check.
- `prepareForValidation()` trims string fields and normalizes empty strings to `null` for nullable fields (following `StoreCompanyRequest`).
- `rules()`:

```php
'headline' => ['nullable', 'string', 'max:150'],
'summary' => ['nullable', 'string', 'max:5000'],
'location' => ['nullable', 'string', 'max:255'],
'skills' => ['nullable', 'array', 'max:30'],
'skills.*' => ['string', 'max:50'],
'educations' => ['nullable', 'array', 'max:20'],
'educations.*.institution' => ['required_with:educations', 'string', 'max:150'],
'educations.*.degree' => ['nullable', 'string', 'max:100'],
'educations.*.field_of_study' => ['nullable', 'string', 'max:100'],
'educations.*.start_date' => ['nullable', 'date'],
'educations.*.end_date' => ['nullable', 'date', 'after_or_equal:educations.*.start_date'],
'educations.*.description' => ['nullable', 'string', 'max:2000'],
'experiences' => ['nullable', 'array', 'max:20'],
'experiences.*.company_name' => ['required_with:experiences', 'string', 'max:150'],
'experiences.*.job_title' => ['required_with:experiences', 'string', 'max:150'],
'experiences.*.location' => ['nullable', 'string', 'max:100'],
'experiences.*.start_date' => ['nullable', 'date'],
'experiences.*.end_date' => ['nullable', 'date', 'after_or_equal:experiences.*.start_date'],
'experiences.*.is_current' => ['boolean'],
'experiences.*.description' => ['nullable', 'string', 'max:2000'],
```

- Cross-field rule: if `is_current` is true, `end_date` must be null. Implement via `after` validation or controller normalization; document as validation error on `end_date`.
- `profileData()` (or `validatedProfile()`) returns explicitly mapped arrays for the service; never pass raw `$request->all()`. Filter out completely empty education/experience rows before mapping? Spec: frontend submits only non-empty rows; server validates each submitted row. Completely empty rows are rejected by `required_with` only if other rows exist; a single empty row with no siblings passes as null arrays only if the whole array is absent. To be safe, controller/service skips rows where all fields are null/empty after validation.

## Models, Relationships, Migration, Constraints, Indexes

### New tables

**`candidate_profiles`**

- `id` (PK)
- `user_id` FK → `users.id`, unique, `cascadeOnDelete`
- `headline` string(150) nullable
- `summary` text nullable
- `location` string(255) nullable
- `skills` json nullable
- timestamps

**`candidate_educations`**

- `id` (PK)
- `candidate_profile_id` FK → `candidate_profiles.id`, `cascadeOnDelete`, indexed
- `institution` string(150) not null
- `degree` string(100) nullable
- `field_of_study` string(100) nullable
- `start_date` date nullable
- `end_date` date nullable
- `description` text nullable
- timestamps

**`candidate_experiences`**

- `id` (PK)
- `candidate_profile_id` FK → `candidate_profiles.id`, `cascadeOnDelete`, indexed
- `company_name` string(150) not null
- `job_title` string(150) not null
- `location` string(100) nullable
- `start_date` date nullable
- `end_date` date nullable
- `is_current` boolean default false
- `description` text nullable
- timestamps

### Models

- `CandidateProfile` (HasFactory, fillable list above minus timestamps; casts `skills` as `array`; `user()` BelongsTo, `educations()` HasMany, `experiences()` HasMany).
- `CandidateEducation` (fillable list; casts dates; `profile()` BelongsTo).
- `CandidateExperience` (fillable list; casts dates + boolean; `profile()` BelongsTo).
- `User` gets `candidateProfile(): HasOne` relationship (optional, added when needed).

### Indexes

- Unique index on `candidate_profiles.user_id` (also FK).
- Index on `candidate_educations.candidate_profile_id`.
- Index on `candidate_experiences.candidate_profile_id`.
- No additional composite indexes needed for this feature; revisit if employer filtering by skill/location is added later.

### Constraints

- `cascadeOnDelete` from user → profile → entries (no orphaned private data).
- No unique constraints on education/experience rows (a user may have duplicate institutions/roles legitimately).

## Query/Data Payload Shape, Eager Loading, Pagination

- Single profile per user; no pagination.
- `edit()` resolves `CandidateProfile::firstOrCreate(['user_id' => $user->id])` and eager-loads `educations` and `experiences` ordered by `start_date` desc, `id` desc (nulls last acceptable; simpler: order by `id` asc to preserve insertion order). Use `educations` ordered by `id` asc and `experiences` by `id` asc to preserve submitted order.
- Select only needed columns:
    - profile: `id, user_id, headline, summary, location, skills, updated_at`
    - education: `id, institution, degree, field_of_study, start_date, end_date, description`
    - experience: `id, company_name, job_title, location, start_date, end_date, is_current, description`
- Inertia props:

```ts
profile: {
  headline: string | null;
  summary: string | null;
  location: string | null;
  skills: string[];
  educations: Array<{
    id: number;
    institution: string;
    degree: string | null;
    field_of_study: string | null;
    start_date: string | null;
    end_date: string | null;
    description: string | null;
  }>;
  experiences: Array<{
    id: number;
    company_name: string;
    job_title: string;
    location: string | null;
    start_date: string | null;
    end_date: string | null;
    is_current: boolean;
    description: string | null;
  }>;
  updated_at: string | null;
}
```

- No pagination/empty-list pagination; empty arrays represent no entries.

## Service/Repository Decision

- **Repository**: Not needed. Single-owner lookup by `user_id` with eager loading; no complex multi-join/aggregate query or reuse beyond this controller.
- **Service**: Use a small `App\Services\Candidate\CandidateProfileService` with `update(User $user, array $validated): CandidateProfile`. Rationale: transactional multi-table sync (profile + replace children) and keeps controller thin; consistent with `RoleAssignmentService` for transactional writes. Method wraps `DB::transaction`, find-or-creates profile, updates scalar fields, deletes existing child rows, inserts validated children in submitted order, returns refreshed profile.
- Controller remains thin: authorize, call service, flash, redirect.

## React/Inertia Page/Form/Component Plan

- **Page**: `resources/js/pages/candidate/profile/edit.tsx` (or `candidate/profile.tsx`).
- **Route prop**: `profile` (typed as above).
- **Form**: `useForm` with state matching payload; dynamic arrays for educations/experiences with add/remove.
- **Components**: reuse shadcn/ui `Input`, `Textarea`, `Button`, `Label`, `InputError`, `Card`, `Badge` (for skills), `Separator`. Maybe a small local `SkillInput` (comma/enter to add) without new primitive library.
- **Behavior**:
    - Prepopulate from `profile`.
    - Add education/experience row appends a blank object; remove filters array.
    - Skills input: type + Enter/Add to append (dedup case-insensitively), remove chip.
    - Submit disabled while `processing`.
    - Field-level errors render for scalar and nested keys (`errors['educations.0.institution']`).
    - Empty states: "No education added yet", "No work experience added yet".
    - Show last updated timestamp.
    - Success flash toast.
- **Accessibility**: labels associated, keyboard-operable add/remove, focus management on remove, semantic headings, `aria-live` for errors optional.
- **Navigation**: add "Profile" link to candidate dashboard/nav (if nav exists) pointing to `candidate.profile.edit`. (No global nav file beyond dashboard; link from dashboard card.)
- **Wayfinder**: regenerate actions/routes after route changes; rebuild frontend assets.

## Tests & Edge Cases

Feature tests (`tests/Feature/Candidate/CandidateProfileTest.php`):

1. **Guest access**:
    - GET/PUT `candidate.profile.*` redirect to login.
2. **Role/permission**:
    - Job seeker can GET edit page and PUT valid profile.
    - Employer-only? User without `job_seeker` role (e.g., platform admin without job_seeker) gets 403 on both routes.
    - Approved employer (retains job_seeker) can manage profile.
3. **Owner scoping / IDOR**:
    - No user_id/profile_id accepted; forged `user_id` in payload is ignored; profile stays owned by auth user.
    - Second user's profile data is not visible (route has no ID; assert response profile belongs to auth user).
4. **Happy path**:
    - Create profile with headline, summary, location, skills, multiple educations/experiences; assert DB rows and Inertia props.
    - Update replaces entries: remove an entry on resubmit → old row gone; add new → new row.
    - `is_current` true forces null `end_date`.
5. **Validation**:
    - Oversized headline/summary/location/skill; too many skills/entries → errors.
    - Education missing institution → error on `educations.0.institution`.
    - Experience missing company/title → errors.
    - `end_date` before `start_date` → error.
    - `is_current` with end_date → error.
    - Invalid skill type (non-string) → error.
6. **Field exposure**:
    - Response does not include password, email? Actually profile page may need no email; assert no `password`/`remember_token`/`two_factor_secret`.
    - Cross-user data not present.
7. **Regression**:
    - Run full suite.

## Security Checks

- **Route boundary**: `auth` + `role:job_seeker` on both routes; guests redirect, unauthorized roles 403.
- **Ownership/IDOR**: no ID params; owner derived from session; forged IDs ignored.
- **Mass assignment**: service maps only validated fields; `user_id` set server-side; child rows linked to resolved profile.
- **Sensitive data**: profile is private; no public route; Inertia props exclude auth secrets and other users' data.
- **XSS**: render all text as text; no HTML injection.
- **Race conditions**: unique `user_id` + find-or-create inside transaction; concurrent first edits may hit unique constraint—handle gracefully (retry or catch) or accept 500? Spec: service should use `firstOrCreate` within transaction; if unique violation occurs, re-fetch. Implementation should handle gracefully.
- **Rate limiting**: throttle writes.
- **Deletion/retention**: cascade delete ensures no orphaned private profile data on account deletion.

## Implementation Sequence

1. Create migration(s) for `candidate_profiles`, `candidate_educations`, `candidate_experiences`.
2. Add models + factories (at least `CandidateProfileFactory`).
3. Add `CandidateProfilePolicy`.
4. Add `UpdateCandidateProfileRequest`.
5. Add `CandidateProfileService`.
6. Add `CandidateProfileController` with `edit`/`update`.
7. Register routes in `routes/web.php`.
8. Add TypeScript types (`resources/js/types/candidate.ts` or extend) and page `candidate/profile/edit.tsx`.
9. Update candidate dashboard link to profile.
10. Add tests.
11. Run `php artisan test`, `npx tsc --noEmit`, `npm run build` (note Windows file-lock caveat).
12. Update `memory.md` after implementation (not during architect).

## Risks & Unresolved Questions

**Risks:**

- Nested form arrays in Inertia require careful error-key handling; tests should assert nested error keys.
- Replacing all child rows on update changes IDs; any future reference to entry IDs (e.g., application snapshots) must snapshot data, not IDs. Feature 21 must follow this.
- JSON `skills` column portability: MySQL JSON vs SQLite JSON (tests use SQLite). Laravel's `json` column maps to TEXT on SQLite; casts handle array. Fine.
- First-edit race condition under concurrent requests; mitigate with unique constraint + re-fetch.

**Non-blocking open questions (assumptions above):**

- Should profile completeness be required before applying? Deferred to Feature 21.
- Should experience entries support month-only or year-only dates? Current assumption uses full dates; UI can use `<input type="date">`.
- Should skills be deduplicated case-insensitively? Assumption: yes in UI; server may also dedup.

## Acceptance Criteria

- [ ] Authenticated job seeker can open `/candidate/profile` and see persisted profile values (or empty form).
- [ ] Authenticated job seeker can save headline, summary, location, skills, education, and experience entries; data persists.
- [ ] Validation errors map to fields, including nested education/experience rows.
- [ ] Guest users are redirected to login; non-job-seeker users receive 403.
- [ ] Forged `user_id`/profile IDs are ignored; profile remains owned by auth user.
- [ ] Other users cannot retrieve or update another user's profile (no ID-based access).
- [ ] Inertia props include only necessary profile fields; no secrets or cross-user data.
- [ ] Empty states render for no education/experience entries.
- [ ] Feature test suite passes; full suite has no regressions.
- [ ] TypeScript check and frontend build succeed.

## Verification Commands

- `php artisan test --filter=CandidateProfileTest`
- `php artisan test`
- `npx tsc --noEmit`
- `npm run build`

## Next Steps After Approval

Once this specification is approved (status changed to `APPROVED` by the user):

1. Invoke `/develop Feature 18` to implement migration, models, policy, Form Request, service, controller, routes, types, page, tests.
2. Verify with `php artisan test --filter=CandidateProfileTest` and `npm run build`.
3. Update `memory.md` with Feature 18 completion status.
4. Proceed to Feature 19 (Private resume upload) or Feature 20 (Saved jobs) as user directs.
