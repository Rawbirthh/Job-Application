---
name: feature-27-screening-questions
description: Specification for Feature 27 — Job-specific screening questions (with reusable question bank and templates)
metadata:
    type: project
    status: IMPLEMENTED
    created: 2026-11-08
    updated: 2026-11-08
---

# Feature 27 — Job-specific screening questions

## Metadata

- **Feature ID**: Feature 27
- **Title**: Job-specific screening questions
- **Source**: `project-overview.md` (Feature 27)
- **Dependencies**: Feature 13 (employer job draft CRUD), Feature 14 (job submission/moderation), Feature 21 (submit application), Feature 23 (employer applicant list/detail)
- **Status**: IMPLEMENTED — Feature 27 complete
- **Baseline**: repository clean on `main`; Feature 26 committed; full suite currently **439 passing / 3161 assertions**.

---

## Goal

Let an approved employer build a reusable bank of screening questions, group them into named templates, and apply a template (or individual questions) to a job they manage. Let eligible job seekers see the job's active questions when applying, answer them with type-specific validation, and have those answers persisted immutably on the application. Let the owning employer and the applicant view the submitted answers on their application detail pages. Prevent cross-company and cross-candidate access.

## Non-goals

- Admin-configured global question templates or a shared platform-wide question bank.
- File-upload, multi-select, date, or numeric question types (MVP types only: short text, long text, yes/no, single choice).
- Conditional/branching questions, scoring, or automated screening.
- Candidate editing of answers after submission (answers are immutable; withdraw + reapply is the only path, and reapplication is not allowed).
- Question versioning or diffing; instead, questions with submitted answers are protected from edits/deletion (see A5).
- Public exposure of questions or answers on public job detail pages or public search.
- Email/SMS/push notifications for question or template changes.
- Company-team scoping (Feature 28); questions/templates remain company-owner-only.

---

## Actors and user flows

### Employer (approved company owner) — question bank

1. Opens the question bank: `/employer/screening-questions`.
2. Creates individual questions: type, prompt, required/optional flag, choice options for `single_choice`.
3. Edits or deactivates a bank question. Hard-deletes only when it is not used in any template or job.
4. Sees usage counts (templates and jobs referencing the question) to understand lock-in.

### Employer — templates

1. Opens templates: `/employer/screening-templates`.
2. Creates a template: name, optional description, and an ordered selection of bank questions.
3. Edits a template's name/description/question set/order.
4. Deactivates or deletes a template. Deleting a template does not affect jobs that already applied it (they hold copied questions).

### Employer — applying questions to a job

1. Opens job questions management: `/employer/jobs/{job}/screening-questions`.
2. Sees the job's current questions (ordered) with type, prompt, required flag, active state, and answer counts.
3. Applies a template: its questions are copied into the job in template order (appended after existing questions). May apply multiple templates.
4. Also adds individual bank questions, creates a new bank question inline, edits/removes job questions, and reorders — all allowed before or after template application (mix allowed).
5. Edits/deactivates/removes a job question subject to the answered-question protection (A5).

### Candidate (job seeker)

1. Opens the apply form for a published job: `/candidate/jobs/{job}/apply`.
2. Sees the job's active screening questions (ordered) in addition to resume selection and cover letter.
3. Answers each question according to its type; required questions must be answered before submission.
4. Submits the application; answers are validated server-side and persisted in the same transaction as the application.
5. Views their submitted answers (read-only) on `/candidate/applications/{application}`.

### Employer reviewer

1. Opens an application detail page: `/employer/applications/{application}`.
2. Sees the candidate's submitted screening answers in question order, with the question prompt and the candidate's answer.

---

## Assumptions and product decisions

