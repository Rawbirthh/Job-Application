<?php

namespace App\Services\Recommendation;

use App\Models\ExperienceLevel;

/**
 * A stable, unique ordering of the experience levels.
 *
 * The matching rules compare a job's level against the candidate's target
 * level by band. `experience_levels.sort_order` is the intended ordering but
 * it is **not unique** — the admin form accepts any integer and the factory
 * defaults to 0 — so two different levels can legitimately share a sort order.
 * Comparing raw sort orders would then report unrelated levels as the same
 * band.
 *
 * The rank below is derived from `(sort_order, id)`, which is deterministic,
 * unique, and keeps the intended ordering. Ties are broken by the primary key,
 * which is the order the levels were created in.
 *
 * This is a per-request value object: it is rebuilt on every call rather than
 * memoised, so a long-lived container can never serve a stale ordering.
 */
final class ExperienceLevelRanking
{
    /**
     * @param  array<int, int>  $ranks  level id => 1-based rank
     */
    private function __construct(
        private readonly array $ranks,
    ) {}

    public static function fromDatabase(): self
    {
        $ranks = [];
        $rank = 1;

        foreach (ExperienceLevel::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id']) as $level) {
            $ranks[(int) $level->id] = $rank++;
        }

        return new self($ranks);
    }

    /**
     * The rank of a level, or null when the level is unknown or absent.
     */
    public function rank(?int $levelId): ?int
    {
        if ($levelId === null) {
            return null;
        }

        return $this->ranks[$levelId] ?? null;
    }

    /**
     * The ids of the levels that sit within one band of the given rank.
     *
     * @return array<int, int>
     */
    public function bandIds(?int $rank): array
    {
        if ($rank === null) {
            return [];
        }

        return array_keys(array_filter(
            $this->ranks,
            static fn (int $candidate): bool => abs($candidate - $rank) <= 1,
        ));
    }
}
