<?php

namespace App\Http\Controllers\Employer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Employer\ApplyScreeningTemplateRequest;
use App\Http\Requests\Employer\ReorderJobScreeningQuestionsRequest;
use App\Http\Requests\Employer\StoreJobScreeningQuestionRequest;
use App\Http\Requests\Employer\UpdateJobScreeningQuestionRequest;
use App\Models\Job;
use App\Models\JobScreeningQuestion;
use App\Models\ScreeningQuestion;
use App\Models\ScreeningQuestionTemplate;
use App\Models\User;
use App\Services\ScreeningTemplateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class JobScreeningQuestionController extends Controller
{
    public function __construct(
        private readonly ScreeningTemplateService $templateService,
    ) {}

    public function index(Job $job): Response
    {
        $this->authorize('viewAny', [JobScreeningQuestion::class, $job]);

        /** @var User $actor */
        $actor = request()->user();

        $questions = $job->screeningQuestions()
            ->withCount('answers')
            ->orderBy('sort_order')
            ->get();

        $templates = $actor->screeningQuestionTemplates()
            ->active()
            ->withCount('questions')
            ->orderBy('name')
            ->get();

        $bankQuestions = $actor->screeningQuestions()
            ->active()
            ->select(['id', 'type', 'label', 'options', 'is_required'])
            ->orderBy('label')
            ->get();

        return Inertia::render('employer/jobs/screening-questions/index', [
            'job' => [
                'id' => $job->id,
                'title' => $job->title,
                'status' => $job->status,
            ],
            'questions' => $questions->map(fn (JobScreeningQuestion $q): array => [
                'id' => $q->id,
                'type' => $q->type->value,
                'type_label' => $q->type->label(),
                'label' => $q->label,
                'options' => $q->options,
                'is_required' => $q->is_required,
                'sort_order' => $q->sort_order,
                'is_active' => $q->is_active,
                'answers_count' => $q->answers_count,
            ])->values()->all(),
            'templates' => $templates->map(fn (ScreeningQuestionTemplate $t): array => [
                'id' => $t->id,
                'name' => $t->name,
                'questions_count' => $t->questions_count,
            ])->values()->all(),
            'bank_questions' => $bankQuestions->map(fn (ScreeningQuestion $q): array => [
                'id' => $q->id,
                'type' => $q->type->value,
                'type_label' => $q->type->label(),
                'label' => $q->label,
                'options' => $q->options,
                'is_required' => $q->is_required,
            ])->values()->all(),
        ]);
    }

    public function store(StoreJobScreeningQuestionRequest $request, Job $job): RedirectResponse
    {
        $this->authorize('create', [JobScreeningQuestion::class, $job]);

        if ($job->screeningQuestions()->count() >= 50) {
            abort(422, __('This job already has the maximum number of screening questions.'));
        }

        /** @var User $actor */
        $actor = $request->user();

        $data = $request->jobScreeningQuestionData();

        if (isset($data['question_id'])) {
            /** @var ScreeningQuestion $bankQuestion */
            $bankQuestion = ScreeningQuestion::query()
                ->where('owner_user_id', $actor->id)
                ->findOrFail($data['question_id']);

            JobScreeningQuestion::create([
                'job_posting_id' => $job->id,
                'source_question_id' => $bankQuestion->id,
                'type' => $bankQuestion->type->value,
                'label' => $bankQuestion->label,
                'options' => $bankQuestion->options,
                'is_required' => $bankQuestion->is_required,
                'sort_order' => $job->screeningQuestions()->count(),
                'is_active' => true,
            ]);
        } else {
            $sortOrder = $data['sort_order'];
            if ($sortOrder === null) {
                $sortOrder = $job->screeningQuestions()->count();
            }

            JobScreeningQuestion::create([
                'job_posting_id' => $job->id,
                'source_question_id' => null,
                'type' => $data['type'],
                'label' => $data['label'],
                'options' => $data['options'],
                'is_required' => $data['is_required'],
                'sort_order' => $sortOrder,
                'is_active' => $data['is_active'],
            ]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Screening question added to job.'),
        ]);

        return to_route('employer.jobs.screening-questions.index', $job);
    }

    public function applyTemplate(ApplyScreeningTemplateRequest $request, Job $job): RedirectResponse
    {
        $this->authorize('applyTemplate', [JobScreeningQuestion::class, $job]);

        /** @var User $actor */
        $actor = $request->user();

        /** @var ScreeningQuestionTemplate $template */
        $template = ScreeningQuestionTemplate::query()
            ->where('owner_user_id', $actor->id)
            ->findOrFail($request->templateId());

        $this->templateService->applyToJob($job, $template);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Template applied to job.'),
        ]);

        return to_route('employer.jobs.screening-questions.index', $job);
    }

    public function update(UpdateJobScreeningQuestionRequest $request, JobScreeningQuestion $question): RedirectResponse
    {
        $this->authorize('update', $question);

        $job = $question->jobPosting;

        if ($question->answers()->exists()) {
            $data = $request->validated();
            // Only is_active and sort_order allowed
            if ($request->has('is_active')) {
                $question->is_active = $request->boolean('is_active');
            }
            if ($request->has('sort_order') && $request->input('sort_order') !== null) {
                $question->sort_order = max(0, (int) $request->input('sort_order'));
            }
            $question->save();

            Inertia::flash('toast', [
                'type' => 'success',
                'message' => __('Screening question updated.'),
            ]);

            return to_route('employer.jobs.screening-questions.index', $job);
        }

        $data = $request->jobScreeningQuestionData();
        if (! array_key_exists('sort_order', $data) || $data['sort_order'] === null) {
            unset($data['sort_order']);
        }
        $question->fill($data);
        $question->save();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Screening question updated.'),
        ]);

        return to_route('employer.jobs.screening-questions.index', $job);
    }

    public function destroy(JobScreeningQuestion $question): RedirectResponse
    {
        $this->authorize('delete', $question);

        if ($question->answers()->exists()) {
            abort(422, __('This question has submitted answers and cannot be deleted. Deactivate it instead.'));
        }

        $job = $question->jobPosting;
        $question->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Screening question deleted.'),
        ]);

        return to_route('employer.jobs.screening-questions.index', $job);
    }

    public function reorder(ReorderJobScreeningQuestionsRequest $request, Job $job): RedirectResponse
    {
        $this->authorize('reorder', [JobScreeningQuestion::class, $job]);

        $ids = $request->questionIds();

        DB::transaction(function () use ($ids): void {
            foreach (array_values($ids) as $index => $id) {
                JobScreeningQuestion::where('id', $id)->update(['sort_order' => $index]);
            }
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Questions reordered.'),
        ]);

        return to_route('employer.jobs.screening-questions.index', $job);
    }
}
