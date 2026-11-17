<?php

namespace Tests\Feature\Employer;

use App\Enums\AuditAction;
use App\Enums\EmployerStatus;
use App\Enums\RoleSlug;
use App\Models\User;
use Carbon\CarbonInterface;
use Database\Seeders\AuthorizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class EmployerOnboardingTrustRampTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AuthorizationSeeder::class);
    }

    /**
     * @return array<string, mixed>
     */
    private static function validPayload(): array
    {
        return [
            'company_name' => 'Acme Corp',
            'company_website' => 'https://acme.example',
            'job_title' => 'Founder',
            'message' => 'We hire developers.',
        ];
    }

    public function test_onboarding_page_exposes_is_verified_false_for_unverified_users(): void
    {
        $user = User::factory()->unverified()->jobSeeker()->create();

        $this->actingAs($user)
            ->get(route('employer.onboarding.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('employer/onboarding')
                ->where('is_verified', false),
            );
    }

    public function test_onboarding_page_exposes_is_verified_true_for_verified_users(): void
    {
        $user = User::factory()->jobSeeker()->create();

        $this->actingAs($user)
            ->get(route('employer.onboarding.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('employer/onboarding')
                ->where('is_verified', true),
            );
    }

    public function test_unverified_user_cannot_submit_an_employer_request(): void
    {
        $user = User::factory()->unverified()->jobSeeker()->create();

        $this->actingAs($user)
            ->post(route('employer.onboarding.store'), self::validPayload())
            ->assertSessionHasErrors('status');

        $this->assertDatabaseCount('employer_onboarding_requests', 0);
        $this->assertNull($user->fresh()->status);
    }

    public function test_verified_user_can_submit_an_employer_request(): void
    {
        $user = User::factory()->jobSeeker()->create();

        $this->actingAs($user)
            ->post(route('employer.onboarding.store'), self::validPayload())
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'status' => 'pending',
        ]);
    }

    public function test_admin_cannot_approve_an_unverified_applicant(): void
    {
        $applicant = $this->createPendingApplicant(verified: false);
        $admin = User::factory()->platformAdmin()->create();

        $this->actingAs($admin)
            ->post(route('admin.employers.approve', $applicant))
            ->assertSessionHasErrors('status');

        $applicant->refresh();
        $this->assertSame(EmployerStatus::Pending, $applicant->status);
        $this->assertNull($applicant->employerOnboardingRequest->reviewed_at);
        $this->assertNull($applicant->employerOnboardingRequest->reviewed_by_id);
        $this->assertFalse($applicant->hasRole(RoleSlug::Employer));
    }

    public function test_admin_can_approve_after_the_applicant_verifies(): void
    {
        $applicant = $this->createPendingApplicant(verified: false);
        $admin = User::factory()->platformAdmin()->create();

        $applicant->markEmailAsVerified();

        $this->actingAs($admin)
            ->post(route('admin.employers.approve', $applicant))
            ->assertSessionHasNoErrors();

        $applicant->refresh();
        $this->assertSame(EmployerStatus::Approved, $applicant->status);
        $this->assertTrue($applicant->hasRole(RoleSlug::Employer));
    }

    public function test_resubmission_is_blocked_during_the_7_day_cooldown(): void
    {
        $user = User::factory()->jobSeeker()->create();

        $rejectedAt = now()->subDays(3);
        $this->createRejectedRequest($user, $rejectedAt);

        $this->actingAs($user)
            ->post(route('employer.onboarding.store'), self::validPayload())
            ->assertSessionHasErrors([
                'status' => 'You can request employer access again after '
                    .$rejectedAt->copy()->addDays(7)->toFormattedDateString()
                    .'.',
            ]);

        $request = $user->employerOnboardingRequest()->firstOrFail();
        $this->assertSame($rejectedAt->toDateTimeString(), $request->reviewed_at->toDateTimeString());
        $this->assertSame('rejected', $user->fresh()->status->value);
    }

    public function test_resubmission_is_allowed_on_the_cooldown_boundary(): void
    {
        $user = User::factory()->jobSeeker()->create();

        $rejectedAt = now()->subDays(7);
        $this->createRejectedRequest($user, $rejectedAt);

        $this->actingAs($user)
            ->post(route('employer.onboarding.store'), self::validPayload())
            ->assertSessionHasNoErrors();

        $this->assertSame('pending', $user->fresh()->status->value);
        $this->assertNull($user->employerOnboardingRequest()->firstOrFail()->reviewed_at);
    }

    public function test_resubmission_is_allowed_when_rejection_timestamp_is_missing(): void
    {
        $user = User::factory()->jobSeeker()->create();

        $this->createRejectedRequest($user, null);

        $this->actingAs($user)
            ->post(route('employer.onboarding.store'), self::validPayload())
            ->assertSessionHasNoErrors();

        $this->assertSame('pending', $user->fresh()->status->value);
    }

    public function test_rejected_user_near_the_boundary_sees_can_apply_false(): void
    {
        $user = User::factory()->jobSeeker()->create();

        $rejectedAt = now()->subDays(6);
        $this->createRejectedRequest($user, $rejectedAt);

        $this->actingAs($user)
            ->get(route('employer.onboarding.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('is_verified', true)
                ->where('can_apply', false),
            );
    }

    public function test_rejected_user_past_the_boundary_sees_can_apply_true(): void
    {
        $user = User::factory()->jobSeeker()->create();

        $rejectedAt = now()->subDays(7);
        $this->createRejectedRequest($user, $rejectedAt);

        $this->actingAs($user)
            ->get(route('employer.onboarding.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('can_apply', true),
            );
    }

    public function test_blocked_approve_writes_no_audit_entry(): void
    {
        $applicant = $this->createPendingApplicant(verified: false);
        $admin = User::factory()->platformAdmin()->create();

        $this->actingAs($admin)
            ->post(route('admin.employers.approve', $applicant))
            ->assertSessionHasErrors('status');

        $this->assertDatabaseMissing('audit_logs', [
            'action' => AuditAction::EmployerApproved->value,
            'target_id' => $applicant->id,
        ]);
    }

    public function test_blocked_reactivation_of_unverified_suspended_employer(): void
    {
        $user = User::factory()->jobSeeker()->create();

        $this->actingAs($user)
            ->post(route('employer.onboarding.store'), self::validPayload())
            ->assertSessionHasNoErrors();

        $admin = User::factory()->platformAdmin()->create();

        $this->actingAs($admin)->post(route('admin.employers.approve', $user))->assertSessionHasNoErrors();
        $this->actingAs($admin)->post(route('admin.employers.suspend', $user), [
            'reason' => 'Policy violations',
        ])->assertSessionHasNoErrors();

        // The employer changes their email after suspension: verification
        // resets, and reactivation must refuse the now-unverified mailbox.
        $user->refresh();
        $user->forceFill(['email' => 'newemail@example.com', 'email_verified_at' => null])->save();

        $this->actingAs($admin)
            ->post(route('admin.employers.reactivate', $user))
            ->assertSessionHasErrors('status');

        $this->assertSame('suspended', $user->fresh()->status->value);

        $this->assertDatabaseMissing('audit_logs', [
            'action' => AuditAction::EmployerReactivated->value,
            'target_id' => $user->id,
        ]);

        // Re-verifying unblocks reactivation.
        $user->refresh()->markEmailAsVerified();

        $this->actingAs($admin)
            ->post(route('admin.employers.reactivate', $user))
            ->assertSessionHasNoErrors();

        $this->assertSame('approved', $user->fresh()->status->value);
    }

    public function test_admin_show_page_exposes_the_email_verified_boolean(): void
    {
        $applicant = $this->createPendingApplicant(verified: false);
        $admin = User::factory()->platformAdmin()->create();

        $this->actingAs($admin)
            ->get(route('admin.employers.show', $applicant))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('employer.email_verified', false)
                ->where('employer.actions.approve', true)
                ->missing('employer.email_verified_at'),
            );
    }

    public function test_admin_index_list_marks_pending_unverified_applicants(): void
    {
        $applicant = $this->createPendingApplicant(verified: false);
        $admin = User::factory()->platformAdmin()->create();

        $this->actingAs($admin)
            ->get(route('admin.employers.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('employers.data.0.email_verified', false),
            );
    }

    private function createPendingApplicant(bool $verified): User
    {
        $applicant = $verified
            ? User::factory()->jobSeeker()->create()
            : User::factory()->unverified()->jobSeeker()->create();

        $applicant->forceFill(['status' => EmployerStatus::Pending])->save();

        $applicant->employerOnboardingRequest()->create([
            'company_name' => 'Acme Corp',
            'company_website' => 'https://acme.example',
            'job_title' => 'Founder',
            'message' => null,
            'submitted_at' => now()->subDay(),
            'reviewed_at' => null,
            'reviewed_by_id' => null,
            'review_note' => null,
        ]);

        return $applicant;
    }

    private function createRejectedRequest(User $user, ?CarbonInterface $rejectedAt): void
    {
        $user->forceFill(['status' => EmployerStatus::Rejected])->save();

        $user->employerOnboardingRequest()->create([
            'company_name' => 'Acme Corp',
            'company_website' => null,
            'job_title' => 'Founder',
            'message' => null,
            'submitted_at' => $rejectedAt?->copy()->subDay() ?? now()->subDay(),
            'reviewed_at' => $rejectedAt,
            'reviewed_by_id' => null,
            'review_note' => 'Not eligible',
        ]);
    }
}
