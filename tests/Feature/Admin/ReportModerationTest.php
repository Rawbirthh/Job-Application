<?php

namespace Tests\Feature\Admin;

use App\Enums\PermissionSlug;
use App\Models\Company;
use App\Models\Job;
use App\Models\Report;
use App\Models\User;
use App\Notifications\ReportOutcomeNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Authorization\Concerns\InteractsWithAuthorizationCatalogue;
use Tests\TestCase;

class ReportModerationTest extends TestCase
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

    /**
     * Seed a report row explicitly — there is intentionally no Report factory.
     *
     * @param  array<string, mixed>  $extra  attributes applied after creation (terminal bookkeeping)
     */
    private function seedReport(
        User $reporter,
        Job|Company $subject,
        string $reason,
        string $status = 'pending',
        array $extra = [],
        int $minutesAgo = 0,
    ): Report {
        $report = Report::query()->create([
            'reporter_id' => $reporter->id,
            'job_posting_id' => $subject instanceof Job ? $subject->id : null,
            'company_id' => $subject instanceof Company ? $subject->id : null,
            'subject_key' => Report::subjectKeyFor(
                $subject instanceof Job ? $subject->id : null,
                $subject instanceof Company ? $subject->id : null,
            ),
            'reason' => $reason,
            'status' => $status,
        ]);

        // Stagger created_at so `latest()` ordering is deterministic.
        $report->created_at = now()->subMinutes($minutesAgo);

        if ($extra !== []) {
            $report->forceFill($extra);
        }

        $report->save();

        return $report;
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $job = $this->publishedJob();
        $report = $this->seedReport(User::factory()->create(), $job, 'spam');

        $this->get(route('admin.reports.index'))
            ->assertRedirect(route('login'));
        $this->get(route('admin.reports.show', $report))
            ->assertRedirect(route('login'));
        $this->post(route('admin.reports.investigate', $report))
            ->assertRedirect(route('login'));
        $this->post(route('admin.reports.resolve', $report))
            ->assertRedirect(route('login'));
        $this->post(route('admin.reports.dismiss', $report))
            ->assertRedirect(route('login'));

        $this->assertDatabaseHas('reports', [
            'id' => $report->id,
            'status' => 'pending',
        ]);
    }

    public function test_authenticated_non_admins_are_forbidden(): void
    {
        $job = $this->publishedJob();
        $report = $this->seedReport(User::factory()->jobSeeker()->create(), $job, 'spam');

        foreach ([User::factory()->jobSeeker()->create(), User::factory()->approvedEmployer()->create()] as $user) {
            $this->actingAs($user)
                ->get(route('admin.reports.index'))
                ->assertForbidden();
            $this->actingAs($user)
                ->get(route('admin.reports.show', $report))
                ->assertForbidden();
            $this->actingAs($user)
                ->post(route('admin.reports.investigate', $report))
                ->assertForbidden();
            $this->actingAs($user)
                ->post(route('admin.reports.resolve', $report))
                ->assertForbidden();
            $this->actingAs($user)
                ->post(route('admin.reports.dismiss', $report))
                ->assertForbidden();
        }

        $this->assertDatabaseHas('reports', [
            'id' => $report->id,
            'status' => 'pending',
        ]);
    }

    public function test_admin_without_reports_moderate_permission_is_forbidden(): void
    {
        $role = $this->createCustomRoleWithPermissions(
            'admin-access-only',
            'Admin Access Only',
            [PermissionSlug::AdminAccess],
        );
        $user = User::factory()->withRole($role)->create();

        $job = $this->publishedJob();
        $report = $this->seedReport(User::factory()->jobSeeker()->create(), $job, 'spam');

        $this->actingAs($user)
            ->get(route('admin.reports.index'))
            ->assertForbidden();
        $this->actingAs($user)
            ->get(route('admin.reports.show', $report))
            ->assertForbidden();
        $this->actingAs($user)
            ->post(route('admin.reports.investigate', $report))
            ->assertForbidden();
        $this->actingAs($user)
            ->post(route('admin.reports.resolve', $report))
            ->assertForbidden();
        $this->actingAs($user)
            ->post(route('admin.reports.dismiss', $report))
            ->assertForbidden();

        $this->assertDatabaseHas('reports', [
            'id' => $report->id,
            'status' => 'pending',
        ]);
    }

    public function test_queue_defaults_to_pending_and_exposes_the_expected_row_shape(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $reporter = User::factory()->jobSeeker()->create();
        $job = $this->publishedJob();
        $company = $job->company;

        $pending = $this->seedReport($reporter, $job, 'spam', 'pending', minutesAgo: 10);
        $this->seedReport(
            User::factory()->jobSeeker()->create(),
            $job,
            'other',
            'investigating',
            minutesAgo: 9,
        );
        $this->seedReport(
            User::factory()->jobSeeker()->create(),
            $company,
            'spam',
            'resolved',
            ['resolved_by_id' => $admin->id, 'resolved_at' => now()->subMinutes(5), 'open_marker' => null],
            minutesAgo: 8,
        );
        $this->seedReport(
            User::factory()->jobSeeker()->create(),
            $company,
            'offensive_content',
            'dismissed',
            ['resolved_by_id' => $admin->id, 'resolved_at' => now()->subMinutes(4), 'open_marker' => null],
            minutesAgo: 7,
        );

        $this->actingAs($admin)
            ->get(route('admin.reports.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/reports/index')
                ->where('filters.status', 'pending')
                ->where('filters.subject', 'all')
                ->where('filters.reason', 'all')
                ->where('reports.total', 1)
                ->where('reports.per_page', 15)
                ->where('reports.data.0.id', $pending->id)
                ->where('reports.data.0.subject_type', 'job')
                ->where('reports.data.0.subject_title', $job->title)
                ->where('reports.data.0.subject_status', 'published')
                ->where('reports.data.0.reason', 'spam')
                ->where('reports.data.0.status', 'pending')
                ->where('reports.data.0.reporter.id', $reporter->id)
                ->where('reports.data.0.reporter.name', $reporter->name));
    }

    public function test_status_filter_all_shows_every_report(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $job = $this->publishedJob();
        $company = $job->company;

        $dismissed = $this->seedReport(
            User::factory()->jobSeeker()->create(),
            $company,
            'offensive_content',
            'dismissed',
            ['resolved_by_id' => $admin->id, 'resolved_at' => now(), 'open_marker' => null],
            minutesAgo: 12,
        );
        $resolved = $this->seedReport(
            User::factory()->jobSeeker()->create(),
            $company,
            'spam',
            'resolved',
            ['resolved_by_id' => $admin->id, 'resolved_at' => now(), 'open_marker' => null],
            minutesAgo: 11,
        );
        $investigating = $this->seedReport(
            User::factory()->jobSeeker()->create(),
            $job,
            'other',
            'investigating',
            minutesAgo: 10,
        );
        $pending = $this->seedReport(
            User::factory()->jobSeeker()->create(),
            $job,
            'spam',
            'pending',
            minutesAgo: 9,
        );

        $this->actingAs($admin)
            ->get(route('admin.reports.index', ['status' => 'all']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.status', 'all')
                ->where('reports.total', 4)
                ->where('reports.data.0.id', $pending->id)
                ->where('reports.data.0.status', 'pending')
                ->where('reports.data.1.id', $investigating->id)
                ->where('reports.data.1.status', 'investigating')
                ->where('reports.data.2.id', $resolved->id)
                ->where('reports.data.2.status', 'resolved')
                ->where('reports.data.3.id', $dismissed->id)
                ->where('reports.data.3.status', 'dismissed'));
    }

    public function test_subject_and_reason_filters_apply_and_unknown_values_fall_back(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $job = $this->publishedJob();
        $company = $job->company;

        $jobReport = $this->seedReport(
            User::factory()->jobSeeker()->create(),
            $job,
            'spam',
            minutesAgo: 10,
        );
        $companyReport = $this->seedReport(
            User::factory()->jobSeeker()->create(),
            $company,
            'offensive_content',
            minutesAgo: 9,
        );

        // Subject filter: only company-subject rows.
        $this->actingAs($admin)
            ->get(route('admin.reports.index', ['subject' => 'company']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.subject', 'company')
                ->where('reports.total', 1)
                ->where('reports.data.0.id', $companyReport->id)
                ->where('reports.data.0.subject_type', 'company')
                ->where('reports.data.0.subject_title', $company->name)
                ->where('reports.data.0.subject_status', 'active'));

        // Reason filter: only spam rows.
        $this->actingAs($admin)
            ->get(route('admin.reports.index', ['reason' => 'spam']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.reason', 'spam')
                ->where('reports.total', 1)
                ->where('reports.data.0.id', $jobReport->id)
                ->where('reports.data.0.reason', 'spam'));

        // Unrecognised filter values fall back to the queue defaults.
        $this->actingAs($admin)
            ->get(route('admin.reports.index', ['status' => 'nonsense', 'subject' => 'vehicle']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.status', 'pending')
                ->where('filters.subject', 'all')
                ->where('filters.reason', 'all')
                ->where('reports.total', 2));
    }

    public function test_queue_is_paginated_and_preserves_the_query_string(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $job = $this->publishedJob();
        $reporters = User::factory()->count(16)->jobSeeker()->create();

        $reports = $reporters->map(
            fn (User $reporter, int $index): Report => $this->seedReport(
                $reporter,
                $job,
                'spam',
                'pending',
                minutesAgo: $index,
            ),
        );

        // First page: 15 of 16 rows, newest first.
        $this->actingAs($admin)
            ->get(route('admin.reports.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('reports.total', 16)
                ->where('reports.per_page', 15)
                ->where('reports.current_page', 1)
                ->where('reports.from', 1)
                ->where('reports.to', 15)
                ->where('reports.data.0.id', $reports[0]->id)
                ->where('reports.data.14.id', $reports[14]->id)
                ->where('reports.next_page_url', fn (string $url): bool => str_contains($url, 'page=2')));

        // Second page with filters: the query string survives pagination.
        $this->actingAs($admin)
            ->get(route('admin.reports.index', ['page' => 2, 'status' => 'all', 'reason' => 'spam']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('reports.current_page', 2)
                ->where('reports.total', 16)
                ->where('reports.data.0.id', $reports[15]->id)
                ->where('reports.prev_page_url', fn (string $url): bool => str_contains($url, 'page=1')
                    && str_contains($url, 'status=all')
                    && str_contains($url, 'reason=spam')));
    }

    public function test_moderator_can_view_the_report_detail(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $reporter = User::factory()->jobSeeker()->create();
        $job = $this->publishedJob();

        $report = Report::query()->create([
            'reporter_id' => $reporter->id,
            'job_posting_id' => $job->id,
            'company_id' => null,
            'subject_key' => "job:{$job->id}",
            'reason' => 'scam_or_fraud',
            'explanation' => 'They asked for an upfront training fee.',
            'status' => 'pending',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.reports.show', $report))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/reports/show')
                ->has('report')
                ->where('report.id', $report->id)
                ->where('report.status', 'pending')
                ->where('report.reason', 'scam_or_fraud')
                ->where('report.explanation', 'They asked for an upfront training fee.')
                ->whereNull('report.resolution_action')
                ->whereNull('report.resolution_message')
                ->whereNull('report.internal_note')
                ->whereNull('report.resolved_at')
                ->where('report.reporter.id', $reporter->id)
                ->where('report.reporter.name', $reporter->name)
                ->where('report.reporter.email', $reporter->email)
                ->where('report.subject.type', 'job')
                ->where('report.subject.id', $job->id)
                ->where('report.subject.title', $job->title)
                ->where('report.subject.status', 'published')
                ->where('report.subject.company.id', $job->company_id)
                ->where('report.subject.company.name', $job->company->name)
                ->where('report.subject.company.owner.id', $job->company->owner->id));
    }

    public function test_pending_report_can_be_moved_to_investigating(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $report = $this->seedReport(
            User::factory()->jobSeeker()->create(),
            $this->publishedJob(),
            'spam',
        );

        $response = $this->actingAs($admin)
            ->from(route('admin.reports.show', $report))
            ->post(route('admin.reports.investigate', $report));

        $response->assertRedirect(route('admin.reports.show', $report));
        $response->assertInertiaFlash('toast', [
            'type' => 'success',
            'message' => 'Report marked as under investigation.',
        ]);

        $this->assertDatabaseHas('reports', [
            'id' => $report->id,
            'status' => 'investigating',
            'open_marker' => 'open',
        ]);
    }

    public function test_investigating_a_closed_report_is_rejected_without_state_change(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $report = $this->seedReport(
            User::factory()->jobSeeker()->create(),
            $this->publishedJob(),
            'spam',
            'resolved',
            ['resolved_by_id' => $admin->id, 'resolved_at' => now()->subDay(), 'open_marker' => null],
        );

        $response = $this->actingAs($admin)
            ->from(route('admin.reports.index'))
            ->post(route('admin.reports.investigate', $report));

        $response->assertRedirect(route('admin.reports.index'));
        $response->assertSessionHas('error', 'Only pending reports can be investigated.');

        $this->assertDatabaseHas('reports', [
            'id' => $report->id,
            'status' => 'resolved',
            'open_marker' => null,
        ]);
    }

    public function test_investigating_an_already_investigating_report_is_rejected(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $report = $this->seedReport(
            User::factory()->jobSeeker()->create(),
            $this->publishedJob(),
            'spam',
            'investigating',
        );

        $response = $this->actingAs($admin)
            ->from(route('admin.reports.index'))
            ->post(route('admin.reports.investigate', $report));

        $response->assertRedirect(route('admin.reports.index'));
        $response->assertSessionHas('error', 'This report is already under investigation.');

        $this->assertDatabaseHas('reports', [
            'id' => $report->id,
            'status' => 'investigating',
            'open_marker' => 'open',
        ]);
    }

    public function test_resolve_from_pending_closes_the_report_and_notifies_the_reporter(): void
    {
        Notification::fake();

        $admin = User::factory()->platformAdmin()->create();
        $reporter = User::factory()->jobSeeker()->create();
        $other = User::factory()->jobSeeker()->create();
        $report = $this->seedReport($reporter, $this->publishedJob(), 'scam_or_fraud');

        $response = $this->actingAs($admin)
            ->from(route('admin.reports.show', $report))
            ->post(route('admin.reports.resolve', $report), [
                'resolution_action' => 'content_removed',
                'resolution_message' => 'The listing was removed.',
                'internal_note' => 'Confirmed payment scam.',
            ]);

        $response->assertRedirect(route('admin.reports.index'));
        $response->assertInertiaFlash('toast', [
            'type' => 'success',
            'message' => 'Report resolved.',
        ]);

        $this->assertDatabaseHas('reports', [
            'id' => $report->id,
            'status' => 'resolved',
            'resolution_action' => 'content_removed',
            'resolution_message' => 'The listing was removed.',
            'internal_note' => 'Confirmed payment scam.',
            'resolved_by_id' => $admin->id,
            'open_marker' => null,
        ]);
        $this->assertNotNull($report->fresh()->resolved_at);

        // The reporter (and only the reporter) is notified with the outcome.
        Notification::assertSentTo($reporter, ReportOutcomeNotification::class, function (
            ReportOutcomeNotification $notification,
        ) use ($reporter): bool {
            $data = $notification->toDatabase($reporter);

            return $data['event'] === 'report.outcome'
                && $data['title'] === 'Your report was resolved'
                && $notification->resolutionMessage === 'The listing was removed.'
                && str_contains((string) $data['message'], 'Message from the moderation team: The listing was removed.');
        });
        Notification::assertNotSentTo($admin, ReportOutcomeNotification::class);
        Notification::assertNotSentTo($other, ReportOutcomeNotification::class);
    }

    public function test_resolve_from_investigating_is_allowed(): void
    {
        Notification::fake();

        $admin = User::factory()->platformAdmin()->create();
        $reporter = User::factory()->jobSeeker()->create();
        $report = $this->seedReport(
            $reporter,
            $this->publishedJob(),
            'spam',
            'investigating',
        );

        $this->actingAs($admin)
            ->post(route('admin.reports.resolve', $report), [
                'resolution_action' => 'no_action',
            ])
            ->assertRedirect(route('admin.reports.index'));

        $this->assertDatabaseHas('reports', [
            'id' => $report->id,
            'status' => 'resolved',
            'resolution_action' => 'no_action',
            'resolution_message' => null,
            'internal_note' => null,
            'resolved_by_id' => $admin->id,
            'open_marker' => null,
        ]);
        $this->assertNotNull($report->fresh()->resolved_at);

        Notification::assertSentToTimes($reporter, ReportOutcomeNotification::class, 1);
    }

    public function test_dismiss_closes_the_report_with_an_internal_note_and_notifies_the_reporter(): void
    {
        Notification::fake();

        $admin = User::factory()->platformAdmin()->create();
        $reporter = User::factory()->jobSeeker()->create();
        $other = User::factory()->jobSeeker()->create();
        $report = $this->seedReport($reporter, $this->publishedJob(), 'spam');

        $response = $this->actingAs($admin)
            ->from(route('admin.reports.show', $report))
            ->post(route('admin.reports.dismiss', $report), [
                'internal_note' => 'No evidence of wrongdoing.',
            ]);

        $response->assertRedirect(route('admin.reports.index'));
        $response->assertInertiaFlash('toast', [
            'type' => 'success',
            'message' => 'Report dismissed.',
        ]);

        $this->assertDatabaseHas('reports', [
            'id' => $report->id,
            'status' => 'dismissed',
            'resolution_action' => null,
            'resolution_message' => null,
            'internal_note' => 'No evidence of wrongdoing.',
            'resolved_by_id' => $admin->id,
            'open_marker' => null,
        ]);
        $this->assertNotNull($report->fresh()->resolved_at);

        Notification::assertSentTo($reporter, ReportOutcomeNotification::class, function (
            ReportOutcomeNotification $notification,
        ) use ($reporter): bool {
            $data = $notification->toDatabase($reporter);

            return $data['event'] === 'report.outcome'
                && $data['title'] === 'Your report was dismissed'
                && $notification->resolutionMessage === null
                && ! str_contains((string) $data['message'], 'Message from the moderation team');
        });
        Notification::assertNotSentTo($admin, ReportOutcomeNotification::class);
        Notification::assertNotSentTo($other, ReportOutcomeNotification::class);
    }

    public function test_dismissing_an_already_closed_report_is_rejected(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $report = $this->seedReport(
            User::factory()->jobSeeker()->create(),
            $this->publishedJob(),
            'spam',
            'resolved',
            ['resolved_by_id' => $admin->id, 'resolved_at' => now()->subDay(), 'open_marker' => null],
        );

        $response = $this->actingAs($admin)
            ->from(route('admin.reports.index'))
            ->post(route('admin.reports.dismiss', $report), [
                'internal_note' => 'Attempted re-close.',
            ]);

        $response->assertRedirect(route('admin.reports.index'));
        $response->assertSessionHas('error', 'Only pending or investigating reports can be dismissed.');

        $this->assertDatabaseHas('reports', [
            'id' => $report->id,
            'status' => 'resolved',
            'internal_note' => null,
        ]);
    }
}
