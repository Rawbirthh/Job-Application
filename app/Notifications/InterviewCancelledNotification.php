<?php

namespace App\Notifications;

use App\Models\Interview;
use App\Notifications\Concerns\DescribesInterviewTime;
use Illuminate\Notifications\Notification;

class InterviewCancelledNotification extends Notification
{
    use DescribesInterviewTime;

    public function __construct(
        public Interview $interview,
        public bool $applicationReverted = false,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        $this->interview->loadMissing(['jobApplication.jobPosting']);

        $message = __('Your interview for :job on :time was cancelled. Reason: :reason', [
            'job' => $this->interview->jobApplication->jobPosting->title,
            'time' => $this->interviewTimeLabel($this->interview),
            'reason' => $this->interview->cancellation_reason ?? __('Not specified'),
        ]);

        if ($this->applicationReverted) {
            $message .= ' '.__('Your application has been returned to the shortlisted stage.');
        }

        return [
            'event' => 'interview.cancelled',
            'title' => __('Interview cancelled'),
            'message' => $message,
            'url' => route('candidate.applications.show', $this->interview->jobApplication),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }
}
