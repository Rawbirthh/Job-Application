<?php

namespace Tests\Feature\Admin;

use App\Models\ExperienceLevel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Authorization\Concerns\InteractsWithAuthorizationCatalogue;
use Tests\TestCase;

class ExperienceLevelMinYearsTest extends TestCase
{
    use InteractsWithAuthorizationCatalogue, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAuthorizationCatalogue();

        $this->withoutVite();
    }

    public function test_admin_can_create_an_experience_level_with_minimum_years(): void
    {
        $admin = User::factory()->platformAdmin()->create();

        $this->actingAs($admin)
            ->post(route('admin.experience-levels.store'), [
                'name' => 'Senior',
                'slug' => 'senior',
                'description' => 'Senior level',
                'sort_order' => 4,
                'min_years' => 5,
                'is_active' => true,
            ])
            ->assertRedirect(route('admin.experience-levels.index'));

        $this->assertDatabaseHas('experience_levels', [
            'slug' => 'senior',
            'min_years' => 5,
        ]);
    }

    public function test_admin_can_update_the_minimum_years_of_an_experience_level(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $level = ExperienceLevel::factory()->create(['min_years' => 2]);

        $this->actingAs($admin)
            ->put(route('admin.experience-levels.update', $level), [
                'name' => $level->name,
                'slug' => $level->slug,
                'description' => $level->description,
                'sort_order' => $level->sort_order,
                'min_years' => 8,
                'is_active' => true,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.experience-levels.index'));

        $this->assertDatabaseHas('experience_levels', [
            'id' => $level->id,
            'min_years' => 8,
        ]);
    }

    public function test_minimum_years_is_optional(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $level = ExperienceLevel::factory()->create(['min_years' => 5]);

        $this->actingAs($admin)
            ->put(route('admin.experience-levels.update', $level), [
                'name' => $level->name,
                'slug' => $level->slug,
                'description' => $level->description,
                'sort_order' => $level->sort_order,
                'is_active' => true,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.experience-levels.index'));

        $this->assertDatabaseHas('experience_levels', [
            'id' => $level->id,
            'min_years' => null,
        ]);
    }

    public function test_minimum_years_must_be_a_sane_number(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $level = ExperienceLevel::factory()->create();

        $this->actingAs($admin)
            ->put(route('admin.experience-levels.update', $level), [
                'name' => $level->name,
                'slug' => $level->slug,
                'description' => $level->description,
                'sort_order' => $level->sort_order,
                'min_years' => -1,
                'is_active' => true,
            ])
            ->assertSessionHasErrors('min_years');

        $this->actingAs($admin)
            ->put(route('admin.experience-levels.update', $level), [
                'name' => $level->name,
                'slug' => $level->slug,
                'description' => $level->description,
                'sort_order' => $level->sort_order,
                'min_years' => 61,
                'is_active' => true,
            ])
            ->assertSessionHasErrors('min_years');
    }

    public function test_the_index_exposes_the_minimum_years(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        ExperienceLevel::factory()->create(['min_years' => 7]);

        $this->actingAs($admin)
            ->get(route('admin.experience-levels.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/experience-levels/index')
                ->where('experienceLevels.data.0.min_years', 7)
            );
    }

    public function test_a_user_without_permission_cannot_change_minimum_years(): void
    {
        $employer = User::factory()->employer()->create();
        $level = ExperienceLevel::factory()->create(['min_years' => 2]);

        $this->actingAs($employer)
            ->put(route('admin.experience-levels.update', $level), [
                'name' => $level->name,
                'slug' => $level->slug,
                'description' => $level->description,
                'sort_order' => $level->sort_order,
                'min_years' => 99,
                'is_active' => true,
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('experience_levels', [
            'id' => $level->id,
            'min_years' => 2,
        ]);
    }
}
