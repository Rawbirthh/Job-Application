<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Database\Seeders\AuthorizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Fortify\Features;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->skipUnlessFortifyHas(Features::registration());
        $this->seed(AuthorizationSeeder::class);
    }

    public function test_registration_screen_can_be_rendered()
    {
        $response = $this->get(route('register'));

        $response->assertOk();
    }

    public function test_new_users_can_register()
    {
        $response = $this->post(route('register.store'), $this->validRegistrationPayload());

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_registration_normalizes_the_email_and_hashes_the_password()
    {
        $response = $this->post(route('register.store'), $this->validRegistrationPayload([
            'email' => 'Test.User@Example.COM',
        ]));

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticated();

        $user = User::sole();

        $this->assertSame('test.user@example.com', $user->email);
        $this->assertNotSame('password', $user->password);
        $this->assertTrue(Hash::check('password', $user->password));
        $this->assertNull($user->email_verified_at);
    }

    public function test_registration_regenerates_the_session_id()
    {
        $this->get(route('register'))->assertOk();
        $sessionId = session()->getId();

        $response = $this->withCookie(config('session.cookie'), $sessionId)
            ->post(route('register.store'), $this->validRegistrationPayload());

        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertNotSame($sessionId, session()->getId());
        $this->assertAuthenticated();
    }

    public function test_registration_requires_a_unique_email()
    {
        User::factory()->create(['email' => 'existing@example.com']);

        $response = $this->post(route('register.store'), $this->validRegistrationPayload([
            'email' => 'existing@example.com',
        ]));

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertDatabaseCount('users', 1);
    }

    public function test_registration_requires_a_valid_email()
    {
        $response = $this->post(route('register.store'), $this->validRegistrationPayload([
            'email' => 'not-an-email',
        ]));

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_registration_requires_a_name()
    {
        $response = $this->post(route('register.store'), [
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertSessionHasErrors('name');
        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_registration_rejects_a_name_longer_than_255_characters()
    {
        $response = $this->post(route('register.store'), $this->validRegistrationPayload([
            'name' => str_repeat('a', 256),
        ]));

        $response->assertSessionHasErrors('name');
        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_registration_requires_a_confirmed_password()
    {
        $response = $this->post(route('register.store'), $this->validRegistrationPayload([
            'password_confirmation' => 'different-password',
        ]));

        $response->assertSessionHasErrors('password');
        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_registration_rejects_a_short_password()
    {
        $response = $this->post(route('register.store'), $this->validRegistrationPayload([
            'password' => 'short',
            'password_confirmation' => 'short',
        ]));

        $response->assertSessionHasErrors('password');
        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_registration_ignores_privileged_payload_fields()
    {
        $response = $this->post(route('register.store'), $this->validRegistrationPayload([
            'role' => 'admin',
            'role_id' => 1,
            'is_admin' => true,
            'permissions' => ['*'],
            'account_status' => 'approved',
            'email_verified_at' => now()->toIso8601String(),
        ]));

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('dashboard', absolute: false));

        $user = User::sole();

        $this->assertSame(['job_seeker'], $user->roles()->pluck('slug')->all());
        $this->assertNull($user->email_verified_at);

        foreach (['role', 'role_id', 'is_admin', 'permissions', 'account_status'] as $field) {
            $this->assertNull($user->getAttribute($field));
        }

        $this->assertAuthenticatedAs($user);
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
