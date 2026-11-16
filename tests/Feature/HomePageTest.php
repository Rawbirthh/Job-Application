<?php

namespace Tests\Feature;

use App\Models\Job;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class HomePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_can_view_the_homepage_without_being_redirected()
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertHeaderMissing('Location');
        $response->assertInertia(fn (Assert $page) => $page
            ->component('home'),
        );
    }

    public function test_authenticated_users_can_view_the_homepage_without_being_redirected()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/');

        $response->assertOk();
        $response->assertHeaderMissing('Location');
        $response->assertInertia(fn (Assert $page) => $page
            ->component('home'),
        );
    }

    public function test_homepage_exposes_live_stats_for_published_jobs_only()
    {
        Job::factory()->status('published')->count(2)->create();
        Job::factory()->status('draft')->create();

        $response = $this->get('/');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('home')
            ->where('stats.openRoles', 2)
            ->where('stats.hiringCompanies', 2),
        );
    }

    public function test_guest_shared_auth_user_is_null()
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('home')
            ->whereNull('auth.user'),
        );
    }
}
