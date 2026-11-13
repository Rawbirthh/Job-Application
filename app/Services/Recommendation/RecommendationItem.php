<?php

namespace App\Services\Recommendation;

use App\Models\Job;

/**
 * One ranked job with its score, reasons and candidate-specific flags.
 */
final class RecommendationItem
{
    /**
     * @param  array<int, RecommendationReason>  $reasons
     */
    public function __construct(
        public readonly Job $job,
        public readonly ?int $score,
        public readonly array $reasons = [],
        public readonly bool $hasApplied = false,
        public readonly bool $isSaved = false,
    ) {}

    /**
     * Return a copy carrying the candidate's applied/saved flags.
     */
    public function withFlags(bool $hasApplied, bool $isSaved): self
    {
        return new self($this->job, $this->score, $this->reasons, $hasApplied, $isSaved);
    }
}
