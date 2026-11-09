<?php

namespace App\Enums;

enum AlertFrequency: string
{
    case Daily = 'daily';
    case Weekly = 'weekly';

    /**
     * Get the human-readable label for the alert frequency.
     */
    public function label(): string
    {
        return match ($this) {
            self::Daily => 'Daily',
            self::Weekly => 'Weekly',
        };
    }
}
