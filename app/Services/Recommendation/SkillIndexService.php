<?php

namespace App\Services\Recommendation;

use App\Models\CandidateProfile;
use App\Models\Job;
use App\Support\SkillNormalizer;
use Illuminate\Support\Facades\DB;

/**
 * The single writer of the derived skill-index tables.
 *
 * Both `candidate_skills` and `job_skills` are rebuilt from the JSON source of
 * truth whenever the corresponding skill list changes, inside the same
 * transaction as the parent write, so the index can never drift.
 */
class SkillIndexService
{
    /**
     * Rebuild the canonical skill index for a candidate profile.
     */
    public function syncProfile(CandidateProfile $profile): void
    {
        $skills = SkillNormalizer::normalizeList($profile->skills ?? []);

        DB::transaction(function () use ($profile, $skills): void {
            $profile->skillTags()->delete();

            foreach ($skills as $skill) {
                $profile->skillTags()->create(['skill' => $skill]);
            }
        });
    }

    /**
     * Rebuild the canonical skill index for a job posting.
     */
    public function syncJob(Job $job): void
    {
        $skills = SkillNormalizer::normalizeList($job->skills ?? []);

        DB::transaction(function () use ($job, $skills): void {
            $job->skillTags()->delete();

            foreach ($skills as $skill) {
                $job->skillTags()->create(['skill' => $skill]);
            }
        });
    }
}
