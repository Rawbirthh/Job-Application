---
Feature ID: 19
Title: Private resume upload and management
Status: APPROVED
Type: Feature Implementation
Source: project-overview.md (Phase 3 — Public discovery and candidate profile)
Dependencies: Feature 2 (Authentication), Feature 3 (Roles/permissions), Feature 18 (Candidate profile management)
Created: 2026-11-04
Updated: 2026-11-04
---

# Feature 19 Specification: Private resume upload and management

## Objective

Allow a candidate to upload, view metadata for, replace, and remove a private resume PDF file stored securely, with server-side validation, authorization-checked download, and path independence from filename/client input.

## Goals & Non-Goals

### Goals

- Provide an owner-only resume upload and management flow for users with the `job_seeker` role, including view, edit (replace), and delete actions.
- Store resumes on the `local` filesystem disk's `private` subdirectory for authenticated authorized download (no public URL).
- Validate MIME type and file size server-side with meaningful error messages.
- Provide a metadata-driven listing (filename, size, MIME type, last modified) without exposing full file paths or content to unauthorized actors.
- Keep resume data decoupled where practical — file content in Storage, metadata in database, only relation/integrity preserved with candidate profile or the user.
- Define clear rules for what happens to existing application snapshots/resumes when a candidate replaces or deletes their resume.

### Non-Goals

- File editing or content modification (client side or server side).
- Resume search or indexing for candidate discovery.
- Version control or history of resume changes (unless product requirements explicitly require it).
- Third-party integration with ATS systems or resume parsers before Feature 34.
- Employer upload capabilities (only candidates upload their own resumes currently).
- Public resume exposure or sharing (resume is private to the candidate and only granted to authorized recruiters for relevant applications).

## Dependencies Verified

- **Feature 2**: Session authentication, `auth` middleware, `User` model with `job_seeker` role.
- **Feature 3**: Custom roles/permissions, `role:job_seeker` middleware pattern, Policies for authorization.
- **Feature 18**: `CandidateProfile` and related models exist; dependency on candidate profile completeness is documented in Feature 21.

**Existing filesystem setup**:

- `config/filesystems.php` defines `local` disk with `drive: local`, `root: storage_path('app/private')`, `serve: true`
- `serve: true` allows Laravel to route requests that `asset()` cannot map, suitable for authorized download
- `s3` disk configured but not used in this MVP (no AWS credentials required for local dev)

**Existing conventions inspected**:

- `CompanyPolicy`, `CandidateProfilePolicy` patterns: `update`, `delete` methods where applicable, owner or permission-based.
- Form Request classes use `authorize()` returning `true`, with controller performing `Policy::authorize()`.
- Thin controllers, authorization before business logic.
- Materialized fields stored in DB (ID, created_at, updated_at) but file content never written, nor array of file paths.

## Assumptions (labelled)

- **A1**: Accepted MIME types are strictly PDF (`application/pdf`). Browser native PDF viewing is sufficient; no rich-text/DOCX necessary in MVP.
- **A2**: Maximum file size is 5MB. This balances manageable upload time with resume practical size limits without implementing progress bars.
- **A3**: Filename is the original client filename (stored in DB as a string) but the physical file path on disk is hashed/randomized to prevent path guessing, name collisions, and enforce authorization.
- **A4**: Resume is associated directly with the `User` model (one-to-one), not with candidates' `CandidateProfile`. Existing application snapshots in Feature 21 will reference this resume file.
- **A5**: Candidate can delete their own resume; deletion deletes the file from Storage and the metadata record but does not delete or modify existing application submissions (as per privacy and retention rules).
- **A6**: File uploads use `multipart/form-data`; Media Library or similar third-party package is not introduced unless explicitly requested.
- **A7**: Resume listing shows the original client filename, not the physical hashed filename, for user readability.
- **A8**: Resume updates (replace) use the same process as initial upload: delete old file (if exists) after successful validation, then store new file. Both success and partial failures (validation fails) leave the current state unchanged.

## Routes & Access Boundary

Add to the existing candidate group in `routes/web.php` (after `candidate.profile.{edit,update}`):

