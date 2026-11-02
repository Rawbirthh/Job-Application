<?php

namespace Tests\Feature\Admin;

use App\Models\Company;
use App\Models\EmploymentType;
use App\Models\Job;
use App\Models\JobCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Authorization\Concerns\InteractsWithAuthorizationCatalogue;
use Tests\TestCase;

class JobModerationTest extends TestCase
{
    use InteractsWithAuthorizationCatalogue, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAuthorizationCatalogue();
    }

    public function test_platform_admin_with_moderation_permission_can_view_moderation_queue_and_job_details(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $employer = User::factory()->approvedEmployer()->create();
        $company = Company::factory()->create(['owner_user_id' => $employer->id, 'is_active' => true]);
        $category = JobCategory::factory()->create(['is_active' => true]);
        $employmentType = EmploymentType::factory()->create(['is_active' => true]);

        $job = Job::factory()->create([
            'company_id' => $company->id,
            'category_id' => $category->id,
            'employment_type_id' => $employmentType->id,
            'status' => 'pending_review',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.jobs.index', ['status' => 'pending_review']));
        $response->assertOk();

        $responseShow = $this->actingAs($admin)->get(route('admin.jobs.show', $job));
        $responseShow->assertOk();
    }

    public function test_admin_can_approve_pending_review_job(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $employer = User::factory()->approvedEmployer()->create();
        $company = Company::factory()->create(['owner_user_id' => $employer->id, 'is_active' => true]);
        $category = JobCategory::factory()->create(['is_active' => true]);
        $employmentType = EmploymentType::factory()->create(['is_active' => true]);

        $job = Job::factory()->create([
            'company_id' => $company->id,
            'category_id' => $category->id,
            'employment_type_id' => $employmentType->id,
            'status' => 'pending_review',
        ]);

        $response = $this->actingAs($admin)->post(route('admin.jobs.approve', $job));

        $response->assertRedirect(route('admin.jobs.index'));
        expect($job->fresh()->status)->toBe('published');
    }

    public function test_admin_can_reject_pending_review_job_with_reason(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $employer = User::factory()->approvedEmployer()->create();
        $company = Company::factory()->create(['owner_user_id' => $employer->id, 'is_active' => true]);
        $category = JobCategory::factory()->create(['is_active' => true]);
        $employmentType = EmploymentType::factory()->create(['is_active' => true]);

        $job = Job::factory()->create([
            'company_id' => $company->id,
            'category_id' => $category->id,
            'employment_type_id' => $employmentType->id,
            'status' => 'pending_review',
        ]);

        $response = $this->actingAs($admin)->post(route('admin.jobs.reject', $job), [
            'rejection_reason' => 'Violation of community standards.',
        ]);

        $response->assertRedirect(route('admin.jobs.index'));
        $fresh = $job->fresh();
        expect($fresh->status)->toBe('rejected')
            ->and($fresh->rejection_reason)->toBe('Violation of community standards.');
    }

    public function test_non_admin_is_denied_moderation_access(): void
    {
        $jobSeeker = User::factory()->jobSeeker()->create();
        $employer = User::factory()->approvedEmployer()->create();
        $company = Company::factory()->create(['owner_user_id' => $employer->id, 'is_active' => true]);
        $category = JobCategory::factory()->create(['is_active' => true]);
        $employmentType = EmploymentType::factory()->create(['is_active' => true]);

        $job = Job::factory()->create([
            'company_id' => $company->id,
            'category_id' => $category->id,
            'employment_type_id' => $employmentType->id,
            'status' => 'pending_review',
        ]);

        $this->actingAs($jobSeeker)->get(route('admin.jobs.index'))->assertForbidden();
        $this->actingAs($employer)->post(route('admin.jobs.approve', $job))->assertForbidden();
    }
}
