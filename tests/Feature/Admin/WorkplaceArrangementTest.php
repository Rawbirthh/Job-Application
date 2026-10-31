<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Models\WorkplaceArrangement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Authorization\Concerns\InteractsWithAuthorizationCatalogue;
use Tests\TestCase;

class WorkplaceArrangementTest extends TestCase
{
    use InteractsWithAuthorizationCatalogue, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAuthorizationCatalogue();
    }

    public function test_admin_can_view_workplace_arrangements_index(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        WorkplaceArrangement::factory()->create([
            'name' => 'Remote',
            'slug' => 'remote',
            'sort_order' => 3,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.workplace-arrangements.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/workplace-arrangements/index')
                ->has('workplaceArrangements.data', 1)
                ->where('workplaceArrangements.data.0.name', 'Remote')
                ->where('workplaceArrangements.data.0.slug', 'remote')
                ->where('workplaceArrangements.data.0.sort_order', 3)
            );
    }

    public function test_admin_can_create_workplace_arrangement_with_default_active_state(): void
    {
        $admin = User::factory()->platformAdmin()->create();

        $response = $this->actingAs($admin)
            ->post(route('admin.workplace-arrangements.store'), [
                'name' => 'Hybrid',
                'slug' => 'hybrid',
                'description' => 'Mix of remote and onsite work',
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('admin.workplace-arrangements.index'));

        $this->assertDatabaseHas('workplace_arrangements', [
            'name' => 'Hybrid',
            'slug' => 'hybrid',
            'is_active' => true,
            'sort_order' => 0,
        ]);
    }

    public function test_admin_can_create_workplace_arrangement_with_explicit_sort_order(): void
    {
        $admin = User::factory()->platformAdmin()->create();

        $response = $this->actingAs($admin)
            ->post(route('admin.workplace-arrangements.store'), [
                'name' => 'Onsite',
                'slug' => 'onsite',
                'sort_order' => 7,
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('admin.workplace-arrangements.index'));

        $this->assertDatabaseHas('workplace_arrangements', [
            'name' => 'Onsite',
            'slug' => 'onsite',
            'sort_order' => 7,
            'is_active' => true,
        ]);
    }

    public function test_admin_can_update_workplace_arrangement(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $workplaceArrangement = WorkplaceArrangement::factory()->create([
            'name' => 'Remote',
            'slug' => 'remote',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $response = $this->actingAs($admin)
            ->put(route('admin.workplace-arrangements.update', $workplaceArrangement), [
                'name' => 'Fully Remote',
                'slug' => 'fully-remote',
                'description' => 'Work from anywhere',
                'sort_order' => 4,
                'is_active' => false,
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('admin.workplace-arrangements.index'));

        $this->assertDatabaseHas('workplace_arrangements', [
            'id' => $workplaceArrangement->id,
            'name' => 'Fully Remote',
            'slug' => 'fully-remote',
            'sort_order' => 4,
            'is_active' => false,
        ]);
    }

    public function test_admin_can_delete_workplace_arrangement(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $workplaceArrangement = WorkplaceArrangement::factory()->create([
            'name' => 'Temporary',
            'slug' => 'temporary',
        ]);

        $response = $this->actingAs($admin)
            ->delete(route('admin.workplace-arrangements.destroy', $workplaceArrangement));

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('admin.workplace-arrangements.index'));

        $this->assertDatabaseMissing('workplace_arrangements', [
            'id' => $workplaceArrangement->id,
        ]);
    }

    public function test_workplace_arrangement_requires_unique_name_and_slug(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        WorkplaceArrangement::factory()->create([
            'name' => 'Remote',
            'slug' => 'remote',
        ]);

        $response = $this->actingAs($admin)
            ->post(route('admin.workplace-arrangements.store'), [
                'name' => 'Remote',
                'slug' => 'remote',
            ]);

        $response->assertSessionHasErrors(['name', 'slug']);
    }

    public function test_update_ignores_unique_rules_for_the_same_row(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $workplaceArrangement = WorkplaceArrangement::factory()->create([
            'name' => 'Freelance',
            'slug' => 'freelance',
        ]);

        $response = $this->actingAs($admin)
            ->put(route('admin.workplace-arrangements.update', $workplaceArrangement), [
                'name' => 'Freelance',
                'slug' => 'freelance',
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('admin.workplace-arrangements.index'));

        $this->assertDatabaseHas('workplace_arrangements', [
            'id' => $workplaceArrangement->id,
            'name' => 'Freelance',
            'slug' => 'freelance',
        ]);
    }

    public function test_update_rejects_duplicate_name_and_slug_from_another_row(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $workplaceArrangement = WorkplaceArrangement::factory()->create([
            'name' => 'Temporary',
            'slug' => 'temporary',
        ]);
        WorkplaceArrangement::factory()->create([
            'name' => 'Contract',
            'slug' => 'contract',
        ]);

        $response = $this->actingAs($admin)
            ->put(route('admin.workplace-arrangements.update', $workplaceArrangement), [
                'name' => 'Contract',
                'slug' => 'contract',
            ]);

        $response->assertSessionHasErrors(['name', 'slug']);

        $this->assertDatabaseHas('workplace_arrangements', [
            'id' => $workplaceArrangement->id,
            'name' => 'Temporary',
            'slug' => 'temporary',
        ]);
    }

    public function test_workplace_arrangement_requires_valid_input(): void
    {
        $admin = User::factory()->platformAdmin()->create();

        $response = $this->actingAs($admin)
            ->post(route('admin.workplace-arrangements.store'), [
                'name' => '',
                'slug' => 'not a slug!',
                'description' => str_repeat('a', 501),
                'sort_order' => 10000,
            ]);

        $response->assertSessionHasErrors(['name', 'slug', 'description', 'sort_order']);
    }

    public function test_non_admin_cannot_access_workplace_arrangements(): void
    {
        $user = User::factory()->jobSeeker()->create();

        $this->actingAs($user)
            ->get(route('admin.workplace-arrangements.index'))
            ->assertForbidden();

        $this->actingAs($user)
            ->post(route('admin.workplace-arrangements.store'), [
                'name' => 'Contract',
                'slug' => 'contract',
            ])
            ->assertForbidden();
    }

    public function test_search_matches_prefix_by_name(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        WorkplaceArrangement::factory()->create([
            'name' => 'Remote',
            'slug' => 'remote',
        ]);
        WorkplaceArrangement::factory()->create([
            'name' => 'Onsite',
            'slug' => 'onsite',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.workplace-arrangements.index', ['search' => 'Rem']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('workplaceArrangements.data', 1)
                ->where('workplaceArrangements.data.0.name', 'Remote')
            );
    }

    public function test_search_matches_prefix_by_slug(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        WorkplaceArrangement::factory()->create([
            'name' => 'Remote',
            'slug' => 'remote',
        ]);
        WorkplaceArrangement::factory()->create([
            'name' => 'Onsite',
            'slug' => 'onsite',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.workplace-arrangements.index', ['search' => 'ons']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('workplaceArrangements.data', 1)
                ->where('workplaceArrangements.data.0.slug', 'onsite')
            );
    }

    public function test_search_returns_empty_state_when_no_results(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        WorkplaceArrangement::factory()->create([
            'name' => 'Remote',
            'slug' => 'remote',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.workplace-arrangements.index', ['search' => 'nothing']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('workplaceArrangements.data', 0)
            );
    }

    public function test_rows_are_ordered_by_sort_order_then_name_then_id(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        WorkplaceArrangement::factory()->create([
            'name' => 'Zeta',
            'slug' => 'zeta',
            'sort_order' => 2,
        ]);
        WorkplaceArrangement::factory()->create([
            'name' => 'Beta',
            'slug' => 'beta',
            'sort_order' => 1,
        ]);
        WorkplaceArrangement::factory()->create([
            'name' => 'Alpha',
            'slug' => 'alpha',
            'sort_order' => 1,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.workplace-arrangements.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('workplaceArrangements.data', 3)
                ->where('workplaceArrangements.data.0.name', 'Alpha')
                ->where('workplaceArrangements.data.1.name', 'Beta')
                ->where('workplaceArrangements.data.2.name', 'Zeta')
            );
    }
}
