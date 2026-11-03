<?php

use App\Models\Company;
use App\Models\Job;
use App\Models\User;
use Database\Seeders\AuthorizationSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed(AuthorizationSeeder::class);
});

test('guest can view published job from approved active company', function (): void {
    $approvedEmployer = User::factory()->approvedEmployer()->create();
    $company = Company::factory()->create([
        'owner_user_id' => $approvedEmployer->id,
        'is_active' => true,
    ]);

    $job = Job::factory()->create([
        'company_id' => $company->id,
        'title' => 'Senior Software Engineer',
        'description' => 'We are looking for a senior software engineer.',
        'responsibilities' => 'Build and maintain software systems.',
        'requirements' => 'Bachelor degree in Computer Science.',
        'location' => 'San Francisco, CA',
        'status' => 'published',
    ]);

    $response = $this->get(route('jobs.show', $job));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('jobs/show')
            ->has('job')
            ->where('job.id', $job->id)
            ->where('job.title', 'Senior Software Engineer')
            ->where('job.description', 'We are looking for a senior software engineer.')
            ->where('job.responsibilities', 'Build and maintain software systems.')
            ->where('job.requirements', 'Bachelor degree in Computer Science.')
            ->where('job.location', 'San Francisco, CA')
            ->has('job.company')
            ->where('job.company.id', $company->id)
            ->where('job.company.name', $company->name)
            ->has('job.category')
            ->has('job.employment_type')
            ->has('job.workplace_arrangement')
            ->has('job.experience_level')
        );
});

test('authenticated job seeker can view published job', function (): void {
    $approvedEmployer = User::factory()->approvedEmployer()->create();
    $company = Company::factory()->create([
        'owner_user_id' => $approvedEmployer->id,
        'is_active' => true,
    ]);

    $job = Job::factory()->create([
        'company_id' => $company->id,
        'title' => 'Product Designer',
        'status' => 'published',
    ]);

    $jobSeeker = User::factory()->jobSeeker()->create();

    $response = $this->actingAs($jobSeeker)->get(route('jobs.show', $job));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('jobs/show')
            ->has('job')
            ->where('job.id', $job->id)
            ->where('job.title', 'Product Designer')
        );
});

test('draft job returns 404', function (): void {
    $approvedEmployer = User::factory()->approvedEmployer()->create();
    $company = Company::factory()->create([
        'owner_user_id' => $approvedEmployer->id,
        'is_active' => true,
    ]);

    $job = Job::factory()->create([
        'company_id' => $company->id,
        'status' => 'draft',
    ]);

    $this->get(route('jobs.show', $job))->assertNotFound();
});

test('pending review job returns 404', function (): void {
    $approvedEmployer = User::factory()->approvedEmployer()->create();
    $company = Company::factory()->create([
        'owner_user_id' => $approvedEmployer->id,
        'is_active' => true,
    ]);

    $job = Job::factory()->create([
        'company_id' => $company->id,
        'status' => 'pending_review',
    ]);

    $this->get(route('jobs.show', $job))->assertNotFound();
});

test('rejected job returns 404', function (): void {
    $approvedEmployer = User::factory()->approvedEmployer()->create();
    $company = Company::factory()->create([
        'owner_user_id' => $approvedEmployer->id,
        'is_active' => true,
    ]);

    $job = Job::factory()->create([
        'company_id' => $company->id,
        'status' => 'rejected',
        'rejection_reason' => 'Inappropriate content',
    ]);

    $this->get(route('jobs.show', $job))->assertNotFound();
});

test('closed job returns 404', function (): void {
    $approvedEmployer = User::factory()->approvedEmployer()->create();
    $company = Company::factory()->create([
        'owner_user_id' => $approvedEmployer->id,
        'is_active' => true,
    ]);

    $job = Job::factory()->create([
        'company_id' => $company->id,
        'status' => 'closed',
    ]);

    $this->get(route('jobs.show', $job))->assertNotFound();
});

test('published job from inactive company returns 404', function (): void {
    $approvedEmployer = User::factory()->approvedEmployer()->create();
    $company = Company::factory()->create([
        'owner_user_id' => $approvedEmployer->id,
        'is_active' => false,
    ]);

    $job = Job::factory()->create([
        'company_id' => $company->id,
        'status' => 'published',
    ]);

    $this->get(route('jobs.show', $job))->assertNotFound();
});

test('published job from company with unapproved owner returns 404', function (): void {
    $pendingEmployer = User::factory()->pendingEmployerApplicant()->create();
    $company = Company::factory()->create([
        'owner_user_id' => $pendingEmployer->id,
        'is_active' => true,
    ]);

    $job = Job::factory()->create([
        'company_id' => $company->id,
        'status' => 'published',
    ]);

    $this->get(route('jobs.show', $job))->assertNotFound();
});

test('published job from company with suspended owner returns 404', function (): void {
    $suspendedEmployer = User::factory()->suspendedEmployer()->create();
    $company = Company::factory()->create([
        'owner_user_id' => $suspendedEmployer->id,
        'is_active' => true,
    ]);

    $job = Job::factory()->create([
        'company_id' => $company->id,
        'status' => 'published',
    ]);

    $this->get(route('jobs.show', $job))->assertNotFound();
});