1. **A1 — MVP question types only**: `short_text`, `long_text`, `yes_no`, `single_choice`. Stored as a string enum. `short_text` max 255 chars; `long_text` max 5000 chars.
2. **A2 — Single choice options**: 2–10 non-empty, unique strings, each max 100 chars. Stored as a JSON array.
3. **A3 — Required/optional**: `is_required` defaults to false. A required question must have a non-empty answer; an optional question may be omitted entirely (no answer row created).
4. **A4 — Snapshot on apply**: Applying a template (or adding a bank question) **copies** the question definition into `job_screening_questions` (with `source_question_id` for traceability). Template/bank edits never propagate to jobs that already received the questions. This preserves the meaning of prior applications and submitted answers.
5. **A5 — Immutability of answered job questions**: Once a `job_screening_question` has at least one submitted answer, its `type`, `label`, and `options` cannot be changed and it cannot be hard-deleted. It may still be deactivated (`is_active = false`) and reordered. Job questions with no answers may be freely edited or hard-deleted.
6. **A6 — Answers are immutable**: There is no update endpoint for answers. They are written once at submission and read thereafter.
7. **A7 — Ownership scope**: Question bank and templates are owned by the approved employer **user** (reusable across all their companies/jobs), consistent with existing company-owner checks. `owner_user_id` is never taken from the request.
8. **A8 — No new permissions**: Access is enforced through approved-employer + ownership checks (consistent with Features 23–24). No new permission slugs are added.
9. **A9 — Management eligibility**: Job questions may be managed for jobs whose status is `draft`, `rejected`, `pending_review`, or `published`. Closed jobs are read-only for screening questions.
10. **A10 — Answer storage**: Separate nullable columns per answer shape (`answer_text`, `answer_boolean`, `selected_option`) rather than a single JSON blob.
11. **A11 — No snapshot columns on answers**: Because answered job questions are protected from edits/deletion, answer rows safely reference the job question row.
12. **A12 — Transactional submission**: Application creation and answer persistence happen in one DB transaction. Notifications remain outside the transaction (Feature 25).
13. **A13 — Answer ordering**: Answers display in the job question's current `sort_order`.
14. **A14 — Bank question deletion**: A bank question may be hard-deleted only when it is not referenced by any template or job (via `source_question_id`). Otherwise it must be deactivated.
15. **A15 — Template deletion safety**: Deleting a template does not cascade to jobs; jobs retain their copied questions. Templates with jobs that applied them may still be deleted (jobs are independent), but deactivation is preferred for auditability.
16. **A16 — Mixing allowed**: A job may combine questions from multiple templates with individually added bank questions and inline-created questions.
17. **A17 — Cap**: At most 50 questions per job and 100 questions per bank (enforced in controllers to keep payloads bounded).

No blocking questions remain. The assumptions above are design-level refinements of the roadmap text and the user's template proposal.

---

## User-visible acceptance criteria

- [ ] An approved employer can create, edit, deactivate, and (when unused) delete bank questions of all four MVP types.
- [ ] Bank question create/update rejects invalid type, empty prompt, invalid/missing choice options, and out-of-range sort order with field errors and no partial write.
- [ ] An approved employer can create, edit, deactivate, and delete templates, assigning an ordered subset of bank questions.
- [ ] Template question sets can be reordered; duplicate questions within a template are prevented.
- [ ] Applying a template to a job copies its questions into the job in template order (appended); applying multiple templates accumulates questions.
- [ ] Template/bank edits after application do not alter the job's copied questions.
- [ ] Job question management allows individual add (from bank or inline new), edit, deactivate, hard-delete (if no answers), and reorder; mixing with template-applied questions works.
- [ ] A job question that has submitted answers cannot have its `type`, `label`, or `options` changed and cannot be hard-deleted; attempts return 422 with a clear message. It can still be deactivated and reordered.
- [ ] Deactivating a job question removes it from the apply form for new applicants but keeps it readable on prior applications.
- [ ] Hard-deleting an unused job question succeeds; the question disappears entirely.
- [ ] Cross-company / non-owner / unapproved employers receive 403 on every bank, template, and job-question action; guessed IDs do not cross tenant boundaries.
- [ ] Closed jobs reject job-question management even for the owner.
- [ ] A candidate applying to a published job sees the active job questions in order and can submit answers.
- [ ] Required questions without an answer block submission with a field error; optional questions may be omitted.
- [ ] Type-specific validation rejects: short text over 255 chars, long text over 5000 chars, `single_choice` values not in options, yes/no non-boolean.
- [ ] Answers are persisted and visible to the candidate on their application detail page and to the owning employer on the application detail page.
- [ ] Answers are immutable: no endpoint updates them; tampering is rejected.
- [ ] Application submission remains atomic: if answer validation fails, no application row is created.
- [ ] Empty, loading, validation-error, and processing states are clear and accessible on all new pages.

