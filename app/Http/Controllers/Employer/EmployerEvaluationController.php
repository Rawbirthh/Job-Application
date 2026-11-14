<?php

namespace App\Http\Controllers\Employer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Employer\StoreEvaluationRequest;
use App\Models\ApplicationEvaluation;
use App\Models\JobApplication;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;

class EmployerEvaluationController extends Controller
{
    use AuthorizesRequests;

    /**
     * Append a new recruiter evaluation. Evaluations are never edited or deleted.
     */
    public function store(StoreEvaluationRequest $request, JobApplication $application): RedirectResponse
    {
        $this->authorize('createForApplication', [ApplicationEvaluation::class, $application]);

        $validated = $request->validated();
        $comment = isset($validated['comment']) && is_string($validated['comment'])
            ? trim($validated['comment'])
            : null;

        ApplicationEvaluation::query()->create([
            'application_id' => $application->id,
            'evaluator_id' => $request->user()->id,
            'relevant_experience' => $validated['relevant_experience'],
            'technical_skills' => $validated['technical_skills'],
            'communication' => $validated['communication'],
            'interview_performance' => $validated['interview_performance'],
            'comment' => $comment === '' ? null : $comment,
        ]);

        return Redirect::back()->with('success', __('Evaluation saved.'));
    }
}
