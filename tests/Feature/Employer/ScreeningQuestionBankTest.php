<?php

namespace Tests\Feature\Employer;

use App\Models\Company;
use App\Models\Job;
use App\Models\ScreeningQuestion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Authorization\Concerns\InteractsWithAuthorizationCatalogue;
use Tests\TestCase;

class ScreeningQuestionBankTest extends TestCase
{
    use InteractsWithAuthorizationCatalogue, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAuthorizationCatalogue();
    }

    private function approvedEmployer(): User
    {
        $employer = User::factory()->approvedEmployer()->create();
        Company::factory()->for($employer, 'owner')->create();

        return $employer;
    }

    public function test_guests_cannot_access_bank_routes(): void
    {
        $owner = User::factory()->create();
        $question = ScreeningQuestion::create([
            'owner_user_id' => $owner->id,
            'type' => 'short_text',
            'label' => 'Guest question',
            'options' => null,
            'is_required' => false,
            'is_active' => true,
        ]);

        $this->get(route('employer.screening-questions.index'))->assertRedirect(route('login'));
        $this->post(route('employer.screening-questions.store'), [])->assertRedirect(route('login'));
        $this->put(route('employer.screening-questions.update', $question), [])->assertRedirect(route('login'));
        $this->delete(route('employer.screening-questions.destroy', $question))->assertRedirect(route('login'));
    }

    public function test_unapproved_employers_cannot_access_bank_routes(): void
    {
        $employer = User::factory()->employer()->create();
        $question = ScreeningQuestion::create([
            'owner_user_id' => $employer->id,
            'type' => 'short_text',
            'label' => 'Unapproved question',
            'options' => null,
            'is_required' => false,
            'is_active' => true,
        ]);

        $this->actingAs($employer)->get(route('employer.screening-questions.index'))->assertForbidden();
        $this->actingAs($employer)->post(route('employer.screening-questions.store'), [
            'type' => 'short_text', 'label' => 'Question label',
        ])->assertForbidden();
        $this->actingAs($employer)->put(route('employer.screening-questions.update', $question), [
            'type' => 'short_text', 'label' => 'Updated label',
        ])->assertForbidden();
        $this->actingAs($employer)->delete(route('employer.screening-questions.destroy', $question))->assertForbidden();
    }

    public function test_approved_employer_can_create_each_type(): void
    {
        $employer = $this->approvedEmployer();

        // short_text
        $this->actingAs($employer)->post(route('employer.screening-questions.store'), [
            'type' => 'short_text', 'label' => 'Short text question', 'is_required' => true, 'is_active' => true,
        ])->assertRedirect(route('employer.screening-questions.index'));
        $this->assertDatabaseHas('screening_questions', ['owner_user_id' => $employer->id, 'type' => 'short_text', 'label' => 'Short text question']);

        // long_text
        $this->actingAs($employer)->post(route('employer.screening-questions.store'), [
            'type' => 'long_text', 'label' => 'Long text question',
        ])->assertRedirect(route('employer.screening-questions.index'));

        // yes_no
        $this->actingAs($employer)->post(route('employer.screening-questions.store'), [
            'type' => 'yes_no', 'label' => 'Yes no question',
        ])->assertRedirect(route('employer.screening-questions.index'));

        // single_choice
        $this->actingAs($employer)->post(route('employer.screening-questions.store'), [
            'type' => 'single_choice', 'label' => 'Single choice question',
            'options' => ['Yes', 'No', 'Maybe'],
        ])->assertRedirect(route('employer.screening-questions.index'));

        $this->assertDatabaseCount('screening_questions', 4);
    }

    public function test_create_rejects_invalid_type_prompt_and_options(): void
    {
        $employer = $this->approvedEmployer();

        $this->actingAs($employer)->post(route('employer.screening-questions.store'), [
            'type' => 'invalid', 'label' => 'Valid label here',
        ])->assertSessionHasErrors('type');

        $this->actingAs($employer)->post(route('employer.screening-questions.store'), [
            'type' => 'short_text', 'label' => 'ab',
        ])->assertSessionHasErrors('label');

        // single_choice missing options
        $this->actingAs($employer)->post(route('employer.screening-questions.store'), [
            'type' => 'single_choice', 'label' => 'Choice question',
        ])->assertSessionHasErrors('options');

        // single_choice too few options
        $this->actingAs($employer)->post(route('employer.screening-questions.store'), [
            'type' => 'single_choice', 'label' => 'Choice question',
            'options' => ['Only one'],
        ])->assertSessionHasErrors('options');

        // non-single with options provided
        $this->actingAs($employer)->post(route('employer.screening-questions.store'), [
            'type' => 'short_text', 'label' => 'Short question',
            'options' => ['a', 'b'],
        ])->assertSessionHasErrors('options');

        // duplicate options
        $this->actingAs($employer)->post(route('employer.screening-questions.store'), [
            'type' => 'single_choice', 'label' => 'Choice question',
            'options' => ['Yes', 'Yes'],
        ])->assertSessionHasErrors('options.0');
    }

    public function test_approved_employer_can_update_and_deactivate_question(): void
    {
        $employer = $this->approvedEmployer();
        $question = ScreeningQuestion::create([
            'owner_user_id' => $employer->id,
            'type' => 'short_text',
            'label' => 'Original label',
            'options' => null,
            'is_required' => false,
            'is_active' => true,
        ]);

        $this->actingAs($employer)->put(route('employer.screening-questions.update', $question), [
            'type' => 'short_text',
            'label' => 'Updated label',
            'is_required' => true,
            'is_active' => false,
        ])->assertRedirect(route('employer.screening-questions.index'));

        $this->assertDatabaseHas('screening_questions', [
            'id' => $question->id,
            'label' => 'Updated label',
            'is_required' => 1,
            'is_active' => 0,
        ]);
    }

    public function test_hard_delete_unused_question_succeeds(): void
    {
        $employer = $this->approvedEmployer();
        $question = ScreeningQuestion::create([
            'owner_user_id' => $employer->id,
            'type' => 'short_text',
            'label' => 'Delete me',
            'options' => null,
            'is_required' => false,
            'is_active' => true,
        ]);

        $this->actingAs($employer)->delete(route('employer.screening-questions.destroy', $question))
            ->assertRedirect(route('employer.screening-questions.index'));

        $this->assertDatabaseMissing('screening_questions', ['id' => $question->id]);
    }

    public function test_hard_delete_blocked_when_referenced_by_template(): void
    {
        $employer = $this->approvedEmployer();
        $question = ScreeningQuestion::create([
            'owner_user_id' => $employer->id,
            'type' => 'short_text',
            'label' => 'Locked question',
            'options' => null,
            'is_required' => false,
            'is_active' => true,
        ]);

        // create template referencing it
        $this->actingAs($employer)->post(route('employer.screening-templates.store'), [
            'name' => 'Template one',
            'question_ids' => [$question->id],
        ])->assertRedirect();

        $this->actingAs($employer)->delete(route('employer.screening-questions.destroy', $question))
            ->assertRedirect()
            ->assertInertiaFlash('toast', [
                'type' => 'error',
                'message' => 'This question is in use and cannot be deleted. Deactivate it instead.',
            ]);
    }

    public function test_hard_delete_blocked_when_referenced_by_job(): void
    {
        $employer = $this->approvedEmployer();
        $company = Company::query()->where('owner_user_id', $employer->id)->first();
        $job = Job::factory()->for($company)->create(['status' => 'draft']);

        $question = ScreeningQuestion::create([
            'owner_user_id' => $employer->id,
            'type' => 'short_text',
            'label' => 'Job ref question',
            'options' => null,
            'is_required' => false,
            'is_active' => true,
        ]);

        $this->actingAs($employer)->post(route('employer.jobs.screening-questions.store', $job), [
            'question_id' => $question->id,
        ])->assertRedirect();

        $this->actingAs($employer)->delete(route('employer.screening-questions.destroy', $question))
            ->assertRedirect()
            ->assertInertiaFlash('toast', [
                'type' => 'error',
                'message' => 'This question is in use and cannot be deleted. Deactivate it instead.',
            ]);
    }

    public function test_cross_user_cannot_update_or_delete(): void
    {
        $employer1 = $this->approvedEmployer();
        $employer2 = $this->approvedEmployer();

        $question = ScreeningQuestion::create([
            'owner_user_id' => $employer1->id,
            'type' => 'short_text',
            'label' => 'Owner question',
            'options' => null,
            'is_required' => false,
            'is_active' => true,
        ]);

        $this->actingAs($employer2)->put(route('employer.screening-questions.update', $question), [
            'type' => 'short_text', 'label' => 'Hijacked',
        ])->assertForbidden();

        $this->actingAs($employer2)->delete(route('employer.screening-questions.destroy', $question))
            ->assertForbidden();
    }
}
