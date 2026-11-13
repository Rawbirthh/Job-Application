<?php

namespace App\Services\Recommendation;

/**
 * A single, human-readable explanation for one matched signal.
 */
final class RecommendationReason
{
    /**
     * @param  array<int, string>  $values
     */
    public function __construct(
        public readonly string $code,
        public readonly string $label,
        public readonly string $detail,
        public readonly array $values = [],
    ) {}
}
