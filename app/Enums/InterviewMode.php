<?php

namespace App\Enums;

enum InterviewMode: string
{
    case Online = 'online';
    case Onsite = 'onsite';
    case Phone = 'phone';

    /**
     * Get the human-readable label for the interview mode.
     */
    public function label(): string
    {
        return match ($this) {
            self::Online => 'Online',
            self::Onsite => 'On-site',
            self::Phone => 'Phone',
        };
    }
}
