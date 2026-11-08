<?php

namespace App\Enums;

enum InterviewStatus: string
{
    case Scheduled = 'scheduled';
    case Accepted = 'accepted';
    case Declined = 'declined';
    case RescheduleRequested = 'reschedule_requested';
    case Cancelled = 'cancelled';

    /**
     * Get the human-readable label for the interview status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Scheduled => 'Scheduled',
            self::Accepted => 'Accepted',
            self::Declined => 'Declined',
            self::RescheduleRequested => 'Reschedule requested',
            self::Cancelled => 'Cancelled',
        };
    }

    /**
     * Determine whether the status represents an active interview round.
     */
    public function isActive(): bool
    {
        return in_array($this, [
            self::Scheduled,
            self::Accepted,
            self::RescheduleRequested,
        ], true);
    }

    /**
     * Get the values of every active interview status.
     *
     * @return array<int, string>
     */
    public static function activeValues(): array
    {
        return [
            self::Scheduled->value,
            self::Accepted->value,
            self::RescheduleRequested->value,
        ];
    }
}
