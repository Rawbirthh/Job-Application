<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_visit_the_dashboard()
    {
        $user = User::factory()->withTwoFactor()->create();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertInertia(function (Assert $page) use ($user) {
            $page->component('dashboard')
                ->where('auth.user.id', $user->id)
                ->where('auth.user.name', $user->name)
                ->where('auth.user.email', $user->email)
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
