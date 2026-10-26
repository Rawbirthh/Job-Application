<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Fortify\Features;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered()
    {
        $response = $this->get(route('login'));

        $response->assertOk();
    }

    public function test_users_can_authenticate_using_the_login_screen()
    {
        $user = User::factory()->create();

        $response = $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_users_are_returned_to_the_intended_url_after_login()
    {
        $user = User::factory()->create();

        $this->get(route('profile.edit'))->assertRedirect(route('login'));
        $sessionId = session()->getId();

        $response = $this->withCookie(config('session.cookie'), $sessionId)
            ->post(route('login.store'), [
                'email' => $user->email,
                'password' => 'password',
            ]);

        $response->assertRedirect(route('profile.edit'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_login_ignores_external_redirect_payloads()
    {
        $user = User::factory()->create();

        $response = $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
            'redirect' => 'https://evil.example/steal',
            'url' => 'https://evil.example/steal',
        ]);

        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticatedAs($user);
    }

    public function test_authenticated_users_are_redirected_away_from_the_login_screen()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('login'))
            ->assertRedirect(route('dashboard'));
    }

    public function test_authenticated_users_are_redirected_away_from_the_registration_screen()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('register'))
            ->assertRedirect(route('dashboard'));
    }

    public function test_login_regenerates_the_session_id()
    {
        $user = User::factory()->create();

        $this->get(route('login'))->assertOk();
        $sessionId = session()->getId();

        $response = $this->withCookie(config('session.cookie'), $sessionId)
            ->post(route('login.store'), [
                'email' => $user->email,
                'password' => 'password',
            ]);

        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertNotSame($sessionId, session()->getId());
        $this->assertAuthenticatedAs($user);
    }

    public function test_users_with_two_factor_enabled_are_redirected_to_two_factor_challenge()
    {
        $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

        Features::twoFactorAuthentication([
            'confirm' => true,
            'confirmPassword' => true,
        ]);

        $user = User::factory()->withTwoFactor()->create();

        $response = $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('two-factor.login'));
        $response->assertSessionHas('login.id', $user->id);
        $this->assertGuest();
    }

    public function test_users_can_not_authenticate_with_invalid_password()
    {
        $user = User::factory()->create();

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_unknown_email_and_invalid_password_return_the_same_generic_error()
    {
        $user = User::factory()->create();

        $invalidPassword = $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $unknownEmail = $this->post(route('login.store'), [
            'email' => 'missing@example.com',
            'password' => 'wrong-password',
        ]);

        $invalidPassword->assertSessionHasErrors(['email' => trans('auth.failed')]);
        $unknownEmail->assertSessionHasErrors(['email' => trans('auth.failed')]);
        $this->assertGuest();
    }

    public function test_users_can_logout()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('logout'));

        $response->assertRedirect(route('home'));

        $this->assertGuest();
    }

    public function test_protected_routes_require_authentication_after_logout()
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('logout'))->assertRedirect(route('home'));
        $this->assertGuest();

        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_users_are_rate_limited()
    {
        $user = User::factory()->create();

        RateLimiter::increment(md5('login'.implode('|', [$user->email, '127.0.0.1'])), amount: 5);

        $response = $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertTooManyRequests();
    }
}
