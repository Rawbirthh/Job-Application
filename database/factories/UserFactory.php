<?php

namespace Database\Factories;

use App\Enums\RoleSlug;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Indicate that the model has two-factor authentication configured.
     */
    public function withTwoFactor(): static
    {
        return $this->state(fn (array $attributes) => [
            'two_factor_secret' => encrypt('secret'),
            'two_factor_recovery_codes' => encrypt(json_encode(['recovery-code-1'])),
            'two_factor_confirmed_at' => now(),
        ]);
    }

    /**
     * Attach an existing role to the created user.
     */
    public function withRole(Role|RoleSlug|string $role): static
    {
        return $this->afterCreating(function (User $user) use ($role): void {
            $roleModel = $role instanceof Role
                ? $role
                : Role::query()
                    ->where('slug', $role instanceof RoleSlug ? $role->value : $role)
                    ->firstOrFail();

            $user->roles()->syncWithoutDetaching([$roleModel->id]);
            $user->clearPermissionCache();
        });
    }

    /**
     * Indicate that the created user is a platform administrator.
     */
    public function platformAdmin(): static
    {
        return $this->withRole(RoleSlug::PlatformAdmin);
    }

    /**
     * Indicate that the created user is an employer.
     */
    public function employer(): static
    {
        return $this->withRole(RoleSlug::Employer);
    }

    /**
     * Indicate that the created user is a job seeker.
     */
    public function jobSeeker(): static
    {
        return $this->withRole(RoleSlug::JobSeeker);
    }
}
