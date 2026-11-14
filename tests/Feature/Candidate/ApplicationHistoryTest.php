<?php

namespace Tests\Feature\Candidate;

use App\Models\Company;
use App\Models\Job;
use App\Models\JobApplication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Authorization\Concerns\InteractsWithAuthorizationCatalogue;
use Tests\TestCase;

class ApplicationHistoryTest extends TestCase
{
    use InteractsWithAuthorizationCatalogue, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAuthorizationCatalogue();
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('candidate.applications.index'))->assertRedirect(route('login'));
    }

    public function test_non_job_seekers_cannot_view_application_history(): void
    {
        $employer = User::factory()->employer()->create();

        $this->actingAs($employer)
            ->get(route('candidate.applications.index'))
            ->assertForbidden();
    }

    public function test_job_seeker_can_view_their_application_history(): void
    {
        $jobSeeker = User::factory()->jobSeeker()->create();
        $employer = User::factory()->approvedEmployer()->create();
        $company = Company::factory()->for($employer, 'owner')->create();
        $jobs = Job::factory()->count(3)->for($company)->create(['status' => 'published']);

        foreach ($jobs as $job) {
            JobApplication::factory()->create([
                'user_id' => $jobSeeker->id,
                'job_posting_id' => $job->id,
            ]);
        }

        $this->actingAs($jobSeeker)
            ->get(route('candidate.applications.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('candidate/applications/index')
                ->has('applications.data', 3)
                ->has('pagination')
            );
    }

    public function test_applications_can_be_filtered_by_status(): void
    {
        $jobSeeker = User::factory()->jobSeeker()->create();
        $employer = User::factory()->approvedEmployer()->create();
        $company = Company::factory()->for($employer, 'owner')->create();
        $jobs = Job::factory()->count(3)->for($company)->create(['status' => 'published']);

        JobApplication::factory()->create([
            'user_id' => $jobSeeker->id,
            'job_posting_id' => $jobs[0]->id,
            'status' => 'shortlisted',
        ]);
        JobApplication::factory()->create([
            'user_id' => $jobSeeker->id,
            'job_posting_id' => $jobs[1]->id,
            'status' => 'rejected',
        ]);
        JobApplication::factory()->create([
            'user_id' => $jobSeeker->id,
            'job_posting_id' => $jobs[2]->id,
            'status' => 'shortlisted',
        ]);

        $this->actingAs($jobSeeker)
            ->get(route('candidate.applications.index', ['status' => 'shortlisted']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('candidate/applications/index')
                ->has('applications.data', 2)
                ->where('filters.status', 'shortlisted')
                ->where('statusCounts.shortlisted', 2)
                ->where('statusCounts.rejected', 1),
            );
    }

    public function test_status_filter_rejects_an_unknown_status(): void
    {
        $jobSeeker = User::factory()->jobSeeker()->create();

        $response = $this->actingAs($jobSeeker)
            ->from(route('candidate.applications.index'))
            ->get(route('candidate.applications.index', ['status' => 'definitely_not_a_status']));

        $response->assertRedirect(route('candidate.applications.index'));
        $response->assertSessionHasErrors('status');
    }

    public function test_unknown_status_filter_value_never_returns_an_arbitrary_sql_value(): void
    {
        $jobSeeker = User::factory()->jobSeeker()->create();
        $employer = User::factory()->approvedEmployer()->create();
        $company = Company::factory()->for($employer, 'owner')->create();
        $job = Job::factory()->for($company)->create(['status' => 'published']);

        JobApplication::factory()->create([
            'user_id' => $jobSeeker->id,
            'job_posting_id' => $job->id,
            'status' => 'submitted',
        ]);

        $this->actingAs($jobSeeker)
            ->get(route('candidate.applications.index', ['status' => 'withdrawn']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('applications.data', 0),
            );
    }

    public function test_application_history_is_scoped_to_the_authenticated_user(): void
    {
        $jobSeeker1 = User::factory()->jobSeeker()->create();
        $jobSeeker2 = User::factory()->jobSeeker()->create();
        $employer = User::factory()->approvedEmployer()->create();
        $company = Company::factory()->for($employer, 'owner')->create();
        $job = Job::factory()->for($company)->create(['status' => 'published']);

        JobApplication::factory()->create([
            'user_id' => $jobSeeker1->id,
            'job_posting_id' => $job->id,
        ]);

        $this->actingAs($jobSeeker2)
            ->get(route('candidate.applications.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('candidate/applications/index')
                ->has('applications.data', 0)
            );
    }

    public function test_job_seeker_can_view_application_details(): void
    {
        $jobSeeker = User::factory()->jobSeeker()->create();
        $employer = User::factory()->approvedEmployer()->create();
        $company = Company::factory()->for($employer, 'owner')->create();
        $job = Job::factory()->for($company)->create(['status' => 'published']);

        $application = JobApplication::factory()->create([
            'user_id' => $jobSeeker->id,
            'job_posting_id' => $job->id,
            'cover_letter' => 'Test cover letter',
        ]);

        $this->actingAs($jobSeeker)
            ->get(route('candidate.applications.show', $application))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('candidate/applications/show')
                ->where('application.id', $application->id)
                ->where('application.cover_letter', 'Test cover letter')
                ->has('job.company')
            );
    }

    public function test_job_seeker_cannot_view_others_application_details(): void
    {
        $jobSeeker1 = User::factory()->jobSeeker()->create();
        $jobSeeker2 = User::factory()->jobSeeker()->create();
        $employer = User::factory()->approvedEmployer()->create();
        $company = Company::factory()->for($employer, 'owner')->create();
        $job = Job::factory()->for($company)->create(['status' => 'published']);

        $application = JobApplication::factory()->create([
            'user_id' => $jobSeeker1->id,
            'job_posting_id' => $job->id,
        ]);

        $this->actingAs($jobSeeker2)
            ->get(route('candidate.applications.show', $application))
            ->assertForbidden();
    }

    public function test_candidate_sees_closed_job_status_in_applications_and_details(): void
    {
        $jobSeeker = User::factory()->jobSeeker()->create();
        $employer = User::factory()->approvedEmployer()->create();
        $company = Company::factory()->for($employer, 'owner')->create();
        $job = Job::factory()->for($company)->create(['status' => 'closed']);

        $application = JobApplication::factory()->create([
            'user_id' => $jobSeeker->id,
            'job_posting_id' => $job->id,
        ]);

        $this->actingAs($jobSeeker)
            ->get(route('candidate.applications.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('candidate/applications/index')
                ->where('applications.data.0.job_status', 'closed')
            );

        $this->actingAs($jobSeeker)
            ->get(route('candidate.applications.show', $application))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('candidate/applications/show')
                ->where('job.status', 'closed')
            );
    }
}
