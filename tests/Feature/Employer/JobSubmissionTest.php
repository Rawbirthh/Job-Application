<?php

use App\Models\Company;
use App\Models\EmploymentType;
use App\Models\Job;
use App\Models\JobCategory;
use App\Models\User;
use Database\Seeders\AuthorizationSeeder;

beforeEach(function (): void {
    $this->seed(AuthorizationSeeder::class);
});

test('approved employer can submit job draft for review', function (): void {
    $employer = User::factory()->approvedEmployer()->create();
    $company = Company::factory()->create(['owner_user_id' => $employer->id, 'is_active' => true]);
    $category = JobCategory::factory()->create(['is_active' => true]);
    $employmentType = EmploymentType::factory()->create(['is_active' => true]);

    $job = Job::factory()->create([
        'company_id' => $company->id,
        'category_id' => $category->id,
        'employment_type_id' => $employmentType->id,
        'status' => 'draft',
    ]);

    $response = $this->actingAs($employer)->post(route('employer.jobs.submit', $job));

    $response->assertRedirect();
    expect($job->fresh()->status)->toBe('pending_review');
});

test('approved employer can resubmit rejected job clearing rejection reason', function (): void {
    $employer = User::factory()->approvedEmployer()->create();
    $company = Company::factory()->create(['owner_user_id' => $employer->id, 'is_active' => true]);
    $category = JobCategory::factory()->create(['is_active' => true]);
    $employmentType = EmploymentType::factory()->create(['is_active' => true]);

    $job = Job::factory()->create([
        'company_id' => $company->id,
        'category_id' => $category->id,
        'employment_type_id' => $employmentType->id,
        'status' => 'rejected',
        'rejection_reason' => 'Needs more details.',
    ]);

    $response = $this->actingAs($employer)->post(route('employer.jobs.submit', $job));

    $response->assertRedirect();
    $fresh = $job->fresh();
    expect($fresh->status)->toBe('pending_review')
        ->and($fresh->rejection_reason)->toBeNull();
});

test('editing published job resets status to draft', function (): void {
    $employer = User::factory()->approvedEmployer()->create();
    $company = Company::factory()->create(['owner_user_id' => $employer->id, 'is_active' => true]);
    $category = JobCategory::factory()->create(['is_active' => true]);
    $employmentType = EmploymentType::factory()->create(['is_active' => true]);

    $job = Job::factory()->create([
        'company_id' => $company->id,
        'category_id' => $category->id,
        'employment_type_id' => $employmentType->id,
        'status' => 'published',
        'title' => 'Original Title',
    ]);

    $response = $this->actingAs($employer)->put(route('employer.jobs.update', $job), [
        'title' => 'Updated Title',
        'description' => $job->description,
        'category_id' => $category->id,
        'employment_type_id' => $employmentType->id,
    ]);

    $response->assertRedirect();
    $fresh = $job->fresh();
    expect($fresh->title)->toBe('Updated Title')
        ->and($fresh->status)->toBe('draft');
});

test('approved employer can close published job', function (): void {
    $employer = User::factory()->approvedEmployer()->create();
    $company = Company::factory()->create(['owner_user_id' => $employer->id, 'is_active' => true]);
    $category = JobCategory::factory()->create(['is_active' => true]);
    $employmentType = EmploymentType::factory()->create(['is_active' => true]);

    $job = Job::factory()->create([
        'company_id' => $company->id,
        'category_id' => $category->id,
        'employment_type_id' => $employmentType->id,
        'status' => 'published',
    ]);

    $response = $this->actingAs($employer)->post(route('employer.jobs.close', $job));

    $response->assertRedirect();
    expect($job->fresh()->status)->toBe('closed');
});

test('approved employer submitting job for review redirects to jobs index', function (): void {
    $employer = User::factory()->approvedEmployer()->create();
    $company = Company::factory()->create(['owner_user_id' => $employer->id, 'is_active' => true]);
    $category = JobCategory::factory()->create(['is_active' => true]);
    $employmentType = EmploymentType::factory()->create(['is_active' => true]);

    $job = Job::factory()->create([
        'company_id' => $company->id,
        'category_id' => $category->id,
        'employment_type_id' => $employmentType->id,
        'status' => 'draft',
    ]);

    $response = $this->actingAs($employer)->post(route('employer.jobs.submit', $job));
    $response->assertRedirect(route('employer.jobs.index'));
    expect($job->fresh()->status)->toBe('pending_review');
});
