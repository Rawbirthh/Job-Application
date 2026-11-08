<?php

namespace App\Http\Controllers\Employer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Employer\StoreScreeningTemplateRequest;
use App\Http\Requests\Employer\UpdateScreeningTemplateRequest;
use App\Models\ScreeningQuestion;
use App\Models\ScreeningQuestionTemplate;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ScreeningTemplateController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', ScreeningQuestionTemplate::class);

        /** @var User $actor */
        $actor = request()->user();

        $templates = $actor->screeningQuestionTemplates()
            ->with(['questions' => fn ($q) => $q->select('screening_questions.id')])
            ->withCount('questions')
            ->orderBy('name')
            ->paginate(20);

        $templates->through(fn (ScreeningQuestionTemplate $template): array => [
            'id' => $template->id,
            'name' => $template->name,
            'description' => $template->description,
            'is_active' => $template->is_active,
            'questions_count' => $template->questions_count,
            'question_ids' => $template->questions->pluck('id')->values()->all(),
        ]);

        $bankQuestions = $actor->screeningQuestions()
            ->active()
            ->orderBy('label')
            ->limit(100)
            ->get(['id', 'type', 'label', 'options', 'is_required'])
            ->map(fn (ScreeningQuestion $q): array => [
                'id' => $q->id,
                'type' => $q->type->value,
                'type_label' => $q->type->label(),
                'label' => $q->label,
                'options' => $q->options,
                'is_required' => $q->is_required,
                'is_active' => $q->is_active,
            ])
            ->values()
            ->all();

        return Inertia::render('employer/screening-templates/index', [
            'templates' => $templates,
            'bank_questions' => $bankQuestions,
        ]);
    }

    public function show(ScreeningQuestionTemplate $template): Response
    {
        $this->authorize('view', $template);

        $template->load(['questions' => function ($query): void {
            $query->select(['screening_questions.id', 'screening_questions.type', 'screening_questions.label', 'screening_questions.options', 'screening_questions.is_required', 'screening_questions.is_active']);
        }]);

        return Inertia::render('employer/screening-templates/show', [
            'template' => [
                'id' => $template->id,
                'name' => $template->name,
                'description' => $template->description,
                'is_active' => $template->is_active,
                'questions' => $template->questions->values()->map(fn ($q, $index): array => [
                    'id' => $q->id,
                    'type' => $q->type->value,
                    'type_label' => $q->type->label(),
                    'label' => $q->label,
                    'options' => $q->options,
                    'is_required' => $q->is_required,
                    'is_active' => $q->is_active,
                    'sort_order' => $index,
                ])->all(),
            ],
        ]);
    }

    public function store(StoreScreeningTemplateRequest $request): RedirectResponse
    {
        $this->authorize('create', ScreeningQuestionTemplate::class);

        /** @var User $actor */
        $actor = $request->user();

        $template = ScreeningQuestionTemplate::create(array_merge(
            ['owner_user_id' => $actor->id],
            $request->templateData()
        ));

        $this->syncQuestions($template, $request->questionIds());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Screening template created.'),
        ]);

        return to_route('employer.screening-templates.index');
    }

    public function update(UpdateScreeningTemplateRequest $request, ScreeningQuestionTemplate $template): RedirectResponse
    {
        $this->authorize('update', $template);

        $template->fill($request->templateData());
        $template->save();

        $this->syncQuestions($template, $request->questionIds());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Screening template updated.'),
        ]);

        return to_route('employer.screening-templates.index');
    }

    public function destroy(ScreeningQuestionTemplate $template): RedirectResponse
    {
        $this->authorize('delete', $template);

        $template->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Screening template deleted.'),
        ]);

        return to_route('employer.screening-templates.index');
    }

    /**
     * @param  array<int, int>  $questionIds
     */
    private function syncQuestions(ScreeningQuestionTemplate $template, array $questionIds): void
    {
        $sync = [];
        foreach (array_values($questionIds) as $index => $id) {
            $sync[$id] = ['sort_order' => $index];
        }
        $template->questions()->sync($sync);
    }
}
