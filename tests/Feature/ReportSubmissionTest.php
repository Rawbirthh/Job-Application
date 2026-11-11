<?php

namespace Tests\Feature;

use App\Enums\PermissionSlug;
use App\Models\Company;
use App\Models\Job;
use App\Models\Report;
use App\Models\User;
use App\Notifications\ReportSubmittedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\Authorization\Concerns\InteractsWithAuthorizationCatalogue;
use Tests\TestCase;

class ReportSubmissionTest extends TestCase
{
    use InteractsWithAuthorizationCatalogue, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAuthorizationCatalogue();
    }

    /**
     * A publicly visible job: published, under an active company with an approved owner.
     */
    private function publishedJob(): Job
    {
        $employer = User::factory()->approvedEmployer()->create();
        $company = Company::factory()->for($employer, 'owner')->create();

        return Job::factory()->for($company)->create(['status' => 'published']);
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->post(route('reports.store'))
            ->assertRedirect(route('login'));

        $this->assertDatabaseCount('reports', 0);
    }

    public function test_job_seeker_can_report_a_published_job(): void
    {
        $jobSeeker = User::factory()->jobSeeker()->create();
        $job = $this->publishedJob();

        $response = $this->actingAs($jobSeeker)
            ->from(route('jobs.show', $job))
            ->post(route('reports.store'), [
                'subject' => 'job',
                'job_posting_id' => $job->id,
                'reason' => 'scam_or_fraud',
                'explanation' => '  They asked for an upfront training fee.  ',
            ]);

        $response->assertRedirect(route('jobs.show', $job));
        $response->assertInertiaFlash('toast', [
            'type' => 'success',
            'message' => 'Report submitted for review.',
        ]);

        $this->assertDatabaseCount('reports', 1);
        $this->assertDatabaseHas('reports', [
            'reporter_id' => $jobSeeker->id,
            'job_posting_id' => $job->id,
            'company_id' => null,
            'reason' => 'scam_or_fraud',
            'explanation' => 'They asked for an upfront training fee.',
            'status' => 'pending',
            'subject_key' => "job:{$job->id}",
            'open_marker' => 'open',
        ]);
    }

    public function test_forged_reporter_id_in_payload_is_ignored(): void
    {
        $actor = User::factory()->jobSeeker()->create();
        $other = User::factory()->jobSeeker()->create();
        $job = $this->publishedJob();

        $this->actingAs($actor)
            ->post(route('reports.store'), [
                'subject' => 'job',
                'job_posting_id' => $job->id,
                'reason' => 'other',
                'reporter_id' => $other->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseCount('reports', 1);
        $this->assertDatabaseHas('reports', [
            'reporter_id' => $actor->id,
        ]);
        $this->assertDatabaseMissing('reports', [
            'reporter_id' => $other->id,
        ]);
    }

    public function test_employer_user_may_also_report_a_job(): void
    {
        $reporter = User::factory()->approvedEmployer()->create();
        $subjectEmployer = User::factory()->approvedEmployer()->create();
        $company = Company::factory()->for($subjectEmployer, 'owner')->create();
        $job = Job::factory()->for($company)->create(['status' => 'published']);

        $this->actingAs($reporter)
            ->post(route('reports.store'), [
                'subject' => 'job',
                'job_posting_id' => $job->id,
                'reason' => 'spam',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('reports', [
            'reporter_id' => $reporter->id,
            'job_posting_id' => $job->id,
            'reason' => 'spam',
            'status' => 'pending',
        ]);
    }

    public function test_company_subject_can_be_reported(): void
    {
        $jobSeeker = User::factory()->jobSeeker()->create();
        $employer = User::factory()->approvedEmployer()->create();
        $company = Company::factory()->for($employer, 'owner')->create();

        $this->actingAs($jobSeeker)
            ->post(route('reports.store'), [
                'subject' => 'company',
                'company_id' => $company->id,
                'reason' => 'misleading_listing',
                'explanation' => 'They post fake listings to harvest CVs.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('reports', [
            'reporter_id' => $jobSeeker->id,
            'job_posting_id' => null,
            'company_id' => $company->id,
            'reason' => 'misleading_listing',
            'explanation' => 'They post fake listings to harvest CVs.',
            'status' => 'pending',
            'subject_key' => "company:{$company->id}",
            'open_marker' => 'open',
        ]);
    }

    public function test_validation_rejects_invalid_payloads_without_creating_a_row(): void
    {
        $jobSeeker = User::factory()->jobSeeker()->create();
        $job = $this->publishedJob();

        // Missing subject.
        $this->actingAs($jobSeeker)
            ->post(route('reports.store'), [
                'job_posting_id' => $job->id,
                'reason' => 'spam',
            ])
            ->assertSessionHasErrors('subject');

        // Unknown reason (controlled catalogue).
        $this->actingAs($jobSeeker)
            ->post(route('reports.store'), [
                'subject' => 'job',
                'job_posting_id' => $job->id,
                'reason' => 'looks_shady',
            ])
            ->assertSessionHasErrors('reason');

        // Explanation over the 2000 character bound.
        $this->actingAs($jobSeeker)
            ->post(route('reports.store'), [
                'subject' => 'job',
                'job_posting_id' => $job->id,
                'reason' => 'spam',
                'explanation' => str_repeat('a', 2001),
            ])
            ->assertSessionHasErrors('explanation');

        // Company subject without a company id.
        $this->actingAs($jobSeeker)
            ->post(route('reports.store'), [
                'subject' => 'company',
                'reason' => 'spam',
            ])
            ->assertSessionHasErrors('company_id');

        // Job subject without a job id.
        $this->actingAs($jobSeeker)
            ->post(route('reports.store'), [
                'subject' => 'job',
                'reason' => 'spam',
            ])
            ->assertSessionHasErrors('job_posting_id');

        $this->assertDatabaseCount('reports', 0);
    }

    public function test_non_public_subjects_are_rejected_as_validation_errors(): void
    {
        $jobSeeker = User::factory()->jobSeeker()->create();

        $employer = User::factory()->approvedEmployer()->create();
        $company = Company::factory()->for($employer, 'owner')->create();
        $draftJob = Job::factory()->for($company)->create(['status' => 'draft']);
        $closedJob = Job::factory()->for($company)->create(['status' => 'closed']);

        $inactiveOwner = User::factory()->approvedEmployer()->create();
        $inactiveCompany = Company::factory()->for($inactiveOwner, 'owner')->create([
            'is_active' => false,
        ]);

        $suspendedOwner = User::factory()->suspendedEmployer()->create();
        $suspendedCompany = Company::factory()->for($suspendedOwner, 'owner')->create([
            'is_active' => true,
        ]);
        $suspendedOwnerJob = Job::factory()->for($suspendedCompany)->create([
            'status' => 'published',
        ]);

        // Draft job is not publicly visible (IDOR guard).
        $this->actingAs($jobSeeker)
            ->post(route('reports.store'), [
                'subject' => 'job',
                'job_posting_id' => $draftJob->id,
                'reason' => 'spam',
            ])
            ->assertSessionHasErrors(['job_posting_id' => 'Only publicly visible jobs can be reported.']);

        // Closed job is not publicly visible.
        $this->actingAs($jobSeeker)
            ->post(route('reports.store'), [
                'subject' => 'job',
                'job_posting_id' => $closedJob->id,
                'reason' => 'spam',
            ])
            ->assertSessionHasErrors(['job_posting_id' => 'Only publicly visible jobs can be reported.']);

        // Published job of a suspended owner is not publicly visible.
        $this->actingAs($jobSeeker)
            ->post(route('reports.store'), [
                'subject' => 'job',
                'job_posting_id' => $suspendedOwnerJob->id,
                'reason' => 'spam',
            ])
            ->assertSessionHasErrors(['job_posting_id' => 'Only publicly visible jobs can be reported.']);

        // Inactive company subject is rejected.
        $this->actingAs($jobSeeker)
            ->post(route('reports.store'), [
                'subject' => 'company',
                'company_id' => $inactiveCompany->id,
                'reason' => 'spam',
            ])
            ->assertSessionHasErrors(['company_id' => 'Only active employers can be reported.']);

        $this->assertDatabaseCount('reports', 0);
    }

    public function test_duplicate_open_report_is_rejected_until_the_first_is_resolved(): void
    {
        $jobSeeker = User::factory()->jobSeeker()->create();
        $job = $this->publishedJob();

        // First submission is accepted.
        $this->actingAs($jobSeeker)
            ->post(route('reports.store'), [
                'subject' => 'job',
                'job_posting_id' => $job->id,
                'reason' => 'spam',
            ])
            ->assertRedirect();
        $this->assertDatabaseCount('reports', 1);

        // Second submission for the same subject while the first is open.
        $this->actingAs($jobSeeker)
            ->post(route('reports.store'), [
                'subject' => 'job',
                'job_posting_id' => $job->id,
                'reason' => 'other',
            ])
            ->assertSessionHasErrors(['report' => 'You already have an open report for this subject.']);
        $this->assertDatabaseCount('reports', 1);

        // A different open subject is still allowed while under the cap.
        $otherJob = $this->publishedJob();
        $this->actingAs($jobSeeker)
            ->post(route('reports.store'), [
                'subject' => 'job',
                'job_posting_id' => $otherJob->id,
                'reason' => 'other',
            ])
            ->assertRedirect();
        $this->assertDatabaseCount('reports', 2);

        // Close the first report: status resolved, open_marker cleared.
        $first = Report::query()->firstOrFail();
        $first->status = 'resolved';
        $first->open_marker = null;
        $first->save();

        // The same reporter can now re-report the same subject.
        $this->actingAs($jobSeeker)
            ->post(route('reports.store'), [
                'subject' => 'job',
                'job_posting_id' => $job->id,
                'reason' => 'spam',
            ])
            ->assertRedirect();

        $this->assertDatabaseCount('reports', 3);
        $this->assertDatabaseHas('reports', [
            'reporter_id' => $jobSeeker->id,
            'job_posting_id' => $job->id,
            'subject_key' => "job:{$job->id}",
            'status' => 'pending',
            'open_marker' => 'open',
        ]);
    }

    public function test_per_reporter_open_report_cap_is_enforced(): void
    {
        $jobSeeker = User::factory()->jobSeeker()->create();
        $company = Company::factory()->for(User::factory()->approvedEmployer()->create(), 'owner')->create();
        $jobs = Job::factory()->count(11)->for($company)->create(['status' => 'published']);

        // Seed 10 open reports on 10 different subjects (no factory by design).
        foreach (range(0, 9) as $i) {
            Report::query()->create([
                'reporter_id' => $jobSeeker->id,
                'job_posting_id' => $jobs[$i]->id,
                'company_id' => null,
                'subject_key' => "job:{$jobs[$i]->id}",
                'reason' => 'spam',
                'status' => 'pending',
            ]);
        }

        // The 11th valid submission hits the cap.
        $this->actingAs($jobSeeker)
            ->post(route('reports.store'), [
                'subject' => 'job',
                'job_posting_id' => $jobs[10]->id,
                'reason' => 'other',
            ])
            ->assertSessionHasErrors(['reports' => 'You can have up to 10 open reports at a time. Wait for one to be reviewed before reporting again.']);

        $this->assertDatabaseCount('reports', 10);
    }

    public function test_submissions_are_rate_limited(): void
    {
        $jobSeeker = User::factory()->jobSeeker()->create();
        $company = Company::factory()->for(User::factory()->approvedEmployer()->create(), 'owner')->create();
        $jobs = Job::factory()->count(11)->for($company)->create(['status' => 'published']);

        // Ten valid submissions (distinct subjects) exhaust throttle:10,1.
        foreach (range(0, 9) as $i) {
            $this->actingAs($jobSeeker)
                ->post(route('reports.store'), [
                    'subject' => 'job',
                    'job_posting_id' => $jobs[$i]->id,
                    'reason' => 'spam',
                ])
                ->assertRedirect();
        }

        $this->assertDatabaseCount('reports', 10);

        // The next request is throttled before anything else runs.
        $this->actingAs($jobSeeker)
            ->post(route('reports.store'), [
                'subject' => 'job',
                'job_posting_id' => $jobs[10]->id,
                'reason' => 'other',
            ])
            ->assertTooManyRequests();

        $this->assertDatabaseCount('reports', 10);
    }

    public function test_resolving_a_report_does_not_remove_the_reported_content(): void
    {
        $jobSeeker = User::factory()->jobSeeker()->create();
        $job = $this->publishedJob();

        $this->actingAs($jobSeeker)
            ->post(route('reports.store'), [
                'subject' => 'job',
                'job_posting_id' => $job->id,
                'reason' => 'scam_or_fraud',
            ])
            ->assertRedirect();

        $report = Report::query()->firstOrFail();
        $admin = User::factory()->platformAdmin()->create();

        $this->actingAs($admin)
            ->post(route('admin.reports.resolve', $report), [
                'resolution_action' => 'content_removed',
            ])
            ->assertRedirect(route('admin.reports.index'));

        // The report is closed, but the target is untouched: no auto-removal.
        $this->assertSame('published', $job->fresh()->status);
        $this->assertDatabaseHas('companies', [
            'id' => $job->company_id,
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('reports', [
            'id' => $report->id,
            'status' => 'resolved',
            'resolution_action' => 'content_removed',
            'open_marker' => null,
        ]);
        $this->assertNotNull($report->fresh()->resolved_at);
    }

    public function test_submission_notifies_admins_holding_both_moderation_permissions(): void
    {
        Notification::fake();

        $jobSeeker = User::factory()->jobSeeker()->create();
        $job = $this->publishedJob();

        $platformAdmin = User::factory()->platformAdmin()->create();

        // Holds admin.access only: must NOT be notified.
        $adminAccessOnly = User::factory()->withRole(
            $this->createCustomRoleWithPermissions(
                'admin-access-only',
                'Admin Access Only',
                [PermissionSlug::AdminAccess],
            ),
        )->create();

        // Holds reports.moderate only: must NOT be notified.
        $moderateOnly = User::factory()->withRole(
            $this->createCustomRoleWithPermissions(
                'moderate-only',
                'Moderate Only',
                [PermissionSlug::ReportsModerate],
            ),
        )->create();

        $observer = User::factory()->jobSeeker()->create();

        $this->actingAs($jobSeeker)
            ->post(route('reports.store'), [
                'subject' => 'job',
                'job_posting_id' => $job->id,
                'reason' => 'scam_or_fraud',
            ])
            ->assertRedirect();

        Notification::assertSentTo($platformAdmin, ReportSubmittedNotification::class);
        Notification::assertNotSentTo($adminAccessOnly, ReportSubmittedNotification::class);
        Notification::assertNotSentTo($moderateOnly, ReportSubmittedNotification::class);
        Notification::assertNotSentTo($jobSeeker, ReportSubmittedNotification::class);
        Notification::assertNotSentTo($observer, ReportSubmittedNotification::class);
    }
}
