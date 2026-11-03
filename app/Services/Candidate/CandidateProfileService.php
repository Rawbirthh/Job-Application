<?php

namespace App\Services\Candidate;

use App\Models\CandidateProfile;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class CandidateProfileService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $user, array $data): CandidateProfile
    {
        return DB::transaction(function () use ($user, $data): CandidateProfile {
            $lockedUser = User::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();

            try {
                $profile = CandidateProfile::query()->firstOrCreate([
                    'user_id' => $lockedUser->id,
                ]);
            } catch (QueryException $exception) {
                $profile = CandidateProfile::query()
                    ->where('user_id', $lockedUser->id)
                    ->firstOrFail();
            }

            $profile->fill([
                'headline' => $data['headline'] ?? null,
                'summary' => $data['summary'] ?? null,
                'location' => $data['location'] ?? null,
                'skills' => $data['skills'] ?? [],
            ]);
            $profile->save();

            $profile->educations()->delete();
            foreach ($this->filterEducations($data['educations'] ?? []) as $education) {
                $profile->educations()->create([
                    'institution' => $education['institution'],
                    'degree' => $education['degree'] ?? null,
                    'field_of_study' => $education['field_of_study'] ?? null,
                    'start_date' => $education['start_date'] ?? null,
                    'end_date' => $education['end_date'] ?? null,
                    'description' => $education['description'] ?? null,
                ]);
            }

            $profile->experiences()->delete();
            foreach ($this->filterExperiences($data['experiences'] ?? []) as $experience) {
                $isCurrent = (bool) ($experience['is_current'] ?? false);
                $profile->experiences()->create([
                    'company_name' => $experience['company_name'],
                    'job_title' => $experience['job_title'],
                    'location' => $experience['location'] ?? null,
                    'start_date' => $experience['start_date'] ?? null,
                    'end_date' => $isCurrent ? null : ($experience['end_date'] ?? null),
                    'is_current' => $isCurrent,
                    'description' => $experience['description'] ?? null,
                ]);
            }

            return $profile->fresh(['educations', 'experiences']);
        });
    }

    /**
     * @param  array<int, mixed>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function filterEducations(array $rows): array
    {
        $filtered = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $row = array_merge([
                'institution' => null,
                'degree' => null,
                'field_of_study' => null,
                'start_date' => null,
                'end_date' => null,
                'description' => null,
            ], $row);

            $hasValue = false;
            foreach (['institution', 'degree', 'field_of_study', 'start_date', 'end_date', 'description'] as $field) {
                $value = $row[$field] ?? null;
                if (is_string($value) && trim($value) !== '') {
                    $hasValue = true;
                    break;
                }
                if ($value !== null && ! is_string($value)) {
                    $hasValue = true;
                    break;
                }
            }

            if ($hasValue) {
                $filtered[] = $row;
            }
        }

        return array_values($filtered);
    }

    /**
     * @param  array<int, mixed>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function filterExperiences(array $rows): array
    {
        $filtered = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $row = array_merge([
                'company_name' => null,
                'job_title' => null,
                'location' => null,
                'start_date' => null,
                'end_date' => null,
                'is_current' => false,
                'description' => null,
            ], $row);

            $hasValue = (bool) ($row['is_current'] ?? false);
            if (! $hasValue) {
                foreach (['company_name', 'job_title', 'location', 'start_date', 'end_date', 'description'] as $field) {
                    $value = $row[$field] ?? null;
                    if (is_string($value) && trim($value) !== '') {
                        $hasValue = true;
                        break;
                    }
                    if ($value !== null && ! is_string($value)) {
                        $hasValue = true;
                        break;
                    }
                }
            }

            if ($hasValue) {
                $filtered[] = $row;
            }
        }

        return array_values($filtered);
    }
}
