<?php

namespace Tests\Feature\Employer;

use App\Enums\JobApplicationStatus;
use App\Models\ApplicationConversation;
use App\Models\ApplicationEvaluation;
use App\Models\ApplicationNote;
use App\Models\CandidateProfile;
use App\Models\CandidateResume;
use App\Models\Company;
use App\Models\CompanyMember;
use App\Models\ConversationMessage;
use App\Models\Job;
use App\Models\JobApplication;
use App\Models\User;
use App\Notifications\ApplicationStatusChangedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Authorization\Concerns\InteractsWithAuthorizationCatalogue;
use Tests\TestCase;

class RecruitmentPipelineTest extends TestCase
{
    use InteractsWithAuthorizationCatalogue, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAuthorizationCatalogue();
    }

    // ---------------------------------------------------------------- helpers

    /**
     * @return array{employer: User, company: Company, job: Job, candidate: User, application: JobApplication}
     */
    private function context(string $status = 'submitted', string $candidateName = 'Ada Lovelace'): array
    {
        $employer = User::factory()->approvedEmployer()->create();
        $company = Company::factory()->for($employer, 'owner')->create(['name' => 'Acme Corp']);
        $job = Job::factory()->for($company)->create(['title' => 'Senior Engineer']);
        $candidate = User::factory()->jobSeeker()->create(['name' => $candidateName]);

        CandidateProfile::factory()->for($candidate, 'user')->create([
            'headline' => 'Backend specialist',
            'location' => 'Lisbon',
        ]);

        $application = JobApplication::factory()
            ->for($job, 'jobPosting')
            ->for($candidate, 'user')
            ->create(['status' => $status]);

        return compact('employer', 'company', 'job', 'candidate', 'application');
    }

    private function member(User $user, Company $company, string $role = 'recruiter', string $status = 'active'): CompanyMember
    {
        return CompanyMember::create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'company_role' => $role,
            'status' => $status,
            'invited_at' => now(),
            'accepted_at' => now(),
        ]);
    }

    /**
     * Create additional applications for a job, each with its own candidate.
     */
    private function addApplications(Job $job, int $count, string $status = 'submitted'): void
    {
        foreach (range(1, $count) as $index) {
            $candidate = User::factory()->jobSeeker()->create();

            JobApplication::factory()
                ->for($job, 'jobPosting')
                ->for($candidate, 'user')
                ->create(['status' => $status]);
        }
    }

    // ---------------------------------------------------- board visibility

    public function test_owner_sees_the_board_grouped_by_status(): void
    {
        ['employer' => $employer, 'company' => $company, 'application' => $application] = $this->context();

        $this->actingAs($employer)
            ->get(route('employer.pipeline.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('employer/pipeline/index')
                ->where('board.company.id', $company->id)
                ->where('board.can_move', true)
                ->where('board.columns.0.status', 'submitted')
                ->where('board.columns.0.count', 1)
                ->where('board.columns.0.items.0.id', $application->id)
                ->where('board.columns.0.items.0.candidate.name', 'Ada Lovelace')
                ->where('board.columns.0.items.0.candidate.headline', 'Backend specialist')
                ->where('board.columns.0.items.0.job.title', 'Senior Engineer')
                ->where('board.status_counts.submitted', 1));
    }

    public function test_board_renders_every_status_column_in_pipeline_order(): void
    {
        ['employer' => $employer] = $this->context();

        $this->actingAs($employer)
            ->get(route('employer.pipeline.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('board.columns', 8)
                ->where('board.columns.0.status', 'submitted')
                ->where('board.columns.1.status', 'reviewing')
                ->where('board.columns.2.status', 'shortlisted')
                ->where('board.columns.3.status', 'interview_scheduled')
                ->where('board.columns.4.status', 'offer_sent')
                ->where('board.columns.5.status', 'hired')
                ->where('board.columns.6.status', 'rejected')
                ->where('board.columns.7.status', 'withdrawn'));
    }

    public function test_board_exposes_the_server_derived_transition_matrix(): void
    {
        ['employer' => $employer] = $this->context();

        $this->actingAs($employer)
            ->get(route('employer.pipeline.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('board.transition_matrix.submitted', ['reviewing', 'rejected'])
                ->where('board.transition_matrix.reviewing', ['shortlisted', 'rejected'])
                ->where('board.transition_matrix.hired', [])
                ->where('board.status_labels.interview_scheduled', 'Interview Scheduled'));
    }

    public function test_active_recruiter_member_sees_the_board_and_can_move(): void
    {
        ['company' => $company] = $this->context();
        $recruiter = User::factory()->approvedEmployer()->create();
        $this->member($recruiter, $company, 'recruiter');

        $this->actingAs($recruiter)
            ->get(route('employer.pipeline.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('board.company.id', $company->id)
                ->where('board.can_move', true));
    }

    public function test_reviewer_member_sees_a_read_only_board(): void
    {
        ['company' => $company] = $this->context();
        $reviewer = User::factory()->approvedEmployer()->create();
        $this->member($reviewer, $company, 'reviewer');

        $this->actingAs($reviewer)
            ->get(route('employer.pipeline.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('board.company.id', $company->id)
                ->where('board.can_move', false)
                ->where('board.columns.0.count', 1));
    }

    public function test_guest_is_redirected_from_the_board(): void
    {
        $this->get(route('employer.pipeline.index'))->assertRedirect(route('login'));
    }

    public function test_job_seeker_cannot_access_the_board(): void
    {
        $seeker = User::factory()->jobSeeker()->create();

        $this->actingAs($seeker)->get(route('employer.pipeline.index'))->assertForbidden();
    }

    public function test_pending_employer_cannot_access_the_board(): void
    {
        $pending = User::factory()->pendingEmployerApplicant()->create();

        $this->actingAs($pending)->get(route('employer.pipeline.index'))->assertForbidden();
    }

    public function test_suspended_employer_cannot_access_the_board(): void
    {
        $suspended = User::factory()->suspendedEmployer()->create();

        $this->actingAs($suspended)->get(route('employer.pipeline.index'))->assertForbidden();
    }

    public function test_platform_administrator_without_employer_rights_cannot_access_the_board(): void
    {
        $admin = User::factory()->platformAdmin()->create();

        $this->actingAs($admin)->get(route('employer.pipeline.index'))->assertForbidden();
    }

    public function test_employer_cannot_open_another_companys_board(): void
    {
        ['employer' => $employer] = $this->context();
        $otherOwner = User::factory()->approvedEmployer()->create();
        $otherCompany = Company::factory()->for($otherOwner, 'owner')->create();

        $this->actingAs($employer)
            ->get(route('employer.pipeline.index', ['company' => $otherCompany->id]))
            ->assertForbidden();
    }

    public function test_unknown_company_returns_not_found(): void
    {
        ['employer' => $employer] = $this->context();

        $this->actingAs($employer)
            ->get(route('employer.pipeline.index', ['company' => 999999]))
            ->assertNotFound();
    }

    public function test_board_never_exposes_applications_of_another_company(): void
    {
        ['employer' => $employer, 'company' => $company, 'application' => $application] = $this->context();

        $otherOwner = User::factory()->approvedEmployer()->create();
        $otherCompany = Company::factory()->for($otherOwner, 'owner')->create(['name' => 'Other Corp']);
        $otherJob = Job::factory()->for($otherCompany)->create(['title' => 'Secret Role']);
        $otherCandidate = User::factory()->jobSeeker()->create(['name' => 'Secret Candidate']);
        $otherApplication = JobApplication::factory()
            ->for($otherJob, 'jobPosting')
            ->for($otherCandidate, 'user')
            ->create(['status' => 'submitted']);

        $response = $this->actingAs($employer)->get(route('employer.pipeline.index'));

        $response->assertOk()->assertInertia(function (Assert $page) use ($application, $otherApplication): void {
            $page->where('board.status_counts.submitted', 1)
                ->where('board.columns.0.items.0.id', $application->id)
                ->where('board.jobs.0.title', 'Senior Engineer');

            /** @var array<int, array{id: int}> $cards */
            $cards = $page->toArray()['props']['board']['columns'][0]['items'];

            $this->assertNotContains(
                $otherApplication->id,
                array_column($cards, 'id')
            );
        });

        $content = $response->getContent();

        $this->assertStringNotContainsString('Secret Role', $content);
        $this->assertStringNotContainsString('Secret Candidate', $content);
    }

    public function test_board_defaults_to_the_first_authorized_company(): void
    {
        $employer = User::factory()->approvedEmployer()->create();
        $zulu = Company::factory()->for($employer, 'owner')->create(['name' => 'Zulu Ltd']);
        $alpha = Company::factory()->for($employer, 'owner')->create(['name' => 'Alpha Ltd']);

        $this->actingAs($employer)
            ->get(route('employer.pipeline.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('board.company.id', $alpha->id)
                ->where('board.companies.0.id', $alpha->id)
                ->where('board.companies.1.id', $zulu->id));
    }

    public function test_employer_without_any_company_sees_the_empty_board(): void
    {
        $employer = User::factory()->approvedEmployer()->create();

        $this->actingAs($employer)
            ->get(route('employer.pipeline.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('board.company', null)
                ->where('board.companies', [])
                ->where('board.can_move', false)
                ->where('board.status_counts.submitted', 0));
    }

    public function test_revoked_member_loses_board_access(): void
    {
        ['company' => $company] = $this->context();
        $recruiter = User::factory()->approvedEmployer()->create();
        $member = $this->member($recruiter, $company, 'recruiter');

        $this->actingAs($recruiter)->get(route('employer.pipeline.index'))->assertOk();

        $member->update(['status' => 'revoked']);

        $this->actingAs($recruiter)->get(route('employer.pipeline.index'))->assertForbidden();
    }

    // ------------------------------------------------- filters and counts

    public function test_board_filters_by_job(): void
    {
        ['employer' => $employer, 'company' => $company, 'job' => $job] = $this->context();

        $otherJob = Job::factory()->for($company)->create(['title' => 'Other Role']);
        $otherCandidate = User::factory()->jobSeeker()->create(['name' => 'Grace Hopper']);
        JobApplication::factory()
            ->for($otherJob, 'jobPosting')
            ->for($otherCandidate, 'user')
            ->create(['status' => 'submitted']);

        $this->actingAs($employer)
            ->get(route('employer.pipeline.index', ['job' => $job->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('board.filters.job_id', $job->id)
                ->where('board.status_counts.submitted', 1)
                ->where('board.columns.0.items.0.candidate.name', 'Ada Lovelace'));
    }

    public function test_job_filter_from_another_company_returns_not_found(): void
    {
        ['employer' => $employer] = $this->context();

        $otherOwner = User::factory()->approvedEmployer()->create();
        $otherCompany = Company::factory()->for($otherOwner, 'owner')->create();
        $otherJob = Job::factory()->for($otherCompany)->create();

        $this->actingAs($employer)
            ->get(route('employer.pipeline.index', ['job' => $otherJob->id]))
            ->assertNotFound();
    }

    public function test_board_filters_by_candidate_name(): void
    {
        ['employer' => $employer, 'company' => $company, 'job' => $job] = $this->context();

        $otherCandidate = User::factory()->jobSeeker()->create(['name' => 'Grace Hopper']);
        JobApplication::factory()
            ->for($job, 'jobPosting')
            ->for($otherCandidate, 'user')
            ->create(['status' => 'submitted']);

        $this->actingAs($employer)
            ->get(route('employer.pipeline.index', ['q' => 'Grace']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('board.filters.search', 'Grace')
                ->where('board.status_counts.submitted', 1)
                ->where('board.columns.0.items.0.candidate.name', 'Grace Hopper'));
    }

    public function test_board_filters_by_a_candidate_name_containing_a_space(): void
    {
        ['employer' => $employer, 'company' => $company, 'job' => $job] = $this->context('submitted', 'Ada Lovelace');

        $otherCandidate = User::factory()->jobSeeker()->create(['name' => 'Grace Hopper']);
        JobApplication::factory()
            ->for($job, 'jobPosting')
            ->for($otherCandidate, 'user')
            ->create(['status' => 'submitted']);

        foreach (['Ada Lovelace', 'ada lovelace', 'ADA LOVELACE', 'Lovelace'] as $term) {
            $this->actingAs($employer)
                ->get(route('employer.pipeline.index', [
                    'company' => $company->id,
                    'job' => $job->id,
                    'q' => $term,
                ]))
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->where('board.filters.search', $term)
                    ->where('board.status_counts.submitted', 1)
                    ->where('board.columns.0.items.0.candidate.name', 'Ada Lovelace'));
        }
    }

    public function test_board_search_combined_with_the_job_filter(): void
    {
        ['employer' => $employer, 'company' => $company, 'job' => $job] = $this->context('submitted', 'Ada Lovelace');

        $otherJob = Job::factory()->for($company)->create(['title' => 'Other Role']);
        $otherCandidate = User::factory()->jobSeeker()->create(['name' => 'Grace Hopper']);
        JobApplication::factory()
            ->for($otherJob, 'jobPosting')
            ->for($otherCandidate, 'user')
            ->create(['status' => 'submitted']);

        // The candidate did not apply to the filtered job: the combination is empty.
        $this->actingAs($employer)
            ->get(route('employer.pipeline.index', [
                'company' => $company->id,
                'job' => $job->id,
                'q' => 'Grace',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('board.status_counts.submitted', 0)
                ->where('board.columns.0.count', 0));

        // The candidate did apply to the filtered job: the combination matches.
        $this->actingAs($employer)
            ->get(route('employer.pipeline.index', [
                'company' => $company->id,
                'job' => $otherJob->id,
                'q' => 'Grace',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('board.status_counts.submitted', 1)
                ->where('board.columns.0.items.0.candidate.name', 'Grace Hopper'));
    }

    public function test_non_matching_search_yields_an_empty_board(): void
    {
        ['employer' => $employer] = $this->context();

        $this->actingAs($employer)
            ->get(route('employer.pipeline.index', ['q' => 'Nobody']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('board.status_counts.submitted', 0)
                ->where('board.columns.0.count', 0)
                ->where('board.columns.0.shown', 0)
                ->where('board.columns.0.has_more', false));
    }

    public function test_job_filter_options_ignore_the_job_filter_but_apply_the_search(): void
    {
        ['employer' => $employer, 'company' => $company, 'job' => $job] = $this->context();

        $otherJob = Job::factory()->for($company)->create(['title' => 'Other Role']);
        $otherCandidate = User::factory()->jobSeeker()->create(['name' => 'Grace Hopper']);
        JobApplication::factory()
            ->for($otherJob, 'jobPosting')
            ->for($otherCandidate, 'user')
            ->create(['status' => 'submitted']);

        $this->actingAs($employer)
            ->get(route('employer.pipeline.index', ['job' => $job->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('board.jobs', 2)
                ->where('board.jobs.0.title', 'Other Role')
                ->where('board.jobs.0.count', 1)
                ->where('board.jobs.1.title', 'Senior Engineer')
                ->where('board.jobs.1.count', 1));
    }

    public function test_board_uses_the_default_column_limit(): void
    {
        ['employer' => $employer, 'job' => $job] = $this->context();

        $this->addApplications($job, 30);

        $this->actingAs($employer)
            ->get(route('employer.pipeline.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('board.columns.0.count', 31)
                ->where('board.columns.0.shown', 25)
                ->where('board.columns.0.has_more', true));
    }

    public function test_column_limit_raises_only_the_targeted_column(): void
    {
        ['employer' => $employer, 'job' => $job] = $this->context();

        $this->addApplications($job, 30);

        $this->actingAs($employer)
            ->get(route('employer.pipeline.index', ['limit' => ['submitted' => 40]]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('board.columns.0.count', 31)
                ->where('board.columns.0.shown', 31)
                ->where('board.columns.0.has_more', false));
    }

    public function test_column_limit_above_the_maximum_is_clamped_server_side(): void
    {
        ['employer' => $employer, 'job' => $job] = $this->context();

        $this->addApplications($job, 30);

        $this->actingAs($employer)
            ->get(route('employer.pipeline.index', ['limit' => ['submitted' => 5000]]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('board.columns.0.count', 31)
                ->where('board.columns.0.shown', 31)
                ->where('board.columns.0.has_more', false));
    }

    public function test_invalid_filter_values_are_ignored_instead_of_failing(): void
    {
        ['employer' => $employer] = $this->context();

        $this->actingAs($employer)
            ->get(route('employer.pipeline.index', [
                'company' => 'not-an-id',
                'job' => 'not-an-id',
                'q' => str_repeat('a', 300),
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('board.filters.job_id', null)
                ->where('board.filters.search', str_repeat('a', 100))
                ->where('board.status_counts.submitted', 0));
    }

    public function test_unknown_limit_status_is_ignored(): void
    {
        ['employer' => $employer] = $this->context();

        $this->actingAs($employer)
            ->get(route('employer.pipeline.index', ['limit' => ['not_a_status' => 10]]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('board.columns.0.shown', 1));
    }

    // -------------------------------------------------------- transitions

    public function test_board_move_with_a_reason_records_the_reason_in_history(): void
    {
        ['employer' => $employer, 'application' => $application] = $this->context();

        $response = $this->actingAs($employer)
            ->postJson(route('employer.applications.update-status', $application), [
                'current_status' => 'submitted',
                'new_status' => 'reviewing',
                'reason' => 'Promising CV; phone screen next',
            ]);

        $response->assertOk()->assertJson([
            'application_id' => $application->id,
            'status' => 'reviewing',
        ]);

        $this->assertDatabaseHas('job_application_status_histories', [
            'job_application_id' => $application->id,
            'actor_id' => $employer->id,
            'from_status' => 'submitted',
            'to_status' => 'reviewing',
            'reason' => 'Promising CV; phone screen next',
        ]);
    }

    public function test_board_move_without_a_reason_records_no_reason(): void
    {
        ['employer' => $employer, 'application' => $application] = $this->context();

        $this->actingAs($employer)
            ->postJson(route('employer.applications.update-status', $application), [
                'current_status' => 'submitted',
                'new_status' => 'reviewing',
            ])
            ->assertOk()
            ->assertJson(['status' => 'reviewing']);

        $this->assertDatabaseHas('job_application_status_histories', [
            'job_application_id' => $application->id,
            'from_status' => 'submitted',
            'to_status' => 'reviewing',
            'reason' => null,
        ]);
    }

    public function test_board_move_updates_the_status_and_writes_history(): void
    {
        ['employer' => $employer, 'application' => $application] = $this->context();

        $response = $this->actingAs($employer)
            ->postJson(route('employer.applications.update-status', $application), [
                'current_status' => 'submitted',
                'new_status' => 'reviewing',
            ]);

        $response->assertOk()->assertJson([
            'application_id' => $application->id,
            'previous_status' => 'submitted',
            'status' => 'reviewing',
        ]);

        $this->assertEquals(JobApplicationStatus::Reviewing, $application->fresh()->status);

        $this->assertDatabaseHas('job_application_status_histories', [
            'job_application_id' => $application->id,
            'actor_id' => $employer->id,
            'from_status' => 'submitted',
            'to_status' => 'reviewing',
        ]);
    }

    public function test_board_move_notifies_the_candidate_exactly_once(): void
    {
        ['employer' => $employer, 'candidate' => $candidate, 'application' => $application] = $this->context();

        Notification::fake();

        $this->actingAs($employer)
            ->postJson(route('employer.applications.update-status', $application), [
                'current_status' => 'submitted',
                'new_status' => 'reviewing',
            ])
            ->assertOk();

        Notification::assertSentToTimes($candidate, ApplicationStatusChangedNotification::class, 1);
    }

    public function test_invalid_board_move_is_rejected_without_side_effects(): void
    {
        ['employer' => $employer, 'application' => $application] = $this->context();

        $response = $this->actingAs($employer)
            ->postJson(route('employer.applications.update-status', $application), [
                'current_status' => 'submitted',
                'new_status' => 'hired',
            ]);

        $response->assertStatus(422);

        $this->assertEquals(JobApplicationStatus::Submitted, $application->fresh()->status);
        $this->assertDatabaseCount('job_application_status_histories', 0);
    }

    public function test_terminal_application_cannot_be_moved(): void
    {
        ['employer' => $employer, 'application' => $application] = $this->context('hired');

        $this->actingAs($employer)
            ->get(route('employer.pipeline.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('board.transition_matrix.hired', []));

        $this->actingAs($employer)
            ->postJson(route('employer.applications.update-status', $application), [
                'current_status' => 'hired',
                'new_status' => 'reviewing',
            ])
            ->assertStatus(422);

        $this->assertEquals(JobApplicationStatus::Hired, $application->fresh()->status);
    }

    public function test_stale_board_move_returns_conflict(): void
    {
        ['employer' => $employer, 'application' => $application] = $this->context('reviewing');

        $response = $this->actingAs($employer)
            ->postJson(route('employer.applications.update-status', $application), [
                'current_status' => 'submitted',
                'new_status' => 'shortlisted',
            ]);

        $response->assertStatus(409);

        $this->assertEquals(JobApplicationStatus::Reviewing, $application->fresh()->status);
        $this->assertDatabaseCount('job_application_status_histories', 0);
    }

    public function test_reviewer_cannot_move_an_application(): void
    {
        ['company' => $company, 'application' => $application] = $this->context();
        $reviewer = User::factory()->approvedEmployer()->create();
        $this->member($reviewer, $company, 'reviewer');

        $this->actingAs($reviewer)
            ->postJson(route('employer.applications.update-status', $application), [
                'current_status' => 'submitted',
                'new_status' => 'reviewing',
            ])
            ->assertForbidden();

        $this->assertEquals(JobApplicationStatus::Submitted, $application->fresh()->status);
    }

    public function test_unrelated_employer_cannot_move_an_application(): void
    {
        ['application' => $application] = $this->context();
        $stranger = User::factory()->approvedEmployer()->create();

        $this->actingAs($stranger)
            ->postJson(route('employer.applications.update-status', $application), [
                'current_status' => 'submitted',
                'new_status' => 'reviewing',
            ])
            ->assertForbidden();

        $this->assertEquals(JobApplicationStatus::Submitted, $application->fresh()->status);
    }

    public function test_non_json_transition_request_still_redirects(): void
    {
        ['employer' => $employer, 'application' => $application] = $this->context();

        $this->actingAs($employer)
            ->post(route('employer.applications.update-status', $application), [
                'current_status' => 'submitted',
                'new_status' => 'reviewing',
            ])
            ->assertRedirect();

        $this->assertEquals(JobApplicationStatus::Reviewing, $application->fresh()->status);
    }

    // ------------------------------------------------------------ privacy

    public function test_board_payload_excludes_private_recruitment_data(): void
    {
        ['employer' => $employer, 'candidate' => $candidate, 'application' => $application] = $this->context();

        $noteBody = 'Confidential recruiter note body';
        $evaluationComment = 'Confidential evaluation comment';
        $coverLetter = 'Confidential cover letter text';
        $messageBody = 'Confidential message body';
        $resumeFilename = 'confidential-resume.pdf';

        ApplicationNote::create([
            'application_id' => $application->id,
            'author_id' => $employer->id,
            'body' => $noteBody,
        ]);

        ApplicationEvaluation::create([
            'application_id' => $application->id,
            'evaluator_id' => $employer->id,
            'relevant_experience' => 5,
            'technical_skills' => 4,
            'communication' => 3,
            'interview_performance' => 2,
            'comment' => $evaluationComment,
        ]);

        $resume = CandidateResume::factory()->create([
            'user_id' => $candidate->id,
            'original_filename' => $resumeFilename,
        ]);

        $application->update([
            'candidate_resume_id' => $resume->id,
            'cover_letter' => $coverLetter,
        ]);

        $conversation = ApplicationConversation::create([
            'application_id' => $application->id,
            'sort_at' => now(),
        ]);

        ConversationMessage::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $employer->id,
            'body' => $messageBody,
        ]);

        $response = $this->actingAs($employer)->get(route('employer.pipeline.index'));

        $response->assertOk();

        $content = $response->getContent();

        $this->assertStringNotContainsString($noteBody, $content);
        $this->assertStringNotContainsString($evaluationComment, $content);
        $this->assertStringNotContainsString($coverLetter, $content);
        $this->assertStringNotContainsString($messageBody, $content);
        $this->assertStringNotContainsString($resumeFilename, $content);
        $this->assertStringNotContainsString((string) $candidate->email, $content);
    }

    public function test_board_card_contains_only_whitelisted_fields(): void
    {
        ['employer' => $employer] = $this->context();

        $this->actingAs($employer)
            ->get(route('employer.pipeline.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('board.columns.0.items.0', function (mixed $card): bool {
                    $data = $card instanceof Collection ? $card->all() : $card;

                    if (! is_array($data)) {
                        return false;
                    }

                    return array_keys($data) === ['id', 'status', 'applied_at', 'candidate', 'job']
                        && array_keys($data['candidate']) === ['id', 'name', 'headline', 'location']
                        && array_keys($data['job']) === ['id', 'title', 'status'];
                }));
    }

    // --------------------------------------------------------- regression

    public function test_application_detail_page_still_renders_after_the_board_change(): void
    {
        ['employer' => $employer, 'application' => $application] = $this->context();

        $this->actingAs($employer)
            ->get(route('employer.applications.show', $application))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('employer/applications/show'));
    }

    public function test_applicant_list_page_still_renders(): void
    {
        ['employer' => $employer, 'job' => $job] = $this->context();

        $this->actingAs($employer)
            ->get(route('employer.jobs.applicants', $job))
            ->assertOk();
    }
}
