<?php

namespace App\Http\Controllers\Employer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Employer\StoreNoteRequest;
use App\Http\Requests\Employer\UpdateNoteRequest;
use App\Models\ApplicationNote;
use App\Models\JobApplication;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;

class EmployerNoteController extends Controller
{
    use AuthorizesRequests;

    /**
     * Add a private recruiter note to an application.
     */
    public function store(StoreNoteRequest $request, JobApplication $application): RedirectResponse
    {
        $this->authorize('createForApplication', [ApplicationNote::class, $application]);

        ApplicationNote::query()->create([
            'application_id' => $application->id,
            'author_id' => $request->user()->id,
            'body' => $request->validated('body'),
        ]);

        return Redirect::back()->with('success', __('Note added.'));
    }

    /**
     * Edit one of the current user's own notes.
     */
    public function update(
        UpdateNoteRequest $request,
        JobApplication $application
    ): RedirectResponse {
        $note = $request->note();
        $this->authorize('update', $note);

        $note->fill([
            'body' => $request->validated('body'),
            'edited_at' => now(),
        ])->save();

        return Redirect::back()->with('success', __('Note updated.'));
    }

    /**
     * Soft-delete one of the current user's own notes.
     */
    public function destroy(JobApplication $application, string $note): RedirectResponse
    {
        $note = $this->resolveNote($application, $note);
        $this->authorize('delete', $note);

        $note->delete();

        return Redirect::back()->with('success', __('Note deleted.'));
    }

    private function resolveNote(JobApplication $application, string $note): ApplicationNote
    {
        abort_unless(is_numeric($note), 404);

        $resolved = ApplicationNote::query()
            ->where('application_id', $application->id)
            ->find((int) $note);

        abort_if($resolved === null, 404);

        return $resolved;
    }
}
