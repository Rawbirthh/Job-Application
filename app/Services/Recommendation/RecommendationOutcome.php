<?php

namespace App\Services\Recommendation;

/**
 * The score for one job and the reasons that produced it.
 */
final class RecommendationOutcome
{
    /**
     * @param  array<int, RecommendationReason>  $reasons
     */
    public function __construct(
        public readonly int $score,
        public readonly array $reasons,
    ) {}
}
