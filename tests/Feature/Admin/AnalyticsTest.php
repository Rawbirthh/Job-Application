<?php

namespace Tests\Feature\Admin;

use App\Enums\AuditAction;
use App\Enums\AuditTargetType;
use App\Enums\PermissionSlug;
use App\Models\AuditLog;
use App\Models\CandidateResume;
use App\Models\Company;
use App\Models\Job;
use App\Models\JobApplication;
use App\Models\JobCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Authorization\Concerns\InteractsWithAuthorizationCatalogue;
use Tests\TestCase;

class AnalyticsTest extends TestCase
{
    use InteractsWithAuthorizationCatalogue, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAuthorizationCatalogue();

        // The Inertia root view resolves the page component through the Vite
        // manifest. Disabling Vite keeps this backend suite independent of the
        // production asset build that the frontend owns.
        $this->withoutVite();
    }

    // ---------------------------------------------------------------- helpers

    /**
     * Create a company owned by the given employer.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function company(User $owner, array $attributes = []): Company
    {
        return Company::factory()->create([
            'owner_user_id' => $owner->id,
            'is_active' => true,
            ...$attributes,
        ]);
    }

    /**
     * Create a job posting for a company.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function job(Company $company, array $attributes = []): Job
    {
        return Job::factory()->create([
            'company_id' => $company->id,
            'created_at' => now()->subDays(2)->toDateTimeString(),
            ...$attributes,
        ]);
    }

    /**
     * Create a job application together with its own candidate.
     *
     * The candidate account is created outside the default analytics window so
     * that the platform account totals stay deterministic.
     *
     * @param  array<string, mixed>  $candidateAttributes
     */
    private function application(
        Job $job,
        string $status,
        string $createdAt,
        array $candidateAttributes = [],
    ): JobApplication {
        $candidate = User::factory()->jobSeeker()->create([
            'created_at' => now()->subDays(60)->toDateTimeString(),
            ...$candidateAttributes,
        ]);

        return JobApplication::factory()->create([
            'job_posting_id' => $job->id,
            'user_id' => $candidate->id,
            'candidate_resume_id' => CandidateResume::factory()->for($candidate, 'user')->create()->id,
            'status' => $status,
            'cover_letter' => 'A cover letter written by '.$candidate->name.'.',
            'created_at' => $createdAt,
        ]);
    }

    /**
     * Forget the cached controller instance of a route.
     *
     * Laravel caches the resolved controller on the route object, so two
     * requests inside the same test would share one controller (and one
     * analytics service) instance. Flushing it keeps every measurement an
     * independent page read.
     */
    private function forgetRouteController(string $routeName): void
    {
        Route::getRoutes()->getByName($routeName)?->flushController();
    }

    /**
     * Count the queries a single platform analytics page read costs.
     */
    private function countQueriesForAnalytics(User $administrator): int
    {
        $actor = User::query()->findOrFail($administrator->id);

        $this->forgetRouteController('admin.analytics.index');

        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->actingAs($actor)
            ->get(route('admin.analytics.index'))
            ->assertOk();

        $queryCount = count(DB::getQueryLog());

        DB::disableQueryLog();

        return $queryCount;
    }

    /**
     * Flatten the analytics props into their scalar values.
     *
     * @param  array<string, mixed>  $props
     * @return array<int, mixed>
     */
    private function analyticsScalars(array $props): array
    {
        $analyticsProps = array_intersect_key($props, array_flip([
            'window',
            'summary',
            'jobsByStatus',
            'applicationsByStatus',
            'employersByStatus',
            'topCategories',
            'trend',
            'definitions',
        ]));

        $scalars = [];

        array_walk_recursive($analyticsProps, function ($value) use (&$scalars): void {
            $scalars[] = $value;
        });

        return $scalars;
    }

    /**
     * Build the known platform fixture.
     *
     * @return array<string, mixed>
     */
    private function platformFixture(): array
    {
        $administrator = User::factory()->platformAdmin()->create();
        $employerOne = User::factory()->approvedEmployer()->create();
        $employerTwo = User::factory()->approvedEmployer()->create();

        // An employer and a company that live outside of the default window.
        $staleEmployer = User::factory()->approvedEmployer()->create([
            'created_at' => now()->subDays(60)->toDateTimeString(),
        ]);
        $this->company($staleEmployer, [
            'created_at' => now()->subDays(60)->toDateTimeString(),
        ]);

        $companyOne = $this->company($employerOne);
        $companyTwo = $this->company($employerTwo);

        $categoryAlpha = JobCategory::factory()->create(['name' => 'Alpha Category']);
        $categoryBeta = JobCategory::factory()->create(['name' => 'Beta Category']);

        $alphaJob = $this->job($companyOne, [
            'category_id' => $categoryAlpha->id,
            'status' => 'published',
            'title' => 'Alpha Engineer',
        ]);
        $betaJob = $this->job($companyOne, [
            'category_id' => $categoryBeta->id,
            'status' => 'draft',
            'title' => 'Beta Engineer',
        ]);
        $alphaJobTwo = $this->job($companyTwo, [
            'category_id' => $categoryAlpha->id,
            'status' => 'published',
            'title' => 'Alpha Engineer Two',
        ]);
        $this->job($companyOne, [
            'category_id' => $categoryAlpha->id,
            'status' => 'published',
            'title' => 'Stale Engineer',
            'created_at' => now()->subDays(60)->toDateTimeString(),
        ]);

        foreach (['submitted', 'shortlisted', 'hired', 'rejected'] as $status) {
            $this->application($alphaJob, $status, now()->subDay()->toDateTimeString());
        }

        foreach (['submitted', 'withdrawn'] as $status) {
            $this->application($alphaJobTwo, $status, now()->subDay()->toDateTimeString());
        }

        return [
            'administrator' => $administrator,
            'companyOne' => $companyOne,
            'companyTwo' => $companyTwo,
            'categoryAlpha' => $categoryAlpha,
            'categoryBeta' => $categoryBeta,
            'alphaJob' => $alphaJob,
            'betaJob' => $betaJob,
            'alphaJobTwo' => $alphaJobTwo,
        ];
    }

    // ------------------------------------------------------------------ access

    public function test_guests_are_redirected_to_login_when_accessing_platform_analytics(): void
    {
        $this->get(route('admin.analytics.index'))->assertRedirect(route('login'));
    }

    public function test_authenticated_users_without_admin_access_cannot_access_platform_analytics(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('admin.analytics.index'))->assertForbidden();
    }

    public function test_admin_access_without_platform_analytics_permission_cannot_access_platform_analytics(): void
    {
        $role = $this->createCustomRoleWithPermissions(
            'analytics-reader',
            'Analytics Reader',
            [PermissionSlug::AdminAccess],
        );

        $actor = User::factory()->withRole($role)->create();

        $this->actingAs($actor)->get(route('admin.analytics.index'))->assertForbidden();
    }

    public function test_a_platform_administrator_can_view_the_platform_analytics(): void
    {
        $administrator = User::factory()->platformAdmin()->create();

        $this->actingAs($administrator)
            ->get(route('admin.analytics.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/analytics')
                ->has('window')
                ->where('window.from', now()->subDays(29)->toDateString())
                ->where('window.to', now()->toDateString())
                ->where('window.preset', '30d')
                ->has('summary')
                ->has('jobsByStatus', 5)
                ->has('applicationsByStatus', 8)
                ->has('employersByStatus', 4)
                ->has('topCategories')
                ->has('trend')
                ->has('definitions')
            );
    }

    public function test_an_employer_cannot_access_platform_analytics(): void
    {
        $employer = User::factory()->approvedEmployer()->create();
        $company = $this->company($employer);

        $this->actingAs($employer)->get(route('admin.analytics.index'))->assertForbidden();
    }

    // ------------------------------------------------------------------ metrics

    public function test_the_platform_scope_counts_the_data_of_every_company(): void
    {
        $fixture = $this->platformFixture();

        $this->actingAs($fixture['administrator'])
            ->get(route('admin.analytics.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/analytics')
                // Three postings: two of company one and one of company two.
                ->where('summary.totalJobs', 3)
                ->where('summary.publishedJobs', 2)
                // Six applications spread over two different companies.
                ->where('summary.totalApplications', 6)
                ->where('summary.advancedApplications', 2)
                ->where('summary.hires', 1)
                ->where('summary.totalCompanies', 2)
            );
    }

    public function test_the_platform_metrics_match_the_known_fixture(): void
    {
        $fixture = $this->platformFixture();

        $response = $this->actingAs($fixture['administrator'])
            ->get(route('admin.analytics.index'))
            ->assertOk();

        $response->assertInertia(fn (Assert $page) => $page
            ->component('admin/analytics')
            ->where('summary.totalJobs', 3)
            ->where('summary.publishedJobs', 2)
            ->where('summary.totalApplications', 6)
            ->where('summary.advancedApplications', 2)
            ->where('summary.hires', 1)
            // The administrator and the two employers; the candidates and the
            // stale employer were created outside of the window.
            ->where('summary.totalUsers', 3)
            ->where('summary.totalCompanies', 2)
            ->where('jobsByStatus.0.status', 'draft')
            ->where('jobsByStatus.0.count', 1)
            ->where('jobsByStatus.1.status', 'pending_review')
            ->where('jobsByStatus.1.count', 0)
            ->where('jobsByStatus.2.status', 'published')
            ->where('jobsByStatus.2.count', 2)
            ->where('jobsByStatus.3.status', 'rejected')
            ->where('jobsByStatus.3.count', 0)
            ->where('jobsByStatus.4.status', 'closed')
            ->where('jobsByStatus.4.count', 0)
            ->where('applicationsByStatus.0.status', 'submitted')
            ->where('applicationsByStatus.0.count', 2)
            ->where('applicationsByStatus.1.status', 'reviewing')
            ->where('applicationsByStatus.1.count', 0)
            ->where('applicationsByStatus.2.status', 'shortlisted')
            ->where('applicationsByStatus.2.count', 1)
            ->where('applicationsByStatus.3.status', 'interview_scheduled')
            ->where('applicationsByStatus.3.count', 0)
            ->where('applicationsByStatus.4.status', 'offer_sent')
            ->where('applicationsByStatus.4.count', 0)
            ->where('applicationsByStatus.5.status', 'hired')
            ->where('applicationsByStatus.5.count', 1)
            ->where('applicationsByStatus.6.status', 'rejected')
            ->where('applicationsByStatus.6.count', 1)
            ->where('applicationsByStatus.7.status', 'withdrawn')
            ->where('applicationsByStatus.7.count', 1)
            ->where('employersByStatus.0.status', 'pending')
            ->where('employersByStatus.0.count', 0)
            ->where('employersByStatus.1.status', 'approved')
            ->where('employersByStatus.1.count', 2)
            ->where('employersByStatus.2.status', 'rejected')
            ->where('employersByStatus.2.count', 0)
            ->where('employersByStatus.3.status', 'suspended')
            ->where('employersByStatus.3.count', 0)
            ->has('topCategories', 1)
            ->where('topCategories.0.id', $fixture['categoryAlpha']->id)
            ->where('topCategories.0.name', 'Alpha Category')
            ->where('topCategories.0.publishedJobs', 2)
        );

        expect($response->inertiaPage()['props']['summary']['conversionRate'])->toEqual(0.3333);
    }

    public function test_records_outside_of_the_window_are_excluded_from_every_metric(): void
    {
        $fixture = $this->platformFixture();

        $response = $this->actingAs($fixture['administrator'])
            ->get(route('admin.analytics.index', [
                'from' => now()->subDays(7)->toDateString(),
                'to' => now()->toDateString(),
            ]))
            ->assertOk();

        $response->assertInertia(fn (Assert $page) => $page
            ->component('admin/analytics')
            ->where('window.preset', 'custom')
            // The stale posting, employer and company stay outside.
            ->where('summary.totalJobs', 3)
            ->where('summary.publishedJobs', 2)
            ->where('summary.totalApplications', 6)
            ->where('summary.totalUsers', 3)
            ->where('summary.totalCompanies', 2)
            ->where('employersByStatus.1.status', 'approved')
            ->where('employersByStatus.1.count', 2)
            ->has('topCategories', 1)
            ->where('topCategories.0.publishedJobs', 2)
        );
    }

    public function test_the_top_categories_are_bounded_to_five_rows_and_ordered(): void
    {
        $administrator = User::factory()->platformAdmin()->create();
        $employer = User::factory()->approvedEmployer()->create();
        $company = $this->company($employer);

        $expected = [
            'Alpha Category' => 3,
            'Beta Category' => 3,
            'Gamma Category' => 2,
            'Delta Category' => 1,
            'Epsilon Category' => 1,
            'Zeta Category' => 1,
        ];

        foreach ($expected as $name => $postings) {
            $category = JobCategory::factory()->create(['name' => $name]);

            for ($index = 0; $index < $postings; $index++) {
                $this->job($company, [
                    'category_id' => $category->id,
                    'status' => 'published',
                    'title' => $name.' Engineer '.$index,
                ]);
            }
        }

        $response = $this->actingAs($administrator)
            ->get(route('admin.analytics.index'))
            ->assertOk();

        $topCategories = $response->inertiaPage()['props']['topCategories'];

        $this->assertCount(5, $topCategories);
        $this->assertSame(
            ['Alpha Category', 'Beta Category', 'Gamma Category', 'Delta Category', 'Epsilon Category'],
            array_column($topCategories, 'name'),
        );
        $this->assertSame([3, 3, 2, 1, 1], array_column($topCategories, 'publishedJobs'));
    }

    public function test_the_platform_page_renders_an_empty_platform_without_errors(): void
    {
        $administrator = User::factory()->platformAdmin()->create();

        $response = $this->actingAs($administrator)
            ->get(route('admin.analytics.index'))
            ->assertOk();

        $response->assertInertia(fn (Assert $page) => $page
            ->component('admin/analytics')
            ->where('summary.totalJobs', 0)
            ->where('summary.publishedJobs', 0)
            ->where('summary.totalApplications', 0)
            ->where('summary.advancedApplications', 0)
            ->where('summary.hires', 0)
            ->where('summary.totalCompanies', 0)
            ->has('topCategories', 0)
            ->has('jobsByStatus', 5)
            ->has('applicationsByStatus', 8)
            ->has('employersByStatus', 4)
        );

        $props = $response->inertiaPage()['props'];

        $this->assertEquals(0.0, $props['summary']['conversionRate']);
        $this->assertSame([0, 0, 0, 0], array_column($props['employersByStatus'], 'count'));
        $this->assertSame([0, 0, 0, 0, 0], array_column($props['jobsByStatus'], 'count'));
        $this->assertSame(
            [0, 0, 0, 0, 0, 0, 0, 0],
            array_column($props['applicationsByStatus'], 'count'),
        );
    }

    public function test_the_daily_trend_is_gap_filled_and_empty_for_an_unbounded_window(): void
    {
        $administrator = User::factory()->platformAdmin()->create();
        $employer = User::factory()->approvedEmployer()->create();
        $company = $this->company($employer);
        $job = $this->job($company);

        $response = $this->actingAs($administrator)->get(route('admin.analytics.index'));
        $trend = $response->inertiaPage()['props']['trend'];

        $this->assertCount(30, $trend);
        $this->assertSame(now()->subDays(29)->toDateString(), $trend[0]['date']);
        $this->assertSame(now()->toDateString(), $trend[29]['date']);
        $this->assertCount(30, array_unique(array_column($trend, 'date')));

        $today = collect($trend)->firstWhere('date', now()->toDateString());

        // The administrator and the employer were both created today.
        $this->assertSame(2, $today['users']);
        $this->assertSame(0, $today['jobs']);
        $this->assertSame(0, $today['applications']);

        $emptyDay = collect($trend)->firstWhere('date', now()->subDays(10)->toDateString());

        $this->assertSame(0, $emptyDay['users']);
        $this->assertSame(0, $emptyDay['jobs']);
        $this->assertSame(0, $emptyDay['applications']);

        $this->actingAs($administrator)
            ->get(route('admin.analytics.index', ['preset' => 'all']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/analytics')
                ->where('window.preset', 'all')
                ->where('window.from', null)
                ->where('window.to', null)
                ->has('trend', 0)
            );
    }

    // --------------------------------------------------------------- validation

    public function test_a_from_date_after_the_to_date_is_rejected(): void
    {
        $administrator = User::factory()->platformAdmin()->create();

        $this->actingAs($administrator)
            ->getJson(route('admin.analytics.index', [
                'from' => now()->toDateString(),
                'to' => now()->subDay()->toDateString(),
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['to']);
    }

    public function test_an_out_of_range_from_date_is_rejected(): void
    {
        $administrator = User::factory()->platformAdmin()->create();

        $this->actingAs($administrator)
            ->getJson(route('admin.analytics.index', [
                'from' => now()->subDays(367)->toDateString(),
                'to' => now()->toDateString(),
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['from']);
    }

    // ------------------------------------------------------------------ privacy

    public function test_the_platform_props_contain_no_candidate_personal_data(): void
    {
        $administrator = User::factory()->platformAdmin()->create();
        $employer = User::factory()->approvedEmployer()->create();
        $company = $this->company($employer);
        $job = $this->job($company);

        // A distinctive identifier proves that no candidate identifier crosses
        // the boundary; the identifier is never referenced by the payload.
        $candidate = User::factory()->jobSeeker()->create([
            'id' => 987654,
            'name' => 'Confidential Candidate',
            'email' => 'confidential.candidate@example.com',
        ]);

        $resume = CandidateResume::factory()->for($candidate, 'user')->create(['id' => 987654]);

        JobApplication::factory()->create([
            'job_posting_id' => $job->id,
            'user_id' => $candidate->id,
            'candidate_resume_id' => $resume->id,
            'status' => 'submitted',
            'cover_letter' => 'Dear hiring manager, this cover letter belongs to Confidential Candidate.',
            'created_at' => now()->subDay()->toDateTimeString(),
        ]);

        $response = $this->actingAs($administrator)
            ->get(route('admin.analytics.index'))
            ->assertOk();

        $scalars = $this->analyticsScalars($response->inertiaPage()['props']);

        $this->assertNotContains(987654, $scalars);
        $this->assertNotContains('987654', $scalars);
        $this->assertNotContains('Confidential Candidate', $scalars);
        $this->assertNotContains('confidential.candidate@example.com', $scalars);
        $this->assertNotContains(
            'Dear hiring manager, this cover letter belongs to Confidential Candidate.',
            $scalars,
        );

        $body = (string) $response->getContent();

        $this->assertStringNotContainsString('Confidential Candidate', $body);
        $this->assertStringNotContainsString('confidential.candidate@example.com', $body);
        $this->assertStringNotContainsString('this cover letter belongs to Confidential Candidate', $body);
    }

    // ------------------------------------------------------------- performance

    public function test_the_platform_analytics_does_not_issue_a_query_per_record(): void
    {
        $administrator = User::factory()->platformAdmin()->create();
        $employer = User::factory()->approvedEmployer()->create();
        $company = $this->company($employer);

        $smallJob = $this->job($company);
        $this->application($smallJob, 'submitted', now()->subDay()->toDateTimeString());

        $smallQueryCount = $this->countQueriesForAnalytics($administrator);

        Job::factory()->count(10)->create([
            'company_id' => $company->id,
            'status' => 'published',
            'created_at' => now()->subDays(2)->toDateTimeString(),
        ]);

        $largeJob = $this->job($company);
        $this->application($largeJob, 'shortlisted', now()->subDay()->toDateTimeString());

        $largeQueryCount = $this->countQueriesForAnalytics($administrator);

        $this->assertSame(
            $smallQueryCount,
            $largeQueryCount,
            'The platform analytics query count grew with the number of records, suggesting an N+1 query.',
        );
    }

    public function test_every_platform_analytics_aggregate_is_executed_exactly_once(): void
    {
        $administrator = User::factory()->platformAdmin()->create();
        $employer = User::factory()->approvedEmployer()->create();
        $company = $this->company($employer);

        $job = $this->job($company);
        $this->application($job, 'shortlisted', now()->subDay()->toDateTimeString());

        $actor = User::query()->findOrFail($administrator->id);

        $this->forgetRouteController('admin.analytics.index');

        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->actingAs($actor)
            ->get(route('admin.analytics.index'))
            ->assertOk();

        $sql = array_map(
            static fn (array $entry): string => (string) $entry['query'],
            DB::getQueryLog(),
        );

        DB::disableQueryLog();

        $count = static function (string $needle) use ($sql): int {
            return count(array_filter(
                $sql,
                static fn (string $query): bool => str_contains($query, $needle),
            ));
        };

        // Each aggregate feeds both the headline metrics and a breakdown, so
        // it must run once — not once per consumer.
        $this->assertSame(1, $count('select "job_postings"."status", COUNT(*) as metric_count'));
        $this->assertSame(1, $count('select "job_applications"."status", COUNT(*) as metric_count'));
        $this->assertSame(1, $count('select "users"."status", COUNT(*) as metric_count'));
        $this->assertSame(3, $count('as metric_day, COUNT(*) as metric_count'));
    }

    // -------------------------------------------------------------------- audit

    public function test_exactly_one_platform_analytics_viewed_audit_row_is_written(): void
    {
        $administrator = User::factory()->platformAdmin()->create();

        $this->actingAs($administrator)
            ->get(route('admin.analytics.index'))
            ->assertOk();

        $entries = AuditLog::query()
            ->where('action', AuditAction::AnalyticsViewed->value)
            ->get();

        $this->assertCount(1, $entries);

        $entry = $entries->first();

        $this->assertSame(AuditTargetType::Analytics->value, $entry->target_type);
        $this->assertSame('platform', $entry->target_label);
        $this->assertSame($administrator->id, $entry->actor_id);
        $this->assertSame('platform', $entry->metadata['scope']);
        $this->assertSame(now()->subDays(29)->toDateString(), $entry->metadata['from']);
        $this->assertSame(now()->toDateString(), $entry->metadata['to']);
        $this->assertSame('bounded', $entry->metadata['window']);
    }

    public function test_an_all_time_platform_analytics_read_still_writes_one_audit_row(): void
    {
        $administrator = User::factory()->platformAdmin()->create();

        $this->actingAs($administrator)
            ->get(route('admin.analytics.index', ['preset' => 'all']))
            ->assertOk();

        $entries = AuditLog::query()
            ->where('action', AuditAction::AnalyticsViewed->value)
            ->get();

        $this->assertCount(1, $entries);

        $entry = $entries->first();

        // An unbounded window has no bounds, and a null metadata value must
        // still be recorded rather than silently dropping the whole entry.
        $this->assertNull($entry->metadata['from']);
        $this->assertNull($entry->metadata['to']);
        $this->assertSame('all_time', $entry->metadata['window']);
    }

    public function test_platform_account_totals_are_not_stale_across_reads(): void
    {
        $administrator = User::factory()->platformAdmin()->create();

        $this->actingAs($administrator)
            ->get(route('admin.analytics.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('summary.totalUsers', 1)
                ->where('employersByStatus.1.count', 0)
            );

        User::factory()->approvedEmployer()->create();

        // The route keeps its resolved controller between reads, so a
        // controller that cached its aggregates would serve stale totals.
        $this->actingAs($administrator)
            ->get(route('admin.analytics.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('summary.totalUsers', 2)
                ->where('employersByStatus.1.count', 1)
            );
    }
}
