<?php

namespace App\Http\Controllers\Candidate;

use App\Http\Controllers\Controller;
use App\Http\Requests\Candidate\UpdateCandidateProfileRequest;
use App\Models\CandidateProfile;
use App\Models\User;
use App\Services\Candidate\CandidateProfileService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class CandidateProfileController extends Controller
{
    public function __construct(
        private readonly CandidateProfileService $profileService,
    ) {}

    public function edit(): Response
    {
        /** @var User $user */
        $user = request()->user();

        $profile = CandidateProfile::query()->firstOrCreate([
            'user_id' => $user->id,
        ]);

        $this->authorize('update', $profile);

        $profile = CandidateProfile::query()
            ->whereKey($profile->getKey())
            ->select(['id', 'user_id', 'headline', 'summary', 'location', 'skills', 'updated_at'])
            ->with([
                'educations' => fn ($query) => $query->select([
                    'id', 'candidate_profile_id', 'institution', 'degree', 'field_of_study',
                    'start_date', 'end_date', 'description',
                ])->orderBy('id'),
                'experiences' => fn ($query) => $query->select([
                    'id', 'candidate_profile_id', 'company_name', 'job_title', 'location',
                    'start_date', 'end_date', 'is_current', 'description',
                ])->orderBy('id'),
            ])
            ->firstOrFail();

        return Inertia::render('candidate/profile/edit', [
            'profile' => $this->profilePayload($profile),
        ]);
    }

    public function update(UpdateCandidateProfileRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $profile = CandidateProfile::query()->firstOrCreate([
            'user_id' => $user->id,
        ]);

        $this->authorize('update', $profile);

        $this->profileService->update($user, $request->profileData());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Profile updated.'),
        ]);

        return to_route('candidate.profile.edit');
    }

    /**
     * @return array<string, mixed>
     */
    private function profilePayload(CandidateProfile $profile): array
    {
        return [
            'headline' => $profile->headline,
            'summary' => $profile->summary,
            'location' => $profile->location,
            'skills' => $profile->skills ?? [],
            'educations' => $profile->educations->map(fn ($education): array => [
                'id' => $education->id,
                'institution' => $education->institution,
                'degree' => $education->degree,
                'field_of_study' => $education->field_of_study,
                'start_date' => $education->start_date?->toDateString(),
                'end_date' => $education->end_date?->toDateString(),
                'description' => $education->description,
            ])->values()->all(),
            'experiences' => $profile->experiences->map(fn ($experience): array => [
                'id' => $experience->id,
                'company_name' => $experience->company_name,
                'job_title' => $experience->job_title,
                'location' => $experience->location,
                'start_date' => $experience->start_date?->toDateString(),
                'end_date' => $experience->end_date?->toDateString(),
                'is_current' => (bool) $experience->is_current,
                'description' => $experience->description,
            ])->values()->all(),
            'updated_at' => $profile->updated_at?->toIso8601String(),
        ];
    }
}
