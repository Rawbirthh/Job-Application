<?php

use App\Enums\EmployerStatus;
use App\Models\Company;
use App\Models\Job;
use App\Models\JobCategory;
use App\Models\User;
use Database\Seeders\AuthorizationSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed(AuthorizationSeeder::class);
});

test('guests and users can view the public jobs index page', function (): void {
    $this->get(route('jobs.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('jobs/index')
            ->has('jobs')
            ->has('categories')
            ->has('employmentTypes')
            ->has('workplaceArrangements')
            ->has('experienceLevels')
            ->has('filters')
        );

    $user = User::factory()->jobSeeker()->create();
    $this->actingAs($user)
        ->get(route('jobs.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('jobs/index')
        );
});

test('public job listing only shows published jobs from approved and active employers', function (): void {
    $approvedEmployer = User::factory()->approvedEmployer()->create();
    $approvedCompany = Company::factory()->create([
        'owner_user_id' => $approvedEmployer->id,
        'is_active' => true,
    ]);

    $unapprovedEmployer = User::factory()->jobSeeker()->create();
    $unapprovedEmployer->forceFill(['status' => EmployerStatus::Pending->value])->save();
    $unapprovedCompany = Company::factory()->create([
        'owner_user_id' => $unapprovedEmployer->id,
        'is_active' => true,
    ]);

    $suspendedEmployer = User::factory()->approvedEmployer()->create();
    $suspendedEmployer->forceFill(['status' => EmployerStatus::Suspended->value])->save();
    $suspendedCompany = Company::factory()->create([
        'owner_user_id' => $suspendedEmployer->id,
        'is_active' => true,
    ]);

    // 1. Published job from approved company (should show)
    $publishedJob = Job::factory()->create([
        'company_id' => $approvedCompany->id,
        'title' => 'Published Software Engineer',
        'status' => 'published',
    ]);

    // 2. Draft job from approved company (should NOT show)
    Job::factory()->create([
        'company_id' => $approvedCompany->id,
        'title' => 'Draft Engineer',
        'status' => 'draft',
    ]);

    // 3. Published job from unapproved company (should NOT show)
    Job::factory()->create([
        'company_id' => $unapprovedCompany->id,
        'title' => 'Unapproved Company Job',
        'status' => 'published',
    ]);

    // 4. Published job from suspended company (should NOT show)
    Job::factory()->create([
        'company_id' => $suspendedCompany->id,
        'title' => 'Suspended Company Job',
        'status' => 'published',
    ]);

    $this->get(route('jobs.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('jobs/index')
            ->has('jobs.data', 1)
            ->where('jobs.data.0.id', $publishedJob->id)
            ->where('jobs.data.0.title', 'Published Software Engineer')
        );
});

test('public job listing filters by search and category', function (): void {
    $employer = User::factory()->approvedEmployer()->create();
    $company = Company::factory()->create([
        'owner_user_id' => $employer->id,
        'is_active' => true,
    ]);

    $categoryA = JobCategory::factory()->create(['name' => 'Engineering', 'is_active' => true]);
    $categoryB = JobCategory::factory()->create(['name' => 'Design', 'is_active' => true]);

    $job1 = Job::factory()->create([
        'company_id' => $company->id,
        'title' => 'Backend Developer',
        'category_id' => $categoryA->id,
        'status' => 'published',
    ]);

    $job2 = Job::factory()->create([
        'company_id' => $company->id,
        'title' => 'Product Designer',
        'category_id' => $categoryB->id,
        'status' => 'published',
    ]);

    // Search by title
    $this->get(route('jobs.index', ['search' => 'Backend']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('jobs.data', 1)
            ->where('jobs.data.0.id', $job1->id)
        );

    // Filter by category
    $this->get(route('jobs.index', ['category' => $categoryB->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('jobs.data', 1)
            ->where('jobs.data.0.id', $job2->id)
        );
});
