<?php

namespace App\Http\Controllers\Candidate;

use App\Http\Controllers\Controller;
use App\Http\Requests\Candidate\UpdateCandidateProfileRequest;
use App\Models\CandidateEducation;
use App\Models\CandidateExperience;
use App\Models\CandidateProfile;
use App\Models\ExperienceLevel;
use App\Models\JobCategory;
use App\Models\User;
use App\Models\WorkplaceArrangement;
use App\Services\Candidate\CandidateProfileService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
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
            ->select([
                'id', 'user_id', 'headline', 'summary', 'location', 'skills',
                'preferred_category_id', 'preferred_experience_level_id', 'preferred_workplace_arrangement_id',
                'updated_at',
            ])
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
            'options' => $this->options($profile),
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
            'preferred_category_id' => $profile->preferred_category_id,
            'preferred_experience_level_id' => $profile->preferred_experience_level_id,
            'preferred_workplace_arrangement_id' => $profile->preferred_workplace_arrangement_id,
            'educations' => $profile->educations->map(fn (CandidateEducation $education): array => [
                'id' => $education->id,
                'institution' => $education->institution,
                'degree' => $education->degree,
                'field_of_study' => $education->field_of_study,
                'start_date' => $education->start_date?->toDateString(),
                'end_date' => $education->end_date?->toDateString(),
                'description' => $education->description,
            ])->values()->all(),
            'experiences' => $profile->experiences->map(fn (CandidateExperience $experience): array => [
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

    /**
     * The active configuration options the preference selects are built from.
     *
     * An option the candidate already selected stays listed even when it has
     * since been deactivated, so editing another field never silently clears a
     * preference the candidate can still see.
     *
     * @return array{categories: array<int, array{id: int, name: string}>, experienceLevels: array<int, array{id: int, name: string}>, workplaceArrangements: array<int, array{id: int, name: string}>}
     */
    private function options(CandidateProfile $profile): array
    {
        return [
            'categories' => $this->mergeHeldOption(
                JobCategory::query()->active()->select(['id', 'name'])->orderBy('name')->get(),
                JobCategory::class,
                $profile->preferred_category_id,
            ),
            'experienceLevels' => $this->mergeHeldOption(
                ExperienceLevel::query()->active()->select(['id', 'name'])->orderBy('sort_order')->get(),
                ExperienceLevel::class,
                $profile->preferred_experience_level_id,
            ),
            'workplaceArrangements' => $this->mergeHeldOption(
                WorkplaceArrangement::query()->active()->select(['id', 'name'])->orderBy('name')->get(),
                WorkplaceArrangement::class,
                $profile->preferred_workplace_arrangement_id,
            ),
        ];
    }

    /**
     * @template TModel of Model
     *
     * @param  Collection<int, TModel>  $active
     * @param  class-string<TModel>  $model
     * @return array<int, array{id: int, name: string}>
     */
    private function mergeHeldOption(Collection $active, string $model, ?int $heldId): array
    {
        $options = $active->map(fn (Model $option): array => [
            'id' => (int) $option->getKey(),
            'name' => (string) $option->getAttribute('name'),
        ])->all();

        if ($heldId === null || in_array($heldId, array_column($options, 'id'), true)) {
            return $options;
        }

        $held = $model::query()->find($heldId);

        if (! $held instanceof Model) {
            return $options;
        }

        $options[] = ['id' => (int) $held->getKey(), 'name' => (string) $held->getAttribute('name')];

        return $options;
    }
}