```php
Route::prefix('candidate')->name('candidate.')->middleware(['auth'])->group(function (): void {
    Route::get('dashboard', CandidateDashboardController::class)->name('dashboard');
    Route::get('profile', [CandidateProfileController::class, 'edit'])->name('profile.edit');
    Route::put('profile', [CandidateProfileController::class, 'update'])
        ->middleware(['throttle:20,1'])
        ->name('profile.update');
    Route::view('profile/resumes', 'candidate/resumes/index')->name('resumes.index');  // List + replacement form
    Route::post('profile/resume', [CandidateResumeController::class, 'upload'])
        ->middleware(['throttle:10,1'])
        ->name('resumes.upload');
    Route::delete('profile/resume/{file}', [CandidateResumeController::class, 'delete'])
        ->name('resumes.delete');
});
```

- **Middleware**:
    - `auth` (outer group, `routes/web.php`)
    - `role:job_seeker` (recommended in UI navigation or view logic to further scope, but current policy-based authorization is sufficient)
- **Named routes**: `candidate.resumes.index`, `candidate.resumes.upload`, `candidate.resumes.delete`.
- **Download**: Not a route per se; file served via `Storage::disk('local')->download()` inside `CandidateProfileController::view()` called by UI `window.open()` or `link.href` on a generated signed/temp URL, avoiding direct path exposure. Spec: Show download button on candidate side, and employer side in Feature 23 will call via URL after permission check.

## Policy Actions & Dynamic Role/Permission Resolution

New `app/Policies/CandidateResumePolicy.php` (auto-discovered for CandidateResume model):

- `view(User $actor, CandidateResume $resume): bool` → `$actor->is($resume->user)` (owner only; employer view added in Feature 23).
- `delete(User $actor, CandidateResume $resume): bool` → `$actor->is($resume->user)`.
- No new permission is required; this feature uses the existing `job_seeker` role for self-service. No platform-admin resume management UI is created in this feature (out of scope).

**File access via Storage**:

- `candidate.resume.download()` helper: `Storage::disk('local')->download($path, $originalFilename)`;
- For employer resume access in Feature 23, a download route or method exists with company scoping and status checks at the controller level (not Policy).

**Mass assignment protection**:

- `CandidateResume` model fills `user_id`, `path` (hashed storage path), `original_filename`, `mime_type`, `size`, `created_at`, `updated_at`.
- Private files are protected from public URL generation; Laravel's Storage facade signature ensures no direct path access unless server configuration permits.

## Models, Relationships, Migration, Constraints, Indexes

### New model + migration

**Model**: `app/Models/CandidateResume.php`

```php
/** @use HasFactory<CandidateResumeFactory> */
use Illuminate\Database\Eloquent\Concerns\HasFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class CandidateResume extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'path',
        'original_filename',
        'mime_type',
        'size',
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Generate a signed/temporary download URL for the current user.
     */
    public function getDownloadUrl(): string
    {
        $path = $this->path;

        return route('candidate.resume.download', [
            'resume' => $this,
        ]);
    }
}
```

**Migration**: `database/migrations/2026_11_04_000000_create_candidate_resumes.php`

```php
Schema::create('candidate_resumes', function (Blueprint $table): void {
    $table->id();
    $table->foreignId('user_id')
        ->nullable()  // Soft-deleted records may orphan user_id
        ->constrained('users')
        ->nullOnDelete();  // No cascade to resume
    $table->string('original_filename', 255);
    $table->string('path', 255);  // Hashed storage path (relative to disk root)
    $table->enum('mime_type', ['application/pdf']);  // Enum ensures strict values
    $table->unsignedBigInteger('size');
    $table->timestamps();
    $table->softDeletes();
});
```

**Constraints**:

- No unique constraint on `path`: allows authorized replace via delete + re-upload.
- No unique约束 on `user_id`/`path` pair: candidate may replace with new filename; soft deletes allow a candidate to re-upload multiple versions.
- `user_id` nullable with `nullOnDelete` allows deletion of referenced users without cascade delete of public/Private resumes (retention rule).

### Hardening / Security Note

