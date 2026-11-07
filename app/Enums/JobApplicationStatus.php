<?php

namespace App\Enums;

enum JobApplicationStatus: string
{
    case Submitted = 'submitted';
    case Reviewing = 'reviewing';
    case Shortlisted = 'shortlisted';
    case InterviewScheduled = 'interview_scheduled';
    case OfferSent = 'offer_sent';
    case Hired = 'hired';
    case Rejected = 'rejected';
    case Withdrawn = 'withdrawn';

    /**
     * Get the human-readable label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Submitted => 'Submitted',
            self::Reviewing => 'Reviewing',
            self::Shortlisted => 'Shortlisted',
            self::InterviewScheduled => 'Interview Scheduled',
            self::OfferSent => 'Offer Sent',
            self::Hired => 'Hired',
            self::Rejected => 'Rejected',
            self::Withdrawn => 'Withdrawn',
        };
    }

    /**
     * Get the allowed next statuses for an employer.
     *
     * @return array<int, self>
     */
    public function allowedEmployerTransitions(): array
    {
        return match ($this) {
            self::Submitted => [self::Reviewing, self::Rejected],
            self::Reviewing => [self::Shortlisted, self::Rejected],
            self::Shortlisted => [self::InterviewScheduled, self::Rejected],
            self::InterviewScheduled => [self::OfferSent, self::Rejected],
            self::OfferSent => [self::Hired, self::Rejected],
            self::Hired, self::Rejected, self::Withdrawn => [],
        };
    }

    /**
     * Check if the status can transition to the given target status.
     */
    public function canTransitionTo(self $target, bool $isCandidate = false): bool
    {
        if ($isCandidate) {
            return ! $this->isTerminal() && $target === self::Withdrawn;
        }

        return in_array($target, $this->allowedEmployerTransitions(), true);
    }

    /**
     * Check whether this status is terminal.
     */
    public function isTerminal(): bool
    {
        return in_array($this, [self::Hired, self::Rejected, self::Withdrawn], true);
    }
}