---

## Routes and access boundary

All routes live inside the existing `auth` group in `routes/web.php`, within the employer `role:employer`, `employer.approved` middleware group.

### Question bank routes

| Method | URI                              | Name                                   | Controller                                   |
| ------ | -------------------------------- | -------------------------------------- | -------------------------------------------- |
| GET    | `screening-questions`            | `employer.screening-questions.index`   | `Employer\ScreeningQuestionController@index` |
| POST   | `screening-questions`            | `employer.screening-questions.store`   | `...@store` (throttle:20,1)                  |
| PUT    | `screening-questions/{question}` | `employer.screening-questions.update`  | `...@update` (throttle:20,1)                 |
| DELETE | `screening-questions/{question}` | `employer.screening-questions.destroy` | `...@destroy` (throttle:20,1)                |

### Template routes

| Method | URI                              | Name                                   | Controller                                   |
| ------ | -------------------------------- | -------------------------------------- | -------------------------------------------- |
| GET    | `screening-templates`            | `employer.screening-templates.index`   | `Employer\ScreeningTemplateController@index` |
| POST   | `screening-templates`            | `employer.screening-templates.store`   | `...@store` (throttle:20,1)                  |
| GET    | `screening-templates/{template}` | `employer.screening-templates.show`    | `...@show`                                   |
| PUT    | `screening-templates/{template}` | `employer.screening-templates.update`  | `...@update` (throttle:20,1)                 |
| DELETE | `screening-templates/{template}` | `employer.screening-templates.destroy` | `...@destroy` (throttle:20,1)                |

### Job-question routes

| Method | URI                                             | Name                                               | Controller                                      |
| ------ | ----------------------------------------------- | -------------------------------------------------- | ----------------------------------------------- |
| GET    | `jobs/{job}/screening-questions`                | `employer.jobs.screening-questions.index`          | `Employer\JobScreeningQuestionController@index` |
| POST   | `jobs/{job}/screening-questions`                | `employer.jobs.screening-questions.store`          | `...@store` (throttle:20,1)                     |
| POST   | `jobs/{job}/screening-questions/apply-template` | `employer.jobs.screening-questions.apply-template` | `...@applyTemplate` (throttle:20,1)             |
| PUT    | `jobs/screening-questions/{question}`           | `employer.jobs.screening-questions.update`         | `...@update` (throttle:20,1)                    |
| DELETE | `jobs/screening-questions/{question}`           | `employer.jobs.screening-questions.destroy`        | `...@destroy` (throttle:20,1)                   |
| POST   | `jobs/{job}/screening-questions/reorder`        | `employer.jobs.screening-questions.reorder`        | `...@reorder` (throttle:20,1)                   |

No candidate-facing new routes: answers travel through the existing `candidate.applications.store`. No admin routes: question management is employer-owned.

---

## Policy actions and dynamic role/permission resolution

Create three policies, each `authorize()`-driven from controllers:

### `Employer\ScreeningQuestionPolicy` (bank questions)