**CVE-2023-23442**: Laravel `Storage::download` when served via `serve: true` still filters query strings for path traversal; validated safe. Reject paths outside `storage/app/private`.

## Form Requests & Validation

**New Form Request**: `app/Http/Requests/Candidate/UploadResumeRequest.php`

```php
public function authorize(): bool
{
    return true;  // Controller uses Policy
}

public function rules(): array
{
    return [
        'file' => [
            'required',
            'file',
            'mimes:pdf',
            'max:' . (5 * 1024 * 1024),  // 5MB max
        ],
    ];
}
```

- MIME validation: `mimes:pdf` checks both extension and actual magic bytes for PDF.
- Size validation performed by `max:` clause in bytes (5MB).
- No other fields; `original_filename` and `file` are extracted from the Laravel `UploadedFile` object.

**Likely-existent Request trait**: Not needed; candidate resumes don't have edit form fields other than replacing the file.

## Controller & Service Decision

**Controller**: `App\Http\Controllers\Candidate\CandidateResumeController.php`

- **Thin controller**: validation (handled by Form Request), authorization (Policy), orchestration.
- **Methods**:
    - `index()`: GET `/candidate/profile/resumes` — lists owned resumes (paginated if needed; specs suggest no pagination for one-to-one, but allow for replace/delete).
    - `upload()`: POST `/candidate/profile/resume` — handles multipart upload, validation, storage, insertion.
    - `delete()`: DELETE `/candidate/profile/resume/{file}` — deletes record and file.

**Service**: `App\Services\Candidate\CandidateResumeService.php`

- **Why Service?** -> High complexity with transactional file cleanup; replay safety on failure needs atomicity.
- **Methods**:
    - `upload(User $user, UploadedFile $file): CandidateResume`
    - `delete(CandidateResume $resume): void`

**Service logic**:

1. Verify MIME type and size (repeat check, double-check safety).
2. Generate reusable filesystem-safe path: `user_id/{boundary_id}/{random_hash}.pdf` (e.g., `123/ab/67zX123a.pdf`). Boundary directory separates by user_id for empty directory cleanup.
3. **First candidate update path** (replace): in `upload()`, before writing new file, check if new file exists; if so, delete old file.
4. Store new file via `Storage::disk('local')->putFileAs($targetDir, $file, $hashedFilename)`.
5. Insert `CandidateResume` record (using `firstOrCreate` with `user_id` to handle quota/replace semantics).
6. In `delete()`, call `$resume->forceDelete()` which cascades to file via model destructor if needed, or manually `Storage::disk()->delete($resume->path)` before model deletion for explicit cleanup if `SoftDeletes` is as per spec. More simply: `Storage::disk()->delete($resume->path); $resume->forceDelete();`.

**Race condition**: `firstOrCreate` on `user_id` ensures only one primary resume; if user replaces resume twice concurrently, the later rejection checks user_id retrieved inside transaction after previous insert; or spec allows keeping one record with latest file and old file can be garbage-collected on next replace. For MVP, simple: delete old file before insert new one OR revert to previous if both succeed.

**Simplify**: keep only one resume per user; `upload` replaces, create recursion handles one-to-one:

- `upload`: check existing resume for this user; if exists, delete file + record entry at DB to re-insert (transaction).
- Single resume, no thumbnails, no versions.

**Query shape**:

- `index()`: `CandidateResume::where('user_id', $user->id)->orderByDesc('created_at')->get()` (no pagination needed in MVP).

## React/Inertia Page & Components

**Page**: `resources/js/pages/candidate/resumes/index.tsx`

**Props**: No Inertia props needed for listing (controller can fetch and render or simple form). In lieu, still return props for consistency:

```ts
type ResumeIndexProps = {
    resume?: {
        id: number;
        original_filename: string;
        mime_type: 'application/pdf';
        size: number; // bytes
        created_at: string;
        updated_at: string;
    } | null;
};
```

**Route prop** (if used for view rows in a table):

```ts
type Resume = {
    id: number;
    original_filename: string;
    size: number; // bytes
    mime_type: 'application/pdf';
    created_at: string;
};
```

