<?php

namespace Tests\Feature\Authorization;

use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Authorization\Concerns\InteractsWithAuthorizationCatalogue;
use Tests\TestCase;

class AdminPermissionManagementTest extends TestCase
{
    use InteractsWithAuthorizationCatalogue, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAuthorizationCatalogue();
    }

    public function test_admin_can_view_permissions_list(): void
    {
        $admin = User::factory()->platformAdmin()->create();

        $this->actingAs($admin)
            ->get(route('admin.permissions.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/permissions/index')
                ->has('permissions.data')
            );
    }

    public function test_admin_can_create_new_permission(): void
    {
        $admin = User::factory()->platformAdmin()->create();

        $response = $this->actingAs($admin)
            ->post(route('admin.permissions.store'), [
                'name' => 'View Reports',
                'slug' => 'reports.view',
                'description' => 'Can view platform reports',
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('admin.permissions.index'));

        $this->assertDatabaseHas('permissions', [
            'slug' => 'reports.view',
            'name' => 'View Reports',
            'is_system' => false,
        ]);
    }

    public function test_admin_cannot_create_permission_with_duplicate_slug(): void
    {
        $admin = User::factory()->platformAdmin()->create();

        $response = $this->actingAs($admin)
            ->post(route('admin.permissions.store'), [
                'name' => 'Duplicate Access',
                'slug' => 'admin.access',
            ]);

        $response->assertSessionHasErrors('slug');
    }

    public function test_admin_cannot_delete_system_permission(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $systemPermission = Permission::where('slug', 'admin.access')->first();

        $response = $this->actingAs($admin)
            ->delete(route('admin.permissions.destroy', $systemPermission));

        $response->assertForbidden();
        $this->assertDatabaseHas('permissions', ['id' => $systemPermission->id]);
    }

    public function test_non_admin_cannot_access_permission_management(): void
    {
        $user = User::factory()->jobSeeker()->create();

        $this->actingAs($user)
            ->get(route('admin.permissions.index'))
            ->assertForbidden();

        $this->actingAs($user)
            ->post(route('admin.permissions.store'), [
                'name' => 'New Perm',
                'slug' => 'new.perm',
            ])
            ->assertForbidden();
    }
}
