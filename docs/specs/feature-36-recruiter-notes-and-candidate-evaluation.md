# Feature 36 — Recruiter notes and candidate evaluation

    status: IMPLEMENTED (approved 2026-11-14)
    depends-on: Feature 23 (employer applicant list/detail), Feature 24 (application status transitions)
    scope-note: Feature 28 (company hiring-team members) is honored through the existing company-scoped
    permission model (`applicants.view` read / `applicants.manage` write); no new platform permission
    and no new company permission is introduced (see D1). Feature 26 may later link evaluations to
    interview-specific feedback; out of scope here.

## 1. Goal / non-goals

### Goal

Let authorized employer/company participants keep **private, application-scoped recruiter notes** and
record a **consistent, human-controlled evaluation scorecard** during recruitment — inside the existing
employer application detail workflow — without exposing any of it to candidates, unrelated companies,
or the public.

### Non-goals (explicitly out of scope)

- Candidate-visible feedback of any kind; public reviews; sharing notes/evaluations with the candidate.
- AI-generated evaluations, automated candidate ranking, automated rejection/hiring decisions,
  personality inference, or using an evaluation to change application status.
- Complex configurable assessment forms, weighted custom criteria, or questionnaires (a fixed,
  documented criterion set only).
- Interview-specific feedback linkage (Feature 26 integration) — evaluations are application-scoped.
- Platform-administrator moderation routes for notes/evaluations (no admin access path; not logged in
  the audit log, see D6).
- Email/push notifications for notes or evaluations (in-app anti-noise; Feature 25 covers application
  workflow events only).
- Bulk evaluation import/export; PDF/printable report cards.
- Cross-application or cross-job candidate comparison views (that is Feature 37 territory, and even
  there notes/evaluations must never be exposed).

## 2. User-visible behavior and acceptance criteria

1. On the employer application detail page (`employer/applications/show`), an authorized employer sees
   a **Private notes** section and an **Evaluation** section below the existing interview/screening
   sections. The candidate-facing application pages never render or receive these props.
2. Any company participant with the company-scoped `applicants.view` permission (owner, recruiter,
   reviewer) can **read** all notes and the full evaluation history for the application.
3. A participant with `applicants.manage` (owner, recruiter) can **create** a note; the note records
   the author and timestamps. They can **edit** and **delete** only **their own** notes, and only while
   they still hold the live `applicants.manage` permission (a revoked member loses edit/delete on their
   own past notes; the rows remain visible to colleagues).
4. Notes are listed newest-first with author name and timestamps; edited notes show an "(edited)"
   marker. Deleting a note removes it from the list (soft delete; see §6). There is no restore UI.
5. A participant with `applicants.manage` can **submit an evaluation**: four fixed criteria
   (relevant experience, technical skills, communication, interview performance), each an integer
   rating **1–5**, plus an optional supporting comment. Each submission is **append-only**: it creates a
   new evaluation entry; existing entries are never edited or deleted.
6. The page shows the **latest evaluation** highlighted (evaluator, timestamp, per-criterion ratings,
   comment) and a **history list** of previous evaluations (same fields, compact). If none exists, an
   explicit "No evaluation recorded yet" empty state is shown — the rest of the page is unaffected.
7. Submitting or editing an evaluation **never changes the application status** and creates no
   notification. Status changes continue exclusively through Feature 24.
8. Notes and evaluations remain available on **terminal applications** (`hired`, `rejected`,
   `withdrawn`) — they are recruitment records, not messages (contrast: Feature 35 read-only rule).
9. A **closed or archived job** likewise does not block notes/evaluations on its applications.
10. Unrelated companies, other employers without company membership, candidates/job seekers, guests,
    and platform administrators cannot read or write notes/evaluations, even by guessing IDs — every
    route binds the application and re-authorizes through the Policy.
11. Inertia props for notes/evaluations are sent **only** to the authorized employer detail page; the
    candidate pages (`candidate/applications/...`) and any shared props gain **no** notes/evaluation
    fields.