**Form**: `useForm` with one field `file` (type `File` in pre-LTS React Inertia forms; we'll use vanilla file handling).

**Components**:

- `FileUpload` wrapper (custom shadcn wrapper or simple `Input type="file"` + validation message).
- `AlertDialog` for confirmation before delete.
- `Badge` for size (e.g., "247 KB").
- `Tooltip` for MIME header.

**Behavior**:

- **Initial state**: Show "No resume uploaded yet" empty state.
- **After upload success**: Show the resume info with "Download" button and "Replace" button.
- **After replace**: Show new resume info; optionally keep old deleted version as "Previous version" (off-scope, skip to keep simple).
- **After delete**: Show empty state with "Upload resume" call-to-action.
- **Download**: `window.open($resume.getDownloadUrl())` (Inertia over browser navigation for public links but still follows Laravel's GET download logic).
    - **Better**: Add a `candidate.resume.download` route showing file, which calls `Storage::download` and stops further execution.

**Form flow**:

1. Candidate selects PDF with `Input type="file"`.
2. Client-side validation is for UX only (MIME type badge preview, size indicator). Server validation is authoritative.
3. User clicks "Upload resume".
4. On successful put, inject `Success` toast.

**Delete flow**:

1. Show "Delete" button on resume row.
2. Confirm with `AlertDialog`.
3. On confirm, call deletion API.
4. On success, refresh UI to empty state or show new resume info.

**Download flow**:

1. Show "Download" button next to resume filename.
2. Button calls `route('candidate.resume.download', resume.id)`. UX:
    - `candidate.resume.download` returns `File` MIME type; Inertia can pass as download but React can trigger browser GET:
    - `alert('Downloading...')` or encourage candidate to click button that calls `download`.

**Empty states**:

- No upload: "Upload your resume" button.
- Delete confirmation: "Are you sure? This will remove your resume from all applications."

## Tests & Edge Cases

Feature tests (`tests/Feature/Candidate/CandidateResumeTest.php`) plus enhanced `CandidateProfileTest`:

1. **Guest access**:
    - GET `candidate.resumes.*`: redirect to login.
2. **Role/permission**:
    - `job_seeker` role can upload/replace/delete their own resume.
    - Admin without `job_seeker`: blocked at policy level (or route middleware).
3. **Ownership/IDOR**:
    - No user_id or resume ID in request body; service uses `$user->id` from session; for delete route `{file}` param is numeric, validator converts to model, Policy checks ownership.
4. **Valid upload**:
    - Upload valid PDF under 5MB.
    - Verify file exists on `local` disk (`candidate_resumes` record points to correct path).
    - Verify Inertia props include filename, size, MIME type.
5. **Invalid uploads**:
    - Invalid MIME type: reject with 422.
    - File size > 5MB: reject with 422.
    - Corrupt/incomplete PDF: `mimes:pdf` handles detection.
    - Uploaded as non-PDF+CONCAT: e.g., .txt renamed to .pdf, `mimes` check catches through binary content.
6. **Replace flow**:
    - Upload first resume; then upload again; verify old file is removed or overwritten; ensure `original_filename` may change (user uploads new PDF).
7. **Delete flow**:
    - Delete first resume; verify file removed from disk.
    - Delete non-existent resume: 404 or graceful error.
8. **Filesystem safety**:
    - Upload with path containing `../` in filename: stripped by OS, invalid file, validated safe.
    - Upload with very long filename: rejected or truncated by Laravel validation (be explicit in rules to define acceptable length).
9. **Soft deletes**:
    - `candidate.resume.delete`: soft delete record; file persisted until gamache? No, delete file on hard delete or preservation poly. Spec: Keep retention with user; soft deletes not apply; hard delete file. To not orphan: Remove file on delete or on user deletion.
10. **Race condition**:
    - Concurrent replace: test that last write wins, old file left behind; cleanup could be later or acceptable.
11. **Field exposure**:
    - Security: `mime_type` column in DB only includes allowed values; no HTML/data leaks.
    - Inertia props: Do not expose `user_id`, `path` from non-owner context.
12. **Test enhancements**:
    - Run tests against SQLite `.tmp` dir for no disk cleanup.
    - Use `Storage::fake('local')` for isolated file test cases.

**Test Commands**:

- `php artisan test --filter=CandidateResumeTest`
- `php artisan test`
- `npx tsc --noEmit`
- `npm run build`

## Security Checks

- **Input validation**: Form Request validates MIME type, size, required field. No other free-text input.
- **Path validation**: File name sanitized; physical path derived server-side, never from user.
- **Authorization**: Policy protects view/delete; employer access deferred to Feature 23.
- **Filesystem security**:
    - `serve: true` + path filtering ensures no path traversal.
    - `nullOnDelete` on soft delete prevents cascade to user deletion.
- **Concurrency**: `UploadResumeService` uses transaction; `firstOrCreate` handles duplicate upload safety.
- **Browser security**: Headers `Content-Type: text/html` prevents XSS; MIME type defined by server, not browser.
- **Storage isolation**: `local` disk `root: storage_path('app/private')` for private files.

## Implementation Steps

1. Create migration `database/migrations/YYYY_MM_DD_create_candidate_resumes.php`.
2. Create `app/Models/CandidateResume.php` with factory.
3. Create `app/Http/Requests/Candidate/UploadResumeRequest.php`.
4. Create `app/Policies/CandidateResumePolicy.php`.
5. Create `app/Services/Candidate/CandidateResumeService.php`.
6. Create `app/Http/Controllers/Candidate/CandidateResumeController.php`.
7. Add routes to `routes/web.php`.
8. Add frontend page `resources/js/pages/candidate/resumes/index.tsx`.
9. Add tests `tests/Feature/Candidate/CandidateResumeTest.php`.
10. Run tests, typecheck, build.
11. Update `memory.md` after implementation.

## Risks & Unresolved Questions

**Risks**:

- **RFC 5746 in SSH session**: Not applicable; `serve: true` with `Storage::download` is safe.
- **Replay attacks**: Old resume file remains after replace; next replace overwrites; later replace not idempotent because old file may linger; acceptable for MVP or simpler to delete old file before inserting new one (no version).
- **Dangling files on consecutive replaces**: Service deletes old file before storing new file; if new file fails after old deletion, rollback file delete? Laravel's filesystem doesn't expose well-defined rollback; alternative: store new file first, then delete old file after confirming new insert successful (two-step in transaction).
- **Overriding on concurrent upload**: `firstOrCreate` ensures only one primary; if two candidates with same user_id (same user) spam replaces, latter replaces old; race condition on old file leftover is acceptable.

**Non-blocking open questions**:

- Should we enforce unique `original_filename` per user? Assumption: No — user can upload multiple PDFs with similar names, but only latest kept.
- Should `path` contain seasonally generated path like `/YYYY/MM/DD` for readability? Spec prefers hashed; can add later.
- Should application snapshots reference by resume filename or resume ID? Assumption: reference by resume ID for future reproducibility.
- Should resume metadata be exposed to employer via resume view? Assumption: No, still in Data Privacy consent if product decides.

## Acceptance Criteria

- [ ] Authenticated job seeker can visit `/candidate/profile/resumes` and see a resume listing (or empty state).
- [ ] Job seeker can upload a valid PDF file under 5MB; server validates; file is stored securely; Inertia page shows success.
- [ ] Upload rejects invalid files (wrong MIME, oversized, non-files) with clear 422 errors.
- [ ] Job seeker can replace their old resume; previous file is removed; only one resume exists per user.
- [ ] Job seeker can delete their resume (with confirmation); file is removed from Storage; database record removed.
- [ ] Guests cannot access `candidate.resumes.*` routes.
- [ ] Users without `job_seeker` role cannot manage resumes (Policy enforcement).
- [ ] No sensitive fields (path, container paths) leaked in Inertia props to unauthorized users.
- [ ] Resume downloads are authorized-only (future employer access; candidate download requires authenticated request).
- [ ] Application references to resume document integrity preserved (no orphaned path references on delete/replace).
- [ ] Feature test suite passes, no regressions.

## Verification Commands

- `php artisan test --filter=CandidateResumeTest`
- `php artisan test`
- `npx tsc --noEmit`
- `npm run build`
