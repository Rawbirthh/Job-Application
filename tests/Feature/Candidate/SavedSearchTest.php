<?php

namespace Tests\Feature\Candidate;

use App\Models\Company;
use App\Models\Job;
use App\Models\JobCategory;
use App\Models\SavedSearch;
use App\Models\SavedSearchAlert;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Authorization\Concerns\InteractsWithAuthorizationCatalogue;
use Tests\TestCase;

class SavedSearchTest extends TestCase
{
    use InteractsWithAuthorizationCatalogue, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAuthorizationCatalogue();
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('candidate.saved-searches.index'))
            ->assertRedirect(route('login'));
        $this->post(route('candidate.saved-searches.store'))
            ->assertRedirect(route('login'));
        $this->put(route('candidate.saved-searches.update', 1))
            ->assertRedirect(route('login'));
        $this->delete(route('candidate.saved-searches.destroy', 1))
            ->assertRedirect(route('login'));
    }

    public function test_non_job_seekers_cannot_access_saved_searches(): void
    {
        $employer = User::factory()->employer()->create();

        $this->actingAs($employer)
            ->get(route('candidate.saved-searches.index'))
            ->assertForbidden();

        $this->actingAs($employer)
            ->post(route('candidate.saved-searches.store'))
            ->assertForbidden();
    }

    public function test_job_seeker_can_view_saved_searches_index(): void
    {
        $jobSeeker = User::factory()->jobSeeker()->create();

        $this->actingAs($jobSeeker)
            ->get(route('candidate.saved-searches.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('candidate/saved-searches/index')
                ->has('savedSearches')
                ->has('filterOptions'));
    }

    public function test_job_seeker_can_save_a_search(): void
    {
        $jobSeeker = User::factory()->jobSeeker()->create();
        $category = JobCategory::factory()->create();

        $response = $this->actingAs($jobSeeker)
            ->post(route('candidate.saved-searches.store'), [
                'name' => 'My search',
                'search' => 'Laravel',
                'category_id' => $category->id,
                'alerts_enabled' => true,
                'alert_frequency' => 'daily',
            ]);

        $response->assertRedirect(route('candidate.saved-searches.index'));

        $this->assertDatabaseHas('saved_searches', [
            'user_id' => $jobSeeker->id,
            'name' => 'My search',
            'search' => 'Laravel',
            'category_id' => $category->id,
            'alerts_enabled' => true,
            'alert_frequency' => 'daily',
        ]);

        // last_alerted_at is set at creation so no retro-alert storm.
        $this->assertNotNull(
            SavedSearch::query()
                ->where('user_id', $jobSeeker->id)
                ->value('last_alerted_at')
        );

        // A forged user_id in the payload is ignored: the row is owned by the
        // authenticated user, never by the supplied user_id.
        $other = User::factory()->jobSeeker()->create();
        $this->actingAs($jobSeeker)
            ->post(route('candidate.saved-searches.store'), [
                'name' => 'Forged',
                'user_id' => $other->id,
            ])
            ->assertRedirect(route('candidate.saved-searches.index'));

        $this->assertDatabaseCount('saved_searches', 2);
        $this->assertDatabaseHas('saved_searches', [
            'user_id' => $jobSeeker->id,
            'name' => 'Forged',
        ]);
        $this->assertDatabaseMissing('saved_searches', [
            'user_id' => $other->id,
        ]);
    }

    public function test_validation_rejects_invalid_criteria(): void
    {
        $jobSeeker = User::factory()->jobSeeker()->create();

        $this->actingAs($jobSeeker)
            ->post(route('candidate.saved-searches.store'), [
                'category_id' => 99999,
            ])
            ->assertSessionHasErrors('category_id');

        $this->actingAs($jobSeeker)
            ->post(route('candidate.saved-searches.store'), [
                'alert_frequency' => 'hourly',
            ])
            ->assertSessionHasErrors('alert_frequency');

        $this->actingAs($jobSeeker)
            ->post(route('candidate.saved-searches.store'), [
                'search' => str_repeat('a', 181),
            ])
            ->assertSessionHasErrors('search');

        $this->actingAs($jobSeeker)
            ->post(route('candidate.saved-searches.store'), [
                'name' => str_repeat('b', 121),
            ])
            ->assertSessionHasErrors('name');
    }

    public function test_per_user_cap_is_enforced(): void
    {
        $jobSeeker = User::factory()->jobSeeker()->create();

        foreach (range(1, 20) as $i) {
            SavedSearch::query()->create([
                'user_id' => $jobSeeker->id,
                'name' => 'Existing '.$i,
                'alerts_enabled' => true,
                'alert_frequency' => 'daily',
                'last_alerted_at' => now(),
            ]);
        }

        $this->actingAs($jobSeeker)
            ->post(route('candidate.saved-searches.store'), [
                'name' => 'Twenty-first',
            ])
            ->assertSessionHasErrors('searches');

        $this->assertDatabaseCount('saved_searches', 20);
    }

    public function test_owner_can_update_and_preserves_last_alerted_at(): void
    {
        $jobSeeker = User::factory()->jobSeeker()->create();
        $preservedAt = now()->subDays(2);
        $savedSearch = SavedSearch::query()->create([
            'user_id' => $jobSeeker->id,
            'name' => 'Original',
            'search' => 'PHP',
            'alerts_enabled' => true,
            'alert_frequency' => 'daily',
        ]);
        $savedSearch->last_alerted_at = $preservedAt;
        $savedSearch->save();

        $category = JobCategory::factory()->create();

        $before = $savedSearch->fresh()->last_alerted_at;

        $this->actingAs($jobSeeker)
            ->put(route('candidate.saved-searches.update', $savedSearch), [
                'name' => 'Updated',
                'search' => 'Laravel',
                'category_id' => $category->id,
                'alerts_enabled' => false,
                'alert_frequency' => 'weekly',
            ])
            ->assertRedirect(route('candidate.saved-searches.index'));

        $this->assertDatabaseHas('saved_searches', [
            'id' => $savedSearch->id,
            'name' => 'Updated',
            'search' => 'Laravel',
            'category_id' => $category->id,
            'alerts_enabled' => false,
            'alert_frequency' => 'weekly',
        ]);

        // last_alerted_at is preserved on update.
        $this->assertTrue(
            $savedSearch->fresh()->last_alerted_at?->eq($before)
        );
    }

    public function test_non_owner_cannot_update_or_delete(): void
    {
        $owner = User::factory()->jobSeeker()->create();
        $other = User::factory()->jobSeeker()->create();

        $savedSearch = SavedSearch::query()->create([
            'user_id' => $owner->id,
            'name' => 'Owned',
        ]);

        // Non-owner update: policy denies at the FormRequest layer → 403.
        // Row is untouched either way (403 or 404 are both acceptable per spec).
        $this->actingAs($other)
            ->put(route('candidate.saved-searches.update', $savedSearch), [
                'name' => 'Hacked',
            ])
            ->assertStatus(403);

        $this->assertDatabaseHas('saved_searches', [
            'id' => $savedSearch->id,
            'name' => 'Owned',
        ]);

        // Non-owner delete: record not found → no destructive effect.
        $this->actingAs($other)
            ->delete(route('candidate.saved-searches.destroy', $savedSearch))
            ->assertRedirect(route('candidate.saved-searches.index'));

        $this->assertDatabaseHas('saved_searches', ['id' => $savedSearch->id]);
    }

    public function test_owner_can_delete_and_cascades_ledger(): void
    {
        $jobSeeker = User::factory()->jobSeeker()->create();
        $employer = User::factory()->approvedEmployer()->create();
        $company = Company::factory()->for($employer, 'owner')->create();
        $job = Job::factory()->for($company)->create(['status' => 'published']);
        $savedSearch = SavedSearch::query()->create([
            'user_id' => $jobSeeker->id,
            'name' => 'To delete',
        ]);

        SavedSearchAlert::query()->create([
            'saved_search_id' => $savedSearch->id,
            'job_posting_id' => $job->id,
        ]);

        $this->actingAs($jobSeeker)
            ->delete(route('candidate.saved-searches.destroy', $savedSearch))
            ->assertRedirect(route('candidate.saved-searches.index'));

        $this->assertDatabaseMissing('saved_searches', ['id' => $savedSearch->id]);
        $this->assertDatabaseMissing('saved_search_alerts', [
            'saved_search_id' => $savedSearch->id,
        ]);
    }

    public function test_unknown_delete_id_is_idempotent(): void
    {
        $jobSeeker = User::factory()->jobSeeker()->create();

        $this->actingAs($jobSeeker)
            ->delete(route('candidate.saved-searches.destroy', 99999))
            ->assertRedirect(route('candidate.saved-searches.index'));
    }

    public function test_criteria_shape_matches_public_filters(): void
    {
        $category = JobCategory::factory()->create();
        $savedSearch = SavedSearch::query()->create([
            'user_id' => User::factory()->jobSeeker()->create()->id,
            'search' => 'Laravel',
            'category_id' => $category->id,
        ]);

        $criteria = $savedSearch->criteria();

        $this->assertSame(
            ['search', 'category', 'employment_type', 'workplace_arrangement', 'experience_level'],
            array_keys($criteria)
        );
        $this->assertSame('Laravel', $criteria['search']);
        $this->assertSame($category->id, $criteria['category']);
        $this->assertNull($criteria['employment_type']);
        $this->assertNull($criteria['workplace_arrangement']);
        $this->assertNull($criteria['experience_level']);
    }

    public function test_index_shows_only_owned_searches(): void
    {
        $jobSeeker = User::factory()->jobSeeker()->create();
        $other = User::factory()->jobSeeker()->create();

        SavedSearch::query()->create([
            'user_id' => $jobSeeker->id,
            'name' => 'Mine',
            'alerts_enabled' => true,
            'alert_frequency' => 'daily',
            'last_alerted_at' => now(),
        ]);
        SavedSearch::query()->create([
            'user_id' => $other->id,
            'name' => 'Theirs',
            'alerts_enabled' => true,
            'alert_frequency' => 'daily',
            'last_alerted_at' => now(),
        ]);

        $this->actingAs($jobSeeker)
            ->get(route('candidate.saved-searches.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('candidate/saved-searches/index')
                ->where('savedSearches.total', 1)
                ->where('savedSearches.data.0.name', 'Mine'));
    }
}