12. Revoking a company member (Feature 28) immediately removes their read/write access to notes and
    evaluations for that company's applications on the next request (live permission check, no caching).

## 3. Assumptions / decisions (labeled — approve or override at the approval gate)

- **D1 (permission mapping, decided):** No new company-scoped permission is added. Read =
  `applicants.view`; write (create/edit/delete notes, submit evaluation) = `applicants.manage`.
  Read-only company **reviewers can read but not write**. This mirrors the Feature 35 mapping and
  respects Feature 28's existing roles (owner/recruiter write, reviewer read). If the product wants
  reviewers to record evaluations, a new company permission (e.g. `evaluations.manage`) and a
  `CompanyMemberRole::permissions()` change would be required — that is a product decision, not assumed
  here.
- **D2 (rating scale, decided):** Fixed four criteria, integer ratings **1–5** with documented labels:
  1 Poor, 2 Fair, 3 Good, 4 Very good, 5 Excellent. No decimals, no 0, no 6+. Scale and criteria are
  fixed in code (enum), not configurable.
- **D3 (evaluation history, decided):** Evaluations are **append-only**. "Show the evaluation history or
  latest assessment" is satisfied by showing both. There are **no update or delete routes** for
  evaluations; a corrected assessment is a new entry. This preserves an honest accountability trail.
- **D4 (note edit/delete policy, decided):** Author-only edit and delete, each re-checked against the
  **live** `applicants.manage` predicate (same pattern as Feature 35 sender-only edit/delete).
  Notes use **soft deletes** (row retained, hidden from all views, no restore UI). Authorship
  timestamps are the retention/accountability mechanism; no separate note-history table.
- **D5 (bounded loading, justified exception to pagination):** Notes and evaluations per application
  are bounded by human authorship (same pattern as `statusHistories` and `screeningAnswers` on this
  page, which are also eager-loaded without pagination). Both queries select needed columns and eager-load
  `author/evaluator:id,name` only. No list endpoint is introduced. If a company ever accumulates
  pathological per-application note counts, converting to cursor pagination is a localized change.
- **D6 (no audit-log entries, decided):** Note/evaluation bodies are private recruitment data and must
  never enter the append-only audit log (Feature 31 already redacts/limits metadata). Accountability
  is via `author_id`/`evaluator_id` + timestamps on the rows themselves. Revisit only if compliance
  demands it.
- **D7 (author deletion):** `author_id`/`evaluator_id` are nullable with `nullOnDelete()` (mirrors
  `interviews.scheduled_by_id`). A deleted user's notes/evaluations remain (soft-kept), displayed as
  "Former team member". Rationale: hard cascade would destroy co-authored accountability data;
  restrictOnDelete would block unrelated user lifecycle operations.
- **D8 (plain text only):** Note bodies and comments are plain text, rendered by React (escaped by
  default). No Markdown/HTML rendering, no rich-text editor (XSS surface).
- **D9 (terminal/closed allowed, decided):** Status does not gate notes/evaluations (unlike messaging).
- **D10 (no notifications):** Creating/editing notes or evaluations notifies no one.
- **D11 (authoritative "authorized participants"):** "Currently authorized employer/company participants"
  means approved employers holding `applicants.view`/`applicants.manage` for the application's job's
  company — identical resolution to `JobApplicationPolicy::viewAsEmployer` / `transitionStatus`.

### Open questions for the user (non-blocking, safe defaults chosen in D1–D11)

- Should company **reviewers** be allowed to create evaluations (would add a `CompanyMemberRole`
  permission)? Default: no (D1).
- Should note deletion be hard delete instead of soft delete? Default: soft (D4).
- Is 1–5 the desired scale (vs 1–4 or 0–10)? Default: 1–5 (D2).

## 4. Routes and access boundary

All routes live in the existing `employer.` + `role:employer` + `employer.approved` middleware group in
`routes/web.php` (same group as `applications.update-status`). Named routes, consistent with Features
23–27 patterns:

