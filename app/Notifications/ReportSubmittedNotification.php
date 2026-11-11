<?php

namespace App\Notifications;

use App\Models\Report;
use Illuminate\Notifications\Notification;

class ReportSubmittedNotification extends Notification
{
    public function __construct(public Report $report) {}

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
        $this->report->loadMissing(['jobPosting:id,title', 'company:id,name']);

        $subject = $this->report->job_posting_id !== null
            ? __('the job :title', ['title' => $this->report->jobPosting?->title])
            : __('the employer :name', ['name' => $this->report->company?->name]);

        return [
            'event' => 'report.submitted',
            'title' => __('New report submitted for review'),
            'message' => __('A :reason report was filed for :subject.', [
                'reason' => $this->report->reason->label(),
                'subject' => $subject,
            ]),
            'url' => route('admin.reports.show', $this->report),
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
