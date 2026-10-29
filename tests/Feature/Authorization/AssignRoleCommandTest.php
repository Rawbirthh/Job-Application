<?php

namespace Tests\Feature\Authorization;

use App\Enums\RoleSlug;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Tests\Feature\Authorization\Concerns\InteractsWithAuthorizationCatalogue;
use Tests\TestCase;

class AssignRoleCommandTest extends TestCase
{
    use InteractsWithAuthorizationCatalogue, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAuthorizationCatalogue();
    }

    public function test_command_assigns_platform_admin_to_an_existing_user_by_default(): void
    {
        $user = User::factory()->create(['email' => 'operator@example.com']);

        $this->artisan('auth:assign-role', ['email' => 'OPERATOR@EXAMPLE.COM'])
            ->expectsOutputToContain('Assigned role [platform_admin] to [operator@example.com].')
            ->assertSuccessful();

        $this->assertDatabaseHas('role_user', [
            'user_id' => $user->id,
            'role_id' => $this->roleId(RoleSlug::PlatformAdmin),
        ]);
    }

    public function test_command_is_idempotent(): void
    {
        $user = User::factory()->create(['email' => 'operator@example.com']);

        $this->artisan('auth:assign-role', ['email' => $user->email])
            ->assertSuccessful();
        $this->artisan('auth:assign-role', ['email' => $user->email])
            ->assertSuccessful();

        $this->assertSame(
            [RoleSlug::PlatformAdmin->value],
            $user->roles()->pluck('slug')->all(),
        );
        $this->assertSame(1, $user->roles()->count());
    }

    public function test_command_fails_with_an_actionable_message_for_an_unknown_user(): void
    {
        $this->artisan('auth:assign-role', ['email' => 'missing@example.com'])
            ->expectsOutputToContain('No user found with email [missing@example.com].')
            ->assertFailed();

        $this->assertDatabaseCount('users', 0);
    }

    public function test_command_fails_with_an_actionable_message_for_an_unknown_role(): void
    {
        $user = User::factory()->create(['email' => 'operator@example.com']);

        $this->artisan('auth:assign-role', [
            'email' => $user->email,
            'role' => 'missing_role',
        ])
            ->expectsOutputToContain('Role [missing_role] does not exist.')
            ->assertFailed();

        $this->assertSame([], $user->roles()->pluck('slug')->all());
        $this->assertDatabaseCount('users', 1);
    }

    public function test_command_does_not_accept_a_password_argument(): void
    {
        $user = User::factory()->create(['email' => 'operator@example.com']);

        try {
            $this->artisan('auth:assign-role', [
                'email' => $user->email,
                'password' => 'secret-password',
            ])->run();

            $this->fail('The assignment command should not accept a password argument.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('password', $exception->getMessage());
        }

        $this->assertSame([], $user->roles()->pluck('slug')->all());
    }
}