| Method | URI                                       | Controller                           | Middleware      | Name                                      |
| ------ | ----------------------------------------- | ------------------------------------ | --------------- | ----------------------------------------- |
| POST   | `applications/{application}/notes`        | `EmployerNoteController@store`       | `throttle:20,1` | `employer.applications.notes.store`       |
| PUT    | `applications/{application}/notes/{note}` | `EmployerNoteController@update`      | `throttle:20,1` | `employer.applications.notes.update`      |
| DELETE | `applications/{application}/notes/{note}` | `EmployerNoteController@destroy`     | `throttle:20,1` | `employer.applications.notes.destroy`     |
| POST   | `applications/{application}/evaluations`  | `EmployerEvaluationController@store` | `throttle:20,1` | `employer.applications.evaluations.store` |

- No GET routes: notes/evaluations are delivered as Inertia props on the existing
  `employer.applications.show` Inertia page.
- `{note}` is scoped implicitly: the controller loads the note by `id` **and** `application_id`
  (Route binding does not constrain it) — a guessed note ID from another application 404s.
- Server-side page GET authorization (already in place): `viewAsEmployer` on the show controller.
- Form Request `authorize()` delegates to the Policy (`can`), per house convention.

## 5. Policy actions (new policies)

New file `app/Policies/ApplicationNotePolicy.php`:

```
view(User $actor, ApplicationNote $note): bool
    // live applicants.view on the application's job's company (D11)
createForApplication(User $actor, JobApplication $application): bool
    // live applicants.manage
update(User $actor, ApplicationNote $note): bool
    // actor is the (live, non-null) author AND live applicants.manage
delete(User $actor, ApplicationNote $note): bool
    // same predicate as update
```

New file `app/Policies/ApplicationEvaluationPolicy.php`:

```
view(User $actor, ApplicationEvaluation $evaluation): bool  // live applicants.view
createForApplication(User $actor, JobApplication $application): bool  // live applicants.manage
// Intentionally NO update/delete methods — evaluations are append-only (D3).
```

Registration in `App\Providers\AppServiceProvider::boot()`:
`Gate::policy(ApplicationNote::class, ApplicationNotePolicy::class);`
`Gate::policy(ApplicationEvaluation::class, ApplicationEvaluationPolicy::class);`

Both policies resolve company via `$application->jobPosting->company` and call
`$actor->isApprovedEmployer()` + `$actor->hasCompanyPermission(...)` — the same private helper pattern
as `ConversationPolicy`/`InterviewPolicy` (duplicated per policy, per codebase precedent; no shared
trait is introduced). Job ownership scoping is guaranteed because every route binds `{application}`
first and all checks derive the company from it; `job_posting_id`/`candidate_id` are never read from
request input.

## 6. Models, relationships, migration, constraints, indexes, deletion semantics

### `application_notes` (new)

| Column           | Type                              | Constraints                                             |
| ---------------- | --------------------------------- | ------------------------------------------------------- |
| `id`             | bigint unsigned auto-increment PK |                                                         |
| `application_id` | bigint unsigned                   | FK → `job_applications.id`, `cascadeOnDelete`, required |
| `author_id`      | bigint unsigned nullable          | FK → `users.id`, `nullOnDelete`                         |
| `body`           | text                              | required                                                |
| `edited_at`      | timestamp nullable                |                                                         |
| `deleted_at`     | soft deletes                      |                                                         |
| `timestamps`     |                                   |                                                         |

Index: `(application_id, created_at)` named `application_notes_application_created_idx`.
`$fillable = ['application_id', 'author_id', 'body', 'edited_at']`; SoftDeletes on the model.

### `application_evaluations` (new)

