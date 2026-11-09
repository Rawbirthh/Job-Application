<?php

namespace App\Notifications;

use App\Models\Company;
use Illuminate\Notifications\Notification;

class CompanyMemberRevokedNotification extends Notification
{
    public function __construct(public Company $company) {}

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
            'event' => 'company.member.revoked',
            'title' => __('Your access to a company was revoked'),
            'message' => __('Your access to :company has been revoked.', [
                'company' => $this->company->name,
            ]),
            'url' => route('employer.companies.index'),
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