- `viewAny(User $actor)`: `$actor->isApprovedEmployer()`.
- `create(User $actor)`: same.
- `update(User $actor, ScreeningQuestion $question)`: `$actor->isApprovedEmployer() && $question->owner_user_id === $actor->id`.
- `delete(User $actor, ScreeningQuestion $question)`: same ownership; hard delete blocked in controller when referenced by any template or job.

### `Employer\ScreeningQuestionTemplatePolicy`

- `viewAny/create(User $actor)`: `$actor->isApprovedEmployer()`.
- `view/update/delete(User $actor, ScreeningQuestionTemplate $template)`: `$actor->isApprovedEmployer() && $template->owner_user_id === $actor->id`.

### `Employer\JobScreeningQuestionPolicy`

- `viewAny/create/applyTemplate/reorder(User $actor, Job $job)`: `$actor->isApprovedEmployer() && $job->company !== null && $job->company->owner_user_id === $actor->id && in_array($job->status, ['draft','rejected','pending_review','published'], true)`.
- `update/delete(User $actor, JobScreeningQuestion $question)`: resolve `$job = $question->jobPosting`; same ownership + status eligibility. Additionally, if the question has submitted answers, `update` allows only `is_active` and `sort_order` (enforced in Form Request/controller).

No new permission slugs. Company ownership checked server-side; `owner_user_id` never from request.

`JobApplicationPolicy::view` / `viewAsEmployer` continue to govern who may see answers (candidate owner or owning employer).

---

## Form Requests and validation

### Bank questions — `Employer\StoreScreeningQuestionRequest` / `UpdateScreeningQuestionRequest`

- `authorize()` returns `true` (controller authorizes).
- Rules:
    - `type`: required, string, in `short_text,long_text,yes_no,single_choice`.
    - `label`: required, string, min:3, max:500.
    - `options`: required if `type = single_choice`, otherwise nullable; array, min:2, max:10; each string, max:100, distinct.
    - `is_required`: boolean.
    - `is_active`: boolean (default true).
- Mapper returns explicitly mapped fields only.

### Templates — `Employer\StoreScreeningTemplateRequest` / `UpdateScreeningTemplateRequest`

- `name`: required, string, min:3, max:150.
- `description`: nullable, string, max:1000.
- `question_ids`: required, array, min:1, max:50 (bank question IDs).
- `withValidator`: all IDs exist, belong to the actor's bank, and are unique; cap total template questions at 50.

### Job questions — `Employer\StoreJobScreeningQuestionRequest` / `UpdateJobScreeningQuestionRequest`

- For `store`: either `question_id` (existing bank question) OR a full new-question payload (`type`, `label`, `options`, `is_required`). Validated via `withValidator` that exactly one source is provided and, if `question_id`, it belongs to the actor's bank.
- For `update`: same fields as bank question update, plus `is_active`, `sort_order`. Answer-protection (A5) enforced in `withValidator`: if answers exist, reject changes to `type`, `label`, `options`.

### `Employer\ApplyScreeningTemplateRequest`

- `template_id`: required, integer, exists in `screening_question_templates`.
- `withValidator`: template belongs to actor; job has capacity (< 50 questions after apply).

### `Employer\ReorderJobScreeningQuestionsRequest`

- `question_ids`: required, array; each integer exists in `job_screening_questions` and belongs to the route job; unique.

### Candidate answers in `Candidate\StoreJobApplicationRequest`

Extend the existing request:

- Add `answers` => `nullable`, `array`.
- Each item:
    - `answers.*.question_id`: required, integer, exists in `job_screening_questions`.
- In `withValidator` (after existing job checks):
    - Load the job's active questions.
    - Ensure every submitted `question_id` belongs to the job and is active.
    - Ensure no duplicate `question_id`s.
    - For each active required question, require a corresponding answer.
    - Type-specific:
        - `short_text`: `answer_text` required (if required) / nullable string, max:255.
        - `long_text`: `answer_text` nullable string, max:5000.
        - `yes_no`: `answer_boolean` required (if required) / nullable boolean.
        - `single_choice`: `selected_option` required (if required) / nullable string and must be in the question's options.
    - Reject answers for inactive or unknown questions.
    - Trim string answers; rendering escapes by default in React (no `dangerouslySetInnerHTML`).

