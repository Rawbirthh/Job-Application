<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\QueuedVerifyEmail;
use Database\Seeders\AuthorizationSeeder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_verification_notification_is_dispatched_through_the_queue(): void
    {
        $this->assertInstanceOf(
            ShouldQueue::class,
            new QueuedVerifyEmail,
        );
    }

    public function test_fortify_email_verification_routes_are_registered(): void
    {
        $this->assertTrue(Route::has('verification.notice'));
        $this->assertTrue(Route::has('verification.verify'));
        $this->assertTrue(Route::has('verification.send'));
    }

    public function test_registration_does_not_send_a_verification_email(): void
    {
        $this->seed(AuthorizationSeeder::class);
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

        $user = User::query()->where('email', 'test@example.com')->firstOrFail();
        $this->assertNull($user->email_verified_at);

        Notification::assertNotSentTo($user, QueuedVerifyEmail::class);
    }

    public function test_unverified_users_can_access_the_dashboard_and_profile_settings(): void
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

    public function test_dashboard_auth_user_payload_only_exposes_public_profile_fields(): void
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

    public function test_signed_verification_link_marks_the_user_as_verified(): void
    {
        $user = User::factory()->unverified()->create();

        $url = URL::signedRoute('verification.verify', [
            'id' => $user->id,
            'hash' => sha1($user->getEmailForVerification()),
        ]);

        $this->actingAs($user)->get($url);

        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_unsigned_verification_link_is_rejected(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->get(route('verification.verify', [
                'id' => $user->id,
                'hash' => sha1($user->getEmailForVerification()),
            ]))
            ->assertForbidden();

        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_verification_notice_page_renders_for_unverified_users(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->get(route('verification.notice'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('auth/verify-email'),
            );
    }

    public function test_unverified_user_can_resend_verification_notification(): void
    {
        $user = User::factory()->unverified()->create();
        Notification::fake();

        $this->actingAs($user)->post(route('verification.send'));

        Notification::assertSentTo($user, QueuedVerifyEmail::class);
    }

    public function test_guests_cannot_resend_verification_notification(): void
    {
        Notification::fake();

        $this->post(route('verification.send'))
            ->assertRedirect(route('login'));

        Notification::assertNothingSent();
    }

    public function test_already_verified_user_resend_is_benign(): void
    {
        $user = User::factory()->create();
        Notification::fake();

        $this->actingAs($user)
            ->post(route('verification.send'))
            ->assertStatus(302)
            ->assertSessionHasNoErrors();

        Notification::assertNotSentTo($user, QueuedVerifyEmail::class);
    }

    public function test_expired_signed_verification_link_is_rejected(): void
    {
        $user = User::factory()->unverified()->create();

        $url = URL::temporarySignedRoute('verification.verify', now()->subMinute(), [
            'id' => $user->id,
            'hash' => sha1($user->getEmailForVerification()),
        ]);

        $this->actingAs($user)->get($url)->assertForbidden();

        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_verification_resend_is_rate_limited(): void
    {
        $user = User::factory()->unverified()->create();
        Notification::fake();

        for ($i = 0; $i < 6; $i++) {
            $this->actingAs($user)->post(route('verification.send'));
        }

        $this->actingAs($user)
            ->post(route('verification.send'))
            ->assertTooManyRequests();
    }
}
