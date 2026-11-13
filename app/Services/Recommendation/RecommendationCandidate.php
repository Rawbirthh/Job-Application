<?php

namespace App\Services\Recommendation;

/**
 * The candidate-side signals used by the matching rules.
 */
final class RecommendationCandidate
{
    /**
     * @param  array<int, string>  $skills  canonical skill tokens
     * @param  array<string, string>  $skillLabels  canonical token => the spelling the candidate used
     */
    public function __construct(
        public readonly array $skills,
        public readonly array $skillLabels,
        public readonly ?int $preferredCategoryId,
        public readonly ?int $targetExperienceLevelId,
        public readonly ?int $targetExperienceRank,
        public readonly ?string $targetExperienceLevelName,
        public readonly ?string $locationCity,
        public readonly ?int $preferredWorkplaceArrangementId,
    ) {}

    public function hasSkills(): bool
    {
        return $this->skills !== [];
    }

    public function hasTargetLevel(): bool
    {
        return $this->targetExperienceRank !== null;
    }

    /**
     * The number of signals the candidate has stated.
     */
    public function signalCount(): int
    {
        return count(array_filter([
            $this->hasSkills(),
            $this->preferredCategoryId !== null,
            $this->hasTargetLevel(),
            $this->locationCity !== null,
            $this->preferredWorkplaceArrangementId !== null,
        ], static fn (bool $stated): bool => $stated));
    }
}
