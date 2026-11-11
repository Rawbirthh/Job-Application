<?php

namespace App\Enums;

enum ReportStatus: string
{
    case Pending = 'pending';
    case Investigating = 'investigating';
    case Resolved = 'resolved';
    case Dismissed = 'dismissed';

    /**
     * Get the human-readable label for the report status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Investigating => 'Investigating',
            self::Resolved => 'Resolved',
            self::Dismissed => 'Dismissed',
        };
    }

    /**
     * Determine whether the report is still open for moderation.
     */
    public function isOpen(): bool
    {
        return in_array($this, [self::Pending, self::Investigating], true);
    }

    /**
     * Determine whether the report received a final outcome.
     */
    public function isResolved(): bool
    {
        return in_array($this, [self::Resolved, self::Dismissed], true);
    }

    /**
     * Get the values of every open report status.
     *
     * @return array<int, string>
     */
    public static function openValues(): array
    {
        return [
            self::Pending->value,
            self::Investigating->value,
        ];
    }

    /**
     * Get the values of every report status.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return [
            self::Pending->value,
            self::Investigating->value,
            self::Resolved->value,
            self::Dismissed->value,
        ];
    }
}
