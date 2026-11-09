<?php

namespace Tests\Feature\Candidate;

use App\Models\Company;
use App\Models\Job;
use App\Models\JobCategory;
use App\Models\SavedSearch;
use App\Models\SavedSearchAlert;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Authorization\Concerns\InteractsWithAuthorizationCatalogue;
use Tests\TestCase;

class SavedSearchAlertsTest extends TestCase
{
    use InteractsWithAuthorizationCatalogue, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAuthorizationCatalogue();
    }

    public function test_invalid_frequency_fails(): void
    {
        $this->artisan('saved-searches:dispatch-alerts', [
            'frequency' => 'monthly',
        ])->assertExitCode(1);
    }

    public function test_dispatch_notifies_matching_daily_search(): void
    {
        $jobSeeker = User::factory()->jobSeeker()->create();
        $employer = User::factory()->approvedEmployer()->create();
        $company = Company::factory()->for($employer, 'owner')->create();
        $category = JobCategory::factory()->create();

        $job = Job::factory()->for($company)->create([
            'status' => 'published',
            'category_id' => $category->id,
            'title' => 'Laravel Developer',
        ]);

        // Stale last_alerted_at simulates a delayed scheduler run.
        $search = SavedSearch::query()->create([
            'user_id' => $jobSeeker->id,
            'name' => 'Laravel jobs',
            'category_id' => $category->id,
            'alerts_enabled' => true,
            'alert_frequency' => 'daily',
            'last_alerted_at' => now()->subDays(3),
        ]);

        $this->artisan('saved-searches:dispatch-alerts', [
            'frequency' => 'daily',
        ])->assertExitCode(0);

        $this->assertDatabaseHas('saved_search_alerts', [
            'saved_search_id' => $search->id,
            'job_posting_id' => $job->id,
        ]);

        $this->assertSame(1, $jobSeeker->fresh()->notifications()->count());
        $this->assertSame(
            'saved-search.new_jobs',
            $jobSeeker->fresh()->notifications()->first()->data['event'] ?? null
        );

        // last_alerted_at is refreshed by the run.
        $this->assertNotSame(
            now()->subDays(3)->format('Y-m-d H:i:s'),
            $search->fresh()->last_alerted_at?->format('Y-m-d H:i:s')
        );
    }

    public function test_frequency_isolation_and_opt_out(): void
    {
        $jobSeeker = User::factory()->jobSeeker()->create();
        $employer = User::factory()->approvedEmployer()->create();
        $company = Company::factory()->for($employer, 'owner')->create();
        $category = JobCategory::factory()->create();

        Job::factory()->for($company)->create([
            'status' => 'published',
            'category_id' => $category->id,
        ]);

        // A weekly search must not be alerted on the daily run.
        SavedSearch::query()->create([
            'user_id' => $jobSeeker->id,
            'category_id' => $category->id,
            'alerts_enabled' => true,
            'alert_frequency' => 'weekly',
            'last_alerted_at' => now()->subDays(3),
        ]);

        // A disabled daily search must not be alerted.
        SavedSearch::query()->create([
            'user_id' => $jobSeeker->id,
            'category_id' => $category->id,
            'alerts_enabled' => false,
            'alert_frequency' => 'daily',
            'last_alerted_at' => now()->subDays(3),
        ]);

        $this->artisan('saved-searches:dispatch-alerts', [
            'frequency' => 'daily',
        ])->assertExitCode(0);

        $this->assertSame(0, $jobSeeker->fresh()->notifications()->count());
        $this->assertDatabaseCount('saved_search_alerts', 0);
    }

    public function test_duplicate_prevention_on_rerun(): void
    {
        $jobSeeker = User::factory()->jobSeeker()->create();
        $employer = User::factory()->approvedEmployer()->create();
        $company = Company::factory()->for($employer, 'owner')->create();
        $category = JobCategory::factory()->create();

        $job = Job::factory()->for($company)->create([
            'status' => 'published',
            'category_id' => $category->id,
        ]);

        SavedSearch::query()->create([
            'user_id' => $jobSeeker->id,
            'category_id' => $category->id,
            'alerts_enabled' => true,
            'alert_frequency' => 'daily',
            'last_alerted_at' => now()->subDays(3),
        ]);

        $this->artisan('saved-searches:dispatch-alerts', [
            'frequency' => 'daily',
        ])->assertExitCode(0);

        $this->assertSame(1, $jobSeeker->fresh()->notifications()->count());
        $this->assertDatabaseCount('saved_search_alerts', 1);

        // Re-running must not create another notification or ledger row.
        $this->artisan('saved-searches:dispatch-alerts', [
            'frequency' => 'daily',
        ])->assertExitCode(0);

        $this->assertSame(1, $jobSeeker->fresh()->notifications()->count());
        $this->assertDatabaseCount('saved_search_alerts', 1);
    }

    public function test_cross_search_dedup_within_one_run(): void
    {
        $jobSeeker = User::factory()->jobSeeker()->create();
        $employer = User::factory()->approvedEmployer()->create();
        $company = Company::factory()->for($employer, 'owner')->create();
        $category = JobCategory::factory()->create();

        $job = Job::factory()->for($company)->create([
            'status' => 'published',
            'category_id' => $category->id,
        ]);

        // Two overlapping searches matching the same job.
        SavedSearch::query()->create([
            'user_id' => $jobSeeker->id,
            'category_id' => $category->id,
            'alerts_enabled' => true,
            'alert_frequency' => 'daily',
            'last_alerted_at' => now()->subDays(3),
        ]);
        SavedSearch::query()->create([
            'user_id' => $jobSeeker->id,
            'category_id' => $category->id,
            'alerts_enabled' => true,
            'alert_frequency' => 'daily',
            'last_alerted_at' => now()->subDays(3),
        ]);

        $this->artisan('saved-searches:dispatch-alerts', [
            'frequency' => 'daily',
        ])->assertExitCode(0);

        // Exactly one notification across both searches.
        $this->assertSame(1, $jobSeeker->fresh()->notifications()->count());

        // The job is alerted exactly once overall.
        $this->assertSame(1, SavedSearchAlert::query()
            ->where('job_posting_id', $job->id)
            ->count());
    }

    public function test_non_visible_jobs_are_not_alerted(): void
    {
        $jobSeeker = User::factory()->jobSeeker()->create();
        $employer = User::factory()->approvedEmployer()->create();
        $company = Company::factory()->for($employer, 'owner')->create();
        $category = JobCategory::factory()->create();

        SavedSearch::query()->create([
            'user_id' => $jobSeeker->id,
            'category_id' => $category->id,
            'alerts_enabled' => true,
            'alert_frequency' => 'daily',
            'last_alerted_at' => now()->subDays(3),
        ]);

        Job::factory()->for($company)->create([
            'status' => 'draft',
            'category_id' => $category->id,
        ]);
        Job::factory()->for($company)->create([
            'status' => 'rejected',
            'category_id' => $category->id,
        ]);
        Job::factory()->for($company)->create([
            'status' => 'closed',
            'category_id' => $category->id,
        ]);

        $this->artisan('saved-searches:dispatch-alerts', [
            'frequency' => 'daily',
        ])->assertExitCode(0);

        $this->assertSame(0, $jobSeeker->fresh()->notifications()->count());
        $this->assertDatabaseCount('saved_search_alerts', 0);
    }

    public function test_suspended_owner_and_inactive_company_jobs_are_not_alerted(): void
    {
        $jobSeeker = User::factory()->jobSeeker()->create();
        $category = JobCategory::factory()->create();

        SavedSearch::query()->create([
            'user_id' => $jobSeeker->id,
            'category_id' => $category->id,
            'alerts_enabled' => true,
            'alert_frequency' => 'daily',
            'last_alerted_at' => now()->subDays(3),
        ]);

        // Published job but suspended owner → not visible.
        $suspended = User::factory()->suspendedEmployer()->create();
        $suspendedCompany = Company::factory()->for($suspended, 'owner')->create([
            'is_active' => true,
        ]);
        Job::factory()->for($suspendedCompany)->create([
            'status' => 'published',
            'category_id' => $category->id,
        ]);

        // Published job but inactive company → not visible.
        $approved = User::factory()->approvedEmployer()->create();
        $inactiveCompany = Company::factory()->for($approved, 'owner')->create([
            'is_active' => false,
        ]);
        Job::factory()->for($inactiveCompany)->create([
            'status' => 'published',
            'category_id' => $category->id,
        ]);

        $this->artisan('saved-searches:dispatch-alerts', [
            'frequency' => 'daily',
        ])->assertExitCode(0);

        $this->assertSame(0, $jobSeeker->fresh()->notifications()->count());
        $this->assertDatabaseCount('saved_search_alerts', 0);
    }

    public function test_fifty_match_cap(): void
    {
        $jobSeeker = User::factory()->jobSeeker()->create();
        $employer = User::factory()->approvedEmployer()->create();
        $company = Company::factory()->for($employer, 'owner')->create();
        $category = JobCategory::factory()->create();

        SavedSearch::query()->create([
            'user_id' => $jobSeeker->id,
            'category_id' => $category->id,
            'alerts_enabled' => true,
            'alert_frequency' => 'daily',
            'last_alerted_at' => now()->subDays(3),
        ]);

        Job::factory()->count(60)->for($company)->create([
            'status' => 'published',
            'category_id' => $category->id,
        ]);

        $this->artisan('saved-searches:dispatch-alerts', [
            'frequency' => 'daily',
        ])->assertExitCode(0);

        // Capped at 50 ledger rows per search per run.
        $this->assertDatabaseCount('saved_search_alerts', 50);
    }

    public function test_deactivated_category_does_not_break_matcher(): void
    {
        $jobSeeker = User::factory()->jobSeeker()->create();

        $category = JobCategory::factory()->create(['is_active' => false]);

        SavedSearch::query()->create([
            'user_id' => $jobSeeker->id,
            'category_id' => $category->id,
            'alerts_enabled' => true,
            'alert_frequency' => 'daily',
            'last_alerted_at' => now()->subDays(3),
        ]);

        $this->artisan('saved-searches:dispatch-alerts', [
            'frequency' => 'daily',
        ])->assertExitCode(0);

        $this->assertSame(0, $jobSeeker->fresh()->notifications()->count());
        $this->assertDatabaseCount('saved_search_alerts', 0);
    }
}
