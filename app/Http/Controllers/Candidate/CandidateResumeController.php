<?php

namespace App\Http\Controllers\Candidate;

use App\Http\Controllers\Controller;
use App\Http\Requests\Candidate\UploadResumeRequest;
use App\Models\CandidateResume;
use App\Models\User;
use App\Services\Candidate\CandidateResumeService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CandidateResumeController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private readonly CandidateResumeService $resumeService,
    ) {}

    /**
     * Show candidate resume management page.
     */
    public function index(): Response
    {
        /** @var User $user */
        $user = request()->user();

        $resume = CandidateResume::query()
            ->where('user_id', $user->id)
            ->latest('created_at')
            ->first();

        return Inertia::render('candidate/resumes/index', [
            'resume' => $resume ? [
                'id' => $resume->id,
                'original_filename' => $resume->original_filename,
                'mime_type' => $resume->mime_type,
                'size' => $resume->size,
                'created_at' => $resume->created_at?->toIso8601String(),
                'updated_at' => $resume->updated_at?->toIso8601String(),
            ] : null,
        ]);
    }

    /**
     * Upload or replace candidate resume.
     */
    public function upload(UploadResumeRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $this->authorize('create', CandidateResume::class);

        $this->resumeService->upload($user, $request->file('file'));

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Resume uploaded successfully.'),
        ]);

        return redirect()->route('candidate.resumes.index');
    }

    /**
     * Delete candidate resume.
     */
    public function delete(CandidateResume $resume): RedirectResponse
    {
        $this->authorize('delete', $resume);

        $this->resumeService->delete($resume);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Resume deleted successfully.'),
        ]);

        return redirect()->route('candidate.resumes.index');
    }

    /**
     * Download candidate resume file.
     */
    public function download(CandidateResume $resume): StreamedResponse
    {
        $this->authorize('view', $resume);

        if (! Storage::disk('local')->exists($resume->path)) {
            abort(404, 'Resume file not found.');
        }

        return Storage::disk('local')->download(
            $resume->path,
            $resume->original_filename
        );
    }
}
