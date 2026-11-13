<?php

namespace App\Services\Recommendation;

/**
 * The rule engine behind Feature 34.
 *
 * This class produces the score *and* the reasons from one computation, so a
 * reason can never disagree with the score that produced it. It performs no
 * database access, which makes every rule directly unit-testable.
 */
final class RecommendationScorer
{
    public const SKILL_WEIGHT = 40;

    public const CATEGORY_WEIGHT = 20;

    public const LEVEL_WEIGHT = 20;

    public const LOCATION_WEIGHT = 10;

    public const WORKPLACE_WEIGHT = 10;

    /**
     * Score a job against the candidate's signals.
     *
     * Returns null when no signal applies to this job, which means the job
     * cannot be recommended at all.
     */
    public function score(RecommendationCandidate $candidate, RecommendationJob $job): ?RecommendationOutcome
    {
        $earned = 0;
        $applicable = 0;
        $reasons = [];

        $skillPoints = $this->skillPoints($candidate, $job);
        if ($skillPoints !== null) {
            $applicable += self::SKILL_WEIGHT;
            $earned += $skillPoints;

            if ($skillPoints > 0) {
                $reasons[] = new RecommendationReason(
                    'skill_overlap',
                    'Skill match',
                    sprintf(
                        "Matches %d of the job's %d listed skills",
                        $job->matchedSkillCount,
                        $job->totalSkillCount,
                    ),
                    $job->matchedSkillLabels,
                );
            }
        }

        if ($candidate->preferredCategoryId !== null) {
            $applicable += self::CATEGORY_WEIGHT;

            if ($job->categoryId === $candidate->preferredCategoryId) {
                $earned += self::CATEGORY_WEIGHT;

                $reasons[] = new RecommendationReason(
                    'category_match',
                    'Category match',
                    sprintf('%s — matches your preferred category', $job->categoryName ?? 'This category'),
                );
            }
        }

        $levelPoints = $this->levelPoints($candidate, $job);
        if ($levelPoints !== null) {
            $applicable += self::LEVEL_WEIGHT;
            $earned += $levelPoints;

            if ($levelPoints > 0) {
                $reasons[] = new RecommendationReason(
                    'experience_level_match',
                    'Experience level',
                    $this->levelDetail($candidate, $job),
                );
            }
        }

        if ($candidate->locationCity !== null && $job->locationCity !== null) {
            $applicable += self::LOCATION_WEIGHT;

            if ($job->locationCity === $candidate->locationCity) {
                $earned += self::LOCATION_WEIGHT;

                $reasons[] = new RecommendationReason(
                    'location_match',
                    'Location match',
                    sprintf('%s — matches your location', ucfirst($candidate->locationCity)),
                );
            }
        }

        if ($candidate->preferredWorkplaceArrangementId !== null && $job->workplaceArrangementId !== null) {
            $applicable += self::WORKPLACE_WEIGHT;

            if ($job->workplaceArrangementId === $candidate->preferredWorkplaceArrangementId) {
                $earned += self::WORKPLACE_WEIGHT;

                $reasons[] = new RecommendationReason(
                    'workplace_match',
                    'Work arrangement',
                    sprintf(
                        '%s — matches your preferred work arrangement',
                        $job->workplaceArrangementName ?? 'This arrangement',
                    ),
                );
            }
        }

        if ($applicable === 0) {
            return null;
        }

        return new RecommendationOutcome((int) round($earned / $applicable * 100), $reasons);
    }

    /**
     * The points earned by the skill-overlap signal, or null when it does not apply.
     */
    private function skillPoints(RecommendationCandidate $candidate, RecommendationJob $job): ?int
    {
        if (! $candidate->hasSkills() || $job->totalSkillCount === 0) {
            return null;
        }

        $points = (int) floor(self::SKILL_WEIGHT * ($job->matchedSkillCount / $job->totalSkillCount));

        return min(self::SKILL_WEIGHT, max(0, $points));
    }

    /**
     * The points earned by the experience-level signal, or null when it does not apply.
     */
    private function levelPoints(RecommendationCandidate $candidate, RecommendationJob $job): ?int
    {
        if (! $candidate->hasTargetLevel() || $job->experienceLevelRank === null) {
            return null;
        }

        $difference = abs($job->experienceLevelRank - $candidate->targetExperienceRank);

        if ($difference === 0) {
            return self::LEVEL_WEIGHT;
        }

        if ($difference === 1) {
            return (int) (self::LEVEL_WEIGHT / 2);
        }

        return 0;
    }

    /**
     * The human-readable explanation for the experience-level signal.
     */
    private function levelDetail(RecommendationCandidate $candidate, RecommendationJob $job): string
    {
        $levelName = $job->experienceLevelName ?? 'This level';
        $targetName = $candidate->targetExperienceLevelName;

        if ($job->experienceLevelRank === $candidate->targetExperienceRank) {
            return sprintf('%s — matches your target level', $levelName);
        }

        if ($targetName !== null) {
            return sprintf('%s — one level from your target (%s)', $levelName, $targetName);
        }

        return sprintf('%s — one level from your target', $levelName);
    }
}
