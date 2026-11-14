<?php

namespace Tests\Feature\Recruitment;

use App\Enums\JobApplicationStatus;
use App\Models\ApplicationNote;
use App\Models\Company;
use App\Models\CompanyMember;
use App\Models\Job;
use App\Models\JobApplication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Authorization\Concerns\InteractsWithAuthorizationCatalogue;
use Tests\TestCase;

class ApplicationNotesTest extends TestCase
{
    use InteractsWithAuthorizationCatalogue, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAuthorizationCatalogue();
    }

    // ------------------------------------------------------------ store

    public function test_owner_can_create_a_note(): void
    {
        [$application, $employer] = $this->context();

        $this->actingAs($employer)
            ->post(route('employer.applications.notes.store', $application), ['body' => 'Strong REST API background'])
            ->assertRedirect();

        $note = ApplicationNote::query()->where('application_id', $application->id)->sole();

        $this->assertSame($employer->id, $note->author_id);
        $this->assertSame('Strong REST API background', $note->body);
        $this->assertNotNull($note->created_at);
    }

    public function test_recruiter_member_can_create_a_note(): void
    {
        [$application, $employer] = $this->context();
        $company = $application->jobPosting->company;
        $recruiter = $this->memberOf($company, 'recruiter');

        $this->actingAs($recruiter)
            ->post(route('employer.applications.notes.store', $application), ['body' => 'Recruiter note'])
            ->assertRedirect();

        $this->assertDatabaseHas('application_notes', [
            'application_id' => $application->id,
            'author_id' => $recruiter->id,
            'body' => 'Recruiter note',
        ]);
    }

    public function test_reviewer_can_read_but_not_create_note(): void
    {
        [$application, $employer] = $this->context();
        $company = $application->jobPosting->company;
        $reviewer = $this->memberOf($company, 'reviewer');

        $note = ApplicationNote::query()->create([
            'application_id' => $application->id,
            'author_id' => $employer->id,
            'body' => 'Visible to reviewers',
        ]);

        $this->actingAs($reviewer)
            ->get(route('employer.applications.show', $application))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('notes.0.body', 'Visible to reviewers')
                ->where('can_manage_notes', false)
                ->where('notes.0.can_edit', false)
                ->where('notes.0.can_delete', false));

        $this->actingAs($reviewer)
            ->post(route('employer.applications.notes.store', $application), ['body' => 'Nope'])
            ->assertForbidden();

        $this->actingAs($reviewer)
            ->put(route('employer.applications.notes.update', ['application' => $application->id, 'note' => $note->id]), ['body' => 'Nope'])
            ->assertForbidden();

        $this->actingAs($reviewer)
            ->delete(route('employer.applications.notes.destroy', ['application' => $application->id, 'note' => $note->id]))
            ->assertForbidden();
    }

    public function test_candidate_cannot_access_note_routes(): void
    {
        [$application, $employer, $candidate] = $this->context();

        $this->actingAs($candidate)
            ->post(route('employer.applications.notes.store', $application), ['body' => 'Nope'])
            ->assertForbidden();

        $note = ApplicationNote::query()->create([
            'application_id' => $application->id,
            'author_id' => $employer->id,
            'body' => 'Private',
        ]);

        $this->actingAs($candidate)
            ->delete(route('employer.applications.notes.destroy', ['application' => $application->id, 'note' => $note->id]))
            ->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        [$application] = $this->context();

        $this->post(route('employer.applications.notes.store', $application), ['body' => 'Nope'])
            ->assertRedirect(route('login'));

        $this->delete(route('employer.applications.notes.destroy', ['application' => $application->id, 'note' => 1]))
            ->assertRedirect(route('login'));
    }

    public function test_cross_company_employer_cannot_manage_notes(): void
    {
        [$application] = $this->context();

        $outsider = User::factory()->approvedEmployer()->create();
        Company::factory()->for($outsider, 'owner')->create(); // owner of a *different* company
        $note = ApplicationNote::query()->create([
            'application_id' => $application->id,
            'author_id' => $application->jobPosting->company->owner_user_id,
            'body' => 'Internal',
        ]);

        $this->actingAs($outsider)
            ->get(route('employer.applications.show', $application))
            ->assertForbidden();

        $this->actingAs($outsider)
            ->post(route('employer.applications.notes.store', $application), ['body' => 'Ick'])
            ->assertForbidden();

        $this->actingAs($outsider)
            ->put(route('employer.applications.notes.update', ['application' => $application->id, 'note' => $note->id]), ['body' => 'Ick'])
            ->assertForbidden();

        $this->actingAs($outsider)
            ->delete(route('employer.applications.notes.destroy', ['application' => $application->id, 'note' => $note->id]))
            ->assertForbidden();
    }

    public function test_note_from_another_application_is_not_resolvable(): void
    {
        [$applicationX, $employer] = $this->context();
        [$applicationY] = $this->context();

        $note = ApplicationNote::query()->create([
            'application_id' => $applicationY->id,
            'author_id' => $employer->id,
            'body' => 'Belongs to Y',
        ]);

        $this->actingAs($employer)
            ->put(route('employer.applications.notes.update', ['application' => $applicationX->id, 'note' => $note->id]), ['body' => 'Hijack'])
            ->assertNotFound();

        $this->actingAs($employer)
            ->delete(route('employer.applications.notes.destroy', ['application' => $applicationX->id, 'note' => $note->id]))
            ->assertNotFound();

        // And the 404 did not mutate the note.
        $this->assertDatabaseHas('application_notes', [
            'id' => $note->id,
            'body' => 'Belongs to Y',
        ]);
    }

    // ------------------------------------------------------------ validation

    public function test_store_rejects_empty_whitespace_and_oversized_bodies(): void
    {
        [$application, $employer] = $this->context();

        $this->actingAs($employer)
            ->post(route('employer.applications.notes.store', $application), ['body' => ''])
            ->assertSessionHasErrors('body');

        $this->actingAs($employer)
            ->post(route('employer.applications.notes.store', $application), ['body' => "   \n  "])
            ->assertSessionHasErrors('body');

        $this->actingAs($employer)
            ->post(route('employer.applications.notes.store', $application), ['body' => str_repeat('a', 5001)])
            ->assertSessionHasErrors('body');

        $this->assertSame(0, ApplicationNote::query()->where('application_id', $application->id)->count());
    }

    // ------------------------------------------------------------ edit / delete

    public function test_author_can_edit_own_note_and_sets_edited_at(): void
    {
        [$application, $employer] = $this->context();

        $note = ApplicationNote::query()->create([
            'application_id' => $application->id,
            'author_id' => $employer->id,
            'body' => 'Original wording',
        ]);

        $this->actingAs($employer)
            ->put(route('employer.applications.notes.update', ['application' => $application->id, 'note' => $note->id]), ['body' => 'Corrected wording'])
            ->assertRedirect();

        $note->refresh();
        $this->assertSame('Corrected wording', $note->body);
        $this->assertNotNull($note->edited_at);
    }

    public function test_even_the_company_owner_cannot_edit_recruiter_note(): void
    {
        [$application] = $this->context();
        $company = $application->jobPosting->company;
        $recruiter = $this->memberOf($company, 'recruiter');

        $note = ApplicationNote::query()->create([
            'application_id' => $application->id,
            'author_id' => $recruiter->id,
            'body' => 'Recruiter note',
        ]);

        $owner = User::query()->findOrFail($company->owner_user_id);

        $this->actingAs($owner)
            ->put(route('employer.applications.notes.update', ['application' => $application->id, 'note' => $note->id]), ['body' => 'Owner override'])
            ->assertForbidden();

        $this->actingAs($owner)
            ->delete(route('employer.applications.notes.destroy', ['application' => $application->id, 'note' => $note->id]))
            ->assertForbidden();

        $this->assertDatabaseHas('application_notes', [
            'id' => $note->id,
            'body' => 'Recruiter note',
        ]);
    }

    public function test_other_company_member_cannot_edit_note(): void
    {
        [$application, $employer] = $this->context();
        $company = $application->jobPosting->company;
        $recruiter = $this->memberOf($company, 'recruiter');

        $note = ApplicationNote::query()->create([
            'application_id' => $application->id,
            'author_id' => $employer->id,
            'body' => 'Owner note',
        ]);

        $this->actingAs($recruiter)
            ->put(route('employer.applications.notes.update', ['application' => $application->id, 'note' => $note->id]), ['body' => 'Recruiter override'])
            ->assertForbidden();

        $this->assertDatabaseHas('application_notes', [
            'id' => $note->id,
            'body' => 'Owner note',
        ]);
    }

    public function test_revoked_member_loses_edit_and_delete_on_own_note(): void
    {
        [$application] = $this->context();
        $company = $application->jobPosting->company;
        $recruiter = $this->memberOf($company, 'recruiter');

        $note = ApplicationNote::query()->create([
            'application_id' => $application->id,
            'author_id' => $recruiter->id,
            'body' => 'Recruiter note',
        ]);

        // Revoke the membership; the note must remain visible but immutable to them.
        CompanyMember::query()
            ->where('company_id', $company->id)
            ->where('user_id', $recruiter->id)
            ->update(['status' => 'revoked']);

        $this->actingAs($recruiter)
            ->put(route('employer.applications.notes.update', ['application' => $application->id, 'note' => $note->id]), ['body' => 'Edited'])
            ->assertForbidden();

        $this->actingAs($recruiter)
            ->delete(route('employer.applications.notes.destroy', ['application' => $application->id, 'note' => $note->id]))
            ->assertForbidden();

        // The company owner can still read it.
        $owner = User::query()->findOrFail($company->owner_user_id);
        $this->actingAs($owner)
            ->get(route('employer.applications.show', $application))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('notes.0.body', 'Recruiter note'));
    }

    public function test_author_can_soft_delete_own_note(): void
    {
        [$application, $employer] = $this->context();

        $note = ApplicationNote::query()->create([
            'application_id' => $application->id,
            'author_id' => $employer->id,
            'body' => 'Delete me',
        ]);

        $this->actingAs($employer)
            ->delete(route('employer.applications.notes.destroy', ['application' => $application->id, 'note' => $note->id]))
            ->assertRedirect();

        $this->assertSoftDeleted('application_notes', ['id' => $note->id, 'body' => 'Delete me']);

        $this->actingAs($employer)
            ->get(route('employer.applications.show', $application))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('notes', 0));
    }

    public function test_delete_by_stranger_is_denied_but_row_survives(): void
    {
        [$application, $employer] = $this->context();
        $company = $application->jobPosting->company;
        $recruiter = $this->memberOf($company, 'recruiter');

        $note = ApplicationNote::query()->create([
            'application_id' => $application->id,
            'author_id' => $employer->id,
            'body' => 'Owner note',
        ]);

        $this->actingAs($recruiter)
            ->delete(route('employer.applications.notes.destroy', ['application' => $application->id, 'note' => $note->id]))
            ->assertForbidden();

        $this->assertDatabaseHas('application_notes', ['id' => $note->id]);
    }

    // ------------------------------------------------------------ terminal / closed

    public function test_notes_can_be_created_on_terminal_applications(): void
    {
        foreach ([JobApplicationStatus::Hired, JobApplicationStatus::Rejected, JobApplicationStatus::Withdrawn] as $status) {
            [$application, $employer] = $this->context($status);

            $this->actingAs($employer)
                ->post(route('employer.applications.notes.store', $application), ['body' => 'Post-decision note'])
                ->assertRedirect();

            $this->assertDatabaseHas('application_notes', [
                'application_id' => $application->id,
                'body' => 'Post-decision note',
            ]);
        }
    }

    public function test_notes_can_be_created_when_job_is_closed(): void
    {
        [$application, $employer] = $this->context();
        $application->jobPosting->update(['status' => 'closed']);

        $this->actingAs($employer)
            ->post(route('employer.applications.notes.store', $application), ['body' => 'Closed-job note'])
            ->assertRedirect();

        $this->assertDatabaseHas('application_notes', [
            'application_id' => $application->id,
            'body' => 'Closed-job note',
        ]);
    }

    // ------------------------------------------------------------ payload privacy

    public function test_candidate_application_page_never_exposes_notes(): void
    {
        [$application, $employer, $candidate] = $this->context();

        ApplicationNote::query()->create([
            'application_id' => $application->id,
            'author_id' => $employer->id,
            'body' => 'Never for candidates',
        ]);

        $this->actingAs($candidate)
            ->get(route('candidate.applications.show', $application))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->missing('notes')
                ->missing('evaluations')
                ->missing('can_manage_notes'));
    }

    // ------------------------------------------------------------ helpers

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
