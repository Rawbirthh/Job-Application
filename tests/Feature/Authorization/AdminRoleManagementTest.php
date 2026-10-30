<?php

namespace Tests\Feature\Authorization;

use App\Enums\RoleSlug;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Authorization\Concerns\InteractsWithAuthorizationCatalogue;
use Tests\TestCase;

class AdminRoleManagementTest extends TestCase
{
    use InteractsWithAuthorizationCatalogue, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAuthorizationCatalogue();
    }

    public function test_admin_can_view_roles_list(): void
    {
        $admin = User::factory()->platformAdmin()->create();

        $this->actingAs($admin)
            ->get(route('admin.roles.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/roles/index')
                ->has('roles.data', 3)
                ->where('canManageRoles', true)
            );
    }

    public function test_admin_can_create_new_role(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $permission = Permission::query()->first();

        $response = $this->actingAs($admin)
            ->post(route('admin.roles.store'), [
                'name' => 'Content Editor',
                'slug' => 'content-editor',
                'description' => 'Can edit content',
                'permissions' => [$permission->id],
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('admin.roles.index'));

        $this->assertDatabaseHas('roles', [
            'slug' => 'content-editor',
            'name' => 'Content Editor',
            'is_system' => false,
        ]);

        $role = Role::where('slug', 'content-editor')->first();
        $this->assertTrue($role->permissions->contains($permission->id));
    }

    public function test_admin_cannot_create_role_with_duplicate_slug(): void
    {
        $admin = User::factory()->platformAdmin()->create();

        $response = $this->actingAs($admin)
            ->post(route('admin.roles.store'), [
                'name' => 'Duplicate Admin',
                'slug' => RoleSlug::PlatformAdmin->value,
            ]);

        $response->assertSessionHasErrors('slug');
    }

    public function test_admin_cannot_delete_system_role(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $systemRole = Role::where('slug', RoleSlug::PlatformAdmin->value)->first();

        $response = $this->actingAs($admin)
            ->delete(route('admin.roles.destroy', $systemRole));

        $response->assertForbidden();
        $this->assertDatabaseHas('roles', ['id' => $systemRole->id]);
    }

    public function test_non_admin_cannot_access_role_management(): void
    {
        $user = User::factory()->jobSeeker()->create();

        $this->actingAs($user)
            ->get(route('admin.roles.index'))
            ->assertForbidden();

        $this->actingAs($user)
            ->post(route('admin.roles.store'), [
                'name' => 'New Role',
                'slug' => 'new-role',
            ])
            ->assertForbidden();
    }
}
