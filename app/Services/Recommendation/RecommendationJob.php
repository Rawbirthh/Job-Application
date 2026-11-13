<?php

namespace App\Services\Recommendation;

/**
 * The job-side values the matching rules read.
 */
final class RecommendationJob
{
    /**
     * @param  array<int, string>  $matchedSkillLabels  display spellings of the skills the candidate shares with the job
     */
    public function __construct(
        public readonly int $id,
        public readonly int $categoryId,
        public readonly ?int $experienceLevelId,
        public readonly ?int $experienceLevelRank,
        public readonly ?string $experienceLevelName,
        public readonly ?string $locationCity,
        public readonly ?int $workplaceArrangementId,
        public readonly ?string $workplaceArrangementName,
        public readonly ?string $categoryName,
        public readonly int $totalSkillCount,
        public readonly int $matchedSkillCount,
        public readonly array $matchedSkillLabels,
    ) {}
}
