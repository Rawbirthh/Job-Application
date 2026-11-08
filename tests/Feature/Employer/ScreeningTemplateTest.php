<?php

namespace Tests\Feature\Employer;

use App\Models\Company;
use App\Models\Job;
use App\Models\ScreeningQuestion;
use App\Models\ScreeningQuestionTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Authorization\Concerns\InteractsWithAuthorizationCatalogue;
use Tests\TestCase;

class ScreeningTemplateTest extends TestCase
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

    private function bankQuestion(User $employer, string $label = 'Bank question'): ScreeningQuestion
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

    public function test_guests_cannot_access_template_routes(): void
    {
        $employer = User::factory()->create();
        $template = ScreeningQuestionTemplate::create([
            'owner_user_id' => $employer->id,
            'name' => 'Guest template',
            'description' => null,
            'is_active' => true,
        ]);

        $this->get(route('employer.screening-templates.index'))->assertRedirect(route('login'));
        $this->post(route('employer.screening-templates.store'), [])->assertRedirect(route('login'));
        $this->get(route('employer.screening-templates.show', $template))->assertRedirect(route('login'));
        $this->put(route('employer.screening-templates.update', $template), [])->assertRedirect(route('login'));
        $this->delete(route('employer.screening-templates.destroy', $template))->assertRedirect(route('login'));
    }

    public function test_unapproved_employers_cannot_access_template_routes(): void
    {
        $employer = User::factory()->employer()->create();
        $template = ScreeningQuestionTemplate::create([
            'owner_user_id' => $employer->id,
            'name' => 'Unapproved template',
            'description' => null,
            'is_active' => true,
        ]);

        $this->actingAs($employer)->get(route('employer.screening-templates.index'))->assertForbidden();
        $this->actingAs($employer)->post(route('employer.screening-templates.store'), [])->assertForbidden();
        $this->actingAs($employer)->get(route('employer.screening-templates.show', $template))->assertForbidden();
        $this->actingAs($employer)->put(route('employer.screening-templates.update', $template), [])->assertForbidden();
        $this->actingAs($employer)->delete(route('employer.screening-templates.destroy', $template))->assertForbidden();
    }

    public function test_approved_employer_can_create_template_with_ordered_questions(): void
    {
        $employer = $this->approvedEmployer();
        $q1 = $this->bankQuestion($employer, 'First question');
        $q2 = $this->bankQuestion($employer, 'Second question');

        $this->actingAs($employer)->post(route('employer.screening-templates.store'), [
            'name' => 'My template',
            'description' => 'A description',
            'question_ids' => [$q1->id, $q2->id],
        ])->assertRedirect(route('employer.screening-templates.index'));

        $template = ScreeningQuestionTemplate::where('owner_user_id', $employer->id)->firstOrFail();
        $this->assertSame('My template', $template->name);
        $this->assertSame('A description', $template->description);
        $this->assertTrue($template->is_active);

        $ordered = $template->questions()->pluck('screening_questions.id')->all();
        $this->assertSame([$q1->id, $q2->id], $ordered);
    }

    public function test_template_duplicate_questions_prevented(): void
    {
        $employer = $this->approvedEmployer();
        $q1 = $this->bankQuestion($employer, 'First question');

        $this->actingAs($employer)->post(route('employer.screening-templates.store'), [
            'name' => 'Dup template',
            'question_ids' => [$q1->id, $q1->id],
        ])->assertSessionHasErrors('question_ids');
    }

    public function test_template_rejects_invalid_question_ids_and_empty_set(): void
    {
        $employer = $this->approvedEmployer();
        $other = $this->approvedEmployer();
        $foreign = $this->bankQuestion($other, 'Foreign question');

        $this->actingAs($employer)->post(route('employer.screening-templates.store'), [
            'name' => 'Invalid template',
            'question_ids' => [],
        ])->assertSessionHasErrors('question_ids');

        $this->actingAs($employer)->post(route('employer.screening-templates.store'), [
            'name' => 'Invalid template',
            'question_ids' => [$foreign->id],
        ])->assertSessionHasErrors('question_ids');
    }

    public function test_approved_employer_can_update_template_name_description_and_reorder(): void
    {
        $employer = $this->approvedEmployer();
        $q1 = $this->bankQuestion($employer, 'First question');
        $q2 = $this->bankQuestion($employer, 'Second question');

        $template = ScreeningQuestionTemplate::create([
            'owner_user_id' => $employer->id,
            'name' => 'Original name',
            'description' => null,
            'is_active' => true,
        ]);
        $template->questions()->attach($q1->id, ['sort_order' => 0]);
        $template->questions()->attach($q2->id, ['sort_order' => 1]);

        $this->actingAs($employer)->put(route('employer.screening-templates.update', $template), [
            'name' => 'Updated name',
            'description' => 'Updated desc',
            'question_ids' => [$q2->id, $q1->id],
            'is_active' => false,
        ])->assertRedirect(route('employer.screening-templates.index'));

        $template->refresh();
        $this->assertSame('Updated name', $template->name);
        $this->assertSame('Updated desc', $template->description);
        $this->assertFalse($template->is_active);

        $ordered = $template->questions()->pluck('screening_questions.id')->all();
        $this->assertSame([$q2->id, $q1->id], $ordered);
    }

    public function test_approved_employer_can_delete_template(): void
    {
        $employer = $this->approvedEmployer();
        $template = ScreeningQuestionTemplate::create([
            'owner_user_id' => $employer->id,
            'name' => 'Delete me',
            'description' => null,
            'is_active' => true,
        ]);

        $this->actingAs($employer)->delete(route('employer.screening-templates.destroy', $template))
            ->assertRedirect(route('employer.screening-templates.index'));

        $this->assertDatabaseMissing('screening_question_templates', ['id' => $template->id]);
    }

    public function test_deleting_template_does_not_affect_job_questions(): void
    {
        $employer = $this->approvedEmployer();
        $company = Company::query()->where('owner_user_id', $employer->id)->first();
        $job = Job::factory()->for($company)->create(['status' => 'draft']);
        $q1 = $this->bankQuestion($employer, 'First question');

        $template = ScreeningQuestionTemplate::create([
            'owner_user_id' => $employer->id,
            'name' => 'Apply template',
            'description' => null,
            'is_active' => true,
        ]);
        $template->questions()->attach($q1->id, ['sort_order' => 0]);

        $this->actingAs($employer)->post(route('employer.jobs.screening-questions.apply-template', $job), [
            'template_id' => $template->id,
        ])->assertRedirect();

        $before = $job->screeningQuestions()->count();
        $this->assertGreaterThan(0, $before);

        $this->actingAs($employer)->delete(route('employer.screening-templates.destroy', $template))
            ->assertRedirect();

        $this->assertSame($before, $job->fresh()->screeningQuestions()->count());
    }

    public function test_cross_user_cannot_view_update_or_delete_template(): void
    {
        $employer1 = $this->approvedEmployer();
        $employer2 = $this->approvedEmployer();

        $template = ScreeningQuestionTemplate::create([
            'owner_user_id' => $employer1->id,
            'name' => 'Owner template',
            'description' => null,
            'is_active' => true,
        ]);

        $foreignBank = ScreeningQuestion::create([
            'owner_user_id' => $employer2->id,
            'type' => 'short_text',
            'label' => 'Foreign bank',
            'options' => null,
            'is_required' => false,
            'is_active' => true,
        ]);

        $this->actingAs($employer2)->get(route('employer.screening-templates.show', $template))->assertForbidden();
        $this->actingAs($employer2)->put(route('employer.screening-templates.update', $template), [
            'name' => 'Hijacked', 'question_ids' => [$foreignBank->id],
        ])->assertForbidden();
        $this->actingAs($employer2)->delete(route('employer.screening-templates.destroy', $template))->assertForbidden();
    }
}
