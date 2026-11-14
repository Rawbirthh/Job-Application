<?php

namespace Tests\Feature\Candidate;

use App\Models\CandidateResume;
use App\Models\Company;
use App\Models\Job;
use App\Models\JobApplication;
use App\Models\JobApplicationAnswer;
use App\Models\JobScreeningQuestion;
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
        $job = Job::factory()->for($company)->create([
            'status' => 'published',
            // Pin a far-future deadline so the apply form cannot reject on a clock boundary.
            'application_deadline' => now()->addDays(30),
        ]);

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

    public function test_apply_form_exposes_active_screening_questions(): void
    {
        $jobSeeker = User::factory()->jobSeeker()->create();
        $employer = User::factory()->approvedEmployer()->create();
        $company = Company::factory()->for($employer, 'owner')->create();
        $job = Job::factory()->for($company)->create(['status' => 'published']);

        $active = JobScreeningQuestion::create([
            'job_posting_id' => $job->id,
            'type' => 'short_text',
            'label' => 'Active question',
            'options' => null,
            'is_required' => true,
            'sort_order' => 0,
            'is_active' => true,
        ]);
        JobScreeningQuestion::create([
            'job_posting_id' => $job->id,
            'type' => 'short_text',
            'label' => 'Inactive question',
            'options' => null,
            'is_required' => false,
            'sort_order' => 1,
            'is_active' => false,
        ]);

        CandidateResume::factory()->for($jobSeeker, 'user')->create();

        $this->actingAs($jobSeeker)
            ->get(route('candidate.applications.create', $job))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('candidate/applications/create')
                ->has('screening_questions', 1)
                ->where('screening_questions.0.id', $active->id)
                ->where('screening_questions.0.label', 'Active question')
            );
    }

    public function test_successful_submission_persists_answers(): void
    {
        $jobSeeker = User::factory()->jobSeeker()->create();
        $employer = User::factory()->approvedEmployer()->create();
        $company = Company::factory()->for($employer, 'owner')->create();
        $job = Job::factory()->for($company)->create(['status' => 'published']);

        $qShort = JobScreeningQuestion::create([
            'job_posting_id' => $job->id,
            'type' => 'short_text',
            'label' => 'Short',
            'options' => null,
            'is_required' => true,
            'sort_order' => 0,
            'is_active' => true,
        ]);
        $qYesNo = JobScreeningQuestion::create([
            'job_posting_id' => $job->id,
            'type' => 'yes_no',
            'label' => 'YesNo',
            'options' => null,
            'is_required' => true,
            'sort_order' => 1,
            'is_active' => true,
        ]);
        $qChoice = JobScreeningQuestion::create([
            'job_posting_id' => $job->id,
            'type' => 'single_choice',
            'label' => 'Choice',
            'options' => ['A', 'B'],
            'is_required' => false,
            'sort_order' => 2,
            'is_active' => true,
        ]);

        $resume = CandidateResume::factory()->for($jobSeeker, 'user')->create();

        $this->actingAs($jobSeeker)
            ->post(route('candidate.applications.store', $job), [
                'candidate_resume_id' => $resume->id,
                'answers' => [
                    ['question_id' => $qShort->id, 'answer_text' => 'My answer'],
                    ['question_id' => $qYesNo->id, 'answer_boolean' => true],
                    ['question_id' => $qChoice->id, 'selected_option' => 'A'],
                ],
            ])
            ->assertRedirect();

        $application = JobApplication::where('user_id', $jobSeeker->id)->firstOrFail();
        $this->assertDatabaseHas('job_application_answers', [
            'job_application_id' => $application->id,
            'job_screening_question_id' => $qShort->id,
            'answer_text' => 'My answer',
        ]);
        $this->assertDatabaseHas('job_application_answers', [
            'job_application_id' => $application->id,
            'job_screening_question_id' => $qYesNo->id,
        ]);
        // boolean stored
        $ans = JobApplicationAnswer::where('job_application_id', $application->id)
            ->where('job_screening_question_id', $qYesNo->id)->first();
        $this->assertTrue($ans->answer_boolean);
        $this->assertDatabaseHas('job_application_answers', [
            'job_application_id' => $application->id,
            'job_screening_question_id' => $qChoice->id,
            'selected_option' => 'A',
        ]);
    }

    public function test_missing_required_answer_blocks_submission(): void
    {
        $jobSeeker = User::factory()->jobSeeker()->create();
        $employer = User::factory()->approvedEmployer()->create();
        $company = Company::factory()->for($employer, 'owner')->create();
        $job = Job::factory()->for($company)->create(['status' => 'published']);

        JobScreeningQuestion::create([
            'job_posting_id' => $job->id,
            'type' => 'short_text',
            'label' => 'Required Q',
            'options' => null,
            'is_required' => true,
            'sort_order' => 0,
            'is_active' => true,
        ]);

        $resume = CandidateResume::factory()->for($jobSeeker, 'user')->create();

        $this->actingAs($jobSeeker)
            ->post(route('candidate.applications.store', $job), [
                'candidate_resume_id' => $resume->id,
                'answers' => [],
            ])
            ->assertSessionHasErrors();

        $this->assertDatabaseCount('job_applications', 0);
    }

    public function test_type_specific_validation_rejects_invalid_answers(): void
    {
        $jobSeeker = User::factory()->jobSeeker()->create();
        $employer = User::factory()->approvedEmployer()->create();
        $company = Company::factory()->for($employer, 'owner')->create();
        $job = Job::factory()->for($company)->create(['status' => 'published']);

        $qShort = JobScreeningQuestion::create(['job_posting_id' => $job->id, 'type' => 'short_text', 'label' => 'Short', 'options' => null, 'is_required' => true, 'sort_order' => 0, 'is_active' => true]);
        $qLong = JobScreeningQuestion::create(['job_posting_id' => $job->id, 'type' => 'long_text', 'label' => 'Long', 'options' => null, 'is_required' => true, 'sort_order' => 1, 'is_active' => true]);
        $qChoice = JobScreeningQuestion::create(['job_posting_id' => $job->id, 'type' => 'single_choice', 'label' => 'Choice', 'options' => ['A', 'B'], 'is_required' => true, 'sort_order' => 2, 'is_active' => true]);
        $qYesNo = JobScreeningQuestion::create(['job_posting_id' => $job->id, 'type' => 'yes_no', 'label' => 'YN', 'options' => null, 'is_required' => true, 'sort_order' => 3, 'is_active' => true]);

        $resume = CandidateResume::factory()->for($jobSeeker, 'user')->create();

        // short text too long
        $this->actingAs($jobSeeker)->post(route('candidate.applications.store', $job), [
            'candidate_resume_id' => $resume->id,
            'answers' => [
                ['question_id' => $qShort->id, 'answer_text' => str_repeat('a', 256)],
                ['question_id' => $qLong->id, 'answer_text' => 'ok'],
                ['question_id' => $qChoice->id, 'selected_option' => 'A'],
                ['question_id' => $qYesNo->id, 'answer_boolean' => true],
            ],
        ])->assertSessionHasErrors();

        // long text too long
        $this->actingAs($jobSeeker)->post(route('candidate.applications.store', $job), [
            'candidate_resume_id' => $resume->id,
            'answers' => [
                ['question_id' => $qShort->id, 'answer_text' => 'ok'],
                ['question_id' => $qLong->id, 'answer_text' => str_repeat('a', 5001)],
                ['question_id' => $qChoice->id, 'selected_option' => 'A'],
                ['question_id' => $qYesNo->id, 'answer_boolean' => true],
            ],
        ])->assertSessionHasErrors();

        // invalid single choice
        $this->actingAs($jobSeeker)->post(route('candidate.applications.store', $job), [
            'candidate_resume_id' => $resume->id,
            'answers' => [
                ['question_id' => $qShort->id, 'answer_text' => 'ok'],
                ['question_id' => $qLong->id, 'answer_text' => 'ok'],
                ['question_id' => $qChoice->id, 'selected_option' => 'Z'],
                ['question_id' => $qYesNo->id, 'answer_boolean' => true],
            ],
        ])->assertSessionHasErrors();

        // yes/no non-boolean (string invalid)
        $this->actingAs($jobSeeker)->post(route('candidate.applications.store', $job), [
            'candidate_resume_id' => $resume->id,
            'answers' => [
                ['question_id' => $qShort->id, 'answer_text' => 'ok'],
                ['question_id' => $qLong->id, 'answer_text' => 'ok'],
                ['question_id' => $qChoice->id, 'selected_option' => 'A'],
                ['question_id' => $qYesNo->id, 'answer_boolean' => 'maybe'],
            ],
        ])->assertSessionHasErrors();

        $this->assertDatabaseCount('job_applications', 0);
    }

    public function test_duplicate_and_unknown_question_ids_rejected(): void
    {
        $jobSeeker = User::factory()->jobSeeker()->create();
        $employer = User::factory()->approvedEmployer()->create();
        $company = Company::factory()->for($employer, 'owner')->create();
        $job = Job::factory()->for($company)->create(['status' => 'published']);

        $q = JobScreeningQuestion::create(['job_posting_id' => $job->id, 'type' => 'short_text', 'label' => 'Q', 'options' => null, 'is_required' => true, 'sort_order' => 0, 'is_active' => true]);

        $resume = CandidateResume::factory()->for($jobSeeker, 'user')->create();

        // duplicate
        $this->actingAs($jobSeeker)->post(route('candidate.applications.store', $job), [
            'candidate_resume_id' => $resume->id,
            'answers' => [
                ['question_id' => $q->id, 'answer_text' => 'a'],
                ['question_id' => $q->id, 'answer_text' => 'b'],
            ],
        ])->assertSessionHasErrors();

        // unknown/inactive question (use a question from another job)
        $otherJob = Job::factory()->for($company)->create(['status' => 'published']);
        $foreignQ = JobScreeningQuestion::create(['job_posting_id' => $otherJob->id, 'type' => 'short_text', 'label' => 'Foreign', 'options' => null, 'is_required' => false, 'sort_order' => 0, 'is_active' => true]);
        $this->actingAs($jobSeeker)->post(route('candidate.applications.store', $job), [
            'candidate_resume_id' => $resume->id,
            'answers' => [
                ['question_id' => $foreignQ->id, 'answer_text' => 'a'],
            ],
        ])->assertSessionHasErrors();

        $this->assertDatabaseCount('job_applications', 0);
    }

    public function test_candidate_sees_own_answers_on_application_show(): void
    {
        $jobSeeker = User::factory()->jobSeeker()->create();
        $otherCandidate = User::factory()->jobSeeker()->create();
        $employer = User::factory()->approvedEmployer()->create();
        $company = Company::factory()->for($employer, 'owner')->create();
        $job = Job::factory()->for($company)->create([
            'status' => 'published',
            // Pin a far-future deadline so store validation cannot reject the apply on a clock boundary.
            'application_deadline' => now()->addDays(30),
        ]);

        $q = JobScreeningQuestion::create(['job_posting_id' => $job->id, 'type' => 'short_text', 'label' => 'Question label', 'options' => null, 'is_required' => true, 'sort_order' => 0, 'is_active' => true]);

        $resume = CandidateResume::factory()->for($jobSeeker, 'user')->create();
        $this->actingAs($jobSeeker)->post(route('candidate.applications.store', $job), [
            'candidate_resume_id' => $resume->id,
            'answers' => [['question_id' => $q->id, 'answer_text' => 'My answer']],
        ]);

        $application = JobApplication::where('user_id', $jobSeeker->id)->firstOrFail();

        $this->actingAs($jobSeeker)
            ->get(route('candidate.applications.show', $application))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('screening_answers', 1)
                ->where('screening_answers.0.question_label', 'Question label')
                ->where('screening_answers.0.answer_text', 'My answer')
            );

        $this->actingAs($otherCandidate)
            ->get(route('candidate.applications.show', $application))
            ->assertForbidden();
    }
}
