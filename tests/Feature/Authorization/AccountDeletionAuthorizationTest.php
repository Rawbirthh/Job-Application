<?php

namespace Tests\Feature\Authorization;

use App\Enums\RoleSlug;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\Feature\Authorization\Concerns\InteractsWithAuthorizationCatalogue;
use Tests\TestCase;

class AccountDeletionAuthorizationTest extends TestCase
{
    use InteractsWithAuthorizationCatalogue, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAuthorizationCatalogue();
    }

    public function test_non_administrator_can_still_delete_their_own_account(): void
    {
        $user = User::factory()->jobSeeker()->create();

        $response = $this->actingAs($user)
            ->delete(route('profile.destroy'), ['password' => 'password']);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('home'));

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    public function test_last_platform_administrator_cannot_delete_their_account(): void
    {
        $administrator = User::factory()->platformAdmin()->create();

        $response = $this->actingAs($administrator)
            ->from(route('profile.edit'))
            ->delete(route('profile.destroy'), ['password' => 'password']);

        $response->assertRedirect(route('profile.edit'));
        $response->assertSessionHasErrors('account');

        $this->assertAuthenticatedAs($administrator);
        $this->assertDatabaseHas('users', ['id' => $administrator->id]);
        $this->assertDatabaseHas('role_user', [
            'user_id' => $administrator->id,
            'role_id' => $this->roleId(RoleSlug::PlatformAdmin),
        ]);
    }

    public function test_platform_administrator_can_delete_their_account_when_another_administrator_exists(): void
    {
        $administrator = User::factory()->platformAdmin()->create();
        $remainingAdministrator = User::factory()->platformAdmin()->create();

        $response = $this->actingAs($administrator)
            ->delete(route('profile.destroy'), ['password' => 'password']);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('home'));

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['id' => $administrator->id]);
        $this->assertDatabaseHas('users', ['id' => $remainingAdministrator->id]);
    }

    public function test_wrong_password_still_returns_a_password_error_without_deleting_the_account(): void
    {
        $administrator = User::factory()->platformAdmin()->create();

        $response = $this->actingAs($administrator)
            ->from(route('profile.edit'))
            ->delete(route('profile.destroy'), ['password' => 'wrong-password']);

        $response->assertRedirect(route('profile.edit'));
        $response->assertSessionHasErrors('password');

        $this->assertAuthenticatedAs($administrator);
        $this->assertDatabaseHas('users', ['id' => $administrator->id]);
    }

    public function test_user_policy_denies_deleting_another_user(): void
    {
        $actor = User::factory()->jobSeeker()->create();
        $otherUser = User::factory()->jobSeeker()->create();

        $this->assertFalse(Gate::forUser($actor)->allows('delete', $otherUser));
        $this->assertTrue(Gate::forUser($actor)->allows('delete', $actor));
    }
}
