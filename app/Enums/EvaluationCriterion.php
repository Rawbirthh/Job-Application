<?php

namespace App\Enums;

enum EvaluationCriterion: string
{
    case RelevantExperience = 'relevant_experience';
    case TechnicalSkills = 'technical_skills';
    case Communication = 'communication';
    case InterviewPerformance = 'interview_performance';

    public function label(): string
    {
        return match ($this) {
            self::RelevantExperience => 'Relevant experience',
            self::TechnicalSkills => 'Technical skills',
            self::Communication => 'Communication',
            self::InterviewPerformance => 'Interview performance',
        };
    }
}
