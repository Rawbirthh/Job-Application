<?php

namespace App\Enums;

enum ScreeningQuestionType: string
{
    case ShortText = 'short_text';
    case LongText = 'long_text';
    case YesNo = 'yes_no';
    case SingleChoice = 'single_choice';

    public function label(): string
    {
        return match ($this) {
            self::ShortText => 'Short text',
            self::LongText => 'Long text',
            self::YesNo => 'Yes/No',
            self::SingleChoice => 'Single choice',
        };
    }
}