---

## Models, relationships, migration, constraints, and indexes

### New enum

`App\Enums\ScreeningQuestionType` (string): `ShortText = 'short_text'`, `LongText = 'long_text'`, `YesNo = 'yes_no'`, `SingleChoice = 'single_choice'`. Includes `label()`.

### New models

**`App\Models\ScreeningQuestion`** (table `screening_questions`) — bank question

- `$fillable`: `owner_user_id`, `type`, `label`, `options`, `is_required`, `is_active`.
- `$casts`: `type => ScreeningQuestionType`, `options => array`, `is_required => boolean`, `is_active => boolean`.
- `belongsTo(User::class, 'owner_user_id')`.
- `belongsToMany(ScreeningQuestionTemplate::class, 'screening_template_questions')`.
- `hasMany(JobScreeningQuestion::class, 'source_question_id')`.
- Scope `active()`.

**`App\Models\ScreeningQuestionTemplate`** (table `screening_question_templates`)

- `$fillable`: `owner_user_id`, `name`, `description`, `is_active`.
- `$casts`: `is_active => boolean`.
- `belongsTo(User::class, 'owner_user_id')`.
- `belongsToMany(ScreeningQuestion::class, 'screening_template_questions')->withPivot('sort_order')->orderByPivot('sort_order')`.

**`App\Models\JobScreeningQuestion`** (table `job_screening_questions`) — copied job question

- `$fillable`: `job_posting_id`, `source_question_id`, `type`, `label`, `options`, `is_required`, `sort_order`, `is_active`.
- `$casts`: `type => ScreeningQuestionType`, `options => array`, `is_required => boolean`, `is_active => boolean`.
- `belongsTo(Job::class, 'job_posting_id')`.
- `belongsTo(ScreeningQuestion::class, 'source_question_id')`.
- `hasMany(JobApplicationAnswer::class, 'job_screening_question_id')`.
- Scope `active()`.

**`App\Models\JobApplicationAnswer`** (table `job_application_answers`)

- `$fillable`: `job_application_id`, `job_screening_question_id`, `answer_text`, `answer_boolean`, `selected_option`.
- `$casts`: `answer_boolean => boolean`.
- `belongsTo(JobApplication::class)`.
- `belongsTo(JobScreeningQuestion::class, 'job_screening_question_id')`.

### Relationship additions

- `User::screeningQuestions(): HasMany` → `ScreeningQuestion` by `owner_user_id`.
- `User::screeningQuestionTemplates(): HasMany` → `ScreeningQuestionTemplate` by `owner_user_id`.
- `Job::screeningQuestions(): HasMany` → `JobScreeningQuestion` by `job_posting_id`.
- `JobApplication::screeningAnswers(): HasMany` → `JobApplicationAnswer` by `job_application_id`.

### Migration 1 — `screening_questions`

Columns: `id`; `foreignId('owner_user_id')->constrained('users')->cascadeOnDelete()`; `string('type', 30)`; `text('label')`; `json('options')->nullable()`; `boolean('is_required')->default(false)`; `boolean('is_active')->default(true)`; timestamps.

Indexes: `(owner_user_id, is_active)`.

### Migration 2 — `screening_question_templates`

Columns: `id`; `foreignId('owner_user_id')->constrained('users')->cascadeOnDelete()`; `string('name', 150)`; `text('description')->nullable()`; `boolean('is_active')->default(true)`; timestamps.

Indexes: `(owner_user_id, is_active)`.

### Migration 3 — `screening_template_questions` (pivot)

