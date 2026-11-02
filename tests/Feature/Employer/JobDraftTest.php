<?php

namespace Tests\Feature\Employer;

use App\Models\Company;
use App\Models\EmploymentType;
use App\Models\ExperienceLevel;
use App\Models\Job;
use App\Models\JobCategory;
use App\Models\PayPeriod;
use App\Models\User;
use App\Models\WorkplaceArrangement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Authorization\Concerns\InteractsWithAuthorizationCatalogue;
use Tests\TestCase;

class JobDraftTest extends TestCase
{
    use InteractsWithAuthorizationCatalogue, RefreshDatabase;

    protected JobCategory $category;

    protected EmploymentType $employmentType;

    protected WorkplaceArrangement $workplaceArrangement;

    protected ExperienceLevel $experienceLevel;

    protected PayPeriod $payPeriod;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAuthorizationCatalogue();

        $this->category = JobCategory::factory()->create(['is_active' => true]);
        $this->employmentType = EmploymentType::factory()->create(['is_active' => true]);
        $this->workplaceArrangement = WorkplaceArrangement::factory()->create(['is_active' => true]);
        $this->experienceLevel = ExperienceLevel::factory()->create(['is_active' => true]);
        $this->payPeriod = PayPeriod::factory()->create(['is_active' => true]);
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $job = Job::factory()->create();

