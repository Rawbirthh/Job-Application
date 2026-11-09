<?php

namespace App\Notifications;

use App\Models\Company;
use Illuminate\Notifications\Notification;

class CompanyMemberRoleChangedNotification extends Notification
{
    public function __construct(public Company $company, public string $oldRole, public string $newRole) {}

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
            'event' => 'company.member.role_changed',
            'title' => __('Your company role changed'),
            'message' => __('Your role in :company changed from :old to :new.', [
                'company' => $this->company->name,
                'old' => ucfirst($this->oldRole),
                'new' => ucfirst($this->newRole),
            ]),
            'url' => route('employer.companies.show', $this->company),
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
