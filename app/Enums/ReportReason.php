<?php

namespace App\Enums;

enum ReportReason: string
{
    case ScamOrFraud = 'scam_or_fraud';
    case MisleadingListing = 'misleading_listing';
    case OffensiveContent = 'offensive_content';
    case Spam = 'spam';
    case Other = 'other';

    /**
     * Get the human-readable label for the report reason.
     */
    public function label(): string
    {
        return match ($this) {
            self::ScamOrFraud => 'Scam or fraud',
            self::MisleadingListing => 'Misleading listing',
            self::OffensiveContent => 'Offensive content',
            self::Spam => 'Spam',
            self::Other => 'Other',
        };
    }

    /**
     * Get the values of every report reason.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return [
            self::ScamOrFraud->value,
            self::MisleadingListing->value,
            self::OffensiveContent->value,
            self::Spam->value,
            self::Other->value,
        ];
    }
}
