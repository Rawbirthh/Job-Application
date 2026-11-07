<?php

namespace Tests\Feature\Candidate;

use App\Enums\JobApplicationStatus;
use App\Models\Company;
use App\Models\Job;
use App\Models\JobApplication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Authorization\Concerns\InteractsWithAuthorizationCatalogue;
use Tests\TestCase;

class JobApplicationWithdrawalTest extends TestCase
{
    use InteractsWithAuthorizationCatalogue, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAuthorizationCatalogue();
    }

    public function test_candidate_can_withdraw_own_application(): void
    {
        $jobSeeker = User::factory()->jobSeeker()->create();
        $employer = User::factory()->approvedEmployer()->create();
        $company = Company::factory()->for($employer, 'owner')->create();
        $job = Job::factory()->for($company)->create();
        $application = JobApplication::factory()->for($job, 'jobPosting')->for($jobSeeker, 'user')->create([
            'status' => JobApplicationStatus::Submitted,
        ]);

        $response = $this->actingAs($jobSeeker)
            ->post(route('candidate.applications.withdraw', $application), [
                'reason' => 'Accepted another role.',
            ]);

        $response->assertRedirect();
        $this->assertEquals(JobApplicationStatus::Withdrawn, $application->fresh()->status);

        $this->assertDatabaseHas('job_application_status_histories', [
            'job_application_id' => $application->id,
            'actor_id' => $jobSeeker->id,
            'from_status' => 'submitted',
            'to_status' => 'withdrawn',
            'reason' => 'Accepted another role.',
        ]);
    }

    public function test_candidate_cannot_withdraw_another_candidates_application(): void
    {
        $jobSeeker1 = User::factory()->jobSeeker()->create();
        $jobSeeker2 = User::factory()->jobSeeker()->create();
        $employer = User::factory()->approvedEmployer()->create();
        $company = Company::factory()->for($employer, 'owner')->create();
        $job = Job::factory()->for($company)->create();
        $application = JobApplication::factory()->for($job, 'jobPosting')->for($jobSeeker1, 'user')->create([
            'status' => JobApplicationStatus::Submitted,
        ]);

        $response = $this->actingAs($jobSeeker2)
            ->post(route('candidate.applications.withdraw', $application), [
                'reason' => 'Trying to withdraw someone else application.',
            ]);

        $response->assertForbidden();
        $this->assertEquals(JobApplicationStatus::Submitted, $application->fresh()->status);
    }

    public function test_candidate_cannot_withdraw_terminal_application(): void
    {
        $jobSeeker = User::factory()->jobSeeker()->create();
        $employer = User::factory()->approvedEmployer()->create();
        $company = Company::factory()->for($employer, 'owner')->create();
        $job = Job::factory()->for($company)->create();
        $application = JobApplication::factory()->for($job, 'jobPosting')->for($jobSeeker, 'user')->create([
            'status' => JobApplicationStatus::Rejected,
        ]);

        $response = $this->actingAs($jobSeeker)
            ->post(route('candidate.applications.withdraw', $application), [
                'reason' => 'Trying to withdraw rejected application.',
            ]);

        $response->assertStatus(422);
        $this->assertEquals(JobApplicationStatus::Rejected, $application->fresh()->status);
    }
}
