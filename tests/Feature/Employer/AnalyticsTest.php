<?php

use App\Enums\AuditAction;
use App\Enums\AuditTargetType;
use App\Models\AuditLog;
use App\Models\CandidateResume;
use App\Models\Company;
use App\Models\CompanyMember;
use App\Models\Job;
use App\Models\JobApplication;
use App\Models\User;
use Database\Seeders\AuthorizationSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed(AuthorizationSeeder::class);

    // The Inertia root view resolves the page component through the Vite
    // manifest. Disabling Vite keeps this backend suite independent of the
    // production asset build that the frontend owns.
    $this->withoutVite();
});

/**
 * Create a company owned by the given employer.
 *
 * @param  array<string, mixed>  $attributes
 */
function employerAnalyticsCompany(User $owner, array $attributes = []): Company
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
function employerAnalyticsJob(Company $company, array $attributes = []): Job
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
 * Every application needs a distinct candidate because `job_applications`
 * enforces a unique (`user_id`, `job_posting_id`) pair. The candidate account
 * is created outside the default analytics window so that the platform
 * account totals stay deterministic.
 *
 * @param  array<string, mixed>  $candidateAttributes
 */
function employerAnalyticsApplication(
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
 * Add an active or revoked company membership.
 */
function employerAnalyticsMembership(
    Company $company,
    User $member,
    string $companyRole,
    string $status = 'active',
): CompanyMember {
    return CompanyMember::create([
        'company_id' => $company->id,
        'user_id' => $member->id,
        'company_role' => $companyRole,
        'status' => $status,
        'invited_at' => now(),
        'accepted_at' => $status === 'active' ? now() : null,
        'revoked_at' => $status === 'revoked' ? now() : null,
    ]);
}

/**
 * Count the queries a single employer analytics page read costs.
 *
 * The employer is re-read from the database so that every measurement pays
 * the same role and permission resolution cost. The cached controller instance
 * is dropped as well, because Laravel reuses the resolved controller - and
 * therefore its analytics service - for every request on the same route.
 */
function employerAnalyticsQueryCount(User $employer): int
{
    $actor = User::query()->findOrFail($employer->id);

    Route::getRoutes()->getByName('employer.analytics.index')?->flushController();

    DB::flushQueryLog();
    DB::enableQueryLog();

    test()->actingAs($actor)
        ->get(route('employer.analytics.index'))
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
function employerAnalyticsScalars(array $props): array
{
    $analyticsProps = array_intersect_key($props, array_flip([
        'window',
        'companies',
        'selectedCompanyId',
        'summary',
        'jobsByStatus',
        'applicationsByStatus',
        'trend',
        'jobs',
        'definitions',
    ]));

    $scalars = [];

    array_walk_recursive($analyticsProps, function ($value) use (&$scalars): void {
        $scalars[] = $value;
    });

    return $scalars;
}

// ------------------------------------------------------------------- access

test('guests are redirected to login when accessing employer analytics', function (): void {
    $this->get(route('employer.analytics.index'))->assertRedirect(route('login'));
});

test('job seekers cannot access employer analytics', function (): void {
    $user = User::factory()->jobSeeker()->create();

    $this->actingAs($user)->get(route('employer.analytics.index'))->assertForbidden();
});

test('approved employers without a company cannot access employer analytics', function (): void {
    $employer = User::factory()->approvedEmployer()->create();

    $this->actingAs($employer)->get(route('employer.analytics.index'))->assertForbidden();
});

test('approved employers can view the analytics of their company', function (): void {
    $employer = User::factory()->approvedEmployer()->create();
    $company = employerAnalyticsCompany($employer);

    $this->actingAs($employer)
        ->get(route('employer.analytics.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('employer/analytics')
            ->has('window')
            ->where('window.from', now()->subDays(29)->toDateString())
            ->where('window.to', now()->toDateString())
            ->where('window.preset', '30d')
            ->has('companies', 1)
            ->where('companies.0.id', $company->id)
            ->where('companies.0.name', $company->name)
            ->where('companies.0.slug', $company->slug)
            ->where('selectedCompanyId', null)
            ->has('summary')
            ->has('jobsByStatus', 5)
            ->has('applicationsByStatus', 8)
            ->has('trend')
            ->has('jobs.data', 0)
            ->has('definitions')
        );
});

test('employers cannot request the analytics of a company they do not manage', function (): void {
    $employerA = User::factory()->approvedEmployer()->create();
    $companyA = employerAnalyticsCompany($employerA);
    employerAnalyticsJob($companyA, ['title' => 'Company A Engineer']);

    $employerB = User::factory()->approvedEmployer()->create();
    $companyB = employerAnalyticsCompany($employerB);
    employerAnalyticsJob($companyB, ['title' => 'Company B Confidential Engineer']);

    $response = $this->actingAs($employerA)
        ->get(route('employer.analytics.index', ['company_id' => $companyB->id]));

    $response->assertForbidden();
    expect($response->getContent())->not->toContain('Company B Confidential Engineer');
});

test('employer analytics only contain the jobs and applications of their own company', function (): void {
    $employerA = User::factory()->approvedEmployer()->create();
    $companyA = employerAnalyticsCompany($employerA);
    $jobA = employerAnalyticsJob($companyA, ['title' => 'Company A Engineer']);
    employerAnalyticsApplication($jobA, 'submitted', now()->subDay()->toDateTimeString());

    $employerB = User::factory()->approvedEmployer()->create();
    $companyB = employerAnalyticsCompany($employerB);
    employerAnalyticsJob($companyB, ['title' => 'Company B Confidential Engineer']);

    $response = $this->actingAs($employerA)
        ->get(route('employer.analytics.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('employer/analytics')
            ->has('companies', 1)
            ->where('companies.0.id', $companyA->id)
            ->has('jobs.data', 1)
            ->where('jobs.data.0.id', $jobA->id)
            ->where('jobs.data.0.title', 'Company A Engineer')
            ->where('summary.totalJobs', 1)
            ->where('summary.totalApplications', 1)
        );

    expect($response->getContent())->not->toContain('Company B Confidential Engineer');
});

test('an active recruiter member can view the analytics of the company', function (): void {
    $owner = User::factory()->approvedEmployer()->create();
    $company = employerAnalyticsCompany($owner);
    employerAnalyticsJob($company);

    $recruiter = User::factory()->approvedEmployer()->create();
    employerAnalyticsMembership($company, $recruiter, 'recruiter');

    $this->actingAs($recruiter)
        ->get(route('employer.analytics.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('employer/analytics')
            ->has('companies', 1)
            ->where('companies.0.id', $company->id)
        );
});

test('an active reviewer member can view the analytics of the company', function (): void {
    $owner = User::factory()->approvedEmployer()->create();
    $company = employerAnalyticsCompany($owner);
    employerAnalyticsJob($company);

    $reviewer = User::factory()->approvedEmployer()->create();
    employerAnalyticsMembership($company, $reviewer, 'reviewer');

    $this->actingAs($reviewer)
        ->get(route('employer.analytics.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('employer/analytics')
            ->has('companies', 1)
            ->where('companies.0.id', $company->id)
        );
});

test('an active member of another company cannot read that company analytics', function (): void {
    $owner = User::factory()->approvedEmployer()->create();
    $company = employerAnalyticsCompany($owner);
    $job = employerAnalyticsJob($company, ['title' => 'Owned Company Engineer']);
    employerAnalyticsApplication($job, 'submitted', now()->subDay()->toDateTimeString());

    $otherOwner = User::factory()->approvedEmployer()->create();
    $otherCompany = employerAnalyticsCompany($otherOwner);
    employerAnalyticsJob($otherCompany, ['title' => 'Other Company Confidential Engineer']);

    $member = User::factory()->approvedEmployer()->create();
    employerAnalyticsMembership($otherCompany, $member, 'recruiter');

    // The member sees nothing of the company they are not a member of.
    $response = $this->actingAs($member)
        ->get(route('employer.analytics.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('employer/analytics')
            ->has('companies', 1)
            ->where('companies.0.id', $otherCompany->id)
            ->has('jobs.data', 1)
            ->where('summary.totalApplications', 0)
        );

    expect($response->getContent())->not->toContain('Owned Company Engineer');

    // Requesting the other company explicitly is forbidden.
    $this->actingAs($member)
        ->get(route('employer.analytics.index', ['company_id' => $company->id]))
        ->assertForbidden();
});

test('a revoked member is not part of the authorized company set', function (): void {
    $owner = User::factory()->approvedEmployer()->create();
    $company = employerAnalyticsCompany($owner);

    $revokedOwner = User::factory()->approvedEmployer()->create();
    $revokedCompany = employerAnalyticsCompany($revokedOwner);
    employerAnalyticsJob($revokedCompany, ['title' => 'Revoked Company Engineer']);

    $member = User::factory()->approvedEmployer()->create();
    $ownCompany = employerAnalyticsCompany($member);
    employerAnalyticsMembership($revokedCompany, $member, 'recruiter', 'revoked');

    $this->actingAs($member)
        ->get(route('employer.analytics.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('employer/analytics')
            ->has('companies', 1)
            ->where('companies.0.id', $ownCompany->id)
            ->has('jobs.data', 0)
        );

    $this->actingAs($member)
        ->get(route('employer.analytics.index', ['company_id' => $revokedCompany->id]))
        ->assertForbidden();
});

test('pending employers cannot access employer analytics', function (): void {
    $user = User::factory()->pendingEmployerApplicant()->create();

    $this->actingAs($user)->get(route('employer.analytics.index'))->assertForbidden();
});

test('suspended employers cannot access employer analytics', function (): void {
    $user = User::factory()->suspendedEmployer()->create();

    $this->actingAs($user)->get(route('employer.analytics.index'))->assertForbidden();
});

// --------------------------------------------------------------- validation

test('a company id that does not exist is rejected by validation', function (): void {
    $employer = User::factory()->approvedEmployer()->create();
    employerAnalyticsCompany($employer);

    $this->actingAs($employer)
        ->getJson(route('employer.analytics.index', ['company_id' => 987654]))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['company_id']);

    // The very same input surfaces the field error on a plain browser request.
    $this->actingAs($employer)
        ->get(route('employer.analytics.index', ['company_id' => 987654]))
        ->assertSessionHasErrors('company_id');
});

test('an existing but unauthorized company id is forbidden', function (): void {
    $employer = User::factory()->approvedEmployer()->create();
    employerAnalyticsCompany($employer);

    $otherOwner = User::factory()->approvedEmployer()->create();
    $otherCompany = employerAnalyticsCompany($otherOwner);

    $this->actingAs($employer)
        ->get(route('employer.analytics.index', ['company_id' => $otherCompany->id]))
        ->assertForbidden();
});

test('a from date after the to date is rejected', function (): void {
    $employer = User::factory()->approvedEmployer()->create();
    employerAnalyticsCompany($employer);

    $this->actingAs($employer)
        ->getJson(route('employer.analytics.index', [
            'from' => now()->toDateString(),
            'to' => now()->subDay()->toDateString(),
        ]))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['to']);
});

test('an invalid date format is rejected', function (): void {
    $employer = User::factory()->approvedEmployer()->create();
    employerAnalyticsCompany($employer);

    $this->actingAs($employer)
        ->getJson(route('employer.analytics.index', ['from' => 'not-a-date']))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['from']);

    $this->actingAs($employer)
        ->getJson(route('employer.analytics.index', ['to' => '01/11/2026']))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['to']);
});

test('a from date older than 366 days is rejected', function (): void {
    $employer = User::factory()->approvedEmployer()->create();
    employerAnalyticsCompany($employer);

    $this->actingAs($employer)
        ->getJson(route('employer.analytics.index', [
            'from' => now()->subDays(367)->toDateString(),
            'to' => now()->toDateString(),
        ]))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['from']);
});

test('an array from parameter is rejected instead of being ignored', function (): void {
    $employer = User::factory()->approvedEmployer()->create();
    employerAnalyticsCompany($employer);

    $this->actingAs($employer)
        ->getJson(route('employer.analytics.index', ['from' => ['x']]))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['from']);
});

// ------------------------------------------------------------------ metrics

test('the metrics match the known fixture', function (): void {
    $employer = User::factory()->approvedEmployer()->create();
    $company = employerAnalyticsCompany($employer);

    $draftJob = employerAnalyticsJob($company, [
        'title' => 'Backend Engineer',
        'status' => 'draft',
        'created_at' => now()->subDays(2)->toDateTimeString(),
    ]);
    $publishedJob = employerAnalyticsJob($company, [
        'title' => 'Frontend Engineer',
        'status' => 'published',
        'created_at' => now()->subDays(3)->toDateTimeString(),
    ]);

    // Outside the default thirty day window.
    employerAnalyticsJob($company, [
        'title' => 'Archived Engineer',
        'status' => 'published',
        'created_at' => now()->subDays(90)->toDateTimeString(),
    ]);

    $insideWindow = now()->subDay()->toDateTimeString();

    foreach (['submitted', 'reviewing', 'shortlisted', 'interview_scheduled', 'offer_sent', 'hired', 'rejected'] as $status) {
        employerAnalyticsApplication($draftJob, $status, $insideWindow);
    }

    foreach (['submitted', 'withdrawn'] as $status) {
        employerAnalyticsApplication($publishedJob, $status, $insideWindow);
    }

    $response = $this->actingAs($employer)
        ->get(route('employer.analytics.index'))
        ->assertOk();

    $response->assertInertia(fn (Assert $page) => $page
        ->component('employer/analytics')
        ->where('summary.totalJobs', 2)
        ->where('summary.publishedJobs', 1)
        ->where('summary.totalApplications', 9)
        ->where('summary.advancedApplications', 4)
        ->where('summary.hires', 1)
        ->where('jobsByStatus.0.status', 'draft')
        ->where('jobsByStatus.0.count', 1)
        ->where('jobsByStatus.1.status', 'pending_review')
        ->where('jobsByStatus.1.count', 0)
        ->where('jobsByStatus.2.status', 'published')
        ->where('jobsByStatus.2.count', 1)
        ->where('jobsByStatus.3.status', 'rejected')
        ->where('jobsByStatus.3.count', 0)
        ->where('jobsByStatus.4.status', 'closed')
        ->where('jobsByStatus.4.count', 0)
        ->where('applicationsByStatus.0.status', 'submitted')
        ->where('applicationsByStatus.0.count', 2)
        ->where('applicationsByStatus.1.status', 'reviewing')
        ->where('applicationsByStatus.1.count', 1)
        ->where('applicationsByStatus.2.status', 'shortlisted')
        ->where('applicationsByStatus.2.count', 1)
        ->where('applicationsByStatus.3.status', 'interview_scheduled')
        ->where('applicationsByStatus.3.count', 1)
        ->where('applicationsByStatus.4.status', 'offer_sent')
        ->where('applicationsByStatus.4.count', 1)
        ->where('applicationsByStatus.5.status', 'hired')
        ->where('applicationsByStatus.5.count', 1)
        ->where('applicationsByStatus.6.status', 'rejected')
        ->where('applicationsByStatus.6.count', 1)
        ->where('applicationsByStatus.7.status', 'withdrawn')
        ->where('applicationsByStatus.7.count', 1)
        ->has('jobs.data', 2)
        ->where('jobs.data.0.id', $draftJob->id)
        ->where('jobs.data.0.title', 'Backend Engineer')
        ->where('jobs.data.0.applications_count', 7)
        ->where('jobs.data.0.advanced_count', 4)
        ->where('jobs.data.0.conversion_rate', 0.5714)
        ->where('jobs.data.1.id', $publishedJob->id)
        ->where('jobs.data.1.title', 'Frontend Engineer')
        ->where('jobs.data.1.applications_count', 2)
        ->where('jobs.data.1.advanced_count', 0)
    );

    $props = $response->inertiaPage()['props'];

    expect($props['summary']['conversionRate'])->toEqual(0.4444);
    expect($props['jobs']['data'][1]['conversion_rate'])->toEqual(0.0);
});

test('applications created outside the window are excluded from the metrics', function (): void {
    $employer = User::factory()->approvedEmployer()->create();
    $company = employerAnalyticsCompany($employer);
    $job = employerAnalyticsJob($company, ['title' => 'Backend Engineer']);

    employerAnalyticsApplication($job, 'hired', now()->subDay()->toDateTimeString());
    employerAnalyticsApplication($job, 'submitted', now()->subDays(60)->toDateTimeString());

    $response = $this->actingAs($employer)
        ->get(route('employer.analytics.index'))
        ->assertOk();

    $response->assertInertia(fn (Assert $page) => $page
        ->component('employer/analytics')
        ->where('summary.totalApplications', 1)
        ->where('summary.advancedApplications', 1)
        ->where('summary.hires', 1)
        ->where('applicationsByStatus.0.status', 'submitted')
        ->where('applicationsByStatus.0.count', 0)
        ->has('jobs.data', 1)
        ->where('jobs.data.0.applications_count', 1)
        ->where('jobs.data.0.advanced_count', 1)
    );

    expect($response->inertiaPage()['props']['summary']['conversionRate'])->toEqual(1.0);
});

test('a rejected application is in the denominator but not in the numerator', function (): void {
    $employer = User::factory()->approvedEmployer()->create();
    $company = employerAnalyticsCompany($employer);
    $job = employerAnalyticsJob($company);

    employerAnalyticsApplication($job, 'rejected', now()->subDay()->toDateTimeString());
    employerAnalyticsApplication($job, 'shortlisted', now()->subDay()->toDateTimeString());

    $response = $this->actingAs($employer)
        ->get(route('employer.analytics.index'))
        ->assertOk();

    $response->assertInertia(fn (Assert $page) => $page
        ->component('employer/analytics')
        ->where('summary.totalApplications', 2)
        ->where('summary.advancedApplications', 1)
        ->where('summary.hires', 0)
        ->where('applicationsByStatus.6.status', 'rejected')
        ->where('applicationsByStatus.6.count', 1)
        ->has('jobs.data', 1)
        ->where('jobs.data.0.applications_count', 2)
        ->where('jobs.data.0.advanced_count', 1)
        ->where('jobs.data.0.conversion_rate', 0.5)
    );

    expect($response->inertiaPage()['props']['summary']['conversionRate'])->toEqual(0.5);
});

test('a withdrawn application is in the denominator but not in the numerator', function (): void {
    $employer = User::factory()->approvedEmployer()->create();
    $company = employerAnalyticsCompany($employer);
    $job = employerAnalyticsJob($company);

    employerAnalyticsApplication($job, 'withdrawn', now()->subDay()->toDateTimeString());
    employerAnalyticsApplication($job, 'hired', now()->subDay()->toDateTimeString());

    $response = $this->actingAs($employer)
        ->get(route('employer.analytics.index'))
        ->assertOk();

    $response->assertInertia(fn (Assert $page) => $page
        ->component('employer/analytics')
        ->where('summary.totalApplications', 2)
        ->where('summary.advancedApplications', 1)
        ->where('summary.hires', 1)
        ->where('applicationsByStatus.7.status', 'withdrawn')
        ->where('applicationsByStatus.7.count', 1)
        ->has('jobs.data', 1)
        ->where('jobs.data.0.applications_count', 2)
        ->where('jobs.data.0.advanced_count', 1)
        ->where('jobs.data.0.conversion_rate', 0.5)
    );

    expect($response->inertiaPage()['props']['summary']['conversionRate'])->toEqual(0.5);
});

test('the conversion rate is zero when there are no applications', function (): void {
    $employer = User::factory()->approvedEmployer()->create();
    $company = employerAnalyticsCompany($employer);
    employerAnalyticsJob($company);

    $response = $this->actingAs($employer)
        ->get(route('employer.analytics.index'))
        ->assertOk();

    $response->assertInertia(fn (Assert $page) => $page
        ->component('employer/analytics')
        ->where('summary.totalApplications', 0)
        ->where('summary.advancedApplications', 0)
        ->where('summary.hires', 0)
        ->has('jobs.data', 1)
        ->where('jobs.data.0.applications_count', 0)
        ->where('jobs.data.0.advanced_count', 0)
    );

    $props = $response->inertiaPage()['props'];

    expect($props['summary']['conversionRate'])->toEqual(0.0);
    expect($props['jobs']['data'][0]['conversion_rate'])->toEqual(0.0);
});

test('the daily trend is gap filled and empty for an unbounded window', function (): void {
    $employer = User::factory()->approvedEmployer()->create();
    $company = employerAnalyticsCompany($employer);
    $job = employerAnalyticsJob($company, ['created_at' => now()->subDays(2)->toDateTimeString()]);
    employerAnalyticsApplication($job, 'submitted', now()->subDays(2)->toDateTimeString());

    $response = $this->actingAs($employer)->get(route('employer.analytics.index'));
    $trend = $response->inertiaPage()['props']['trend'];

    expect($trend)->toHaveCount(30);
    expect($trend[0]['date'])->toBe(now()->subDays(29)->toDateString());
    expect($trend[29]['date'])->toBe(now()->toDateString());
    expect(array_unique(array_column($trend, 'date')))->toHaveCount(30);

    // The day the job was created carries the job and the application.
    $twoDaysAgo = collect($trend)->firstWhere('date', now()->subDays(2)->toDateString());

    expect($twoDaysAgo['jobs'])->toBe(1);
    expect($twoDaysAgo['applications'])->toBe(1);

    // A day without rows is present with a zero count.
    $emptyDay = collect($trend)->firstWhere('date', now()->subDays(10)->toDateString());

    expect($emptyDay['jobs'])->toBe(0);
    expect($emptyDay['applications'])->toBe(0);

    // An unbounded window cannot produce a daily series.
    $this->actingAs($employer)
        ->get(route('employer.analytics.index', ['preset' => 'all']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('employer/analytics')
            ->where('window.preset', 'all')
            ->where('window.from', null)
            ->where('window.to', null)
            ->has('trend', 0)
        );
});

// --------------------------------------------------------------- pagination

test('the per-job table paginates and preserves the active filters', function (): void {
    $employer = User::factory()->approvedEmployer()->create();
    $company = employerAnalyticsCompany($employer);

    Job::factory()->count(12)->create([
        'company_id' => $company->id,
        'status' => 'published',
        'created_at' => now()->subDays(2)->toDateTimeString(),
    ]);

    $from = now()->subDays(10)->toDateString();
    $to = now()->toDateString();

    $firstPage = $this->actingAs($employer)
        ->get(route('employer.analytics.index', [
            'company_id' => $company->id,
            'from' => $from,
            'to' => $to,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('employer/analytics')
            ->where('jobs.current_page', 1)
            ->where('jobs.last_page', 2)
            ->where('jobs.per_page', 10)
            ->where('jobs.total', 12)
            ->has('jobs.data', 10)
        );

    $nextPageUrl = $firstPage->inertiaPage()['props']['jobs']['next_page_url'];

    expect($nextPageUrl)->toBeString()
        ->and($nextPageUrl)->toContain('company_id='.$company->id)
        ->and($nextPageUrl)->toContain('from='.$from)
        ->and($nextPageUrl)->toContain('to='.$to);

    $secondPage = $this->actingAs($employer)
        ->get(route('employer.analytics.index', [
            'company_id' => $company->id,
            'from' => $from,
            'to' => $to,
            'page' => 2,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('employer/analytics')
            ->where('jobs.current_page', 2)
            ->where('jobs.total', 12)
            ->has('jobs.data', 2)
        );

    $firstIds = array_column($firstPage->inertiaPage()['props']['jobs']['data'], 'id');
    $secondIds = array_column($secondPage->inertiaPage()['props']['jobs']['data'], 'id');

    expect(array_intersect($firstIds, $secondIds))->toBeEmpty();
});

test('the per-job table only lists the jobs of the employer', function (): void {
    $employer = User::factory()->approvedEmployer()->create();
    $company = employerAnalyticsCompany($employer);
    $ownJob = employerAnalyticsJob($company, ['title' => 'Own Company Engineer']);

    $otherOwner = User::factory()->approvedEmployer()->create();
    $otherCompany = employerAnalyticsCompany($otherOwner);
    employerAnalyticsJob($otherCompany, ['title' => 'Other Company Confidential Engineer']);

    $this->actingAs($employer)
        ->get(route('employer.analytics.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('employer/analytics')
            ->has('jobs.data', 1)
            ->where('jobs.data.0.id', $ownJob->id)
            ->where('jobs.total', 1)
        );
});

// ------------------------------------------------------------------ privacy

test('the analytics props contain no candidate personal data', function (): void {
    $employer = User::factory()->approvedEmployer()->create();
    $company = employerAnalyticsCompany($employer);
    $job = employerAnalyticsJob($company);

    // A distinctive identifier proves that no candidate identifier crosses the
    // boundary; the identifier is never referenced by the analytics payload.
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

    $response = $this->actingAs($employer)
        ->get(route('employer.analytics.index'))
        ->assertOk();

    $props = $response->inertiaPage()['props'];
    $row = $props['jobs']['data'][0];

    expect(array_keys($row))->toBe([
        'id',
        'title',
        'status',
        'company',
        'created_at',
        'applications_count',
        'advanced_count',
        'conversion_rate',
    ]);

    $scalars = employerAnalyticsScalars($props);

    expect($scalars)->not->toContain(987654)
        ->and($scalars)->not->toContain('987654')
        ->and($scalars)->not->toContain('Confidential Candidate')
        ->and($scalars)->not->toContain('confidential.candidate@example.com')
        ->and($scalars)->not->toContain('Dear hiring manager, this cover letter belongs to Confidential Candidate.');

    $body = (string) $response->getContent();

    expect($body)->not->toContain('Confidential Candidate')
        ->and($body)->not->toContain('confidential.candidate@example.com')
        ->and($body)->not->toContain('this cover letter belongs to Confidential Candidate');
});

// ------------------------------------------------------------- performance

test('the query count does not grow with the number of jobs and applications', function (): void {
    $employer = User::factory()->approvedEmployer()->create();
    $company = employerAnalyticsCompany($employer);

    $smallJob = employerAnalyticsJob($company);
    employerAnalyticsApplication($smallJob, 'submitted', now()->subDay()->toDateTimeString());

    $smallQueryCount = employerAnalyticsQueryCount($employer);

    Job::factory()->count(10)->create([
        'company_id' => $company->id,
        'status' => 'published',
        'created_at' => now()->subDays(2)->toDateTimeString(),
    ]);

    $largeJob = employerAnalyticsJob($company);
    employerAnalyticsApplication($largeJob, 'shortlisted', now()->subDay()->toDateTimeString());

    $largeQueryCount = employerAnalyticsQueryCount($employer);

    expect($largeQueryCount)->toBe($smallQueryCount);
});

test('every analytics aggregate is executed exactly once per page read', function (): void {
    $employer = User::factory()->approvedEmployer()->create();
    $company = employerAnalyticsCompany($employer);

    $job = employerAnalyticsJob($company);
    employerAnalyticsApplication($job, 'shortlisted', now()->subDay()->toDateTimeString());

    $actor = User::query()->findOrFail($employer->id);

    Route::getRoutes()->getByName('employer.analytics.index')?->flushController();

    DB::flushQueryLog();
    DB::enableQueryLog();

    test()->actingAs($actor)
        ->get(route('employer.analytics.index'))
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

    // The job status aggregate feeds both the headline metrics and the status
    // breakdown, so it must run once — not once per consumer.
    expect($count('select "job_postings"."status", COUNT(*) as metric_count'))->toBe(1);
    expect($count('select "job_applications"."status", COUNT(*) as metric_count'))->toBe(1);
    expect($count('as metric_day, COUNT(*) as metric_count'))->toBe(2);
});

// ------------------------------------------------------------------- filters

test('the same filter url produces the same props', function (): void {
    $employer = User::factory()->approvedEmployer()->create();
    $company = employerAnalyticsCompany($employer);
    $job = employerAnalyticsJob($company);
    employerAnalyticsApplication($job, 'shortlisted', now()->subDay()->toDateTimeString());

    $url = route('employer.analytics.index', [
        'company_id' => $company->id,
        'from' => now()->subDays(10)->toDateString(),
        'to' => now()->toDateString(),
    ]);

    $first = $this->actingAs($employer)->get($url)->assertOk()->inertiaPage();
    $second = $this->actingAs($employer)->get($url)->assertOk()->inertiaPage();

    $analyticsProps = ['window', 'companies', 'selectedCompanyId', 'summary', 'jobsByStatus', 'applicationsByStatus', 'trend', 'jobs', 'definitions'];

    foreach ($analyticsProps as $prop) {
        expect($second['props'][$prop])->toEqual($first['props'][$prop]);
    }

    // The query string matches the applied filters.
    expect($first['props']['window']['from'])->toBe(now()->subDays(10)->toDateString());
    expect($first['props']['window']['to'])->toBe(now()->toDateString());
    expect($first['props']['window']['preset'])->toBe('custom');
    expect($first['props']['selectedCompanyId'])->toBe($company->id);
});

// -------------------------------------------------------------------- audit

test('exactly one analytics viewed audit row is written for the employer scope', function (): void {
    $employer = User::factory()->approvedEmployer()->create();
    $company = employerAnalyticsCompany($employer);
    employerAnalyticsJob($company);

    $this->actingAs($employer)
        ->get(route('employer.analytics.index'))
        ->assertOk();

    $entries = AuditLog::query()
        ->where('action', AuditAction::AnalyticsViewed->value)
        ->get();

    expect($entries)->toHaveCount(1);

    $entry = $entries->first();

    expect($entry->target_type)->toBe(AuditTargetType::Analytics->value);
    expect($entry->target_label)->toBe('employer');
    expect($entry->actor_id)->toBe($employer->id);
    expect($entry->metadata['scope'])->toBe('employer');
    expect($entry->metadata['company_ids'])->toBe([$company->id]);
    expect($entry->metadata['company_count'])->toBe(1);
    expect($entry->metadata['from'])->toBe(now()->subDays(29)->toDateString());
    expect($entry->metadata['to'])->toBe(now()->toDateString());
    expect($entry->metadata['window'])->toBe('bounded');
});
