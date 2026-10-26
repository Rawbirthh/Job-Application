<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Laravel\Fortify\Features;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->skipUnlessFortifyHas(Features::resetPasswords());
    }

    public function test_reset_password_link_screen_can_be_rendered()
    {
        $response = $this->get(route('password.request'));

        $response->assertOk();
    }

    public function test_reset_password_link_can_be_requested()
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post(route('password.email'), ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_reset_password_screen_can_be_rendered()
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post(route('password.email'), ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) {
            $response = $this->get(route('password.reset', $notification->token));

            $response->assertOk();

            return true;
        });
    }

    public function test_password_can_be_reset_with_valid_token()
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post(route('password.email'), ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
            $response = $this->post(route('password.update'), [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ]);

            $response
                ->assertSessionHasNoErrors()
                ->assertRedirect(route('login'));

            $this->assertGuest();
            $this->assertTrue(Hash::check('new-password', $user->fresh()->password));
            $this->assertFalse(Hash::check('password', $user->fresh()->password));

            return true;
        });
    }

    public function test_old_password_cannot_be_used_after_a_password_reset()
    {
        $user = User::factory()->create();
        $token = Password::broker()->createToken($user);

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertSessionHasNoErrors()->assertRedirect(route('login'));

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'new-password',
        ])->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($user);
    }

    public function test_password_cannot_be_reset_with_a_mismatched_confirmation()
    {
        $user = User::factory()->create();
        $token = Password::broker()->createToken($user);

        $response = $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'new-password',
            'password_confirmation' => 'different-password',
        ]);

        $response->assertSessionHasErrors('password');

        $this->assertGuest();
        $this->assertTrue(Hash::check('password', $user->fresh()->password));
        $this->assertFalse(Hash::check('new-password', $user->fresh()->password));
    }

    public function test_password_cannot_be_reset_with_a_short_password()
    {
        $user = User::factory()->create();
        $token = Password::broker()->createToken($user);

        $response = $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'short',
            'password_confirmation' => 'short',
        ]);

        $response->assertSessionHasErrors('password');

        $this->assertGuest();
        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_password_cannot_be_reset_with_invalid_token(): void
    {
        $user = User::factory()->create();

        $response = $this->post(route('password.update'), [
            'token' => 'invalid-token',
            'email' => $user->email,
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_unknown_email_receives_the_generic_reset_link_status()
    {
        Notification::fake();

        $response = $this->post(route('password.email'), ['email' => 'missing@example.com']);

        $response->assertSessionHas('status', trans('passwords.sent'));
        $response->assertSessionDoesntHaveErrors('email');

        Notification::assertNothingSent();
    }

    public function test_unknown_email_receives_the_generic_reset_link_status_in_json()
    {
        $response = $this->postJson(route('password.email'), ['email' => 'missing@example.com']);

        $response->assertOk();
        $response->assertJson(['message' => trans('passwords.sent')]);
    }

    public function test_password_reset_link_requests_are_rate_limited()
    {
        Notification::fake();

        $user = User::factory()->create();
        $limiterKey = 'password-reset|'.Str::transliterate(Str::lower($user->email).'|127.0.0.1');

        RateLimiter::clear($limiterKey);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post(route('password.email'), ['email' => $user->email])->assertRedirect();
        }

        $response = $this->post(route('password.email'), ['email' => $user->email]);

        $response->assertTooManyRequests();
        $response->assertHeader('Retry-After');
        $this->assertGreaterThan(0, (int) $response->headers->get('Retry-After'));

        RateLimiter::clear($limiterKey);
    }
}
