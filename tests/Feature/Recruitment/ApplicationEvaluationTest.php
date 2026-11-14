<?php

namespace Tests\Feature\Recruitment;

use App\Enums\JobApplicationStatus;
use App\Models\ApplicationEvaluation;
use App\Models\Company;
use App\Models\CompanyMember;
use App\Models\Job;
use App\Models\JobApplication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Authorization\Concerns\InteractsWithAuthorizationCatalogue;
use Tests\TestCase;

class ApplicationEvaluationTest extends TestCase
{
    use InteractsWithAuthorizationCatalogue, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAuthorizationCatalogue();
    }

    public function test_owner_can_submit_an_evaluation(): void
    {
        [$application, $employer] = $this->context();

        $this->actingAs($employer)
            ->post(route('employer.applications.evaluations.store', $application), [
                'relevant_experience' => 4,
                'technical_skills' => 5,
                'communication' => 3,
                'interview_performance' => 4,
                'comment' => 'Strong systems thinking',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('application_evaluations', [
            'application_id' => $application->id,
            'evaluator_id' => $employer->id,
            'relevant_experience' => 4,
            'technical_skills' => 5,
            'communication' => 3,
            'interview_performance' => 4,
            'comment' => 'Strong systems thinking',
        ]);

        $this->actingAs($employer)
            ->get(route('employer.applications.show', $application))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('evaluations.0.ratings.relevant_experience', 4)
                ->where('evaluations.0.ratings.technical_skills', 5)
                ->where('evaluations.0.evaluator.name', $employer->name)
                ->where('evaluations.0.comment', 'Strong systems thinking'));
    }

    public function test_recruiter_member_can_submit_an_evaluation(): void
    {
        [$application] = $this->context();
        $recruiter = $this->memberOf($application->jobPosting->company, 'recruiter');

        $this->actingAs($recruiter)
            ->post(route('employer.applications.evaluations.store', $application), $this->validPayload())
            ->assertRedirect();

        $this->assertDatabaseHas('application_evaluations', [
            'application_id' => $application->id,
            'evaluator_id' => $recruiter->id,
        ]);
    }

    public function test_revoked_member_cannot_submit_evaluation(): void
    {
        [$application] = $this->context();
        $company = $application->jobPosting->company;
        $recruiter = $this->memberOf($company, 'recruiter');

        $this->actingAs($recruiter)
            ->post(route('employer.applications.evaluations.store', $application), $this->validPayload())
            ->assertRedirect();

        CompanyMember::query()
            ->where('company_id', $company->id)
            ->where('user_id', $recruiter->id)
            ->update(['status' => 'revoked']);

        $this->actingAs($recruiter)
            ->post(route('employer.applications.evaluations.store', $application), $this->validPayload())
            ->assertForbidden();

        $this->assertSame(1, ApplicationEvaluation::query()->where('application_id', $application->id)->count());
    }

    public function test_reviewer_can_read_but_not_submit_evaluation(): void
    {
        [$application, $employer] = $this->context();
        $reviewer = $this->memberOf($application->jobPosting->company, 'reviewer');

        ApplicationEvaluation::query()->create([
            ...$this->validRatings(),
            'application_id' => $application->id,
            'evaluator_id' => $employer->id,
        ]);

        $this->actingAs($reviewer)
            ->get(route('employer.applications.show', $application))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('evaluations', 1)
                ->where('can_manage_notes', false));

        $this->actingAs($reviewer)
            ->post(route('employer.applications.evaluations.store', $application), $this->validPayload())
            ->assertForbidden();
    }

    public function test_candidate_cannot_read_or_submit_evaluation(): void
    {
        [$application, $employer, $candidate] = $this->context();

        ApplicationEvaluation::query()->create([
            ...$this->validRatings(),
            'application_id' => $application->id,
            'evaluator_id' => $employer->id,
        ]);

        $this->actingAs($candidate)
            ->post(route('employer.applications.evaluations.store', $application), $this->validPayload())
            ->assertForbidden();

        $this->actingAs($candidate)
            ->get(route('candidate.applications.show', $application))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->missing('evaluations')
                ->missing('notes'));
    }

    public function test_guest_is_redirected_to_login(): void
    {
        [$application] = $this->context();

        $this->post(route('employer.applications.evaluations.store', $application), $this->validPayload())
            ->assertRedirect(route('login'));
    }

    public function test_cross_company_employer_cannot_submit_evaluation(): void
    {
        [$application] = $this->context();

        $outsider = User::factory()->approvedEmployer()->create();
        Company::factory()->create(['owner_user_id' => $outsider->id]);

        $this->actingAs($outsider)
            ->post(route('employer.applications.evaluations.store', $application), $this->validPayload())
            ->assertForbidden();

        $this->assertSame(0, ApplicationEvaluation::query()->where('application_id', $application->id)->count());
    }

    // ------------------------------------------------------------ validation

    public function test_missing_criteria_are_rejected(): void
    {
        [$application, $employer] = $this->context();

        $this->actingAs($employer)
            ->post(route('employer.applications.evaluations.store', $application), [])
            ->assertSessionHasErrors(['relevant_experience', 'technical_skills', 'communication', 'interview_performance']);
    }

    public function test_out_of_range_and_non_integer_ratings_are_rejected(): void
    {
        [$application, $employer] = $this->context();

        foreach ([0, 6, '3.5', '5', 2.5] as $bad) {
            $this->actingAs($employer)
                ->post(route('employer.applications.evaluations.store', $application), [
                    ...$this->validPayload(),
                    'relevant_experience' => $bad,
                ])
                ->assertSessionHasErrors('relevant_experience');
        }

        $this->assertSame(0, ApplicationEvaluation::query()->where('application_id', $application->id)->count());
    }

    public function test_comment_over_2000_characters_is_rejected(): void
    {
        [$application, $employer] = $this->context();

        $this->actingAs($employer)
            ->post(route('employer.applications.evaluations.store', $application), [
                ...$this->validPayload(),
                'comment' => str_repeat('a', 2001),
            ])
            ->assertSessionHasErrors('comment');
    }

    public function test_extra_keys_cannot_change_status_job_or_candidate(): void
    {
        [$application, $employer] = $this->context();
        $originalJobId = $application->job_posting_id;
        $originalUserId = $application->user_id;
        $originalStatus = $application->status;

        $this->actingAs($employer)
            ->post(route('employer.applications.evaluations.store', $application), [
                ...$this->validPayload(),
                'status' => 'hired',
                'job_id' => 999999,
                'candidate_id' => 999999,
            ])
            ->assertRedirect();

        $application->refresh();
        $this->assertSame($originalJobId, $application->job_posting_id);
        $this->assertSame($originalUserId, $application->user_id);
        $this->assertEquals($originalStatus, $application->status);
    }

    // ------------------------------------------------------------ behavior

    public function test_evaluation_does_not_change_status_or_notify_anyone(): void
    {
        Notification::fake();

        [$application, $employer, $candidate] = $this->context();
        $originalStatus = $application->status;

        $this->actingAs($employer)
            ->post(route('employer.applications.evaluations.store', $application), $this->validPayload())
            ->assertRedirect();

        $application->refresh();
        $this->assertEquals($originalStatus, $application->status);
        Notification::assertNothingSent();
    }

    public function test_evaluations_are_append_only_and_latest_first(): void
    {
        [$application, $employer] = $this->context();
        $otherEvaluator = $this->memberOf($application->jobPosting->company, 'recruiter');

        $this->actingAs($employer)
            ->post(route('employer.applications.evaluations.store', $application), [
                ...$this->validPayload(),
                'technical_skills' => 2,
                'comment' => 'First assessment',
            ]);

        $this->actingAs($otherEvaluator)
            ->post(route('employer.applications.evaluations.store', $application), [
                ...$this->validPayload(),
                'technical_skills' => 5,
                'comment' => 'Second assessment',
            ]);

        $this->assertSame(2, ApplicationEvaluation::query()->where('application_id', $application->id)->count());

        $this->actingAs($employer)
            ->get(route('employer.applications.show', $application))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('evaluations', 2)
                ->where('evaluations.0.comment', 'Second assessment')
                ->where('evaluations.0.evaluator.name', $otherEvaluator->name)
                ->where('evaluations.0.ratings.technical_skills', 5)
                ->where('evaluations.1.comment', 'First assessment'));

        // No update or delete routes exist for evaluations.
        $this->assertFalse(Route::has('employer.applications.evaluations.update'));
        $this->assertFalse(Route::has('employer.applications.evaluations.destroy'));
    }

    public function test_evaluations_can_be_submitted_on_terminal_applications_and_closed_jobs(): void
    {
        foreach ([JobApplicationStatus::Hired, JobApplicationStatus::Rejected, JobApplicationStatus::Withdrawn] as $status) {
            [$application, $employer] = $this->context($status);

            $this->actingAs($employer)
                ->post(route('employer.applications.evaluations.store', $application), $this->validPayload())
                ->assertRedirect();
        }

        [$application, $employer] = $this->context();
        $application->jobPosting->update(['status' => 'closed']);

        $this->actingAs($employer)
            ->post(route('employer.applications.evaluations.store', $application), $this->validPayload())
            ->assertRedirect();
    }

    // ------------------------------------------------------------ helpers

    /**
     * @return array<string, mixed>
     */
    private function validPayload(): array
    {
        return [
            'relevant_experience' => 4,
            'technical_skills' => 4,
            'communication' => 4,
            'interview_performance' => 4,
            'comment' => null,
        ];
    }

    /**
     * @return array<string, int>
     */
    private function validRatings(): array
    {
        return [
            'relevant_experience' => 4,
            'technical_skills' => 4,
            'communication' => 4,
            'interview_performance' => 4,
        ];
    }

    /**
     * @return array{0: JobApplication, 1: User, 2: User}
     */
    private function context(JobApplicationStatus $status = JobApplicationStatus::Submitted): array
    {
        $candidate = User::factory()->jobSeeker()->create();
        $employer = User::factory()->approvedEmployer()->create();
        $company = Company::factory()->create(['owner_user_id' => $employer->id]);
        $job = Job::factory()->for($company)->create(['status' => 'published']);

        $application = JobApplication::factory()->create([
            'user_id' => $candidate->id,
            'job_posting_id' => $job->id,
            'status' => $status->value,
        ]);
        $application->load(['user', 'jobPosting.company.owner']);

        return [$application, $employer, $candidate];
    }

    private function memberOf(Company $company, string $role): User
    {
        $member = User::factory()->approvedEmployer()->create();

        CompanyMember::create([
            'company_id' => $company->id,
            'user_id' => $member->id,
            'company_role' => $role,
            'status' => 'active',
            'invited_at' => now(),
            'accepted_at' => now(),
        ]);

        return $member;
    }
}
