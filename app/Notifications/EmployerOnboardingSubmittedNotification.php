<?php

namespace App\Notifications;

use App\Models\EmployerOnboardingRequest;
use App\Models\User;
use Illuminate\Notifications\Notification;

class EmployerOnboardingSubmittedNotification extends Notification
{
    public function __construct(
        public User $applicant,
        public EmployerOnboardingRequest $onboardingRequest,
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
            'event' => 'employer.onboarding_submitted',
            'title' => __('Employer application submitted'),
            'message' => __(':name submitted an employer onboarding request for :company.', [
                'name' => $this->applicant->name,
                'company' => $this->onboardingRequest->company_name,
            ]),
            'url' => route('admin.employers.index'),
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