| Column                  | Type                              | Constraints                                             |
| ----------------------- | --------------------------------- | ------------------------------------------------------- |
| `id`                    | bigint unsigned auto-increment PK |                                                         |
| `application_id`        | bigint unsigned                   | FK → `job_applications.id`, `cascadeOnDelete`, required |
| `evaluator_id`          | bigint unsigned nullable          | FK → `users.id`, `nullOnDelete`                         |
| `relevant_experience`   | tinyint unsigned                  | required, 1–5 (validated)                               |
| `technical_skills`      | tinyint unsigned                  | required, 1–5 (validated)                               |
| `communication`         | tinyint unsigned                  | required, 1–5 (validated)                               |
| `interview_performance` | tinyint unsigned                  | required, 1–5 (validated)                               |
| `comment`               | text nullable                     | max 2000 chars (validated)                              |
| `timestamps`            |                                   |                                                         |

Index: `(application_id, created_at)` named `application_evaluations_application_created_idx`.
No soft deletes, no `edited_at`, no unique constraints (multiple evaluations per application allowed).

### Relationships

- `JobApplication`: `hasMany(ApplicationNote::class)` (latest-first scope) and
  `hasMany(ApplicationEvaluation::class)` — both added for ergonomic access from the show controller;
  eager-loaded explicitly there (never via lazy loading in loops).
- `ApplicationNote`: `belongsTo(JobApplication)`, `belongsTo(User, 'author_id')`.
- `ApplicationEvaluation`: `belongsTo(JobApplication)`, `belongsTo(User, 'evaluator_id')`.
- Casts: `edited_at`/`created_at` → datetime; ratings → `int` (or `integer`); status untouched.

### Deletion semantics

- Application deleted → notes + evaluations cascade (same as interviews; applications are never
  hard-deleted in the current codebase, so this is a safety net).
- Note delete = soft delete (D4). Evaluation rows are never deleted by the app (D3).
- User deletion → `nullOnDelete`, display "Former team member" (D7).
- Migrations: two new files (`2026_11_17_000000_create_application_notes_table.php`,
  `2026_11_17_000001_create_application_evaluations_table.php` — exact sequence decided at
  implementation; must sort after the Feature 35 migrations). Both `up()`/`down()` idempotent
  (`Schema::hasTable` guard in `up()`; plain `dropIfExists` in `down()`). No FK-backing index pitfalls
  are expected (single-column FKs with composite `application_id, created_at` index added
  deliberately for the eager-load order); still verify on MySQL, not only SQLite.

### Enum

`App\Enums\EvaluationCriterion` (backed string enum, label()): `RelevantExperience` =
`relevant_experience`, `TechnicalSkills` = `technical_skills`, `Communication` = `communication`,
`InterviewPerformance` = `interview_performance`. Used for labels in UI/tests only; stored columns are
typed, not JSON.

## 7. Form Requests and validation

