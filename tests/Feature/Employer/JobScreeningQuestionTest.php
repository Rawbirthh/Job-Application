<?php

namespace Tests\Feature\Employer;

use App\Models\CandidateResume;
use App\Models\Company;
use App\Models\Job;
use App\Models\JobApplication;
use App\Models\JobApplicationAnswer;
use App\Models\JobScreeningQuestion;
use App\Models\ScreeningQuestion;
use App\Models\ScreeningQuestionTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Authorization\Concerns\InteractsWithAuthorizationCatalogue;
use Tests\TestCase;

class JobScreeningQuestionTest extends TestCase
{
    use InteractsWithAuthorizationCatalogue, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAuthorizationCatalogue();
    }

    private function approvedEmployerWithJob(string $status = 'draft'): array
    {
        $employer = User::factory()->approvedEmployer()->create();
        $company = Company::factory()->for($employer, 'owner')->create();
        $job = Job::factory()->for($company)->create(['status' => $status]);

        return [$employer, $company, $job];
    }

    private function bankQuestion(User $employer, string $label = 'Bank q'): ScreeningQuestion
    {
        return ScreeningQuestion::create([
            'owner_user_id' => $employer->id,
            'type' => 'short_text',
            'label' => $label,
            'options' => null,
            'is_required' => false,
            'is_active' => true,
        ]);
    }

    public function test_guests_cannot_access_job_question_routes(): void
    {
        [$employer, $company, $job] = $this->approvedEmployerWithJob();
        $question = JobScreeningQuestion::create([
            'job_posting_id' => $job->id,
            'type' => 'short_text',
            'label' => 'Q',
            'options' => null,
            'is_required' => false,
            'sort_order' => 0,
            'is_active' => true,
        ]);

        $this->get(route('employer.jobs.screening-questions.index', $job))->assertRedirect(route('login'));
        $this->post(route('employer.jobs.screening-questions.store', $job), [])->assertRedirect(route('login'));
        $this->post(route('employer.jobs.screening-questions.apply-template', $job), [])->assertRedirect(route('login'));
        $this->post(route('employer.jobs.screening-questions.reorder', $job), [])->assertRedirect(route('login'));
        $this->put(route('employer.jobs.screening-questions.update', $question), [])->assertRedirect(route('login'));
        $this->delete(route('employer.jobs.screening-questions.destroy', $question))->assertRedirect(route('login'));
    }

    public function test_unapproved_and_non_owner_and_cross_company_are_forbidden(): void
    {
        [$employer, $company, $job] = $this->approvedEmployerWithJob();
        $unapproved = User::factory()->employer()->create();
        Company::factory()->for($unapproved, 'owner')->create();

        $this->actingAs($unapproved)->get(route('employer.jobs.screening-questions.index', $job))->assertForbidden();

        $employer2 = User::factory()->approvedEmployer()->create();
        Company::factory()->for($employer2, 'owner')->create();
        $this->actingAs($employer2)->get(route('employer.jobs.screening-questions.index', $job))->assertForbidden();
    }

    public function test_closed_job_rejects_management(): void
    {
        [$employer, $company, $job] = $this->approvedEmployerWithJob('closed');

        $this->actingAs($employer)->get(route('employer.jobs.screening-questions.index', $job))->assertForbidden();
    }

    public function test_apply_template_copies_questions_in_order_and_accumulates(): void
    {
        [$employer, $company, $job] = $this->approvedEmployerWithJob();
        $q1 = ScreeningQuestion::create(['owner_user_id' => $employer->id, 'type' => 'short_text', 'label' => 'Q1', 'options' => null, 'is_required' => false, 'is_active' => true]);
        $q2 = ScreeningQuestion::create(['owner_user_id' => $employer->id, 'type' => 'yes_no', 'label' => 'Q2', 'options' => null, 'is_required' => true, 'is_active' => true]);

        $template = ScreeningQuestionTemplate::create(['owner_user_id' => $employer->id, 'name' => 'Tpl', 'description' => null, 'is_active' => true]);
        $template->questions()->attach($q1->id, ['sort_order' => 0]);
        $template->questions()->attach($q2->id, ['sort_order' => 1]);

        $this->actingAs($employer)->post(route('employer.jobs.screening-questions.apply-template', $job), [
            'template_id' => $template->id,
        ])->assertRedirect(route('employer.jobs.screening-questions.index', $job));

        $questions = $job->fresh()->screeningQuestions()->orderBy('sort_order')->get();
        $this->assertCount(2, $questions);
        $this->assertSame('Q1', $questions[0]->label);
        $this->assertSame('short_text', $questions[0]->type->value);
        $this->assertSame('Q2', $questions[1]->label);
        $this->assertNotNull($questions[0]->source_question_id);

        // apply again accumulates
        $this->actingAs($employer)->post(route('employer.jobs.screening-questions.apply-template', $job), [
            'template_id' => $template->id,
        ])->assertRedirect();
        $this->assertCount(4, $job->fresh()->screeningQuestions()->get());
    }

    public function test_template_edits_do_not_propagate_to_job_questions(): void
    {
        [$employer, $company, $job] = $this->approvedEmployerWithJob();
        $q1 = ScreeningQuestion::create(['owner_user_id' => $employer->id, 'type' => 'short_text', 'label' => 'Original label', 'options' => null, 'is_required' => false, 'is_active' => true]);

        $template = ScreeningQuestionTemplate::create(['owner_user_id' => $employer->id, 'name' => 'Tpl', 'description' => null, 'is_active' => true]);
        $template->questions()->attach($q1->id, ['sort_order' => 0]);

        $this->actingAs($employer)->post(route('employer.jobs.screening-questions.apply-template', $job), [
            'template_id' => $template->id,
        ])->assertRedirect();

        // edit bank question label
        $q1->update(['label' => 'Changed label']);

        $jobQ = $job->fresh()->screeningQuestions()->first();
        $this->assertSame('Original label', $jobQ->label);
    }

    public function test_add_from_bank_question_and_inline_new(): void
    {
        [$employer, $company, $job] = $this->approvedEmployerWithJob();
        $bank = $this->bankQuestion($employer, 'Bank label');

        // from bank
        $this->actingAs($employer)->post(route('employer.jobs.screening-questions.store', $job), [
            'question_id' => $bank->id,
        ])->assertRedirect(route('employer.jobs.screening-questions.index', $job));
        $this->assertDatabaseHas('job_screening_questions', ['job_posting_id' => $job->id, 'source_question_id' => $bank->id, 'label' => 'Bank label']);

        // inline new
        $this->actingAs($employer)->post(route('employer.jobs.screening-questions.store', $job), [
            'type' => 'single_choice',
            'label' => 'Inline choice',
            'options' => ['A', 'B'],
            'is_required' => true,
        ])->assertRedirect();
        $this->assertDatabaseHas('job_screening_questions', ['job_posting_id' => $job->id, 'label' => 'Inline choice', 'source_question_id' => null]);
    }

    public function test_store_requires_exactly_one_source(): void
    {
        [$employer, $company, $job] = $this->approvedEmployerWithJob();

        // neither
        $this->actingAs($employer)->post(route('employer.jobs.screening-questions.store', $job), [])
            ->assertSessionHasErrors('question_id');

        // both
        $bank = $this->bankQuestion($employer, 'Bank label');
        $this->actingAs($employer)->post(route('employer.jobs.screening-questions.store', $job), [
            'question_id' => $bank->id,
            'type' => 'short_text',
            'label' => 'Inline',
        ])->assertSessionHasErrors('question_id');
    }

    public function test_store_rejects_foreign_bank_question(): void
    {
        [$employer, $company, $job] = $this->approvedEmployerWithJob();
        $other = User::factory()->approvedEmployer()->create();
        Company::factory()->for($other, 'owner')->create();
        $foreignBank = ScreeningQuestion::create([
            'owner_user_id' => $other->id,
            'type' => 'short_text',
            'label' => 'Foreign',
            'options' => null,
            'is_required' => false,
            'is_active' => true,
        ]);

        $this->actingAs($employer)->post(route('employer.jobs.screening-questions.store', $job), [
            'question_id' => $foreignBank->id,
        ])->assertSessionHasErrors('question_id');
    }

    public function test_edit_job_question_and_locked_protection(): void
    {
        [$employer, $company, $job] = $this->approvedEmployerWithJob();
        $question = JobScreeningQuestion::create([
            'job_posting_id' => $job->id,
            'type' => 'short_text',
            'label' => 'Editable',
            'options' => null,
            'is_required' => false,
            'sort_order' => 0,
            'is_active' => true,
        ]);

        // edit freely
        $this->actingAs($employer)->put(route('employer.jobs.screening-questions.update', $question), [
            'type' => 'long_text',
            'label' => 'Edited label',
            'is_required' => true,
            'is_active' => false,
        ])->assertRedirect(route('employer.jobs.screening-questions.index', $job));

        $question->refresh();
        $this->assertSame('long_text', $question->type->value);
        $this->assertSame('Edited label', $question->label);

        // now create an answer to lock it
        $jobSeeker = User::factory()->jobSeeker()->create();
        $resume = CandidateResume::factory()->for($jobSeeker, 'user')->create();
        $application = JobApplication::factory()->for($job, 'jobPosting')->for($jobSeeker, 'user')->for($resume, 'candidateResume')->create();
        JobApplicationAnswer::create([
            'job_application_id' => $application->id,
            'job_screening_question_id' => $question->id,
            'answer_text' => 'answer',
        ]);

        // attempt to change type/label/options -> validation errors
        $this->actingAs($employer)->put(route('employer.jobs.screening-questions.update', $question), [
            'type' => 'short_text',
            'label' => 'Changed again',
            'is_required' => true,
            'is_active' => true,
        ])->assertSessionHasErrors(['type', 'label']);

        // is_active can still be changed
        $this->actingAs($employer)->put(route('employer.jobs.screening-questions.update', $question), [
            'type' => $question->type->value,
            'label' => $question->label,
            'is_required' => $question->is_required,
            'is_active' => false,
        ])->assertRedirect();
        $question->refresh();
        $this->assertFalse($question->is_active);
    }

    public function test_hard_delete_when_no_answers_and_deactivate_when_answers(): void
    {
        [$employer, $company, $job] = $this->approvedEmployerWithJob();
        $question = JobScreeningQuestion::create([
            'job_posting_id' => $job->id,
            'type' => 'short_text',
            'label' => 'Deletable',
            'options' => null,
            'is_required' => false,
            'sort_order' => 0,
            'is_active' => true,
        ]);

        $this->actingAs($employer)->delete(route('employer.jobs.screening-questions.destroy', $question))
            ->assertRedirect(route('employer.jobs.screening-questions.index', $job));
        $this->assertDatabaseMissing('job_screening_questions', ['id' => $question->id]);

        // answered question cannot be deleted
        $locked = JobScreeningQuestion::create([
            'job_posting_id' => $job->id,
            'type' => 'short_text',
            'label' => 'Locked',
            'options' => null,
            'is_required' => false,
            'sort_order' => 1,
            'is_active' => true,
        ]);
        $jobSeeker = User::factory()->jobSeeker()->create();
        $resume = CandidateResume::factory()->for($jobSeeker, 'user')->create();
        $application = JobApplication::factory()->for($job, 'jobPosting')->for($jobSeeker, 'user')->for($resume, 'candidateResume')->create();
        JobApplicationAnswer::create([
            'job_application_id' => $application->id,
            'job_screening_question_id' => $locked->id,
            'answer_text' => 'answer',
        ]);

        $this->actingAs($employer)->delete(route('employer.jobs.screening-questions.destroy', $locked))
            ->assertStatus(422);
    }

    public function test_reorder_persists(): void
    {
        [$employer, $company, $job] = $this->approvedEmployerWithJob();
        $q1 = JobScreeningQuestion::create(['job_posting_id' => $job->id, 'type' => 'short_text', 'label' => 'Q1', 'options' => null, 'is_required' => false, 'sort_order' => 0, 'is_active' => true]);
        $q2 = JobScreeningQuestion::create(['job_posting_id' => $job->id, 'type' => 'short_text', 'label' => 'Q2', 'options' => null, 'is_required' => false, 'sort_order' => 1, 'is_active' => true]);

        $this->actingAs($employer)->post(route('employer.jobs.screening-questions.reorder', $job), [
            'question_ids' => [$q2->id, $q1->id],
        ])->assertRedirect(route('employer.jobs.screening-questions.index', $job));

        $ordered = $job->fresh()->screeningQuestions()->orderBy('sort_order')->pluck('id')->all();
        $this->assertSame([$q2->id, $q1->id], $ordered);
    }

    public function test_cap_enforcement_fifty_per_job(): void
    {
        [$employer, $company, $job] = $this->approvedEmployerWithJob();
        // create 50 questions
        for ($i = 0; $i < 50; $i++) {
            JobScreeningQuestion::create([
                'job_posting_id' => $job->id,
                'type' => 'short_text',
                'label' => "Q{$i}",
                'options' => null,
                'is_required' => false,
                'sort_order' => $i,
                'is_active' => true,
            ]);
        }

        $this->actingAs($employer)->post(route('employer.jobs.screening-questions.store', $job), [
            'type' => 'short_text',
            'label' => 'Overflow',
        ])->assertSessionHasErrors();

        $bank = $this->bankQuestion($employer, 'Bank overflow');
        $this->actingAs($employer)->post(route('employer.jobs.screening-questions.store', $job), [
            'question_id' => $bank->id,
        ])->assertSessionHasErrors();

        // apply template also blocked
        $template = ScreeningQuestionTemplate::create(['owner_user_id' => $employer->id, 'name' => 'Tpl', 'description' => null, 'is_active' => true]);
        $bq = $this->bankQuestion($employer, 'Tpl q');
        $template->questions()->attach($bq->id, ['sort_order' => 0]);
        $this->actingAs($employer)->post(route('employer.jobs.screening-questions.apply-template', $job), [
            'template_id' => $template->id,
        ])->assertSessionHasErrors('template_id');
    }

    public function test_cross_user_cannot_reorder_or_apply_template(): void
    {
        [$employer, $company, $job] = $this->approvedEmployerWithJob();
        $q = JobScreeningQuestion::create(['job_posting_id' => $job->id, 'type' => 'short_text', 'label' => 'Q', 'options' => null, 'is_required' => false, 'sort_order' => 0, 'is_active' => true]);

        $employer2 = User::factory()->approvedEmployer()->create();
        Company::factory()->for($employer2, 'owner')->create();

        // reorder with valid ids owned by job; policy should deny
        $this->actingAs($employer2)->post(route('employer.jobs.screening-questions.reorder', $job), [
            'question_ids' => [$q->id],
        ])->assertForbidden();

        // apply template owned by employer2 to employer1's job -> policy deny
        $bank2 = ScreeningQuestion::create(['owner_user_id' => $employer2->id, 'type' => 'short_text', 'label' => 'B2', 'options' => null, 'is_required' => false, 'is_active' => true]);
        $template2 = ScreeningQuestionTemplate::create(['owner_user_id' => $employer2->id, 'name' => 'Tpl2', 'description' => null, 'is_active' => true]);
        $template2->questions()->attach($bank2->id, ['sort_order' => 0]);

        $this->actingAs($employer2)->post(route('employer.jobs.screening-questions.apply-template', $job), [
            'template_id' => $template2->id,
        ])->assertForbidden();
    }
}