Columns: `id`; `foreignId('screening_question_template_id')->constrained('screening_question_templates')->cascadeOnDelete()`; `foreignId('screening_question_id')->constrained('screening_questions')->cascadeOnDelete()`; `unsignedSmallInteger('sort_order')->default(0)`; timestamps.

Constraints: `unique(['screening_question_template_id','screening_question_id'])`.

Indexes: `(screening_question_template_id, sort_order)`.

### Migration 4 — `job_screening_questions`

Columns: `id`; `foreignId('job_posting_id')->constrained('job_postings')->cascadeOnDelete()`; `foreignId('source_question_id')->nullable()->constrained('screening_questions')->nullOnDelete()`; `string('type', 30)`; `text('label')`; `json('options')->nullable()`; `boolean('is_required')->default(false)`; `unsignedSmallInteger('sort_order')->default(0)`; `boolean('is_active')->default(true)`; timestamps.

Indexes: `(job_posting_id, sort_order)`; `(job_posting_id, is_active)`.

### Migration 5 — `job_application_answers`

Columns: `id`; `foreignId('job_application_id')->constrained('job_applications')->cascadeOnDelete()`; `foreignId('job_screening_question_id')->constrained('job_screening_questions')->restrictOnDelete()`; `text('answer_text')->nullable()`; `boolean('answer_boolean')->nullable()`; `string('selected_option', 255)->nullable()`; timestamps.

Constraints: `unique(['job_application_id','job_screening_question_id'])`.

Indexes: `job_application_id`; `job_screening_question_id`.

`restrictOnDelete` on the question FK ensures a job question with answers cannot be hard-deleted at the DB level; the application layer converts this into a deactivation.

---

## Query/data payload shape, eager loading, and pagination

### Bank index (`ScreeningQuestionController::index`)

- `$actor` approved employer; load `$actor->screeningQuestions()->withCount(['templates','jobQuestions'])->orderBy('label')`.
- Paginate 20. Payload per question: `id, type, type_label, label, options, is_required, is_active, templates_count, jobs_count`.

### Template index/show

- `index`: paginate 20, payload `id, name, description, is_active, questions_count`.
- `show`: template with questions ordered by pivot `sort_order`, payload includes full question list.

### Job questions management (`JobScreeningQuestionController::index`)

- `$this->authorize('viewAny', [JobScreeningQuestion::class, $job])`.
- Load `$job->screeningQuestions()->withCount('answers')->orderBy('sort_order')`.
- Also load actor's active templates and bank questions for the apply/add UI.
- Payload: `job` (id, title, status); `questions` array (`id, type, type_label, label, options, is_required, sort_order, is_active, answers_count`); `templates` array (`id, name, questions_count`); `bank_questions` array (`id, type, type_label, label, options, is_required`).

### Candidate apply form

`JobApplicationController::create` adds:

- `screening_questions`: active job questions ordered by `sort_order`, mapped to `{id, type, label, options, is_required}`.

### Candidate application detail

`ApplicationHistoryController::show` eager-loads `screeningAnswers.jobScreeningQuestion` and maps `screening_answers`:

```
[{ question_id, question_label, question_type, options, answer_text, answer_boolean, selected_option }]
```

Ordered by `question.sort_order`.

### Employer application detail

`EmployerJobApplicationController::show` adds the same `screening_answers` mapping (top-level, alongside `status_histories`).

---

## Service/Repository decision with rationale

- **Bank question and template CRUD**: no Service/Repository; single-table or simple pivot writes in controllers (consistent with Feature 13).
- **Template application (`applyTemplate`)**: transactional multi-row copy (creates N job question rows). A small `ScreeningTemplateService::applyToJob(Job $job, ScreeningQuestionTemplate $template): void` wraps the copy in a DB transaction and normalizes sort order. Justified by multi-step transactional write and reuse across apply-template and possible bulk-add. Repository not needed (direct Eloquent).
- **Answer persistence**: folded into the existing application submission transaction in `JobApplicationController::store`. No separate service.