`StoreNoteRequest` / `UpdateNoteRequest` (`App\Http\Requests\Employer\`):

```
body: required, string, min:1 (after trim — 'required' handles), max:5000
```

- `authorize()`: `$this->user()?->can('createForApplication', [ApplicationNote::class, $this->route('application')])` for store; for update: loaded `ApplicationNote` resolved with `where application_id = route application`, then `can('update', $note)`. (Alternatively authorize inline in the controller via `$this->authorize` — either satisfies the house rule; Form Request authorize is preferred.)
- `body` is trimmed; stored as-is; empty/whitespace rejected by `required`.

`StoreEvaluationRequest`:

```
relevant_experience:  required, integer, between:1,5
technical_skills:     required, integer, between:1,5
communication:        required, integer, between:1,5
interview_performance: required, integer, between:1,5
comment:              nullable, string, max:2000
```

- `authorize()`: `can('createForApplication', [ApplicationEvaluation::class, $application])`.
- **No** `status`, `job_id`, `candidate_id`, `application_id`, or rating keys beyond the four criteria
  accept-listed — mass assignment impossible via validated mapping (`$request->validated()` mapped
  explicitly to column list).

All three requests ignore any client-supplied author/evaluator IDs (server assigns `auth()->id()`).

## 8. Query shape, selected columns, eager loading, pagination, scale

Employer show endpoint additions (single page load, no extra HTTP round-trips):

```php
$notes = ApplicationNote::query()
    ->where('application_id', $application->id)
    ->select(['id', 'application_id', 'author_id', 'body', 'edited_at', 'created_at'])
    ->with('author:id,name')
    ->latest('created_at')
    ->get();  // justified bounded (D5) — human-bounded rows per application

$evaluations = ApplicationEvaluation::query()
    ->where('application_id', $application->id)
    ->select(['id', 'application_id', 'evaluator_id', 'relevant_experience', 'technical_skills',
              'communication', 'interview_performance', 'comment', 'created_at'])
    ->with('evaluator:id,name')
    ->latest('created_at')
    ->get();  // justified bounded (D5)
```

Implementation note: prefer `$application->notes()->...` / `$application->evaluations()->...` relations
over raw queries; both `latest('created_at')` orders are backed by the `(application_id, created_at)`
index. No N+1 (single eager-load each). No counts needed. List endpoints unchanged; applicant list
(Feature 23) and Feature 37 board payloads gain nothing.

Selected payload columns to the client (see §9) exclude nothing sensitive beyond the above — notes and
evaluations are themselves the private payload, gated by page access.

## 9. Inertia page props, React components, shared types, UI states

`EmployerJobApplicationController@show` gains props (only on this page):

```ts
can_manage_notes: boolean; // live applicants.manage (drives write UI)
notes: Array<{
    // soft-deleted excluded
    id: number;
    body: string;
    edited_at: string | null; // ISO8601
    created_at: string | null;
    author: { id: number; name: string } | null; // null → "Former team member"
    can_edit: boolean; // author && live applicants.manage
    can_delete: boolean; // same predicate
}>;
evaluations: Array<{
    // latest first; evaluations[0] is "latest"
    id: number;
    created_at: string | null;
    evaluator: { id: number; name: string } | null;
    ratings: {
        relevant_experience: number;
        technical_skills: number;
        communication: number;
        interview_performance: number;
    };
    comment: string | null;
}>;
```

(can_view is implied by page access; no separate flag.)

Frontend (new components, mounted in `resources/js/pages/employer/applications/show.tsx` after the
interviews/screening sections):

- `resources/js/components/notes/application-notes.tsx`
    - `useForm({ body: '' })` → `router.post(route actions notes.store ...)` (Wayfinder), `processing`-disabled submit, field error via `<InputError>`.
    - Latest-first: the **latest** note is shown expanded; any older notes sit behind a collapsible
      "Earlier notes (n)" disclosure (mirrors the Evaluation history pattern, keeping long lists from
      bloating the page). Each note shows author name, absolute timestamp, edited marker, and body
      (whitespace-pre-wrap, plain text). Edit-in-place swaps to a textarea (`useForm` put) with cancel;
      Delete opens a confirm dialog (codebase `confirm()`).
    - Empty state: "No notes yet. Notes are private to your company team."
    - Renders read-only (no composer, no edit/delete buttons) when `can_manage_notes` is false/absent.
- `resources/js/components/evaluation/evaluation-scorecard.tsx`
    - Latest evaluation highlighted card; below it a collapsible history list (same compact rows).
    - Form: four `<select>`/radio groups 1–5 with labels (1 Poor … 5 Excellent), optional comment
      `<Textarea maxLength={2000}>`, submit "Save evaluation"; `useForm` post; processing-disabled;
      errors per field. Always appends (never edits). Hidden entirely (read-only latest + history only)
      when `can_manage_notes` is false.
    - Caption on every evaluation view: "Recruiter-recorded ratings — not an automated or objective
      assessment." (Feature 36 rule: never present a score as objective.)
    - Empty state: "No evaluation recorded yet."
- Types: new `resources/js/types/note.ts` and `resources/js/types/evaluation.ts` (or one
  `types/recruitment.ts`), re-exported from `types/index.ts` following existing convention; page `Props`
  extended accordingly. Wayfinder regenerated via `npm run build`.
- No candidate-side pages, shared Inertia props, navigation items, notifications, or Echo/broadcast
  changes. No layout changes.

PDF/print, rich text, avatars: out of scope.

## 10. Test matrix

New tests: `tests/Feature/Recruitment/ApplicationNotesTest.php` and
`tests/Feature/Recruitment/ApplicationEvaluationTest.php` (Pest-functional style consistent with
`tests/Feature/Messaging/`). Fixtures reuse `JobApplicationFactory` etc.; note/evaluation factories
added if the suite uses them conventionally.

### Notes

| #   | Case                                                                                                                                                            |
| --- | --------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| N1  | Owner/recruiter (applicants.manage) creates a note; author and timestamps persisted; visible in show props.                                                     |
| N2  | Reviewer (applicants.view) can read notes but POST/PUT/DELETE → 403.                                                                                            |
| N3  | Candidate (job_seeker role) cannot access employer note routes → 403/redirect per `role:employer` middleware behavior; notes absent from candidate pages/props. |
| N4  | Guest → redirected to login (employer group middleware).                                                                                                        |
| N5  | Cross-company employer (other company, even with applicants.manage there) → 403 on store/update/delete for this application.                                    |
| N6  | Note ID from another application (via `notes/{note}` with mismatched `{application}`) → 404, no mutation.                                                       |
| N7  | Validation: empty body, whitespace-only body, body > 5000 chars → 422 with field errors.                                                                        |
| N8  | Author can edit own note; `edited_at` set; other company member (even owner) cannot edit it.                                                                    |
| N9  | Revoked member (membership revoked after authoring) cannot edit/delete own note; note remains visible to company.                                               |
| N10 | Author can soft-delete own note; row retained in DB, excluded from props; no restore route exists.                                                              |
| N11 | Deleted note no longer appears in Inertia props; evaluation of others' notes: delete-by-stranger denied (403).                                                  |
| N12 | Terminal application (hired/rejected/withdrawn) — note create still allowed (D9); closed job — still allowed.                                                   |
| N13 | `author:id,name` eager load — assert query count bounded (optional `DB::enableQueryLog()` assertion) to guard N+1.                                              |
| N14 | Notes are absent from candidate application show props and from any shared props.                                                                               |

### Evaluations

| #   | Case                                                                                                                                                                                                               |
| --- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| E1  | Owner/recruiter submits evaluation; all four ratings + comment persisted; evaluator + timestamps recorded; appears as latest in props.                                                                             |
| E2  | Reviewer can read latest + history but POST → 403.                                                                                                                                                                 |
| E3  | Candidate and guest cannot read or write (mirror N3/N4); evaluations absent from candidate props.                                                                                                                  |
| E4  | Cross-company employer → 403 on store; cannot fetch via guessed application ID.                                                                                                                                    |
| E5  | Validation: missing each criterion → 422; rating 0 / 6 / 3.5 / `"5"`-as-non-int → 422; comment > 2000 chars → 422; extra keys (`status`, `job_id`, `candidate_id`) ignored — application status and job unchanged. |
| E6  | Submitting an evaluation does **not** change `application.status` and dispatches no notification (assert `Notification::fake()` none sent).                                                                        |
| E7  | Append-only: submitting twice yields two history entries, latest first; no PUT/DELETE/PATCH evaluation routes registered (assert 405 on such verbs).                                                               |
| E8  | Ratings history ordering and evaluator identity correct across multiple evaluators; non-author cannot delete anything (no such route).                                                                             |
| E9  | Terminal application — evaluation create allowed (D9); closed job — allowed.                                                                                                                                       |
| E10 | Inertia show props include `evaluations` scoped to authorized employer only; `can_manage_notes` false for reviewer.                                                                                                |

### Regression / adjacent

- Existing employer application detail tests (`tests/Feature/Employer/...Application*`) still pass —
  page shape is a superset.
- Feature 24 transition tests unaffected (evaluation never writes status).
- Feature 22 candidate history/show tests assert absence of notes/evaluation keys.
- Messaging tests unaffected (no shared code paths touched beyond additive relations).

## 11. Security / performance risks and mitigations

- **IDOR/cross-tenant access (critical):** every route binds `{application}`; company derived from
  `application->jobPosting->company`; live `hasCompanyPermission` check per request; note/evaluation
  rows re-scoped by `application_id`. Tests N5/N6/E4 cover.
- **Privilege creep via reviewer:** reviewers are read-only for writes (D1); write path requires
  `applicants.manage` everywhere (Policy + Form Request authorize double-check).
- **XSS:** React escaping only; no `dangerouslySetInnerHTML`; bodies are plain text (D8).
- **Content leakage via logs/audit/notifications/shared props:** bodies never enter audit log (D6),
  notifications (D10), broadcast events (none), or candidate/admin payloads (Inertia scoping; §9).
- **Mass assignment:** validated-mapping only; author/evaluator IDs server-assigned; `$fillable`
  declared; no `status`/`job_id`/`candidate_id` writable.
- **Soft-delete orphaning:** soft-deleted notes excluded via global scope — verify Livewire/Inertia
  props and any debug dumps use the relation (default) — fine.
- **N+1 / query shape:** eager loads `author`/`evaluator` with `:id,name`; bounded result sets (D5);
  composite `(application_id, created_at)` indexes; no pagination needed (justified).
- **Concurrency:** evaluation append-only — no read-modify-write race; notes edit is a single row
  update with `edited_at` server timestamp; no locking required.
- **Data retention:** cascade on application delete; user deletion → nullOnDelete (D7); soft-deleted
  notes retained in DB (document in retention policy; no purge job in this feature).
- **Scale:** unaffected by user volume; per-application rows only; employer detail page gains 2
  bounded queries. Recommend spot-check with `EXPLAIN` on MySQL for the two new indexes during
  implementation, and rerun `php artisan test`.
- **Role/cache staleness:** `hasCompanyPermission` resolves per request (membership fetch) — revocation
  effective immediately (N9/E-revocation case). No caching.

## 12. Rollout / compatibility notes

- Additive migrations; no changes to existing tables. Safe to deploy ahead of the code paths using
  them. Runs on both SQLite (tests) and MySQL (dev/prod) — guards per §6.
- No new platform permission ⇒ `AuthorizationCatalogueTest`, `docs/authorization.md`, and
  `AuthorizationSeeder` untouched (company-scoped permission reused; `CompanyMemberRole` unchanged).
- No changes to `handleInertiaRequests` shared props, navigation, or `echo.ts`.
- Frontend bundle rebuilt via `npm run build` (Wayfinder regeneration); employer show page only.
- No environment variables, packages, or queue/reverb changes.
- Feature 37 (pipeline board) must not add notes/evaluation data to cards — out of scope, restated.

## 13. Implementation sequence (after approval)

1. Migrations (notes, evaluations) + verify `up()`/`down()` idempotency on MySQL.
2. Models (`ApplicationNote`, `ApplicationEvaluation`) + `EvaluationCriterion` enum + factory; relations on `JobApplication`.
3. Form Requests (`StoreNoteRequest`, `UpdateNoteRequest`, `StoreEvaluationRequest`).
4. `ApplicationNotePolicy`, `ApplicationEvaluationPolicy`; register in `AppServiceProvider`.
5. `EmployerNoteController`, `EmployerEvaluationController` (thin; single-write actions) + named routes + throttles in the employer group.
6. Extend `EmployerJobApplicationController@show` with the two bounded queries + props (+ `can_manage_notes`).
7. Frontend: types, `application-notes.tsx`, `evaluation-scorecard.tsx`, wire into `employer/applications/show.tsx`; `npm run build`.
8. Tests (§10) — feature tests first (red → green), then run full suite.
9. Pint, phpstan, `npm run types:check`, `npm run check`, `npm run build`.
10. Manual smoke: create/edit/delete note; submit evaluation twice (history); reviewer read-only; revoked member denied.

## 14. Verification commands available in the repo

```
php artisan test                                        # full Pest suite (SQLite :memory:)
php artisan test --filter=ApplicationNotes
php artisan test --filter=ApplicationEvaluation
vendor\bin\pint                                          # fix style
php vendor/bin/phpstan analyse --memory-limit=512M --no-progress
npm run types:check                                      # tsc --noEmit
npm run check                                            # vp (prettier+lint) across resources/
npm run build                                            # incl. Wayfinder regen
npx vp check --fix docs/specs/feature-36-recruiter-notes-and-candidate-evaluation.md
```

MySQL migration check: apply on dev database per Feature 35 practice (`php artisan migrate` on the
MySQL `.env`), since InnoDB index behavior differs from SQLite.

## 15. Approval gate

This spec was approved by the user on 2026-11-14. Key decisions D1–D11 are labeled in §3:

- **D1** — reviewers read-only for notes/evaluations (no new company permission).
- **D2** — 1–5 scale, four fixed criteria.
- **D3** — append-only evaluations (no edit/delete, history preserved).
- **D4** — author-only note edit/delete + soft delete.

## 16. Implementation record (2026-11-14)

- Approval: user approved this spec and requested implementation on 2026-11-14.
- Deviations from the spec text, all within its approved decisions:
    - Ratings are validated as strict JSON ints 1–5 (`is_int`), rejecting `"5"` strings and `3.5` floats; this matches the strict reading of D2/§10 E5.
    - `StoreNoteRequest`/`StoreEvaluationRequest` perform the Policy check in `authorize()` (spec §7 preferred) **and** controllers re-call `$this->authorize()` (AGENTS.md rule 2). `UpdateNoteRequest` resolves `{note}` scoped by `application_id` inside `authorize()` and `abort(404)` on mismatch (D6; guessed IDs 404).
    - `EmployerNoteController@destroy` takes a raw `{note}` param and resolves scoped + `findOrFail` (404 on mismatch); `{note}` is not implicitly bound.
    - Both relations order by `created_at DESC` **and** `id DESC` (deterministic tiebreak; MySQL second-precision timestamps could otherwise tie).
    - `can_manage_notes` also requires `isApprovedEmployer()` (mirrors `can_view_applicants`/policies; fail-closed UI gate).
    - `EvaluationCriterion` enum is wired into `StoreEvaluationRequest` (rules/messages) per §6 rather than left dead.
    - Migrations renumbered `2026_11_16_000002/000003_*` (after the Feature 35 migrations; not future-dated).
    - `tests/Feature/Candidate/JobApplicationTest.php::test_successful_submission_persists_answers` was flaky (JobFactory random `application_deadline`) and was pinned to `now()->addDays(30)` — unrelated to this feature; it is committed separately or called out in the commit message, per review.
    - Post-approval UI tweak (same day, user request): notes list now shows the **latest** note expanded with older notes behind an "Earlier notes (n)" collapsible (mirrors the Evaluation history pattern). No data-model change.
- Migrations **not** applied to dev MySQL (local 127.0.0.1:3309 refused connections); both migrations verified only via the SQLite test suite. MySQL/InnoDB index verification pending.
- Verification actually run (post-review tree):
    - `php artisan test` — **799 passed / 799, 6327 assertions** (twice green after fixes; 797 before the two added revocation/owner tests).
    - `vendor\bin\pint` — passed. `php vendor/bin/phpstan analyse --memory-limit=512M --no-progress` — 0 errors.
    - `npm run types:check`, `npm run check` (vp) — passed. `npm run build` — exit 0 (Wayfinder regenerated; build output gitignored).
    - Independent `code-reviewer` (read-only) verdict: PASS with warnings; W1–W4 and suggestions 1–3 were applied (relation tiebreak, `isApprovedEmployer()` in `can_manage_notes`, revocation/owner-edit tests, enum wiring, shared team-member type, `router.delete()` + edit-state reset, migration renumber).
- Manual browser verification: pending (user). Nothing was committed.
