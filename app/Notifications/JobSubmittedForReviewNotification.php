<?php

namespace App\Notifications;

use App\Models\Job;
use Illuminate\Notifications\Notification;

class JobSubmittedForReviewNotification extends Notification
{
    public function __construct(public Job $job) {}

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
        $this->job->loadMissing(['company']);

        return [
            'event' => 'job.submitted_for_review',
            'title' => __('Job submitted for review'),
            'message' => __(':company submitted :job for review.', [
                'company' => $this->job->company->name,
                'job' => $this->job->title,
            ]),
            'url' => route('admin.jobs.show', $this->job),
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
