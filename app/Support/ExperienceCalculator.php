<?php

namespace App\Support;

use App\Models\CandidateProfile;
use Carbon\CarbonImmutable;

/**
 * Calculates how many years of work experience a candidate has.
 *
 * Overlapping roles are merged before summing so that two concurrent jobs are
 * never counted twice.
 */
class ExperienceCalculator
{
    /**
     * Average length of a Gregorian year, used to convert days into years.
     */
    private const DAYS_PER_YEAR = 365.25;

    /**
     * Total years of experience held by the candidate.
     */
    public static function years(CandidateProfile $profile): float
    {
        $intervals = [];

        foreach ($profile->experiences as $experience) {
            $start = $experience->start_date;

            if ($start === null) {
                continue;
            }

            $start = CarbonImmutable::parse($start)->startOfDay();

            $end = $experience->is_current
                ? CarbonImmutable::now()->startOfDay()
                : ($experience->end_date !== null ? CarbonImmutable::parse($experience->end_date)->startOfDay() : $start);

            if ($end->lessThan($start)) {
                continue;
            }

            $intervals[] = [$start, $end];
        }

        if ($intervals === []) {
            return 0.0;
        }

        $days = 0;

        foreach (self::merge($intervals) as [$start, $end]) {
            $days += (int) $start->diffInDays($end);
        }

        return round($days / self::DAYS_PER_YEAR, 1);
    }

    /**
     * Merge overlapping intervals into a minimal set of disjoint intervals.
     *
     * @param  array<int, array{0: CarbonImmutable, 1: CarbonImmutable}>  $intervals
     * @return array<int, array{0: CarbonImmutable, 1: CarbonImmutable}>
     */
    private static function merge(array $intervals): array
    {
        usort($intervals, static fn (array $a, array $b): int => $a[0] <=> $b[0]);

        $merged = [];
        $current = null;

        foreach ($intervals as [$start, $end]) {
            if ($current === null) {
                $current = [$start, $end];

                continue;
            }

            if ($start->lessThanOrEqualTo($current[1])) {
                if ($end->greaterThan($current[1])) {
                    $current[1] = $end;
                }

                continue;
            }

            $merged[] = $current;
            $current = [$start, $end];
        }

        if ($current !== null) {
            $merged[] = $current;
        }

        return $merged;
    }
}