test('non-existent job returns 404', function (): void {
    $this->get(route('jobs.show', 99999))->assertNotFound();
});

test('response does not include rejection_reason', function (): void {
    $approvedEmployer = User::factory()->approvedEmployer()->create();
    $company = Company::factory()->create([
        'owner_user_id' => $approvedEmployer->id,
        'is_active' => true,
    ]);

    $job = Job::factory()->create([
        'company_id' => $company->id,
        'status' => 'published',
        'rejection_reason' => 'This should not be visible',
    ]);

    $response = $this->get(route('jobs.show', $job));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('jobs/show')
            ->missing('job.rejection_reason')
        );
});

test('response does not include owner_user_id', function (): void {
    $approvedEmployer = User::factory()->approvedEmployer()->create();
    $company = Company::factory()->create([
        'owner_user_id' => $approvedEmployer->id,
        'is_active' => true,
    ]);

    $job = Job::factory()->create([
        'company_id' => $company->id,
        'status' => 'published',
    ]);

    $response = $this->get(route('jobs.show', $job));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('jobs/show')
            ->missing('job.company.owner_user_id')
        );
});

test('job without responsibilities renders successfully', function (): void {
    $approvedEmployer = User::factory()->approvedEmployer()->create();
    $company = Company::factory()->create([
        'owner_user_id' => $approvedEmployer->id,
        'is_active' => true,
    ]);

    $job = Job::factory()->create([
        'company_id' => $company->id,
        'status' => 'published',
        'responsibilities' => null,
    ]);

    $response = $this->get(route('jobs.show', $job));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('jobs/show')
            ->where('job.responsibilities', null)
        );
});

test('job without requirements renders successfully', function (): void {
    $approvedEmployer = User::factory()->approvedEmployer()->create();
    $company = Company::factory()->create([
        'owner_user_id' => $approvedEmployer->id,
        'is_active' => true,
    ]);

    $job = Job::factory()->create([
        'company_id' => $company->id,
        'status' => 'published',
        'requirements' => null,
    ]);

    $response = $this->get(route('jobs.show', $job));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('jobs/show')
            ->where('job.requirements', null)
        );
});

test('job without salary but with negotiable flag renders successfully', function (): void {
    $approvedEmployer = User::factory()->approvedEmployer()->create();
    $company = Company::factory()->create([
        'owner_user_id' => $approvedEmployer->id,
        'is_active' => true,
    ]);

    $job = Job::factory()->create([
        'company_id' => $company->id,
        'status' => 'published',
        'salary_min' => null,
        'salary_max' => null,
        'salary_currency' => null,
        'salary_negotiable' => true,
        'pay_period_id' => null,
    ]);

    $response = $this->get(route('jobs.show', $job));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('jobs/show')
            ->where('job.salary_min', null)
            ->where('job.salary_max', null)
            ->where('job.salary_negotiable', true)
            ->where('job.pay_period', null)
        );
});

test('job without deadline renders successfully', function (): void {
    $approvedEmployer = User::factory()->approvedEmployer()->create();
    $company = Company::factory()->create([
        'owner_user_id' => $approvedEmployer->id,
        'is_active' => true,
    ]);

    $job = Job::factory()->create([
        'company_id' => $company->id,
        'status' => 'published',
        'application_deadline' => null,
    ]);

    $response = $this->get(route('jobs.show', $job));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('jobs/show')
            ->where('job.application_deadline', null)
        );
});

test('response includes all expected company fields', function (): void {
    $approvedEmployer = User::factory()->approvedEmployer()->create();
    $company = Company::factory()->create([
        'owner_user_id' => $approvedEmployer->id,
        'is_active' => true,
        'name' => 'Tech Corp',
        'overview' => 'A leading tech company',
        'website' => 'https://techcorp.com',
        'location' => 'New York, NY',
        'industry' => 'Technology',
        'size_band' => '51-200',
    ]);

    $job = Job::factory()->create([
        'company_id' => $company->id,
        'status' => 'published',
    ]);

    $response = $this->get(route('jobs.show', $job));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('jobs/show')
            ->where('job.company.name', 'Tech Corp')
            ->where('job.company.overview', 'A leading tech company')
            ->where('job.company.website', 'https://techcorp.com')
            ->where('job.company.location', 'New York, NY')
            ->where('job.company.industry', 'Technology')
            ->where('job.company.size_band', '51-200')
        );
});

test('response includes salary information when provided', function (): void {
    $approvedEmployer = User::factory()->approvedEmployer()->create();
    $company = Company::factory()->create([
        'owner_user_id' => $approvedEmployer->id,
        'is_active' => true,
    ]);

    $job = Job::factory()->withSalary()->create([
        'company_id' => $company->id,
        'status' => 'published',
        'salary_min' => '80000.00',
        'salary_max' => '120000.00',
        'salary_currency' => 'USD',
    ]);

    $response = $this->get(route('jobs.show', $job));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('jobs/show')
            ->where('job.salary_min', '80000.00')
            ->where('job.salary_max', '120000.00')
            ->where('job.salary_currency', 'USD')
            ->has('job.pay_period')
        );
});
