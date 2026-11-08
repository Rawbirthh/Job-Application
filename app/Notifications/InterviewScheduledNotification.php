<?php

namespace App\Notifications;

use App\Models\Interview;
use App\Notifications\Concerns\DescribesInterviewTime;
use Illuminate\Notifications\Notification;

class InterviewScheduledNotification extends Notification
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
        $this->interview->loadMissing(['jobApplication.jobPosting']);

        return [
            'event' => 'interview.scheduled',
            'title' => __('Interview scheduled'),
            'message' => __('An interview for :job has been scheduled for :time.', [
                'job' => $this->interview->jobApplication->jobPosting->title,
                'time' => $this->interviewTimeLabel($this->interview),
            ]),
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
