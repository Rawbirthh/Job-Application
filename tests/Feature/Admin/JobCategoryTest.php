<?php

namespace Tests\Feature\Admin;

use App\Models\JobCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Authorization\Concerns\InteractsWithAuthorizationCatalogue;
use Tests\TestCase;

class JobCategoryTest extends TestCase
{
    use InteractsWithAuthorizationCatalogue, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAuthorizationCatalogue();
    }

    public function test_admin_can_view_job_categories_index(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        JobCategory::factory()->create([
            'name' => 'Engineering',
            'slug' => 'engineering',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.categories.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/categories/index')
                ->has('categories.data', 1)
                ->where('categories.data.0.name', 'Engineering')
                ->where('categories.data.0.slug', 'engineering')
            );
    }

    public function test_admin_can_create_job_category(): void
    {
        $admin = User::factory()->platformAdmin()->create();

        $response = $this->actingAs($admin)
            ->post(route('admin.categories.store'), [
                'name' => 'Product Management',
                'slug' => 'product-management',
                'description' => 'Product and roadmap management roles',
                'is_active' => true,
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('admin.categories.index'));

        $this->assertDatabaseHas('job_categories', [
            'name' => 'Product Management',
            'slug' => 'product-management',
            'is_active' => true,
        ]);
    }

    public function test_admin_can_update_job_category(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $category = JobCategory::factory()->create([
            'name' => 'Design',
            'slug' => 'design',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)
            ->put(route('admin.categories.update', $category), [
                'name' => 'UX Design',
                'slug' => 'ux-design',
                'description' => 'User experience and visual design',
                'is_active' => false,
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('admin.categories.index'));

        $this->assertDatabaseHas('job_categories', [
            'id' => $category->id,
            'name' => 'UX Design',
            'slug' => 'ux-design',
            'is_active' => false,
        ]);
    }

    public function test_admin_can_delete_job_category(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $category = JobCategory::factory()->create([
            'name' => 'Temporary',
            'slug' => 'temporary',
        ]);

        $response = $this->actingAs($admin)
            ->delete(route('admin.categories.destroy', $category));

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('admin.categories.index'));

        $this->assertDatabaseMissing('job_categories', [
            'id' => $category->id,
        ]);
    }

    public function test_category_requires_unique_name_and_slug(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        JobCategory::factory()->create([
            'name' => 'Engineering',
            'slug' => 'engineering',
        ]);

        $response = $this->actingAs($admin)
            ->post(route('admin.categories.store'), [
                'name' => 'Engineering',
                'slug' => 'engineering',
            ]);

        $response->assertSessionHasErrors(['name', 'slug']);
    }

    public function test_non_admin_cannot_access_job_categories(): void
    {
        $user = User::factory()->jobSeeker()->create();

        $this->actingAs($user)
            ->get(route('admin.categories.index'))
            ->assertForbidden();

        $this->actingAs($user)
            ->post(route('admin.categories.store'), [
                'name' => 'Sales',
                'slug' => 'sales',
            ])
            ->assertForbidden();
    }
}
