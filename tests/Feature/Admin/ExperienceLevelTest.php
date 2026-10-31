<?php

namespace Tests\Feature\Admin;

use App\Enums\PermissionSlug;
use App\Models\ExperienceLevel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Authorization\Concerns\InteractsWithAuthorizationCatalogue;
use Tests\TestCase;

class ExperienceLevelTest extends TestCase
{
    use InteractsWithAuthorizationCatalogue, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAuthorizationCatalogue();
    }

    public function test_admin_can_view_experience_levels_index(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        ExperienceLevel::factory()->create([
            'name' => 'Senior',
            'slug' => 'senior',
            'sort_order' => 4,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.experience-levels.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/experience-levels/index')
                ->has('experienceLevels.data', 1)
                ->where('experienceLevels.data.0.name', 'Senior')
                ->where('experienceLevels.data.0.slug', 'senior')
                ->where('experienceLevels.data.0.sort_order', 4)
                ->where('canManageExperienceLevels', true)
            );
    }

    public function test_admin_can_create_experience_level_with_default_active_state(): void
    {
        $admin = User::factory()->platformAdmin()->create();

        $response = $this->actingAs($admin)
            ->post(route('admin.experience-levels.store'), [
                'name' => 'Mid-level',
                'slug' => 'mid-level',
                'description' => 'Experienced individual contributor',
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('admin.experience-levels.index'));

        $this->assertDatabaseHas('experience_levels', [
            'name' => 'Mid-level',
            'slug' => 'mid-level',
            'is_active' => true,
            'sort_order' => 0,
        ]);
    }

    public function test_admin_can_create_experience_level_with_explicit_sort_order(): void
    {
        $admin = User::factory()->platformAdmin()->create();

        $response = $this->actingAs($admin)
            ->post(route('admin.experience-levels.store'), [
                'name' => 'Lead',
                'slug' => 'lead',
                'sort_order' => 9,
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('admin.experience-levels.index'));

        $this->assertDatabaseHas('experience_levels', [
            'name' => 'Lead',
            'slug' => 'lead',
            'sort_order' => 9,
            'is_active' => true,
        ]);
    }

    public function test_admin_can_update_experience_level(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $experienceLevel = ExperienceLevel::factory()->create([
            'name' => 'Senior',
            'slug' => 'senior',
            'is_active' => true,
            'sort_order' => 4,
        ]);

        $response = $this->actingAs($admin)
            ->put(route('admin.experience-levels.update', $experienceLevel), [
                'name' => 'Principal',
                'slug' => 'principal',
                'description' => 'Organization-wide technical leadership',
                'sort_order' => 6,
                'is_active' => false,
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('admin.experience-levels.index'));

        $this->assertDatabaseHas('experience_levels', [
            'id' => $experienceLevel->id,
            'name' => 'Principal',
            'slug' => 'principal',
            'sort_order' => 6,
            'is_active' => false,
        ]);
    }

    public function test_admin_can_delete_experience_level(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $experienceLevel = ExperienceLevel::factory()->create([
            'name' => 'Internship',
            'slug' => 'internship',
        ]);

        $response = $this->actingAs($admin)
            ->delete(route('admin.experience-levels.destroy', $experienceLevel));

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('admin.experience-levels.index'));

        $this->assertDatabaseMissing('experience_levels', [
            'id' => $experienceLevel->id,
        ]);
    }

    public function test_experience_level_requires_unique_name_and_slug(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        ExperienceLevel::factory()->create([
            'name' => 'Senior',
            'slug' => 'senior',
        ]);

        $response = $this->actingAs($admin)
            ->post(route('admin.experience-levels.store'), [
                'name' => 'Senior',
                'slug' => 'senior',
            ]);

        $response->assertSessionHasErrors(['name', 'slug']);
    }

    public function test_update_ignores_unique_rules_for_the_same_row(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $experienceLevel = ExperienceLevel::factory()->create([
            'name' => 'Junior',
            'slug' => 'junior',
        ]);

        $response = $this->actingAs($admin)
            ->put(route('admin.experience-levels.update', $experienceLevel), [
                'name' => 'Junior',
                'slug' => 'junior',
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('admin.experience-levels.index'));

        $this->assertDatabaseHas('experience_levels', [
            'id' => $experienceLevel->id,
            'name' => 'Junior',
            'slug' => 'junior',
        ]);
    }

    public function test_update_rejects_duplicate_name_and_slug_from_another_row(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $experienceLevel = ExperienceLevel::factory()->create([
            'name' => 'Mid-level',
            'slug' => 'mid-level',
        ]);
        ExperienceLevel::factory()->create([
            'name' => 'Senior',
            'slug' => 'senior',
        ]);

        $response = $this->actingAs($admin)
            ->put(route('admin.experience-levels.update', $experienceLevel), [
                'name' => 'Senior',
                'slug' => 'senior',
            ]);

        $response->assertSessionHasErrors(['name', 'slug']);

        $this->assertDatabaseHas('experience_levels', [
            'id' => $experienceLevel->id,
            'name' => 'Mid-level',
            'slug' => 'mid-level',
        ]);
    }

    public function test_experience_level_requires_valid_input(): void
    {
        $admin = User::factory()->platformAdmin()->create();

        $response = $this->actingAs($admin)
            ->post(route('admin.experience-levels.store'), [
                'name' => '',
                'slug' => 'not a slug!',
                'description' => str_repeat('a', 501),
                'sort_order' => 10000,
            ]);

        $response->assertSessionHasErrors(['name', 'slug', 'description', 'sort_order']);
    }

    public function test_non_admin_cannot_access_experience_levels(): void
    {
        $user = User::factory()->jobSeeker()->create();

        $this->actingAs($user)
            ->get(route('admin.experience-levels.index'))
            ->assertForbidden();

        $this->actingAs($user)
            ->post(route('admin.experience-levels.store'), [
                'name' => 'Senior',
                'slug' => 'senior',
            ])
            ->assertForbidden();

        $this->actingAs($user)
            ->put(route('admin.experience-levels.update', ExperienceLevel::factory()->create()), [
                'name' => 'Senior',
                'slug' => 'senior',
            ])
            ->assertForbidden();

        $this->actingAs($user)
            ->delete(route('admin.experience-levels.destroy', ExperienceLevel::factory()->create()))
            ->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('admin.experience-levels.index'))
            ->assertRedirect(route('login'));
    }

    public function test_view_only_admin_can_view_but_cannot_manage_experience_levels(): void
    {
        $role = $this->createCustomRoleWithPermissions(
            'experience-levels-viewer',
            'Experience Levels Viewer',
            [PermissionSlug::AdminAccess, PermissionSlug::ExperienceLevelsView],
        );
        $user = User::factory()->withRole($role)->create();
        $experienceLevel = ExperienceLevel::factory()->create([
            'name' => 'Senior',
            'slug' => 'senior',
        ]);

        $this->actingAs($user)
            ->get(route('admin.experience-levels.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/experience-levels/index')
                ->where('canManageExperienceLevels', false)
            );

        $this->actingAs($user)
            ->post(route('admin.experience-levels.store'), [
                'name' => 'Lead',
                'slug' => 'lead',
            ])
            ->assertForbidden();

        $this->actingAs($user)
            ->put(route('admin.experience-levels.update', $experienceLevel), [
                'name' => 'Lead',
                'slug' => 'lead',
            ])
            ->assertForbidden();

        $this->actingAs($user)
            ->delete(route('admin.experience-levels.destroy', $experienceLevel))
            ->assertForbidden();

        $this->assertDatabaseHas('experience_levels', [
            'id' => $experienceLevel->id,
            'name' => 'Senior',
            'slug' => 'senior',
        ]);
    }

    public function test_update_and_destroy_return_not_found_for_unknown_experience_level(): void
    {
        $admin = User::factory()->platformAdmin()->create();

        $this->actingAs($admin)
            ->put(route('admin.experience-levels.update', 999999), [
                'name' => 'Senior',
                'slug' => 'senior',
            ])
            ->assertNotFound();

        $this->actingAs($admin)
            ->delete(route('admin.experience-levels.destroy', 999999))
            ->assertNotFound();
    }

    public function test_search_matches_prefix_by_name(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        ExperienceLevel::factory()->create([
            'name' => 'Senior',
            'slug' => 'senior',
        ]);
        ExperienceLevel::factory()->create([
            'name' => 'Junior',
            'slug' => 'junior',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.experience-levels.index', ['search' => 'Sen']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('experienceLevels.data', 1)
                ->where('experienceLevels.data.0.name', 'Senior')
            );
    }

    public function test_search_matches_prefix_by_slug(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        ExperienceLevel::factory()->create([
            'name' => 'Senior',
            'slug' => 'senior',
        ]);
        ExperienceLevel::factory()->create([
            'name' => 'Junior',
            'slug' => 'junior',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.experience-levels.index', ['search' => 'jun']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('experienceLevels.data', 1)
                ->where('experienceLevels.data.0.slug', 'junior')
            );
    }

    public function test_search_returns_empty_state_when_no_results(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        ExperienceLevel::factory()->create([
            'name' => 'Senior',
            'slug' => 'senior',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.experience-levels.index', ['search' => 'nothing']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('experienceLevels.data', 0)
            );
    }

    public function test_rows_are_ordered_by_sort_order_then_name_then_id(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        ExperienceLevel::factory()->create([
            'name' => 'Zeta',
            'slug' => 'zeta',
            'sort_order' => 2,
        ]);
        ExperienceLevel::factory()->create([
            'name' => 'Beta',
            'slug' => 'beta',
            'sort_order' => 1,
        ]);
        ExperienceLevel::factory()->create([
            'name' => 'Alpha',
            'slug' => 'alpha',
            'sort_order' => 1,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.experience-levels.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('experienceLevels.data', 3)
                ->where('experienceLevels.data.0.name', 'Alpha')
                ->where('experienceLevels.data.1.name', 'Beta')
                ->where('experienceLevels.data.2.name', 'Zeta')
            );
    }
}
