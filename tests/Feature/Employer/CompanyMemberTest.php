<?php

namespace Tests\Feature\Employer;

use App\Models\CandidateResume;
use App\Models\Company;
use App\Models\CompanyMember;
use App\Models\EmploymentType;
use App\Models\Job;
use App\Models\JobApplication;
use App\Models\JobCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Authorization\Concerns\InteractsWithAuthorizationCatalogue;
use Tests\TestCase;

class CompanyMemberTest extends TestCase
{
    use InteractsWithAuthorizationCatalogue, RefreshDatabase;

    protected JobCategory $category;

    protected EmploymentType $employmentType;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAuthorizationCatalogue();
        Storage::fake('local');
        Storage::fake('public');

        $this->category = JobCategory::factory()->create(['is_active' => true]);
        $this->employmentType = EmploymentType::factory()->create(['is_active' => true]);
    }

    // ---------------------------------------------------------------- guests

    public function test_guests_are_redirected_on_member_routes(): void
    {
        $owner = User::factory()->approvedEmployer()->create();
        $company = Company::factory()->create(['owner_user_id' => $owner->id]);
        $member = CompanyMember::create([
            'company_id' => $company->id,
            'user_id' => User::factory()->approvedEmployer()->create()->id,
            'company_role' => 'recruiter',
            'status' => 'active',
            'invited_at' => now(),
            'accepted_at' => now(),
        ]);

        $this->get(route('employer.companies.members.index', $company))->assertRedirect(route('login'));
        $this->post(route('employer.companies.members.store', $company), [
            'email' => 'x@example.com',
            'company_role' => 'recruiter',
        ])->assertRedirect(route('login'));
        $this->patch(route('employer.companies.members.update', [$company, $member]), [
            'company_role' => 'reviewer',
        ])->assertRedirect(route('login'));
        $this->delete(route('employer.companies.members.destroy', [$company, $member]))->assertRedirect(route('login'));
    }

    // ---------------------------------------------------------------- job seeker

    public function test_job_seeker_cannot_access_member_routes(): void
    {
        $seeker = User::factory()->jobSeeker()->create();
        $owner = User::factory()->approvedEmployer()->create();
        $company = Company::factory()->create(['owner_user_id' => $owner->id]);

        $this->actingAs($seeker)->get(route('employer.companies.members.index', $company))->assertForbidden();
        $this->actingAs($seeker)->post(route('employer.companies.members.store', $company), [
            'email' => 'x@example.com',
            'company_role' => 'recruiter',
        ])->assertForbidden();
    }

    // ---------------------------------------------------------------- non-approved employer

    public function test_non_approved_employer_cannot_access_member_routes(): void
    {
        $unapproved = User::factory()->employer()->create();
        $owner = User::factory()->approvedEmployer()->create();
        $company = Company::factory()->create(['owner_user_id' => $owner->id]);

        $this->actingAs($unapproved)->get(route('employer.companies.members.index', $company))->assertForbidden();
        $this->actingAs($unapproved)->post(route('employer.companies.members.store', $company), [
            'email' => 'x@example.com',
            'company_role' => 'recruiter',
        ])->assertForbidden();
    }

    // ---------------------------------------------------------------- non-member other owner

    public function test_non_member_cannot_access_members_or_company_resources(): void
    {
        $ownerA = User::factory()->approvedEmployer()->create();
        $companyA = Company::factory()->create(['owner_user_id' => $ownerA->id]);
        $intruder = User::factory()->approvedEmployer()->create();
        Company::factory()->create(['owner_user_id' => $intruder->id]);

        $this->actingAs($intruder)->get(route('employer.companies.members.index', $companyA))->assertForbidden();
        $target = User::factory()->approvedEmployer()->create();
        $this->actingAs($intruder)->post(route('employer.companies.members.store', $companyA), [
            'email' => $target->email,
            'company_role' => 'recruiter',
        ])->assertForbidden();
        $this->actingAs($intruder)->get(route('employer.companies.show', $companyA))->assertForbidden();
        $this->actingAs($intruder)->get(route('employer.companies.edit', $companyA))->assertForbidden();
    }

    // ---------------------------------------------------------------- cross-tenant

    public function test_member_of_company_a_cannot_access_company_b(): void
    {
        $ownerA = User::factory()->approvedEmployer()->create();
        $companyA = Company::factory()->create(['owner_user_id' => $ownerA->id]);
        $ownerB = User::factory()->approvedEmployer()->create();
        $companyB = Company::factory()->create(['owner_user_id' => $ownerB->id]);

        $memberUser = User::factory()->approvedEmployer()->create();
        CompanyMember::create([
            'company_id' => $companyA->id,
            'user_id' => $memberUser->id,
            'company_role' => 'recruiter',
            'status' => 'active',
            'invited_at' => now(),
            'accepted_at' => now(),
        ]);

        $jobB = Job::factory()->create(['company_id' => $companyB->id, 'status' => 'draft']);

        $this->actingAs($memberUser)->get(route('employer.companies.members.index', $companyB))->assertForbidden();
        $this->actingAs($memberUser)->get(route('employer.jobs.edit', $jobB))->assertForbidden();
        $this->actingAs($memberUser)->get(route('employer.companies.show', $companyB))->assertForbidden();
    }

    // ---------------------------------------------------------------- platform admin routes

    public function test_platform_admin_routes_forbidden_for_company_member(): void
    {
        $owner = User::factory()->approvedEmployer()->create();
        $company = Company::factory()->create(['owner_user_id' => $owner->id]);
        $memberUser = User::factory()->approvedEmployer()->create();
        CompanyMember::create([
            'company_id' => $company->id,
            'user_id' => $memberUser->id,
            'company_role' => 'owner',
            'status' => 'active',
            'invited_at' => now(),
            'accepted_at' => now(),
        ]);

        $this->actingAs($memberUser)->get(route('admin.dashboard'))->assertForbidden();
        $this->actingAs($memberUser)->get(route('admin.users.index'))->assertForbidden();
    }

    // ---------------------------------------------------------------- CRUD: add

    public function test_owner_adds_approved_employer_as_active_member(): void
    {
        foreach (['recruiter', 'reviewer', 'owner'] as $role) {
            $owner = User::factory()->approvedEmployer()->create();
            $company = Company::factory()->create(['owner_user_id' => $owner->id]);
            $target = User::factory()->approvedEmployer()->create();

            $response = $this->actingAs($owner)->post(route('employer.companies.members.store', $company), [
                'email' => $target->email,
                'company_role' => $role,
            ]);

            $response->assertRedirect(route('employer.companies.members.index', $company));
            $this->assertDatabaseHas('company_members', [
                'company_id' => $company->id,
                'user_id' => $target->id,
                'company_role' => $role,
                'status' => 'active',
            ]);
        }
    }

    public function test_owner_add_duplicate_member_returns_validation_error(): void
    {
        $owner = User::factory()->approvedEmployer()->create();
        $company = Company::factory()->create(['owner_user_id' => $owner->id]);
        $target = User::factory()->approvedEmployer()->create();

        $this->actingAs($owner)->post(route('employer.companies.members.store', $company), [
            'email' => $target->email,
            'company_role' => 'recruiter',
        ])->assertRedirect();

        $this->actingAs($owner)->post(route('employer.companies.members.store', $company), [
            'email' => $target->email,
            'company_role' => 'reviewer',
        ])->assertSessionHasErrors('email');
    }

    public function test_owner_cannot_add_invalid_members(): void
    {
        $owner = User::factory()->approvedEmployer()->create();
        $company = Company::factory()->create(['owner_user_id' => $owner->id]);

        // missing user
        $this->actingAs($owner)->post(route('employer.companies.members.store', $company), [
            'email' => 'nope@example.com',
            'company_role' => 'recruiter',
        ])->assertSessionHasErrors('email');

        // job seeker
        $seeker = User::factory()->jobSeeker()->create();
        $this->actingAs($owner)->post(route('employer.companies.members.store', $company), [
            'email' => $seeker->email,
            'company_role' => 'recruiter',
        ])->assertSessionHasErrors('email');

        // non-approved employer
        $unapproved = User::factory()->employer()->create();
        $this->actingAs($owner)->post(route('employer.companies.members.store', $company), [
            'email' => $unapproved->email,
            'company_role' => 'recruiter',
        ])->assertSessionHasErrors('email');

        // self
        $this->actingAs($owner)->post(route('employer.companies.members.store', $company), [
            'email' => $owner->email,
            'company_role' => 'recruiter',
        ])->assertSessionHasErrors('email');
    }

    // ---------------------------------------------------------------- role change + last-owner protection

    public function test_owner_changes_member_role(): void
    {
        $owner = User::factory()->approvedEmployer()->create();
        $company = Company::factory()->create(['owner_user_id' => $owner->id]);
        $target = User::factory()->approvedEmployer()->create();

        $this->actingAs($owner)->post(route('employer.companies.members.store', $company), [
            'email' => $target->email,
            'company_role' => 'recruiter',
        ])->assertRedirect();

        $member = CompanyMember::where('company_id', $company->id)->where('user_id', $target->id)->firstOrFail();

        $this->actingAs($owner)->patch(route('employer.companies.members.update', [$company, $member]), [
            'company_role' => 'reviewer',
        ])->assertRedirect();

        $this->assertDatabaseHas('company_members', [
            'id' => $member->id,
            'company_role' => 'reviewer',
        ]);
    }

    public function test_last_owner_protection_blocks_demoting_final_owner_membership(): void
    {
        $owner = User::factory()->approvedEmployer()->create();
        $company = Company::factory()->create(['owner_user_id' => $owner->id]);
        $target = User::factory()->approvedEmployer()->create();

        $this->actingAs($owner)->post(route('employer.companies.members.store', $company), [
            'email' => $target->email,
            'company_role' => 'owner',
        ])->assertRedirect();

        $member = CompanyMember::where('company_id', $company->id)->where('user_id', $target->id)->firstOrFail();

        // only one owner membership -> cannot demote
        $this->actingAs($owner)->patch(route('employer.companies.members.update', [$company, $member]), [
            'company_role' => 'recruiter',
        ])->assertSessionHasErrors('company_role');

        $this->assertDatabaseHas('company_members', [
            'id' => $member->id,
            'company_role' => 'owner',
        ]);
    }

    public function test_demoting_owner_allowed_when_multiple_owner_memberships(): void
    {
        $owner = User::factory()->approvedEmployer()->create();
        $company = Company::factory()->create(['owner_user_id' => $owner->id]);
        $t1 = User::factory()->approvedEmployer()->create();
        $t2 = User::factory()->approvedEmployer()->create();

        foreach ([$t1, $t2] as $t) {
            $this->actingAs($owner)->post(route('employer.companies.members.store', $company), [
                'email' => $t->email,
                'company_role' => 'owner',
            ])->assertRedirect();
        }

        $m1 = CompanyMember::where('company_id', $company->id)->where('user_id', $t1->id)->firstOrFail();

        $this->actingAs($owner)->patch(route('employer.companies.members.update', [$company, $m1]), [
            'company_role' => 'recruiter',
        ])->assertRedirect();

        $this->assertDatabaseHas('company_members', [
            'id' => $m1->id,
            'company_role' => 'recruiter',
        ]);
    }

    // ---------------------------------------------------------------- revoke

    public function test_owner_revokes_member_and_revoked_loses_access(): void
    {
        $owner = User::factory()->approvedEmployer()->create();
        $company = Company::factory()->create(['owner_user_id' => $owner->id]);
        $target = User::factory()->approvedEmployer()->create();

        $this->actingAs($owner)->post(route('employer.companies.members.store', $company), [
            'email' => $target->email,
            'company_role' => 'recruiter',
        ])->assertRedirect();

        $member = CompanyMember::where('company_id', $company->id)->where('user_id', $target->id)->firstOrFail();

        $this->actingAs($owner)->delete(route('employer.companies.members.destroy', [$company, $member]))
            ->assertRedirect();

        $this->assertDatabaseHas('company_members', [
            'id' => $member->id,
            'status' => 'revoked',
        ]);
        $this->assertNotNull($member->fresh()->revoked_at);

        // revoked member loses access
        $this->actingAs($target)->get(route('employer.companies.show', $company))->assertForbidden();
    }

    public function test_last_owner_protection_blocks_revoking_final_owner_membership(): void
    {
        $owner = User::factory()->approvedEmployer()->create();
        $company = Company::factory()->create(['owner_user_id' => $owner->id]);
        $target = User::factory()->approvedEmployer()->create();

        $this->actingAs($owner)->post(route('employer.companies.members.store', $company), [
            'email' => $target->email,
            'company_role' => 'owner',
        ])->assertRedirect();

        $member = CompanyMember::where('company_id', $company->id)->where('user_id', $target->id)->firstOrFail();

        $this->actingAs($owner)->delete(route('employer.companies.members.destroy', [$company, $member]))
            ->assertSessionHasErrors('company_role');

        $this->assertDatabaseHas('company_members', [
            'id' => $member->id,
            'status' => 'active',
        ]);
    }

    // ---------------------------------------------------------------- primary owner protection

    public function test_owner_cannot_modify_primary_owner_membership_row(): void
    {
        $owner = User::factory()->approvedEmployer()->create();
        $company = Company::factory()->create(['owner_user_id' => $owner->id]);

        // manually create a membership row for primary owner (not normal flow)
        $membership = CompanyMember::create([
            'company_id' => $company->id,
            'user_id' => $owner->id,
            'company_role' => 'owner',
            'status' => 'active',
            'invited_at' => now(),
            'accepted_at' => now(),
        ]);

        $this->actingAs($owner)->patch(route('employer.companies.members.update', [$company, $membership]), [
            'company_role' => 'recruiter',
        ])->assertSessionHasErrors('company_role');

        $this->actingAs($owner)->delete(route('employer.companies.members.destroy', [$company, $membership]))
            ->assertSessionHasErrors('company_role');

        $this->assertDatabaseHas('company_members', [
            'id' => $membership->id,
            'status' => 'active',
            'company_role' => 'owner',
        ]);
    }

    // ---------------------------------------------------------------- forManagingUser

    public function test_company_for_managing_user_returns_owned_and_member(): void
    {
        $owner = User::factory()->approvedEmployer()->create();
        $ownedCompany = Company::factory()->create(['owner_user_id' => $owner->id]);

        $otherOwner = User::factory()->approvedEmployer()->create();
        $memberCompany = Company::factory()->create(['owner_user_id' => $otherOwner->id]);

        $memberUser = User::factory()->approvedEmployer()->create();
        CompanyMember::create([
            'company_id' => $memberCompany->id,
            'user_id' => $memberUser->id,
            'company_role' => 'recruiter',
            'status' => 'active',
            'invited_at' => now(),
            'accepted_at' => now(),
        ]);

        $this->assertEqualsCanonicalizing(
            [$ownedCompany->id],
            Company::forManagingUser($owner)->pluck('id')->all()
        );
        $this->assertEqualsCanonicalizing(
            [$memberCompany->id],
            Company::forManagingUser($memberUser)->pluck('id')->all()
        );
    }

    // ---------------------------------------------------------------- role permission matrix

    public function test_role_permission_matrix(): void
    {
        $primaryOwner = User::factory()->approvedEmployer()->create();
        $company = Company::factory()->create(['owner_user_id' => $primaryOwner->id]);

        $job = Job::factory()->create([
            'company_id' => $company->id,
            'status' => 'draft',
            'category_id' => $this->category->id,
            'employment_type_id' => $this->employmentType->id,
        ]);

        // candidate + resume + application (submitted)
        $candidate = User::factory()->jobSeeker()->create();
        $filePath = 'resumes/test-resume.pdf';
        Storage::disk('local')->put($filePath, 'fake-pdf-content');
        $resume = CandidateResume::factory()->for($candidate, 'user')->create([
            'path' => $filePath,
            'original_filename' => 'candidate-cv.pdf',
        ]);
        $application = JobApplication::factory()
            ->for($job, 'jobPosting')
            ->for($candidate, 'user')
            ->for($resume, 'candidateResume')
            ->create(['status' => 'submitted']);

        $candidate2 = User::factory()->jobSeeker()->create();
        $resume2 = CandidateResume::factory()->for($candidate2, 'user')->create([
            'path' => $filePath,
            'original_filename' => 'candidate2-cv.pdf',
        ]);
        $shortlistedApp = JobApplication::factory()
            ->for($job, 'jobPosting')
            ->for($candidate2, 'user')
            ->for($resume2, 'candidateResume')
            ->create(['status' => 'shortlisted']);

        // members
        $reviewerUser = User::factory()->approvedEmployer()->create();
        $recruiterUser = User::factory()->approvedEmployer()->create();
        $coOwnerUser = User::factory()->approvedEmployer()->create();

        foreach ([
            [$reviewerUser, 'reviewer'],
            [$recruiterUser, 'recruiter'],
            [$coOwnerUser, 'owner'],
        ] as [$u, $role]) {
            CompanyMember::create([
                'company_id' => $company->id,
                'user_id' => $u->id,
                'company_role' => $role,
                'status' => 'active',
                'invited_at' => now(),
                'accepted_at' => now(),
            ]);
        }

        $jobPayload = [
            'company_id' => $company->id,
            'title' => 'Software Engineer',
            'description' => 'Description here.',
            'category_id' => $this->category->id,
            'employment_type_id' => $this->employmentType->id,
        ];
        $jobUpdatePayload = [
            'title' => 'Updated Title',
            'description' => $job->description,
            'category_id' => $job->category_id,
            'employment_type_id' => $job->employment_type_id,
        ];
        $statusPayload = [
            'current_status' => 'submitted',
            'new_status' => 'reviewing',
        ];
        $interviewPayload = [
            'scheduled_at_local' => '2026-11-15T10:00',
            'timezone' => 'UTC',
            'duration_minutes' => 60,
            'mode' => 'online',
            'location_details' => 'https://meet.example.com/room-42',
        ];

        // ---- reviewer
        $r = $reviewerUser;
        $this->actingAs($r)->get(route('employer.jobs.index'))->assertOk();
        $this->actingAs($r)->get(route('employer.jobs.applicants', $job))->assertOk();
        $this->actingAs($r)->get(route('employer.applications.show', $application))->assertOk();
        $this->actingAs($r)->get(route('employer.applications.resume.download', $application))->assertOk();

        $this->actingAs($r)->post(route('employer.jobs.store'), $jobPayload)->assertForbidden();
        $this->actingAs($r)->put(route('employer.jobs.update', $job), $jobUpdatePayload)->assertForbidden();
        $this->actingAs($r)->post(route('employer.jobs.submit', $job))->assertForbidden();
        $this->actingAs($r)->post(route('employer.jobs.close', $job))->assertForbidden();
        $this->actingAs($r)->post(route('employer.applications.update-status', $application), $statusPayload)->assertForbidden();
        $this->actingAs($r)->post(route('employer.applications.interviews.store', $shortlistedApp), $interviewPayload)->assertForbidden();
        $this->actingAs($r)->post(route('employer.companies.members.store', $company), [
            'email' => User::factory()->approvedEmployer()->create()->email,
            'company_role' => 'recruiter',
        ])->assertForbidden();
        $this->actingAs($r)->put(route('employer.companies.update', $company), ['name' => 'Hacked'])->assertForbidden();
        $this->actingAs($r)->get(route('employer.companies.edit', $company))->assertForbidden();
        $this->actingAs($r)->get(route('employer.companies.members.index', $company))->assertForbidden();
        $this->actingAs($r)->get(route('employer.jobs.screening-questions.index', $job))->assertForbidden();

        // ---- recruiter
        $rc = $recruiterUser;
        $this->actingAs($rc)->post(route('employer.jobs.store'), $jobPayload)->assertRedirect();
        $this->actingAs($rc)->put(route('employer.jobs.update', $job), $jobUpdatePayload)->assertRedirect();
        $this->actingAs($rc)->get(route('employer.jobs.screening-questions.index', $job))->assertOk();
        $this->actingAs($rc)->post(route('employer.jobs.submit', $job))->assertRedirect();
        $this->actingAs($rc)->post(route('employer.jobs.close', $job))->assertRedirect();
        $this->actingAs($rc)->get(route('employer.jobs.applicants', $job))->assertOk();
        $this->actingAs($rc)->get(route('employer.applications.show', $application))->assertOk();
        $this->actingAs($rc)->post(route('employer.applications.update-status', $application), $statusPayload)->assertRedirect();
        $this->actingAs($rc)->get(route('employer.applications.resume.download', $application))->assertOk();
        $this->actingAs($rc)->post(route('employer.applications.interviews.store', $shortlistedApp), $interviewPayload)->assertRedirect();
        $this->actingAs($rc)->put(route('employer.companies.update', $company), ['name' => 'Hacked'])->assertForbidden();
        $this->actingAs($rc)->get(route('employer.companies.edit', $company))->assertForbidden();
        $this->actingAs($rc)->get(route('employer.companies.members.index', $company))->assertForbidden();
        $this->actingAs($rc)->post(route('employer.companies.members.store', $company), [
            'email' => User::factory()->approvedEmployer()->create()->email,
            'company_role' => 'recruiter',
        ])->assertForbidden();

        // reset state mutated by recruiter assertions
        Job::where('id', $job->id)->update(['status' => 'draft']);
        $job->refresh();
        JobApplication::where('id', $application->id)->update(['status' => 'submitted']);
        $application->refresh();

        // ---- co-owner
        $o = $coOwnerUser;
        $this->actingAs($o)->post(route('employer.jobs.store'), $jobPayload)->assertRedirect();
        $this->actingAs($o)->put(route('employer.jobs.update', $job), $jobUpdatePayload)->assertRedirect();
        $this->actingAs($o)->get(route('employer.jobs.screening-questions.index', $job))->assertOk();
        $this->actingAs($o)->post(route('employer.jobs.submit', $job))->assertRedirect();
        $this->actingAs($o)->post(route('employer.jobs.close', $job))->assertRedirect();
        $this->actingAs($o)->get(route('employer.jobs.applicants', $job))->assertOk();
        $this->actingAs($o)->get(route('employer.applications.show', $application))->assertOk();
        $this->actingAs($o)->post(route('employer.applications.update-status', $application), $statusPayload)->assertRedirect();
        $this->actingAs($o)->get(route('employer.applications.resume.download', $application))->assertOk();
        $this->actingAs($o)->post(route('employer.applications.interviews.store', $shortlistedApp), $interviewPayload)->assertRedirect();
        $this->actingAs($o)->get(route('employer.companies.edit', $company))->assertOk();
        $this->actingAs($o)->get(route('employer.companies.members.index', $company))->assertOk();
    }
}
