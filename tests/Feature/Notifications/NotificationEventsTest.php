<?php

namespace Tests\Feature\Notifications;

use App\Enums\JobApplicationStatus;
use App\Enums\PermissionSlug;
use App\Models\CandidateResume;
use App\Models\Company;
use App\Models\Job;
use App\Models\JobApplication;
use App\Models\User;
use Illuminate\Contracts\Notifications\Dispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Laravel\Fortify\Features;
use Tests\Feature\Authorization\Concerns\InteractsWithAuthorizationCatalogue;
use Tests\TestCase;

class NotificationEventsTest extends TestCase
{
    use InteractsWithAuthorizationCatalogue, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAuthorizationCatalogue();
    }

    public function test_application_submission_notifies_the_candidate_and_employer(): void
    {
        $jobSeeker = User::factory()->jobSeeker()->create();
        $employer = User::factory()->approvedEmployer()->create();
        $company = Company::factory()->for($employer, 'owner')->create();
        $job = Job::factory()->for($company)->create(['status' => 'published']);
        $resume = CandidateResume::factory()->for($jobSeeker, 'user')->create();

        $otherCandidate = User::factory()->jobSeeker()->create();
        $otherEmployer = User::factory()->approvedEmployer()->create();
        Company::factory()->for($otherEmployer, 'owner')->create();

        $this->actingAs($jobSeeker)
            ->post(route('candidate.applications.store', $job), [
                'candidate_resume_id' => $resume->id,
            ])
            ->assertRedirect();

        $this->assertSame(1, $jobSeeker->fresh()->notifications()->count());
        $this->assertSame(
            'application.submitted',
            $jobSeeker->fresh()->notifications()->first()->data['event'] ?? null
        );

        $this->assertSame(1, $employer->fresh()->notifications()->count());
        $this->assertSame(
            'application.received',
            $employer->fresh()->notifications()->first()->data['event'] ?? null
        );

        $this->assertSame(0, $otherCandidate->fresh()->notifications()->count());
        $this->assertSame(0, $otherEmployer->fresh()->notifications()->count());
    }

    public function test_job_submission_notifies_platform_admins(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $unrelated = User::factory()->jobSeeker()->create();
        $employer = User::factory()->approvedEmployer()->create();
        $company = Company::factory()->for($employer, 'owner')->create();
        $job = Job::factory()->for($company)->create(['status' => 'draft']);

        $this->actingAs($employer)
            ->post(route('employer.jobs.submit', $job))
            ->assertRedirect();

        $this->assertSame(1, $admin->fresh()->notifications()->count());
        $this->assertSame(
            'job.submitted_for_review',
            $admin->fresh()->notifications()->first()->data['event'] ?? null
        );
        $this->assertSame(0, $unrelated->fresh()->notifications()->count());
    }

    public function test_job_submission_notifies_users_with_admin_access_and_jobs_moderate_permissions(): void
    {
        $moderatorRole = $this->createCustomRoleWithPermissions(
            'job-moderator',
            'Job Moderator',
            [PermissionSlug::AdminAccess, PermissionSlug::JobsModerate],
        );
        $moderator = User::factory()->withRole($moderatorRole)->create();

        $jobsModerateOnlyRole = $this->createCustomRoleWithPermissions(
            'jobs-only',
            'Jobs Only',
            [PermissionSlug::JobsModerate],
        );
        $jobsModerateOnly = User::factory()->withRole($jobsModerateOnlyRole)->create();

        $adminAccessOnlyRole = $this->createCustomRoleWithPermissions(
            'admin-only',
            'Admin Only',
            [PermissionSlug::AdminAccess],
        );
        $adminAccessOnly = User::factory()->withRole($adminAccessOnlyRole)->create();

        $employer = User::factory()->approvedEmployer()->create();
        $company = Company::factory()->for($employer, 'owner')->create();
        $job = Job::factory()->for($company)->create(['status' => 'draft']);

        $this->actingAs($employer)
            ->post(route('employer.jobs.submit', $job))
            ->assertRedirect();

        $this->assertSame(1, $moderator->fresh()->notifications()->count());
        $this->assertSame(
            'job.submitted_for_review',
            $moderator->fresh()->notifications()->first()->data['event'] ?? null
        );

        $this->assertSame(0, $jobsModerateOnly->fresh()->notifications()->count());
        $this->assertSame(0, $adminAccessOnly->fresh()->notifications()->count());
        $this->assertSame(0, $employer->fresh()->notifications()->count());
    }

    public function test_job_submission_succeeds_when_notification_delivery_fails(): void
    {
        Log::spy();

        $this->app->instance(Dispatcher::class, new class implements Dispatcher
        {
            public function send($notifiables, $notification)
            {
                throw new \RuntimeException('Notification delivery failed.');
            }

            public function sendNow($notifiables, $notification, ?array $channels = null)
            {
                throw new \RuntimeException('Notification delivery failed.');
            }
        });

        $admin = User::factory()->platformAdmin()->create();
        $employer = User::factory()->approvedEmployer()->create();
        $company = Company::factory()->for($employer, 'owner')->create();
        $job = Job::factory()->for($company)->create(['status' => 'draft']);

        $this->actingAs($employer)
            ->post(route('employer.jobs.submit', $job))
            ->assertRedirect(route('employer.jobs.index'));

        $this->assertSame('pending_review', $job->fresh()->status);
        $this->assertSame(0, $admin->fresh()->notifications()->count());

        Log::shouldHaveReceived('warning')
            ->withArgs(fn (string $message): bool => $message === 'Notification creation failed')
            ->once();
    }

    public function test_status_transition_notifies_the_candidate(): void
    {
        $employer = User::factory()->approvedEmployer()->create();
        $company = Company::factory()->for($employer, 'owner')->create();
        $job = Job::factory()->for($company)->create();
        $jobSeeker = User::factory()->jobSeeker()->create();
        $application = JobApplication::factory()->for($job, 'jobPosting')->for($jobSeeker, 'user')->create([
            'status' => JobApplicationStatus::Submitted,
        ]);

        $this->actingAs($employer)
            ->post(route('employer.applications.update-status', $application), [
                'current_status' => 'submitted',
                'new_status' => 'reviewing',
            ])
            ->assertRedirect();

        $this->assertSame(1, $jobSeeker->fresh()->notifications()->count());
        $this->assertSame(
            'application.status_changed',
            $jobSeeker->fresh()->notifications()->first()->data['event'] ?? null
        );
    }

    public function test_withdrawal_notifies_the_employer(): void
    {
        $jobSeeker = User::factory()->jobSeeker()->create();
        $employer = User::factory()->approvedEmployer()->create();
        $company = Company::factory()->for($employer, 'owner')->create();
        $job = Job::factory()->for($company)->create();
        $application = JobApplication::factory()->for($job, 'jobPosting')->for($jobSeeker, 'user')->create([
            'status' => JobApplicationStatus::Submitted,
        ]);

        $this->actingAs($jobSeeker)
            ->post(route('candidate.applications.withdraw', $application), [
                'reason' => 'Accepted another role.',
            ])
            ->assertRedirect();

        $this->assertSame(1, $employer->fresh()->notifications()->count());
        $this->assertSame(
            'application.withdrawn',
            $employer->fresh()->notifications()->first()->data['event'] ?? null
        );
    }

    public function test_job_approval_notifies_the_employer(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $employer = User::factory()->approvedEmployer()->create();
        $company = Company::factory()->create(['owner_user_id' => $employer->id, 'is_active' => true]);
        $job = Job::factory()->for($company)->create(['status' => 'pending_review']);

        $this->actingAs($admin)
            ->post(route('admin.jobs.approve', $job))
            ->assertRedirect();

        $this->assertSame(1, $employer->fresh()->notifications()->count());
        $notification = $employer->fresh()->notifications()->first();
        $this->assertSame('job.moderation_outcome', $notification->data['event'] ?? null);
    }

    public function test_job_rejection_notifies_the_employer(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $employer = User::factory()->approvedEmployer()->create();
        $company = Company::factory()->create(['owner_user_id' => $employer->id, 'is_active' => true]);
        $job = Job::factory()->for($company)->create(['status' => 'pending_review']);

        $this->actingAs($admin)
            ->post(route('admin.jobs.reject', $job), [
                'rejection_reason' => 'Violation of community standards.',
            ])
            ->assertRedirect();

        $this->assertSame(1, $employer->fresh()->notifications()->count());
        $notification = $employer->fresh()->notifications()->first();
        $this->assertSame('job.moderation_outcome', $notification->data['event'] ?? null);
    }

    public function test_registration_notifies_platform_admins(): void
    {
        $this->skipUnlessFortifyHas(Features::registration());

        $admin = User::factory()->platformAdmin()->create();

        $this->post(route('register.store'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertRedirect();

        $this->assertSame(1, $admin->fresh()->notifications()->count());
        $this->assertSame(
            'user.registered',
            $admin->fresh()->notifications()->first()->data['event'] ?? null
        );
    }

    public function test_employer_onboarding_submission_notifies_admins(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $applicant = User::factory()->jobSeeker()->create();

        $this->actingAs($applicant)
            ->post(route('employer.onboarding.store'), [
                'company_name' => 'Acme Corp',
                'job_title' => 'Founder',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, $admin->fresh()->notifications()->count());
        $this->assertSame(
            'employer.onboarding_submitted',
            $admin->fresh()->notifications()->first()->data['event'] ?? null
        );
    }
}