        $this->get(route('employer.jobs.create'))->assertRedirect(route('login'));
        $this->post(route('employer.jobs.store'), [])->assertRedirect(route('login'));
        $this->get(route('employer.jobs.edit', $job))->assertRedirect(route('login'));
        $this->put(route('employer.jobs.update', $job), [])->assertRedirect(route('login'));
    }

    public function test_job_seeker_cannot_access_job_draft_routes(): void
    {
        $user = User::factory()->jobSeeker()->create();
        $job = Job::factory()->create();

        $this->actingAs($user)->get(route('employer.jobs.create'))->assertForbidden();
        $this->actingAs($user)->post(route('employer.jobs.store'), [])->assertForbidden();
        $this->actingAs($user)->get(route('employer.jobs.edit', $job))->assertForbidden();
        $this->actingAs($user)->put(route('employer.jobs.update', $job), [])->assertForbidden();
    }

    public function test_non_approved_employer_cannot_access_job_draft_routes(): void
    {
        $user = User::factory()->employer()->create();
        $job = Job::factory()->create();

        $this->actingAs($user)->get(route('employer.jobs.create'))->assertForbidden();
        $this->actingAs($user)->post(route('employer.jobs.store'), [])->assertForbidden();
        $this->actingAs($user)->get(route('employer.jobs.edit', $job))->assertForbidden();
        $this->actingAs($user)->put(route('employer.jobs.update', $job), [])->assertForbidden();
    }

    public function test_approved_employer_can_view_create_form(): void
    {
        $user = User::factory()->approvedEmployer()->create();
        Company::factory()->create(['owner_user_id' => $user->id]);

        $this->actingAs($user)
            ->get(route('employer.jobs.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('employer/jobs/create')
                ->has('companies', 1)
                ->has('jobCategories')
                ->has('employmentTypes')
            );
    }

    public function test_approved_employer_can_create_job_draft(): void
    {
        $user = User::factory()->approvedEmployer()->create();
        $company = Company::factory()->create(['owner_user_id' => $user->id]);

        $response = $this->actingAs($user)->post(route('employer.jobs.store'), [
            'company_id' => $company->id,
            'title' => 'Software Engineer',
            'description' => 'We are looking for a great software engineer.',
            'responsibilities' => 'Build features.',
            'requirements' => 'Laravel experience.',
            'category_id' => $this->category->id,
            'employment_type_id' => $this->employmentType->id,
            'workplace_arrangement_id' => $this->workplaceArrangement->id,
            'experience_level_id' => $this->experienceLevel->id,
            'salary_min' => 100000,
            'salary_max' => 150000,
            'salary_currency' => 'USD',
            'salary_negotiable' => true,
            'pay_period_id' => $this->payPeriod->id,
            'location' => 'New York, NY',
            'application_deadline' => now()->addMonth()->toDateString(),
        ]);

        $job = Job::query()->latest('id')->first();

        $response->assertRedirect(route('employer.jobs.edit', $job));
        $this->assertDatabaseHas('job_postings', [
            'id' => $job->id,
            'company_id' => $company->id,
            'title' => 'Software Engineer',
            'status' => 'draft',
        ]);
    }

    public function test_status_is_server_controlled_on_create(): void
    {
        $user = User::factory()->approvedEmployer()->create();
        $company = Company::factory()->create(['owner_user_id' => $user->id]);

        $this->actingAs($user)->post(route('employer.jobs.store'), [
            'company_id' => $company->id,
            'title' => 'Software Engineer',
            'description' => 'Description here.',
            'category_id' => $this->category->id,
            'employment_type_id' => $this->employmentType->id,
            'status' => 'published', // Attempt forgery
        ]);

        $job = Job::query()->latest('id')->first();
        $this->assertEquals('draft', $job->status);
    }

    public function test_forged_company_id_returns_404(): void
    {
        $user = User::factory()->approvedEmployer()->create();
        $otherUser = User::factory()->approvedEmployer()->create();
        $otherCompany = Company::factory()->create(['owner_user_id' => $otherUser->id]);

        $this->actingAs($user)->post(route('employer.jobs.store'), [
            'company_id' => $otherCompany->id,
            'title' => 'Software Engineer',
            'description' => 'Description here.',
            'category_id' => $this->category->id,
            'employment_type_id' => $this->employmentType->id,
        ])->assertNotFound();
    }

    public function test_approved_employer_can_edit_and_update_own_draft(): void
    {
        $user = User::factory()->approvedEmployer()->create();
        $company = Company::factory()->create(['owner_user_id' => $user->id]);
        $job = Job::factory()->create([
            'company_id' => $company->id,
            'status' => 'draft',
            'title' => 'Old Title',
        ]);

        $this->actingAs($user)
            ->get(route('employer.jobs.edit', $job))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('employer/jobs/edit')
                ->where('job.id', $job->id)
            );

        $response = $this->actingAs($user)->put(route('employer.jobs.update', $job), [
            'title' => 'Updated Title',
            'description' => $job->description,
            'category_id' => $job->category_id,
            'employment_type_id' => $job->employment_type_id,
        ]);

        $response->assertRedirect(route('employer.jobs.edit', $job));
        $this->assertDatabaseHas('job_postings', [
            'id' => $job->id,
            'title' => 'Updated Title',
        ]);
    }

    public function test_non_owner_cannot_edit_or_update_job_draft(): void
    {
        $user = User::factory()->approvedEmployer()->create();
        $otherUser = User::factory()->approvedEmployer()->create();
        $otherCompany = Company::factory()->create(['owner_user_id' => $otherUser->id]);
        $job = Job::factory()->create([
            'company_id' => $otherCompany->id,
            'status' => 'draft',
        ]);

        $this->actingAs($user)->get(route('employer.jobs.edit', $job))->assertForbidden();
        $this->actingAs($user)->put(route('employer.jobs.update', $job), [
            'title' => 'Hacked Title',
            'description' => $job->description,
            'category_id' => $job->category_id,
            'employment_type_id' => $job->employment_type_id,
        ])->assertForbidden();
    }

    public function test_cannot_update_non_draft_job(): void
    {
        $user = User::factory()->approvedEmployer()->create();
        $company = Company::factory()->create(['owner_user_id' => $user->id]);
        $job = Job::factory()->create([
            'company_id' => $company->id,
            'status' => 'pending_review',
        ]);

        $this->actingAs($user)->put(route('employer.jobs.update', $job), [
            'title' => 'New Title',
            'description' => $job->description,
            'category_id' => $job->category_id,
            'employment_type_id' => $job->employment_type_id,
        ])->assertForbidden();
    }

    public function test_salary_validation_rules(): void
    {
        $user = User::factory()->approvedEmployer()->create();
        $company = Company::factory()->create(['owner_user_id' => $user->id]);

        // Salary min > max should fail
        $this->actingAs($user)->post(route('employer.jobs.store'), [
            'company_id' => $company->id,
            'title' => 'Software Engineer',
            'description' => 'Description here.',
            'category_id' => $this->category->id,
            'employment_type_id' => $this->employmentType->id,
            'salary_min' => 200000,
            'salary_max' => 100000,
            'salary_currency' => 'USD',
            'pay_period_id' => $this->payPeriod->id,
        ])->assertSessionHasErrors('salary_min');

        // Salary provided without currency should fail
        $this->actingAs($user)->post(route('employer.jobs.store'), [
            'company_id' => $company->id,
            'title' => 'Software Engineer',
            'description' => 'Description here.',
            'category_id' => $this->category->id,
            'employment_type_id' => $this->employmentType->id,
            'salary_min' => 100000,
            'salary_currency' => '',
            'pay_period_id' => $this->payPeriod->id,
        ])->assertSessionHasErrors('salary_currency');

        // Salary provided without pay period should fail
        $this->actingAs($user)->post(route('employer.jobs.store'), [
            'company_id' => $company->id,
            'title' => 'Software Engineer',
            'description' => 'Description here.',
            'category_id' => $this->category->id,
            'employment_type_id' => $this->employmentType->id,
            'salary_min' => 100000,
            'salary_currency' => 'USD',
            'pay_period_id' => '',
        ])->assertSessionHasErrors('pay_period_id');
    }

    public function test_company_id_is_immutable_on_update(): void
    {
        $user = User::factory()->approvedEmployer()->create();
        $companyA = Company::factory()->create(['owner_user_id' => $user->id]);
        $companyB = Company::factory()->create(['owner_user_id' => $user->id]);
        $job = Job::factory()->create([
            'company_id' => $companyA->id,
            'status' => 'draft',
        ]);

        $this->actingAs($user)->put(route('employer.jobs.update', $job), [
            'company_id' => $companyB->id,
            'title' => 'Updated Title',
            'description' => $job->description,
            'category_id' => $job->category_id,
            'employment_type_id' => $job->employment_type_id,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('job_postings', [
            'id' => $job->id,
            'company_id' => $companyA->id,
        ]);
    }
}
