<?php

namespace App\Notifications;

use App\Models\JobApplication;
use Illuminate\Notifications\Notification;

class ApplicationSubmittedNotification extends Notification
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
        $this->application->loadMissing(['jobPosting.company']);

        return [
            'event' => 'application.submitted',
            'title' => __('Application submitted'),
            'message' => __('Your application for :job at :company has been submitted.', [
                'job' => $this->application->jobPosting->title,
                'company' => $this->application->jobPosting->company->name ?? '',
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
