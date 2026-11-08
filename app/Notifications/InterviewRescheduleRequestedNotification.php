<?php

namespace App\Notifications;

use App\Models\Interview;
use App\Notifications\Concerns\DescribesInterviewTime;
use Illuminate\Notifications\Notification;

class InterviewRescheduleRequestedNotification extends Notification
{
    use DescribesInterviewTime;

    public function __construct(public Interview $interview) {}

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
        $this->interview->loadMissing(['jobApplication.jobPosting', 'jobApplication.user']);

        $message = __(':candidate requested a new time for the :job interview. Proposed: :time.', [
            'candidate' => $this->interview->jobApplication->user->name,
            'job' => $this->interview->jobApplication->jobPosting->title,
            'time' => $this->requestedTimeLabel($this->interview) ?? $this->interviewTimeLabel($this->interview),
        ]);

        if (! empty($this->interview->response_note)) {
            $message .= ' '.__('Note: :note', ['note' => $this->interview->response_note]);
        }

        return [
            'event' => 'interview.reschedule_requested',
            'title' => __('Interview reschedule requested'),
            'message' => $message,
            'url' => route('employer.applications.show', $this->interview->jobApplication),
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