---

## React/Inertia page/form/component plan

### New types (`resources/js/types/screening.ts`, exported from `index.ts`)

- `ScreeningQuestionType = 'short_text' | 'long_text' | 'yes_no' | 'single_choice'`.
- `BankScreeningQuestion = { id; type; type_label; label; options: string[] | null; is_required; is_active; templates_count?; jobs_count? }`.
- `ScreeningTemplate = { id; name; description: string | null; is_active; questions_count?; questions?: BankScreeningQuestion[] }`.
- `JobScreeningQuestion = { id; type; type_label; label; options: string[] | null; is_required; sort_order; is_active; answers_count? }`.
- `ScreeningAnswer = { question_id; question_label; question_type; options: string[] | null; answer_text: string | null; answer_boolean: boolean | null; selected_option: string | null }`.
- `ScreeningQuestionFormData = { type; label; options: string[]; is_required; is_active: boolean }`.

### New components

- `resources/js/components/screening/screening-question-form.tsx` — create/edit bank or job question.
- `resources/js/components/screening/screening-question-list.tsx` — ordered list with edit/deactivate/delete + locked messaging.
- `resources/js/components/screening/screening-answers.tsx` — read-only answer display.
- `resources/js/components/screening/screening-question-fields.tsx` — renders the appropriate input on the apply form.
- `resources/js/components/screening/template-form.tsx` — template create/edit with question picker + ordering.
- `resources/js/components/screening/template-apply-dialog.tsx` — select a template to apply to a job.

### Pages

- New `resources/js/pages/employer/screening-questions/index.tsx` — bank management.
- New `resources/js/pages/employer/screening-templates/index.tsx` — template management (list + inline create/edit).
- New `resources/js/pages/employer/jobs/screening-questions/index.tsx` — job question management (list, apply template, add from bank/inline, edit, reorder).
- Update `resources/js/pages/candidate/applications/create.tsx` — add screening question fields to the form and include answers in the POST payload.
- Update `resources/js/pages/candidate/applications/show.tsx` — render `ScreeningAnswers` when `screening_answers` present.
- Update `resources/js/pages/employer/applications/show.tsx` — render `ScreeningAnswers` when `screening_answers` present.

### Forms

- Bank/template/job forms use `useForm` and Wayfinder actions.
- Apply form extends its existing `router.post` payload with `answers: [{question_id, answer_text?, answer_boolean?, selected_option?}]`.
- Disable submit while `processing`; show field-level errors via `InputError`.

### Accessibility/empty/loading/error states

- Management pages: empty states ("No questions yet", "No templates yet"); validation errors inline; locked-question message.
- Apply form: required marker on required questions; inline errors; disabled submit when required answer missing (advisory; server authoritative).
- Answer display: empty state "No screening answers recorded." when none.
- All interactive controls keyboard-accessible; use existing shadcn primitives.

---

## Relevant tests and security cases

### New/updated test files

- `tests/Feature/Employer/ScreeningQuestionBankTest.php`:
    - guest redirect; unapproved 403.
    - create each type; invalid type/prompt/options; sort not applicable (bank has no order) but is_active.
    - update editable question; deactivation.
    - hard delete when unused; blocked when referenced by template or job (deactivate instead).
    - ownership/cross-user denial.
- `tests/Feature/Employer/ScreeningTemplateTest.php`:
    - CRUD; duplicate question prevention; reorder; ownership denial.
    - deactivation/deletion; deleting template does not affect jobs that applied it.
- `tests/Feature/Employer/JobScreeningQuestionTest.php`:
    - guest/unapproved/non-owner/cross-company 403.
    - apply template copies questions in order; multiple templates accumulate; template edits don't propagate.
    - add from bank question; create inline new bank question + job question.
    - edit job question; locked (answered) rejects type/label/options but allows is_active/sort_order.
    - hard delete when no answers; deactivate when answers exist.
    - reorder persists.
    - closed job rejects management.
    - cap enforcement (50/job).
