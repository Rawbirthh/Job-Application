<?php

namespace App\Http\Requests\Candidate;

use App\Models\CandidateProfile;
use App\Models\ExperienceLevel;
use App\Models\JobCategory;
use App\Models\User;
use App\Models\WorkplaceArrangement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateCandidateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $merged = [];

        foreach (['headline', 'summary', 'location'] as $field) {
            $value = $this->input($field);
            if (is_string($value)) {
                $value = trim($value);
                $value = $value === '' ? null : $value;
            }
            $merged[$field] = $value;
        }

        $skills = $this->input('skills');
        if (is_array($skills)) {
            $normalizedSkills = [];
            foreach ($skills as $skill) {
                if (is_string($skill)) {
                    $skill = trim($skill);
                    if ($skill !== '') {
                        $normalizedSkills[] = $skill;
                    }
                } else {
                    $normalizedSkills[] = $skill;
                }
            }
            $merged['skills'] = $normalizedSkills;
        } else {
            $merged['skills'] = $skills;
        }

        foreach (['preferred_category_id', 'preferred_experience_level_id', 'preferred_workplace_arrangement_id'] as $field) {
            $value = $this->input($field);
            if (is_string($value)) {
                $value = trim($value);
                $value = $value === '' ? null : $value;
            }
            $merged[$field] = $value;
        }

        foreach (['educations', 'experiences'] as $collection) {
            $rows = $this->input($collection);
            if (! is_array($rows)) {
                $merged[$collection] = $rows;

                continue;
            }

            $normalizedRows = [];
            foreach ($rows as $index => $row) {
                if (! is_array($row)) {
                    $normalizedRows[$index] = $row;

                    continue;
                }

                $normalizedRow = $row;
                $stringFields = $collection === 'educations'
                    ? ['institution', 'degree', 'field_of_study', 'start_date', 'end_date', 'description']
                    : ['company_name', 'job_title', 'location', 'start_date', 'end_date', 'description'];

                foreach ($stringFields as $field) {
                    if (isset($normalizedRow[$field]) && is_string($normalizedRow[$field])) {
                        $value = trim($normalizedRow[$field]);
                        $nullableFields = ['degree', 'field_of_study', 'start_date', 'end_date', 'description', 'location'];
                        if (in_array($field, $nullableFields, true)) {
                            $value = $value === '' ? null : $value;
                        } else {
                            $value = $value === '' ? null : $value;
                        }
                        $normalizedRow[$field] = $value;
                    }
                }

                if (array_key_exists('is_current', $normalizedRow)) {
                    $filtered = filter_var($normalizedRow['is_current'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                    $normalizedRow['is_current'] = $filtered === null ? false : $filtered;
                }

                $normalizedRows[$index] = $normalizedRow;
            }

            $merged[$collection] = array_values($normalizedRows);
        }

        $this->merge($merged);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'headline' => ['nullable', 'string', 'max:150'],
            'summary' => ['nullable', 'string', 'max:5000'],
            'location' => ['nullable', 'string', 'max:255'],
            'skills' => ['nullable', 'array', 'max:30'],
            'skills.*' => ['string', 'max:50'],
            'preferred_category_id' => ['nullable', 'integer', 'exists:job_categories,id'],
            'preferred_experience_level_id' => ['nullable', 'integer', 'exists:experience_levels,id'],
            'preferred_workplace_arrangement_id' => ['nullable', 'integer', 'exists:workplace_arrangements,id'],
            'educations' => ['nullable', 'array', 'max:20'],
            'educations.*.institution' => ['required_with:educations', 'string', 'max:150'],
            'educations.*.degree' => ['nullable', 'string', 'max:100'],
            'educations.*.field_of_study' => ['nullable', 'string', 'max:100'],
            'educations.*.start_date' => ['nullable', 'date'],
            'educations.*.end_date' => ['nullable', 'date', 'after_or_equal:educations.*.start_date'],
            'educations.*.description' => ['nullable', 'string', 'max:2000'],
            'experiences' => ['nullable', 'array', 'max:20'],
            'experiences.*.company_name' => ['required_with:experiences', 'string', 'max:150'],
            'experiences.*.job_title' => ['required_with:experiences', 'string', 'max:150'],
            'experiences.*.location' => ['nullable', 'string', 'max:100'],
            'experiences.*.start_date' => ['nullable', 'date'],
            'experiences.*.end_date' => ['nullable', 'date', 'after_or_equal:experiences.*.start_date'],
            'experiences.*.is_current' => ['boolean'],
            'experiences.*.description' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $experiences = $this->input('experiences', []);

            if (! is_array($experiences)) {
                return;
            }

            foreach ($experiences as $index => $experience) {
                if (! is_array($experience)) {
                    continue;
                }
                $isCurrent = (bool) ($experience['is_current'] ?? false);
                $endDate = $experience['end_date'] ?? null;
                if ($isCurrent && $endDate !== null && $endDate !== '') {
                    $validator->errors()->add(
                        "experiences.{$index}.end_date",
                        'The end date must be null when the experience is current.'
                    );
                }
            }
        });

        $validator->after(function (Validator $validator): void {
            $this->validatePreferenceOptions($validator);
        });
    }

    /**
     * Reject deactivated configuration options that the candidate does not
     * already hold.
     *
     * A deactivated option the candidate already selected stays submittable so
     * that editing an unrelated field never silently clears a preference they
     * can still see.
     */
    private function validatePreferenceOptions(Validator $validator): void
    {
        $user = $this->user();

        if (! $user instanceof User) {
            return;
        }

        $held = CandidateProfile::query()
            ->where('user_id', $user->id)
            ->first();

        $options = [
            'preferred_category_id' => [JobCategory::class, 'is_active'],
            'preferred_experience_level_id' => [ExperienceLevel::class, 'is_active'],
            'preferred_workplace_arrangement_id' => [WorkplaceArrangement::class, 'is_active'],
        ];

        foreach ($options as $field => [$model, $activeColumn]) {
            $value = $this->input($field);

            if ($value === null || $value === '') {
                continue;
            }

            $isActive = $model::query()
                ->whereKey((int) $value)
                ->where($activeColumn, true)
                ->exists();

            if ($isActive) {
                continue;
            }

            if ($held !== null && (int) $held->{$field} === (int) $value) {
                continue;
            }

            $validator->errors()->add($field, __('The selected option is no longer available.'));
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function profileData(): array
    {
        $validated = $this->validated();

        $educations = [];
        foreach ($validated['educations'] ?? [] as $row) {
            $educations[] = [
                'institution' => $row['institution'],
                'degree' => $row['degree'] ?? null,
                'field_of_study' => $row['field_of_study'] ?? null,
                'start_date' => $row['start_date'] ?? null,
                'end_date' => $row['end_date'] ?? null,
                'description' => $row['description'] ?? null,
            ];
        }

        $experiences = [];
        foreach ($validated['experiences'] ?? [] as $row) {
            $isCurrent = (bool) ($row['is_current'] ?? false);
            $experiences[] = [
                'company_name' => $row['company_name'],
                'job_title' => $row['job_title'],
                'location' => $row['location'] ?? null,
                'start_date' => $row['start_date'] ?? null,
                'end_date' => $isCurrent ? null : ($row['end_date'] ?? null),
                'is_current' => $isCurrent,
                'description' => $row['description'] ?? null,
            ];
        }

        return [
            'headline' => $validated['headline'] ?? null,
            'summary' => $validated['summary'] ?? null,
            'location' => $validated['location'] ?? null,
            'skills' => array_values($validated['skills'] ?? []),
            'preferred_category_id' => $validated['preferred_category_id'] ?? null,
            'preferred_experience_level_id' => $validated['preferred_experience_level_id'] ?? null,
            'preferred_workplace_arrangement_id' => $validated['preferred_workplace_arrangement_id'] ?? null,
            'educations' => $educations,
            'experiences' => $experiences,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function validatedProfile(): array
    {
        return $this->profileData();
    }
}
