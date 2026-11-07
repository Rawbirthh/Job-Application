<?php

namespace App\Notifications;

use App\Models\JobApplication;
use Illuminate\Notifications\Notification;

class ApplicationWithdrawnNotification extends Notification
{
    public function __construct(public JobApplication $application) {}

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
        $this->application->loadMissing(['jobPosting', 'user']);

        return [
            'event' => 'application.withdrawn',
            'title' => __('Application withdrawn'),
            'message' => __(':candidate withdrew their application for :job.', [
                'candidate' => $this->application->user->name,
                'job' => $this->application->jobPosting->title,
            ]),
            'url' => route('employer.applications.show', $this->application),
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