- `tests/Feature/Candidate/JobApplicationTest.php` (extend):
    - apply form exposes active job questions; deactivated excluded.
    - successful submission with all required answers persisted and readable.
    - missing required answer blocks submission and creates no application.
    - type-specific validation (short text length, long text length, invalid single choice, yes/no boolean).
    - duplicate/unknown question id rejected.
    - answers immutable (no update route).
    - candidate sees own answers on application show; other candidate 403.
- `tests/Feature/Employer/JobApplicationTest.php` (extend):
    - employer sees screening answers on application detail for owned job.
    - cross-company employer 403.
    - deactivated job question still readable on prior application.

### Security cases

- IDOR: guessed bank/template/job-question IDs from another owner/company rejected.
- Mass assignment: `owner_user_id`, `job_posting_id`, `source_question_id` not settable from request; explicit mapping only.
- XSS: answer text rendered escaped in React; no `dangerouslySetInnerHTML`.
- No execution of submitted HTML/scripts.
- Concurrency: submission transaction protects application+answers atomicity; duplicate application still blocked by unique constraint.
- Template apply is transactional; partial failure rolls back.

---

## Risks, rollout/compatibility notes, and verification commands

### Risks

- Answer protection logic (A5) must be enforced in Form Request and re-checked in controller; bypassed request could mutate a locked question. Mitigate by re-checking `answers()->exists()` in controller before applying locked fields, plus `restrictOnDelete` at DB level.
- Question/template/job-question count growth: enforce caps (50 per job, 100 per bank) to avoid unbounded payloads.
- Reordering + active flags interactions: normalize sort_order on reorder (0..n-1).
- Backfill: none required; existing applications simply have no answers.

### Rollout

- New tables only; no changes to existing tables. No data backfill. Safe to deploy with existing data.
- Frontend assets must be rebuilt after route additions (Wayfinder).

### Verification commands

```text
php artisan migrate
php artisan test --filter=Screening
php artisan test
vendor\bin\pint --test
php vendor/bin/phpstan analyse --memory-limit=512M --no-progress
npm run types:check
npm run check
npm run build
```

---

## Ordered implementation plan

1. Create enum `App\Enums\ScreeningQuestionType`.
2. Create migrations 1–5 for `screening_questions`, `screening_question_templates`, `screening_template_questions`, `job_screening_questions`, `job_application_answers`; run `php artisan migrate`.
3. Create models `ScreeningQuestion`, `ScreeningQuestionTemplate`, `JobScreeningQuestion`, `JobApplicationAnswer`; add relationships to `User`, `Job`, `JobApplication`.
4. Create Form Requests for bank questions, templates, job questions, apply-template, reorder; extend `StoreJobApplicationRequest` for answers.
5. Create policies `ScreeningQuestionPolicy`, `ScreeningQuestionTemplatePolicy`, `JobScreeningQuestionPolicy`; register.
6. Create controllers `Employer\ScreeningQuestionController`, `Employer\ScreeningTemplateController`, `Employer\JobScreeningQuestionController`.
7. Create `ScreeningTemplateService::applyToJob`.
8. Add routes in `routes/web.php` under the employer approved group.
9. Update `JobApplicationController::create` to pass active job screening questions.
10. Update `JobApplicationController::store` to persist answers in a transaction.
11. Update `ApplicationHistoryController::show` and `EmployerJobApplicationController::show` to pass `screening_answers`.
12. Create frontend types and components; create bank/template/job management pages; update apply/show pages.
13. Run Wayfinder generation (automatic via build) and rebuild assets.
14. Add tests; run verification commands; fix and rerun.
15. Review diff against security gate; update `memory.md` via `/remember save` after commit.

---

## Implementation status

Implemented end-to-end. Backend and frontend complete; tests added. See `/remember save` for handoff.
