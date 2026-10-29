<?php

namespace Tests\Feature\Authorization;

use App\Enums\RoleSlug;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Fortify\Features;
use RuntimeException;
use Tests\Feature\Authorization\Concerns\InteractsWithAuthorizationCatalogue;
use Tests\TestCase;

class RegistrationAuthorizationTest extends TestCase
{
    use InteractsWithAuthorizationCatalogue, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->skipUnlessFortifyHas(Features::registration());
    }

    public function test_valid_registration_assigns_exactly_the_job_seeker_role(): void
    {
        $this->seedAuthorizationCatalogue();

        $response = $this->post(route('register.store'), $this->validRegistrationPayload());

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('dashboard', absolute: false));

        $user = User::sole();

        $this->assertSame(
            [RoleSlug::JobSeeker->value],
            $user->roles()->pluck('slug')->all(),
        );
        $this->assertTrue($user->hasRole(RoleSlug::JobSeeker));
        $this->assertFalse($user->hasRole(RoleSlug::Employer));
        $this->assertFalse($user->hasRole(RoleSlug::PlatformAdmin));
        $this->assertDatabaseCount('role_user', 1);
    }

    public function test_forged_authorization_payload_fields_do_not_change_the_registration_role(): void
    {
        $this->seedAuthorizationCatalogue();

        $response = $this->post(route('register.store'), $this->validRegistrationPayload([
            'role' => 'platform_admin',
            'role_id' => $this->roleId(RoleSlug::PlatformAdmin),
            'is_admin' => true,
            'permissions' => ['admin.access', 'users.view', 'users.roles.manage'],
            'account_status' => 'approved',
            'email_verified_at' => now()->toIso8601String(),
        ]));

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('dashboard', absolute: false));

        $user = User::sole();

        $this->assertSame(
            [RoleSlug::JobSeeker->value],
            $user->roles()->pluck('slug')->all(),
        );
        $this->assertFalse($user->hasPermission('admin.access'));
        $this->assertNull($user->email_verified_at);

        foreach (['role', 'role_id', 'is_admin', 'permissions', 'account_status'] as $field) {
            $this->assertNull($user->getAttribute($field));
        }

        $this->assertDatabaseCount('role_user', 1);
    }

    public function test_registration_fails_closed_and_rolls_back_when_the_job_seeker_role_is_missing(): void
    {
        // AuthorizationSeeder is intentionally not run. The catalogue row is
        // absent, so the registration transaction must roll back the user.
        $this->withoutExceptionHandling();

        try {
            $this->post(route('register.store'), $this->validRegistrationPayload());
            $this->fail('Registration should fail when the job_seeker catalogue row is missing.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString(
                'Role [job_seeker] is not defined.',
                $exception->getMessage(),
            );
        }

        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('role_user', 0);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validRegistrationPayload(array $overrides = []): array
    {
        return [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            ...$overrides,
        ];
    }
}
