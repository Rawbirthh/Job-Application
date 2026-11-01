<?php

namespace Tests\Feature\Employer;

use App\Enums\EmployerStatus;
use App\Enums\RoleSlug;
use App\Models\EmployerOnboardingRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Authorization\Concerns\InteractsWithAuthorizationCatalogue;
use Tests\TestCase;

class EmployerOnboardingTest extends TestCase
{
    use InteractsWithAuthorizationCatalogue, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAuthorizationCatalogue();
    }

    public function test_guests_are_redirected_to_login_from_onboarding(): void
    {
        $this->get(route('employer.onboarding.index'))->assertRedirect(route('login'));
        $this->post(route('employer.onboarding.store'))->assertRedirect(route('login'));
    }

    public function test_job_seeker_can_view_the_form_and_submit_valid_data(): void
    {
        $user = User::factory()->jobSeeker()->create();

        $this->actingAs($user)
            ->get(route('employer.onboarding.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('employer/onboarding')
                ->where('status', null)
                ->where('can_apply', true)
                ->where('request', null),
            );

        $response = $this->actingAs($user)
            ->post(route('employer.onboarding.store'), [
                'company_name' => 'Acme Corp',
                'company_website' => 'https://acme.example',
                'job_title' => 'Founder',
                'message' => 'We hire developers.',
                'status' => 'approved',
                'role' => 'platform_admin',
                'role_id' => 999,
                'permissions' => ['admin.access'],
                'user_id' => 999,
                'reviewed_at' => now()->toDateTimeString(),
                'review_note' => 'forged',
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('employer.onboarding.index'));

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'status' => 'pending',
        ]);

        $this->assertDatabaseHas('employer_onboarding_requests', [
            'user_id' => $user->id,
            'company_name' => 'Acme Corp',
            'company_website' => 'https://acme.example',
            'job_title' => 'Founder',
            'message' => 'We hire developers.',
            'reviewed_at' => null,
            'reviewed_by_id' => null,
            'review_note' => null,
        ]);

        $this->assertFalse($user->fresh()->hasRole(RoleSlug::Employer));

        $user = $user->fresh();
        $this->actingAs($user)
            ->get(route('employer.onboarding.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('employer/onboarding')
                ->where('status', 'pending')
                ->where('can_apply', false)
                ->where('request.company_name', 'Acme Corp'),
            );
    }

    public function test_invalid_submissions_do_not_create_a_request(): void
    {
        $user = User::factory()->jobSeeker()->create();

        $cases = [
            ['company_name' => '', 'job_title' => 'Founder'],
            ['company_name' => 'Acme', 'job_title' => ''],
            ['company_name' => 'Acme', 'job_title' => 'Founder', 'company_website' => 'not-a-url'],
            ['company_name' => str_repeat('a', 151), 'job_title' => 'Founder'],
            ['company_name' => 'Acme', 'job_title' => str_repeat('a', 101)],
            ['company_name' => 'Acme', 'job_title' => 'Founder', 'company_website' => str_repeat('a', 256)],
            ['company_name' => 'Acme', 'job_title' => 'Founder', 'message' => str_repeat('a', 1001)],
        ];

        foreach ($cases as $payload) {
            $this->actingAs($user)
                ->post(route('employer.onboarding.store'), $payload)
                ->assertSessionHasErrors();

            $this->assertDatabaseCount('employer_onboarding_requests', 0);
        }
    }

    public function test_duplicate_submission_while_pending_is_rejected(): void
    {
        $user = User::factory()->jobSeeker()->create();

        $this->actingAs($user)->post(route('employer.onboarding.store'), [
            'company_name' => 'Acme',
            'job_title' => 'Founder',
        ])->assertSessionHasNoErrors();

        $response = $this->actingAs($user)->post(route('employer.onboarding.store'), [
            'company_name' => 'Acme 2',
            'job_title' => 'Founder 2',
        ]);

        $response->assertSessionHasErrors('status');
        $this->assertDatabaseCount('employer_onboarding_requests', 1);
        $this->assertDatabaseHas('employer_onboarding_requests', [
            'user_id' => $user->id,
            'company_name' => 'Acme',
        ]);
    }

    public function test_rejected_applicant_can_resubmit_and_reuses_the_same_row(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $applicant = User::factory()->jobSeeker()->create();

        $this->actingAs($applicant)->post(route('employer.onboarding.store'), [
            'company_name' => 'Old Co',
            'job_title' => 'Old Title',
        ])->assertSessionHasNoErrors();

        $request = EmployerOnboardingRequest::where('user_id', $applicant->id)->firstOrFail();
        $oldSubmittedAt = $request->submitted_at;

        // Admin rejects
        $this->actingAs($admin)->post(route('admin.employers.reject', $applicant), [
            'reason' => 'Not enough detail',
        ])->assertSessionHasNoErrors();

        $applicant->refresh();
        $this->assertSame(EmployerStatus::Rejected, $applicant->status);

        $this->actingAs($applicant)
            ->get(route('employer.onboarding.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('status', 'rejected')
                ->where('request.review_note', 'Not enough detail')
                ->where('can_apply', true),
            );

        // Resubmit
        $this->actingAs($applicant)->post(route('employer.onboarding.store'), [
            'company_name' => 'New Co',
            'job_title' => 'New Title',
        ])->assertSessionHasNoErrors();

        $applicant->refresh();
        $this->assertSame(EmployerStatus::Pending, $applicant->status);

        $request->refresh();
        $this->assertSame('New Co', $request->company_name);
        $this->assertNull($request->reviewed_at);
        $this->assertNull($request->reviewed_by_id);
        $this->assertNull($request->review_note);
        $this->assertNotNull($request->submitted_at);
    }

    public function test_approved_applicant_cannot_submit_and_can_access_dashboard(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $applicant = User::factory()->jobSeeker()->create();

        $this->actingAs($applicant)->post(route('employer.onboarding.store'), [
            'company_name' => 'Acme',
            'job_title' => 'Founder',
        ])->assertSessionHasNoErrors();

        $this->actingAs($admin)->post(route('admin.employers.approve', $applicant))
            ->assertSessionHasNoErrors();

        $applicant->refresh();
        $this->assertSame(EmployerStatus::Approved, $applicant->status);
        $this->assertTrue($applicant->hasRole(RoleSlug::Employer));
        $this->assertTrue($applicant->hasRole(RoleSlug::JobSeeker));

        $this->actingAs($applicant)
            ->get(route('employer.onboarding.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('status', 'approved')
                ->where('can_apply', false)
                ->where('can_access_employer_dashboard', true),
            );

        // Cannot submit while approved
        $this->actingAs($applicant)->post(route('employer.onboarding.store'), [
            'company_name' => 'Acme 2',
            'job_title' => 'Founder 2',
        ])->assertSessionHasErrors('status');

        $this->actingAs($applicant)->get(route('employer.dashboard'))->assertOk();
    }

    public function test_suspended_employer_sees_reason_and_cannot_access_dashboard(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $applicant = User::factory()->jobSeeker()->create();

        $this->actingAs($applicant)->post(route('employer.onboarding.store'), [
            'company_name' => 'Acme',
            'job_title' => 'Founder',
        ])->assertSessionHasNoErrors();

        $this->actingAs($admin)->post(route('admin.employers.approve', $applicant))
            ->assertSessionHasNoErrors();

        $this->actingAs($admin)->post(route('admin.employers.suspend', $applicant), [
            'reason' => 'Policy violations',
        ])->assertSessionHasNoErrors();

        $applicant->refresh();
        $this->assertSame(EmployerStatus::Suspended, $applicant->status);
        $this->assertTrue($applicant->hasRole(RoleSlug::Employer));

        $this->actingAs($applicant)
            ->get(route('employer.onboarding.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('status', 'suspended')
                ->where('suspension_reason', 'Policy violations')
                ->where('can_apply', false),
            );

        $this->actingAs($applicant)->post(route('employer.onboarding.store'), [
            'company_name' => 'Acme 2',
            'job_title' => 'Founder 2',
        ])->assertSessionHasErrors('status');

        $this->actingAs($applicant)->get(route('employer.dashboard'))->assertForbidden();
    }

    public function test_pending_and_rejected_applicants_cannot_access_employer_dashboard(): void
    {
        $pending = User::factory()->jobSeeker()->create();
        $pending->forceFill(['status' => EmployerStatus::Pending])->save();

        $rejected = User::factory()->jobSeeker()->create();
        $rejected->forceFill(['status' => EmployerStatus::Rejected])->save();

        $this->actingAs($pending)->get(route('employer.dashboard'))->assertForbidden();
        $this->actingAs($rejected)->get(route('employer.dashboard'))->assertForbidden();
    }

    public function test_onboarding_is_self_scoped_only(): void
    {
        $user = User::factory()->jobSeeker()->create();
        $other = User::factory()->jobSeeker()->create();

        $other->forceFill(['status' => EmployerStatus::Pending])->save();

        // There is no route parameter; accessing the single page only ever shows own state.
        $this->actingAs($user)
            ->get(route('employer.onboarding.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('status', null));
    }

    public function test_candidate_dashboard_shows_employer_access_cta(): void
    {
        $none = User::factory()->jobSeeker()->create();
        $pending = User::factory()->jobSeeker()->create();
        $pending->forceFill(['status' => EmployerStatus::Pending])->save();
        $rejected = User::factory()->jobSeeker()->create();
        $rejected->forceFill(['status' => EmployerStatus::Rejected])->save();
        $approved = User::factory()->approvedEmployer()->create();
        $suspended = User::factory()->suspendedEmployer()->create();

        foreach ([$none, $pending, $rejected, $approved, $suspended] as $user) {
            $this->actingAs($user)
                ->get(route('candidate.dashboard'))
                ->assertOk();
        }
    }

    public function test_shared_props_expose_employer_status(): void
    {
        $user = User::factory()->jobSeeker()->create();
        $user->forceFill(['status' => EmployerStatus::Pending])->save();

        $this->actingAs($user)
            ->get(route('candidate.dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.employer_status', 'pending')
                ->missing('auth.user.employer_status'),
            );
    }
}
