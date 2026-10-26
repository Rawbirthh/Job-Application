<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class EmailVerificationDisabledTest extends TestCase
{
    use RefreshDatabase;

    public function test_fortify_email_verification_routes_are_not_registered()
    {
        $this->assertFalse(Route::has('verification.notice'));
        $this->assertFalse(Route::has('verification.verify'));
        $this->assertFalse(Route::has('verification.send'));
    }

    public function test_registration_succeeds_without_sending_a_verification_email()
    {
        Notification::fake();

        $response = $this->post(route('register.store'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        Notification::assertNothingSent();
    }

    public function test_unverified_users_can_access_the_dashboard_and_profile_settings()
    {
        $user = User::factory()->unverified()->create();

        $this->assertNull($user->email_verified_at);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk();

        $this->actingAs($user)
            ->get(route('profile.edit'))
            ->assertOk();
    }

    public function test_dashboard_auth_user_payload_only_exposes_public_profile_fields()
    {
        $user = User::factory()->unverified()->create();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertInertia(function (Assert $page) use ($user) {
            $page->component('dashboard')
                ->where('auth.user.id', $user->id)
                ->where('auth.user.name', $user->name)
                ->where('auth.user.email', $user->email)
                ->missing('auth.user.email_verified_at')
                ->missing('auth.user.password')
                ->missing('auth.user.two_factor_secret')
                ->missing('auth.user.two_factor_recovery_codes')
                ->missing('auth.user.two_factor_confirmed_at')
                ->missing('auth.user.remember_token');

            $this->assertEqualsCanonicalizing(
                ['id', 'name', 'email'],
                array_keys($page->toArray()['props']['auth']['user']),
            );
        });
    }
}
