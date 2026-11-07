<?php

namespace Tests\Feature\Employer;

use App\Enums\JobApplicationStatus;
use App\Models\Company;
use App\Models\Job;
use App\Models\JobApplication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Authorization\Concerns\InteractsWithAuthorizationCatalogue;
use Tests\TestCase;

class JobApplicationStatusTransitionTest extends TestCase
{
    use InteractsWithAuthorizationCatalogue, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAuthorizationCatalogue();
    }

    public function test_authorized_employer_can_transition_application_status(): void
    {
        $employer = User::factory()->approvedEmployer()->create();
        $company = Company::factory()->for($employer, 'owner')->create();
        $job = Job::factory()->for($company)->create();
        $jobSeeker = User::factory()->jobSeeker()->create();
        $application = JobApplication::factory()->for($job, 'jobPosting')->for($jobSeeker, 'user')->create([
            'status' => JobApplicationStatus::Submitted,
        ]);

        $response = $this->actingAs($employer)
            ->post(route('employer.applications.update-status', $application), [
                'current_status' => 'submitted',
                'new_status' => 'reviewing',
                'reason' => 'Candidate looks promising.',
            ]);

        $response->assertRedirect();
        $this->assertEquals(JobApplicationStatus::Reviewing, $application->fresh()->status);

        $this->assertDatabaseHas('job_application_status_histories', [
            'job_application_id' => $application->id,
            'actor_id' => $employer->id,
            'from_status' => 'submitted',
            'to_status' => 'reviewing',
            'reason' => 'Candidate looks promising.',
        ]);
    }

    public function test_employer_cannot_perform_invalid_transition(): void
    {
        $employer = User::factory()->approvedEmployer()->create();
        $company = Company::factory()->for($employer, 'owner')->create();
        $job = Job::factory()->for($company)->create();
        $jobSeeker = User::factory()->jobSeeker()->create();
        $application = JobApplication::factory()->for($job, 'jobPosting')->for($jobSeeker, 'user')->create([
            'status' => JobApplicationStatus::Submitted,
        ]);

        $response = $this->actingAs($employer)
            ->post(route('employer.applications.update-status', $application), [
                'current_status' => 'submitted',
                'new_status' => 'hired', // Invalid direct jump from submitted to hired
            ]);

        $response->assertStatus(422);
        $this->assertEquals(JobApplicationStatus::Submitted, $application->fresh()->status);
    }

    public function test_unrelated_employer_cannot_transition_application(): void
    {
        $employer1 = User::factory()->approvedEmployer()->create();
        $employer2 = User::factory()->approvedEmployer()->create();
        $company1 = Company::factory()->for($employer1, 'owner')->create();
        $job1 = Job::factory()->for($company1)->create();
        $jobSeeker = User::factory()->jobSeeker()->create();
        $application = JobApplication::factory()->for($job1, 'jobPosting')->for($jobSeeker, 'user')->create([
            'status' => JobApplicationStatus::Submitted,
        ]);

        $response = $this->actingAs($employer2)
            ->post(route('employer.applications.update-status', $application), [
                'current_status' => 'submitted',
                'new_status' => 'reviewing',
            ]);

        $response->assertForbidden();
        $this->assertEquals(JobApplicationStatus::Submitted, $application->fresh()->status);
    }

    public function test_stale_update_returns_conflict(): void
    {
        $employer = User::factory()->approvedEmployer()->create();
        $company = Company::factory()->for($employer, 'owner')->create();
        $job = Job::factory()->for($company)->create();
        $jobSeeker = User::factory()->jobSeeker()->create();
        $application = JobApplication::factory()->for($job, 'jobPosting')->for($jobSeeker, 'user')->create([
            'status' => JobApplicationStatus::Reviewing, // already changed in DB
        ]);

        $response = $this->actingAs($employer)
            ->post(route('employer.applications.update-status', $application), [
                'current_status' => 'submitted', // stale client state
                'new_status' => 'shortlisted',
            ]);

        $response->assertStatus(409);
        $this->assertEquals(JobApplicationStatus::Reviewing, $application->fresh()->status);
    }
}
