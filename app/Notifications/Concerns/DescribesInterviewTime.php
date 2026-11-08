<?php

namespace App\Notifications\Concerns;

use App\Models\Interview;

trait DescribesInterviewTime
{
    /**
     * Format the interview's scheduled time in the employer-selected time zone.
     */
    protected function interviewTimeLabel(Interview $interview): string
    {
        return $interview->scheduled_at
            ->copy()
            ->setTimezone($interview->timezone)
            ->format('M j, Y g:i A').' ('.$interview->timezone.')';
    }

    /**
     * Format the candidate's proposed time in its stored time zone.
     */
    protected function requestedTimeLabel(Interview $interview): ?string
    {
        if ($interview->requested_scheduled_at === null) {
            return null;
        }

        $timezone = $interview->requested_timezone ?? $interview->timezone;

        return $interview->requested_scheduled_at
            ->copy()
            ->setTimezone($timezone)
            ->format('M j, Y g:i A').' ('.$timezone.')';
    }
}
