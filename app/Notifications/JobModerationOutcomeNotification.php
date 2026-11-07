<?php

namespace App\Notifications;

use App\Models\Job;
use Illuminate\Notifications\Notification;

class JobModerationOutcomeNotification extends Notification
{
    public function __construct(
        public Job $job,
        public bool $approved,
        public ?string $reason = null,
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
        return [
            'event' => 'job.moderation_outcome',
            'title' => $this->approved
                ? __('Job approved')
                : __('Job rejected'),
            'message' => $this->approved
                ? __('Your job :title has been approved and published.', ['title' => $this->job->title])
                : __('Your job :title was rejected. Reason: :reason', [
                    'title' => $this->job->title,
                    'reason' => $this->reason ?? __('Not specified'),
                ]),
            'url' => route('employer.jobs.edit', $this->job),
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
