<?php

namespace App\Enums;

enum ReportResolutionAction: string
{
    case NoAction = 'no_action';
    case ContentRemoved = 'content_removed';
    case AccountRestricted = 'account_restricted';
    case Other = 'other';

    /**
     * Get the human-readable label for the resolution action.
     */
    public function label(): string
    {
        return match ($this) {
            self::NoAction => 'No action taken',
            self::ContentRemoved => 'Content removed',
            self::AccountRestricted => 'Account restricted',
            self::Other => 'Other',
        };
    }

    /**
     * Get the values of every report resolution action.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return [
            self::NoAction->value,
            self::ContentRemoved->value,
            self::AccountRestricted->value,
            self::Other->value,
        ];
    }
}
