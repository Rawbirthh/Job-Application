<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use App\Services\Authorization\RoleAssignmentService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class AssignRoleCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'auth:assign-role
                            {email : Existing user email}
                            {role=platform_admin : Role slug}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Assign a global role to an existing user';

    /**
     * Execute the console command.
     */
    public function handle(RoleAssignmentService $roleAssignmentService): int
    {
        $email = Str::lower(trim((string) $this->argument('email')));
        $slug = trim((string) $this->argument('role'));

        $user = User::query()
            ->select(['id', 'email'])
            ->where('email', $email)
            ->first();

        $user ??= User::query()
            ->select(['id', 'email'])
            ->whereRaw('LOWER(email) = ?', [$email])
            ->first();

        if ($user === null) {
            $this->components->error("No user found with email [{$email}].");

            return self::FAILURE;
        }

        if (Role::query()->where('slug', $slug)->doesntExist()) {
            $this->components->error(
                "Role [{$slug}] does not exist. Run the AuthorizationSeeder before assigning roles.",
            );

            return self::FAILURE;
        }

        $roleAssignmentService->assignRoleBySlug($user, $slug);

        $this->components->info("Assigned role [{$slug}] to [{$user->email}].");

        return self::SUCCESS;
    }
}
