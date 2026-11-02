<?php

use App\Models\Company;
use App\Models\Job;
use App\Models\User;
use Database\Seeders\AuthorizationSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed(AuthorizationSeeder::class);
});

test('guests are redirected to login when accessing employer job dashboard', function (): void {
    $this->get(route('employer.jobs.index'))->assertRedirect(route('login'));
});

test('job seekers cannot access employer job dashboard', function (): void {
    $user = User::factory()->jobSeeker()->create();

    $this->actingAs($user)->get(route('employer.jobs.index'))->assertForbidden();
});

test('unapproved employers cannot access employer job dashboard', function (): void {
    $user = User::factory()->employer()->create();

    $this->actingAs($user)->get(route('employer.jobs.index'))->assertForbidden();
});

test('approved employer can view their company job dashboard', function (): void {
    $employer = User::factory()->approvedEmployer()->create();
    $company = Company::factory()->create(['owner_user_id' => $employer->id, 'is_active' => true]);
    $job = Job::factory()->create([
        'company_id' => $company->id,
        'title' => 'Senior Backend Engineer',
        'status' => 'draft',
    ]);

    $this->actingAs($employer)
        ->get(route('employer.jobs.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('employer/jobs/index')
            ->has('jobs.data', 1)
            ->where('jobs.data.0.id', $job->id)
            ->where('jobs.data.0.title', 'Senior Backend Engineer')
            ->has('companies', 1)
            ->where('companies.0.id', $company->id)
            ->has('filters')
        );
});

test('employer job listing is strictly scoped to owned companies and prevents IDOR', function (): void {
    $employerA = User::factory()->approvedEmployer()->create();
    $companyA = Company::factory()->create(['owner_user_id' => $employerA->id, 'is_active' => true]);
    $jobA = Job::factory()->create([
        'company_id' => $companyA->id,
        'title' => 'Company A Job',
    ]);

    $employerB = User::factory()->approvedEmployer()->create();
    $companyB = Company::factory()->create(['owner_user_id' => $employerB->id, 'is_active' => true]);
    $jobB = Job::factory()->create([
        'company_id' => $companyB->id,
        'title' => 'Company B Job',
    ]);

    // Employer A should only see Company A's job
    $this->actingAs($employerA)
        ->get(route('employer.jobs.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('employer/jobs/index')
            ->has('jobs.data', 1)
            ->where('jobs.data.0.id', $jobA->id)
            ->has('companies', 1)
            ->where('companies.0.id', $companyA->id)
        );

    // Employer A tries to filter by Company B's ID (IDOR attempt)
    $this->actingAs($employerA)
        ->get(route('employer.jobs.index', ['company_id' => $companyB->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('employer/jobs/index')
            ->has('jobs.data', 0)
        );
});

test('filtering by status and searching by title work correctly', function (): void {
    $employer = User::factory()->approvedEmployer()->create();
    $company = Company::factory()->create(['owner_user_id' => $employer->id, 'is_active' => true]);

    $draftJob = Job::factory()->create([
        'company_id' => $company->id,
        'title' => 'Laravel Developer',
        'status' => 'draft',
    ]);

    $publishedJob = Job::factory()->create([
        'company_id' => $company->id,
        'title' => 'React Frontend Engineer',
        'status' => 'published',
    ]);

    // Filter by status=published
    $this->actingAs($employer)
        ->get(route('employer.jobs.index', ['status' => 'published']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('employer/jobs/index')
            ->has('jobs.data', 1)
            ->where('jobs.data.0.id', $publishedJob->id)
        );

    // Search by title 'Laravel'
    $this->actingAs($employer)
        ->get(route('employer.jobs.index', ['search' => 'Laravel']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('employer/jobs/index')
            ->has('jobs.data', 1)
            ->where('jobs.data.0.id', $draftJob->id)
        );
});

test('application counts and pagination behave correctly', function (): void {
    $employer = User::factory()->approvedEmployer()->create();
    $company = Company::factory()->create(['owner_user_id' => $employer->id, 'is_active' => true]);

    // Create 12 jobs to test pagination (per page = 10)
    Job::factory()->count(12)->create([
        'company_id' => $company->id,
        'status' => 'draft',
    ]);

    $this->actingAs($employer)
        ->get(route('employer.jobs.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('employer/jobs/index')
            ->has('jobs.data', 10)
            ->where('jobs.total', 12)
            ->has('jobs.data.0.applications_count')
        );

    // Test page 2
    $this->actingAs($employer)
        ->get(route('employer.jobs.index', ['page' => 2]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('employer/jobs/index')
            ->has('jobs.data', 2)
            ->where('jobs.current_page', 2)
        );
});
