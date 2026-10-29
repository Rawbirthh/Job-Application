<?php

namespace Database\Seeders;

use App\Enums\RoleSlug;
use App\Models\User;
use App\Services\Authorization\RoleAssignmentService;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(RoleAssignmentService $roleAssignmentService): void
    {
        $this->call(AuthorizationSeeder::class);

        // User::factory(10)->create();

        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $roleAssignmentService->assignRoleBySlug($user, RoleSlug::JobSeeker);
    }
}
