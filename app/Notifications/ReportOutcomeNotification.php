<?php

namespace App\Notifications;

use App\Enums\ReportStatus;
use App\Models\Report;
use Illuminate\Notifications\Notification;

class ReportOutcomeNotification extends Notification
{
    public function __construct(
        public Report $report,
        public ?string $resolutionMessage = null,
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
        $message = $this->report->status === ReportStatus::Resolved
            ? __('Your report has been reviewed and appropriate action was taken.')
            : __('Your report has been reviewed and was dismissed.');

        if ($this->resolutionMessage !== null && trim($this->resolutionMessage) !== '') {
            $message .= ' '.__('Message from the moderation team: :message', [
                'message' => $this->resolutionMessage,
            ]);
        }

        return [
            'event' => 'report.outcome',
            'title' => $this->report->status === ReportStatus::Resolved
                ? __('Your report was resolved')
                : __('Your report was dismissed'),
            'message' => $message,
            'url' => $this->subjectUrl(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }

    private function subjectUrl(): string
    {
        $this->report->loadMissing('jobPosting:id,title');

        if ($this->report->jobPosting !== null) {
            return route('jobs.show', $this->report->jobPosting);
        }

        return route('home');
    }
}
