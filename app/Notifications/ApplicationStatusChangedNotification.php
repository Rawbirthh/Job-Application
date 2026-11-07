<?php

namespace App\Notifications;

use App\Enums\JobApplicationStatus;
use App\Models\JobApplication;
use Illuminate\Notifications\Notification;

class ApplicationStatusChangedNotification extends Notification
{
    public function __construct(
        public JobApplication $application,
        public JobApplicationStatus $from,
        public JobApplicationStatus $to,
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
        $this->application->loadMissing(['jobPosting']);

        return [
            'event' => 'application.status_changed',
            'title' => __('Application status updated'),
            'message' => __('Your application for :job is now :status.', [
                'job' => $this->application->jobPosting->title,
                'status' => $this->to->label(),
            ]),
            'url' => route('candidate.applications.show', $this->application),
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
