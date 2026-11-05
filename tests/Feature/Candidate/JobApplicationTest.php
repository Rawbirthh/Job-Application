<?php

namespace Tests\Feature\Candidate;

use App\Models\CandidateResume;
use App\Models\Company;
use App\Models\Job;
use App\Models\JobApplication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Authorization\Concerns\InteractsWithAuthorizationCatalogue;
use Tests\TestCase;

class JobApplicationTest extends TestCase
{
    use InteractsWithAuthorizationCatalogue, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAuthorizationCatalogue();
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $employer = User::factory()->approvedEmployer()->create();
        $company = Company::factory()->for($employer, 'owner')->create();
        $job = Job::factory()->for($company)->create(['status' => 'published']);

        $this->get(route('candidate.applications.create', $job))->assertRedirect(route('login'));
        $this->post(route('candidate.applications.store', $job))->assertRedirect(route('login'));
    }

    public function test_non_job_seekers_cannot_apply(): void
    {
        $employer = User::factory()->employer()->create();
        $company = Company::factory()->for($employer, 'owner')->create();
        $job = Job::factory()->for($company)->create(['status' => 'published']);

        $this->actingAs($employer)
            ->get(route('candidate.applications.create', $job))
            ->assertForbidden();

        $this->actingAs($employer)
            ->post(route('candidate.applications.store', $job))
            ->assertForbidden();
    }

    public function test_job_seeker_can_view_application_form(): void
    {
        $jobSeeker = User::factory()->jobSeeker()->create();
        $employer = User::factory()->approvedEmployer()->create();
        $company = Company::factory()->for($employer, 'owner')->create();
        $job = Job::factory()->for($company)->create(['status' => 'published']);

        CandidateResume::factory()->for($jobSeeker, 'user')->create();

        $this->actingAs($jobSeeker)
            ->get(route('candidate.applications.create', $job))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('candidate/applications/create')
                ->has('job')
                ->has('resumes')
                ->where('alreadyApplied', false)
            );
    }

    public function test_job_detail_reflects_has_applied_status(): void
    {
        $jobSeeker = User::factory()->jobSeeker()->create();
        $employer = User::factory()->approvedEmployer()->create();
        $company = Company::factory()->for($employer, 'owner')->create();
        $job = Job::factory()->for($company)->create(['status' => 'published']);

        $resume = CandidateResume::factory()->for($jobSeeker, 'user')->create();

        // Before applying
        $this->actingAs($jobSeeker)
            ->get(route('jobs.show', $job))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('jobs/show')
                ->where('job.has_applied', false)
            );

        // Submit application
        $this->actingAs($jobSeeker)
            ->post(route('candidate.applications.store', $job), [
                'candidate_resume_id' => $resume->id,
            ]);

        // After applying
        $this->actingAs($jobSeeker)
            ->get(route('jobs.show', $job))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('jobs/show')
                ->where('job.has_applied', true)
            );
    }

    public function test_cannot_apply_to_unpublished_or_expired_job(): void
    {
        $jobSeeker = User::factory()->jobSeeker()->create();
        $employer = User::factory()->approvedEmployer()->create();
        $company = Company::factory()->for($employer, 'owner')->create();

        $draftJob = Job::factory()->for($company)->create(['status' => 'draft']);
        $expiredJob = Job::factory()->for($company)->create([
            'status' => 'published',
            'application_deadline' => now()->subDay(),
        ]);

        $resume = CandidateResume::factory()->for($jobSeeker, 'user')->create();

        $this->actingAs($jobSeeker)
            ->post(route('candidate.applications.store', $draftJob), [
                'candidate_resume_id' => $resume->id,
            ])
            ->assertSessionHasErrors('job_posting_id');

        $this->actingAs($jobSeeker)
            ->post(route('candidate.applications.store', $expiredJob), [
                'candidate_resume_id' => $resume->id,
            ])
            ->assertSessionHasErrors('job_posting_id');
    }

    public function test_cannot_apply_twice_to_same_job(): void
    {
        $jobSeeker = User::factory()->jobSeeker()->create();
        $employer = User::factory()->approvedEmployer()->create();
        $company = Company::factory()->for($employer, 'owner')->create();
        $job = Job::factory()->for($company)->create(['status' => 'published']);

        $resume = CandidateResume::factory()->for($jobSeeker, 'user')->create();

        JobApplication::create([
            'job_posting_id' => $job->id,
            'user_id' => $jobSeeker->id,
            'candidate_resume_id' => $resume->id,
            'status' => 'submitted',
        ]);

        $this->actingAs($jobSeeker)
            ->post(route('candidate.applications.store', $job), [
                'candidate_resume_id' => $resume->id,
            ])
            ->assertSessionHasErrors('job_posting_id');

        $this->assertDatabaseCount('job_applications', 1);
    }

    public function test_cannot_use_another_users_resume(): void
    {
        $jobSeeker = User::factory()->jobSeeker()->create();
        $otherCandidate = User::factory()->jobSeeker()->create();
        $employer = User::factory()->approvedEmployer()->create();
        $company = Company::factory()->for($employer, 'owner')->create();
        $job = Job::factory()->for($company)->create(['status' => 'published']);

        $otherResume = CandidateResume::factory()->for($otherCandidate, 'user')->create();

        $this->actingAs($jobSeeker)
            ->post(route('candidate.applications.store', $job), [
                'candidate_resume_id' => $otherResume->id,
            ])
            ->assertSessionHasErrors('candidate_resume_id');

        $this->assertDatabaseCount('job_applications', 0);
    }
}
