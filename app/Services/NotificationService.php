<?php

namespace App\Services;

use App\Enums\CompanyMemberStatus;
use App\Enums\JobApplicationStatus;
use App\Enums\PermissionSlug;
use App\Enums\RoleSlug;
use App\Models\Company;
use App\Models\CompanyMember;
use App\Models\ConversationMessage;
use App\Models\EmployerOnboardingRequest;
use App\Models\Interview;
use App\Models\Job;
use App\Models\JobApplication;
use App\Models\Report;
use App\Models\SavedSearch;
use App\Models\User;
use App\Notifications\ApplicationStatusChangedNotification;
use App\Notifications\ApplicationSubmittedNotification;
use App\Notifications\ApplicationWithdrawnNotification;
use App\Notifications\CompanyMemberAddedNotification;
use App\Notifications\CompanyMemberRevokedNotification;
use App\Notifications\CompanyMemberRoleChangedNotification;
use App\Notifications\EmployerOnboardingSubmittedNotification;
use App\Notifications\InterviewCancelledNotification;
use App\Notifications\InterviewRescheduledNotification;
use App\Notifications\InterviewRescheduleRequestedNotification;
use App\Notifications\InterviewResponseNotification;
use App\Notifications\InterviewScheduledNotification;
use App\Notifications\JobModerationOutcomeNotification;
use App\Notifications\JobSubmittedForReviewNotification;
use App\Notifications\NewConversationMessageNotification;
use App\Notifications\NewJobApplicationNotification;
use App\Notifications\ReportOutcomeNotification;
use App\Notifications\ReportSubmittedNotification;
use App\Notifications\SavedSearchMatchedNotification;
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

    public function reportSubmitted(Report $report): void
    {
        $recipients = User::query()
            ->whereHas('roles.permissions', function ($query): void {
                $query->where('permissions.slug', PermissionSlug::AdminAccess->value);
            })
            ->whereHas('roles.permissions', function ($query): void {
                $query->where('permissions.slug', PermissionSlug::ReportsModerate->value);
            })
            ->get();

        foreach ($recipients as $recipient) {
            $this->notify($recipient, new ReportSubmittedNotification($report));
        }
    }

    public function reportOutcome(Report $report): void
    {
        $report->loadMissing('reporter');

        $recipient = $report->reporter;

        $this->notify($recipient, new ReportOutcomeNotification($report, $report->resolution_message));
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

    public function interviewScheduled(Interview $interview): void
    {
        $interview->loadMissing(['jobApplication.user', 'jobApplication.jobPosting']);

        $this->notify(
            $interview->jobApplication->user,
            new InterviewScheduledNotification($interview)
        );
    }

    public function interviewRescheduled(Interview $interview): void
    {
        $interview->loadMissing(['jobApplication.user', 'jobApplication.jobPosting']);

        $this->notify(
            $interview->jobApplication->user,
            new InterviewRescheduledNotification($interview)
        );
    }

    public function interviewCancelled(Interview $interview, bool $applicationReverted): void
    {
        $interview->loadMissing(['jobApplication.user', 'jobApplication.jobPosting']);

        $this->notify(
            $interview->jobApplication->user,
            new InterviewCancelledNotification($interview, $applicationReverted)
        );
    }

    public function interviewRescheduleRequested(Interview $interview): void
    {
        $interview->loadMissing(['jobApplication.jobPosting.company.owner']);

        $recipient = $interview->jobApplication->jobPosting->company?->owner;

        if ($recipient instanceof User) {
            $this->notify($recipient, new InterviewRescheduleRequestedNotification($interview));
        }
    }

    public function interviewResponded(Interview $interview): void
    {
        $interview->loadMissing(['jobApplication.jobPosting.company.owner']);

        $recipient = $interview->jobApplication->jobPosting->company?->owner;

        if ($recipient instanceof User) {
            $this->notify($recipient, new InterviewResponseNotification($interview));
        }
    }

    public function companyMemberAdded(User $member, Company $company, string $role): void
    {
        $this->notify($member, new CompanyMemberAddedNotification($company, $role));
    }

    public function companyMemberRoleChanged(User $member, Company $company, string $oldRole, string $newRole): void
    {
        $this->notify($member, new CompanyMemberRoleChangedNotification($company, $oldRole, $newRole));
    }

    public function companyMemberRevoked(User $member, Company $company): void
    {
        $this->notify($member, new CompanyMemberRevokedNotification($company));
    }

    /**
     * @param  array<int, array{id: int, title: string, company: string|null}>  $jobs
     */
    public function savedSearchMatched(SavedSearch $search, array $jobs): void
    {
        $this->notify($search->user, new SavedSearchMatchedNotification($search, $jobs));
    }

    /**
     * Notify the people on the other side of a conversation about a new message.
     * Coalesces to one unread notification per conversation per recipient.
     */
    public function newConversationMessage(ConversationMessage $message): void
    {
        $message->loadMissing(['conversation.application.jobPosting.company.owner', 'sender:id,name']);

        $application = $message->conversation->application;
        $company = $application->jobPosting->company;

        if ((int) $message->sender_id === (int) $application->user_id) {
            $recipients = $this->companyParticipantsWithManagePermission($company);
        } else {
            $recipients = [$application->user];
        }

        foreach ($recipients as $recipient) {
            $this->notifyCoalescedMessage($recipient, $message);
        }
    }

    /**
     * Company owner (implicit owner role) plus active members whose company
     * role grants applicants.manage — bounded by team size.
     *
     * @return array<int, User>
     */
    private function companyParticipantsWithManagePermission(?Company $company): array
    {
        if ($company === null) {
            return [];
        }

        $company->loadMissing('owner:id,name,email');

        $recipients = [];
        if ($company->owner instanceof User) {
            $recipients[] = $company->owner;
        }

        $members = CompanyMember::query()
            ->with('user:id,name,email')
            ->where('company_id', $company->id)
            ->where('status', CompanyMemberStatus::Active->value)
            ->get();

        foreach ($members as $membership) {
            $user = $membership->user;
            if ($user instanceof User && in_array('applicants.manage', $membership->company_role->permissions(), true)) {
                $recipients[] = $user;
            }
        }

        return collect($recipients)->unique('id')->values()->all();
    }

    private function notifyCoalescedMessage(User $recipient, ConversationMessage $message): void
    {
        $conversationId = (int) $message->conversation_id;
        $data = NewConversationMessageNotification::dataFor($message);

        try {
            $existing = $recipient->unreadNotifications()
                ->where('type', NewConversationMessageNotification::class)
                ->where('data->conversation_id', $conversationId)
                ->latest()
                ->first();

            if ($existing !== null) {
                $existing->forceFill(['data' => $data])->save();

                return;
            }
        } catch (\Throwable $e) {
            Log::warning('Message notification refresh failed', [
                'exception' => $e->getMessage(),
            ]);

            return;
        }

        $this->notify($recipient, new NewConversationMessageNotification($message));
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
