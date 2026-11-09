<?php

namespace App\Notifications;

use App\Models\Company;
use Illuminate\Notifications\Notification;

class CompanyMemberAddedNotification extends Notification
{
    public function __construct(public Company $company, public string $role) {}

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
            'event' => 'company.member.added',
            'title' => __('You were added to a company team'),
            'message' => __('You were added to :company as :role.', [
                'company' => $this->company->name,
                'role' => ucfirst($this->role),
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
