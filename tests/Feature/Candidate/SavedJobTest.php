<?php

namespace Tests\Feature\Candidate;

use App\Models\Company;
use App\Models\Job;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Authorization\Concerns\InteractsWithAuthorizationCatalogue;
use Tests\TestCase;

class SavedJobTest extends TestCase
{
    use InteractsWithAuthorizationCatalogue, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAuthorizationCatalogue();
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('candidate.saved-jobs.index'))->assertRedirect(route('login'));
        $this->post(route('candidate.saved-jobs.store'))->assertRedirect(route('login'));
    }

    public function test_non_job_seekers_cannot_access_saved_jobs(): void
    {
        $employer = User::factory()->employer()->create();

        $this->actingAs($employer)
            ->get(route('candidate.saved-jobs.index'))
            ->assertForbidden();
    }

    public function test_job_seeker_can_view_saved_jobs_index(): void
    {
        $jobSeeker = User::factory()->jobSeeker()->create();

        $this->actingAs($jobSeeker)
            ->get(route('candidate.saved-jobs.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('candidate/saved-jobs/index')
                ->has('savedJobs')
            );
    }

    public function test_job_seeker_can_save_and_unsave_published_job(): void
    {
        $jobSeeker = User::factory()->jobSeeker()->create();
        $employer = User::factory()->approvedEmployer()->create();
        $company = Company::factory()->for($employer, 'owner')->create();

        $job = Job::factory()->for($company)->create([
            'status' => 'published',
        ]);

        // Save job
        $response = $this->actingAs($jobSeeker)
            ->post(route('candidate.saved-jobs.store'), [
                'job_posting_id' => $job->id,
            ]);

        $response->assertRedirect(route('candidate.saved-jobs.index'));

        $this->assertDatabaseHas('saved_jobs', [
            'user_id' => $jobSeeker->id,
            'job_posting_id' => $job->id,
        ]);

        // Idempotent duplicate save
        $this->actingAs($jobSeeker)
            ->post(route('candidate.saved-jobs.store'), [
                'job_posting_id' => $job->id,
            ])
            ->assertRedirect(route('candidate.saved-jobs.index'));

        $this->assertDatabaseCount('saved_jobs', 1);

        // Unsave job
        $destroyResponse = $this->actingAs($jobSeeker)
            ->delete(route('candidate.saved-jobs.destroy', $job));

        $destroyResponse->assertRedirect(route('candidate.saved-jobs.index'));

        $this->assertDatabaseMissing('saved_jobs', [
            'user_id' => $jobSeeker->id,
            'job_posting_id' => $job->id,
        ]);
    }

    public function test_cannot_save_unpublished_or_closed_job(): void
    {
        $jobSeeker = User::factory()->jobSeeker()->create();
        $employer = User::factory()->approvedEmployer()->create();
        $company = Company::factory()->for($employer, 'owner')->create();

        $draftJob = Job::factory()->for($company)->create([
            'status' => 'draft',
        ]);

        $this->actingAs($jobSeeker)
            ->post(route('candidate.saved-jobs.store'), [
                'job_posting_id' => $draftJob->id,
            ])
            ->assertSessionHasErrors('job_posting_id');

        $this->assertDatabaseMissing('saved_jobs', [
            'user_id' => $jobSeeker->id,
            'job_posting_id' => $draftJob->id,
        ]);
    }
}
