<?php

namespace Tests\Feature\Admin;

use App\Models\EmploymentType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Authorization\Concerns\InteractsWithAuthorizationCatalogue;
use Tests\TestCase;

class EmploymentTypeTest extends TestCase
{
    use InteractsWithAuthorizationCatalogue, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAuthorizationCatalogue();
    }

    public function test_admin_can_view_employment_types_index(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        EmploymentType::factory()->create([
            'name' => 'Full-time',
            'slug' => 'full-time',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.employment-types.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/employment-types/index')
                ->has('employmentTypes.data', 1)
                ->where('employmentTypes.data.0.name', 'Full-time')
                ->where('employmentTypes.data.0.slug', 'full-time')
            );
    }

    public function test_admin_can_create_employment_type_with_default_active_state(): void
    {
        $admin = User::factory()->platformAdmin()->create();

        $response = $this->actingAs($admin)
            ->post(route('admin.employment-types.store'), [
                'name' => 'Part-time',
                'slug' => 'part-time',
                'description' => 'Reduced-hours employment arrangements',
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('admin.employment-types.index'));

        $this->assertDatabaseHas('employment_types', [
            'name' => 'Part-time',
            'slug' => 'part-time',
            'is_active' => true,
        ]);
    }

    public function test_admin_can_update_employment_type(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $employmentType = EmploymentType::factory()->create([
            'name' => 'Contract',
            'slug' => 'contract',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)
            ->put(route('admin.employment-types.update', $employmentType), [
                'name' => 'Fixed-term Contract',
                'slug' => 'fixed-term-contract',
                'description' => 'Time-boxed contractual engagement',
                'is_active' => false,
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('admin.employment-types.index'));

        $this->assertDatabaseHas('employment_types', [
            'id' => $employmentType->id,
            'name' => 'Fixed-term Contract',
            'slug' => 'fixed-term-contract',
            'is_active' => false,
        ]);
    }

    public function test_admin_can_delete_employment_type(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $employmentType = EmploymentType::factory()->create([
            'name' => 'Internship',
            'slug' => 'internship',
        ]);

        $response = $this->actingAs($admin)
            ->delete(route('admin.employment-types.destroy', $employmentType));

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('admin.employment-types.index'));

        $this->assertDatabaseMissing('employment_types', [
            'id' => $employmentType->id,
        ]);
    }

    public function test_employment_type_requires_unique_name_and_slug(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        EmploymentType::factory()->create([
            'name' => 'Full-time',
            'slug' => 'full-time',
        ]);

        $response = $this->actingAs($admin)
            ->post(route('admin.employment-types.store'), [
                'name' => 'Full-time',
                'slug' => 'full-time',
            ]);

        $response->assertSessionHasErrors(['name', 'slug']);
    }

    public function test_update_ignores_unique_rules_for_the_same_row(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $employmentType = EmploymentType::factory()->create([
            'name' => 'Freelance',
            'slug' => 'freelance',
        ]);

        $response = $this->actingAs($admin)
            ->put(route('admin.employment-types.update', $employmentType), [
                'name' => 'Freelance',
                'slug' => 'freelance',
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('admin.employment-types.index'));

        $this->assertDatabaseHas('employment_types', [
            'id' => $employmentType->id,
            'name' => 'Freelance',
            'slug' => 'freelance',
        ]);
    }

    public function test_update_rejects_duplicate_name_and_slug_from_another_row(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $employmentType = EmploymentType::factory()->create([
            'name' => 'Temporary',
            'slug' => 'temporary',
        ]);
        EmploymentType::factory()->create([
            'name' => 'Contract',
            'slug' => 'contract',
        ]);

        $response = $this->actingAs($admin)
            ->put(route('admin.employment-types.update', $employmentType), [
                'name' => 'Contract',
                'slug' => 'contract',
            ]);

        $response->assertSessionHasErrors(['name', 'slug']);

        $this->assertDatabaseHas('employment_types', [
            'id' => $employmentType->id,
            'name' => 'Temporary',
            'slug' => 'temporary',
        ]);
    }

    public function test_employment_type_requires_valid_input(): void
    {
        $admin = User::factory()->platformAdmin()->create();

        $response = $this->actingAs($admin)
            ->post(route('admin.employment-types.store'), [
                'name' => '',
                'slug' => 'not a slug!',
                'description' => str_repeat('a', 501),
            ]);

        $response->assertSessionHasErrors(['name', 'slug', 'description']);
    }

    public function test_non_admin_cannot_access_employment_types(): void
    {
        $user = User::factory()->jobSeeker()->create();

        $this->actingAs($user)
            ->get(route('admin.employment-types.index'))
            ->assertForbidden();

        $this->actingAs($user)
            ->post(route('admin.employment-types.store'), [
                'name' => 'Contract',
                'slug' => 'contract',
            ])
            ->assertForbidden();
    }

    public function test_search_matches_prefix_by_name(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        EmploymentType::factory()->create([
            'name' => 'Full-time',
            'slug' => 'full-time',
        ]);
        EmploymentType::factory()->create([
            'name' => 'Part-time',
            'slug' => 'part-time',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.employment-types.index', ['search' => 'Full']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('employmentTypes.data', 1)
                ->where('employmentTypes.data.0.name', 'Full-time')
            );
    }

    public function test_search_matches_prefix_by_slug(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        EmploymentType::factory()->create([
            'name' => 'Full-time',
            'slug' => 'full-time',
        ]);
        EmploymentType::factory()->create([
            'name' => 'Part-time',
            'slug' => 'part-time',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.employment-types.index', ['search' => 'part']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('employmentTypes.data', 1)
                ->where('employmentTypes.data.0.slug', 'part-time')
            );
    }

    public function test_search_returns_empty_state_when_no_results(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        EmploymentType::factory()->create([
            'name' => 'Full-time',
            'slug' => 'full-time',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.employment-types.index', ['search' => 'nothing']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('employmentTypes.data', 0)
            );
    }
}
