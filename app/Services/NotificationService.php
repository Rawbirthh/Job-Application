<?php

namespace App\Services;

use App\Enums\JobApplicationStatus;
use App\Enums\PermissionSlug;
use App\Enums\RoleSlug;
use App\Models\EmployerOnboardingRequest;
use App\Models\Job;
use App\Models\JobApplication;
use App\Models\User;
use App\Notifications\ApplicationStatusChangedNotification;
use App\Notifications\ApplicationSubmittedNotification;
use App\Notifications\ApplicationWithdrawnNotification;
use App\Notifications\EmployerOnboardingSubmittedNotification;
use App\Notifications\JobModerationOutcomeNotification;
use App\Notifications\JobSubmittedForReviewNotification;
use App\Notifications\NewJobApplicationNotification;
use App\Notifications\UserRegisteredNotification;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    public function applicationSubmitted(JobApplication $application): void
    {
        $application->loadMissing(['user', 'jobPosting.company']);

        $recipient = $application->user;

        $this->notify($recipient, new ApplicationSubmittedNotification($application));
    }

    public function newJobApplication(JobApplication $application): void
    {
        $application->loadMissing(['user', 'jobPosting.company.owner']);

        $recipient = $application->jobPosting->company->owner;

        $this->notify($recipient, new NewJobApplicationNotification($application));
    }

    public function applicationStatusChanged(
        JobApplication $application,
        JobApplicationStatus $from,
        JobApplicationStatus $to,
    ): void {
        $application->loadMissing(['user', 'jobPosting']);

        $recipient = $application->user;

        $this->notify($recipient, new ApplicationStatusChangedNotification($application, $from, $to));
    }

    public function applicationWithdrawn(JobApplication $application): void
    {
        $application->loadMissing(['user', 'jobPosting.company.owner']);

        $recipient = $application->jobPosting->company->owner;

        $this->notify($recipient, new ApplicationWithdrawnNotification($application));
    }

    public function jobModerationOutcome(Job $job, bool $approved, ?string $reason = null): void
    {
        $job->loadMissing(['company.owner']);

        $recipient = $job->company->owner;

        $this->notify($recipient, new JobModerationOutcomeNotification($job, $approved, $reason));
    }

    public function jobSubmittedForReview(Job $job): void
    {
        $job->loadMissing(['company']);

        $recipients = User::query()
            ->whereHas('roles.permissions', function ($query): void {
                $query->where('permissions.slug', PermissionSlug::AdminAccess->value);
            })
            ->whereHas('roles.permissions', function ($query): void {
                $query->where('permissions.slug', PermissionSlug::JobsModerate->value);
            })
            ->get();

        foreach ($recipients as $recipient) {
            $this->notify($recipient, new JobSubmittedForReviewNotification($job));
        }
    }

    public function userRegistered(User $user): void
    {
        $recipients = User::query()
            ->whereHas('roles', function ($query): void {
                $query->where('roles.slug', RoleSlug::PlatformAdmin->value);
            })
            ->get();

        foreach ($recipients as $recipient) {
            $this->notify($recipient, new UserRegisteredNotification($user));
        }
    }

    public function employerOnboardingSubmitted(User $applicant, EmployerOnboardingRequest $request): void
    {
        $recipients = User::query()
            ->where(function ($query): void {
                $query->whereHas('roles', function ($roleQuery): void {
                    $roleQuery->where('roles.slug', RoleSlug::PlatformAdmin->value);
                })->orWhereHas('roles.permissions', function ($permissionQuery): void {
                    $permissionQuery->where('permissions.slug', PermissionSlug::EmployersView->value);
                });
            })
            ->get();

        foreach ($recipients as $recipient) {
            $this->notify($recipient, new EmployerOnboardingSubmittedNotification($applicant, $request));
        }
    }

    private function notify(User $notifiable, object $notification): void
    {
        try {
            $notifiable->notify($notification);
        } catch (\Throwable $e) {
            Log::warning('Notification creation failed', [
                'exception' => $e->getMessage(),
                'notification' => $notification::class,
            ]);
        }
    }
}
