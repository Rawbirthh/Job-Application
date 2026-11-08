<?php

namespace App\Http\Controllers\Employer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Employer\StoreScreeningQuestionRequest;
use App\Http\Requests\Employer\UpdateScreeningQuestionRequest;
use App\Models\ScreeningQuestion;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ScreeningQuestionController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', ScreeningQuestion::class);

        /** @var User $actor */
        $actor = request()->user();

        $questions = $actor->screeningQuestions()
            ->withCount(['templates', 'jobQuestions'])
            ->orderBy('label')
            ->paginate(20);

        $questions->through(fn (ScreeningQuestion $question): array => [
            'id' => $question->id,
            'type' => $question->type->value,
            'type_label' => $question->type->label(),
            'label' => $question->label,
            'options' => $question->options,
            'is_required' => $question->is_required,
            'is_active' => $question->is_active,
            'templates_count' => $question->templates_count,
            'jobs_count' => $question->job_questions_count,
        ]);

        return Inertia::render('employer/screening-questions/index', [
            'questions' => $questions,
        ]);
    }

    public function store(StoreScreeningQuestionRequest $request): RedirectResponse
    {
        $this->authorize('create', ScreeningQuestion::class);

        /** @var User $actor */
        $actor = $request->user();

        if ($actor->screeningQuestions()->count() >= 100) {
            abort(422, __('You have reached the maximum number of screening questions.'));
        }

        ScreeningQuestion::create(array_merge(
            ['owner_user_id' => $actor->id],
            $request->screeningQuestionData()
        ));

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Screening question created.'),
        ]);

        return to_route('employer.screening-questions.index');
    }

    public function update(UpdateScreeningQuestionRequest $request, ScreeningQuestion $question): RedirectResponse
    {
        $this->authorize('update', $question);

        $question->fill($request->screeningQuestionData());
        $question->save();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Screening question updated.'),
        ]);

        return to_route('employer.screening-questions.index');
    }

    public function destroy(ScreeningQuestion $question): RedirectResponse
    {
        $this->authorize('delete', $question);

        if ($question->templates()->exists() || $question->jobQuestions()->exists()) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('This question is in use and cannot be deleted. Deactivate it instead.'),
            ]);

            return back();
        }

        $question->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Screening question deleted.'),
        ]);

        return to_route('employer.screening-questions.index');
    }
}
