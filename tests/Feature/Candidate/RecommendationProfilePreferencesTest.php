<?php

use App\Models\CandidateProfile;
use App\Models\ExperienceLevel;
use App\Models\JobCategory;
use App\Models\User;
use App\Models\WorkplaceArrangement;
use Database\Seeders\AuthorizationSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed(AuthorizationSeeder::class);
    $this->withoutVite();
});

test('a candidate can store their job preferences', function (): void {
    $candidate = User::factory()->jobSeeker()->create();

    $category = JobCategory::factory()->create();
    $level = ExperienceLevel::factory()->create();
    $workplace = WorkplaceArrangement::factory()->create();

    $this->actingAs($candidate)
        ->put(route('candidate.profile.update'), [
            'headline' => 'Backend Developer',
            'preferred_category_id' => $category->id,
            'preferred_experience_level_id' => $level->id,
            'preferred_workplace_arrangement_id' => $workplace->id,
        ])
        ->assertRedirect(route('candidate.profile.edit'));

    $this->assertDatabaseHas('candidate_profiles', [
        'user_id' => $candidate->id,
        'preferred_category_id' => $category->id,
        'preferred_experience_level_id' => $level->id,
        'preferred_workplace_arrangement_id' => $workplace->id,
    ]);
});

test('preferences can be cleared', function (): void {
    $candidate = User::factory()->jobSeeker()->create();
    $category = JobCategory::factory()->create();

    CandidateProfile::factory()->create([
        'user_id' => $candidate->id,
        'preferred_category_id' => $category->id,
    ]);

    $this->actingAs($candidate)
        ->put(route('candidate.profile.update'), [
            'headline' => 'Backend Developer',
            'preferred_category_id' => null,
        ])
        ->assertRedirect(route('candidate.profile.edit'));

    $this->assertDatabaseHas('candidate_profiles', [
        'user_id' => $candidate->id,
        'preferred_category_id' => null,
    ]);
});

test('an unknown preference id is rejected', function (): void {
    $candidate = User::factory()->jobSeeker()->create();

    $this->actingAs($candidate)
        ->put(route('candidate.profile.update'), [
            'headline' => 'Backend Developer',
            'preferred_category_id' => 999999,
        ])
        ->assertSessionHasErrors('preferred_category_id');

    $this->assertDatabaseMissing('candidate_profiles', [
        'user_id' => $candidate->id,
        'preferred_category_id' => 999999,
    ]);
});

test('a deactivated option the candidate does not hold is rejected', function (): void {
    $candidate = User::factory()->jobSeeker()->create();
    $category = JobCategory::factory()->create(['is_active' => false]);

    $this->actingAs($candidate)
        ->put(route('candidate.profile.update'), [
            'headline' => 'Backend Developer',
            'preferred_category_id' => $category->id,
        ])
        ->assertSessionHasErrors('preferred_category_id');
});

test('a deactivated option the candidate already holds stays submittable', function (): void {
    $candidate = User::factory()->jobSeeker()->create();
    $category = JobCategory::factory()->create(['is_active' => false]);

    CandidateProfile::factory()->create([
        'user_id' => $candidate->id,
        'preferred_category_id' => $category->id,
    ]);

    $this->actingAs($candidate)
        ->put(route('candidate.profile.update'), [
            'headline' => 'Backend Developer',
            'preferred_category_id' => $category->id,
        ])
        ->assertRedirect(route('candidate.profile.edit'))
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('candidate_profiles', [
        'user_id' => $candidate->id,
        'preferred_category_id' => $category->id,
    ]);
});

test('the profile editor exposes the preferences and the selectable options', function (): void {
    $candidate = User::factory()->jobSeeker()->create();
    $category = JobCategory::factory()->create();
    $level = ExperienceLevel::factory()->create();
    $workplace = WorkplaceArrangement::factory()->create();

    CandidateProfile::factory()->create([
        'user_id' => $candidate->id,
        'preferred_category_id' => $category->id,
    ]);

    $this->actingAs($candidate)
        ->get(route('candidate.profile.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('candidate/profile/edit')
            ->where('profile.preferred_category_id', $category->id)
            ->has('options.categories')
            ->has('options.experienceLevels')
            ->has('options.workplaceArrangements')
        );
});

test('updating a profile never touches another candidate profile', function (): void {
    $owner = User::factory()->jobSeeker()->create();
    $other = User::factory()->jobSeeker()->create();
    $category = JobCategory::factory()->create();

    CandidateProfile::factory()->create([
        'user_id' => $owner->id,
        'headline' => 'Owner Headline',
        'preferred_category_id' => $category->id,
    ]);

    $this->actingAs($other)
        ->put(route('candidate.profile.update'), [
            'headline' => 'My own headline',
            'preferred_category_id' => $category->id,
        ])
        ->assertRedirect(route('candidate.profile.edit'));

    // The other candidate updated their own profile, never the owner's.
    $this->assertDatabaseHas('candidate_profiles', [
        'user_id' => $owner->id,
        'headline' => 'Owner Headline',
        'preferred_category_id' => $category->id,
    ]);
    $this->assertDatabaseHas('candidate_profiles', [
        'user_id' => $other->id,
        'headline' => 'My own headline',
    ]);
});

test('the preferences are not exposed on the public job pages', function (): void {
    $candidate = User::factory()->jobSeeker()->create();
    $category = JobCategory::factory()->create();

    CandidateProfile::factory()->create([
        'user_id' => $candidate->id,
        'preferred_category_id' => $category->id,
    ]);

    $response = $this->actingAs($candidate)->get('/jobs');

    $response->assertOk();

    expect(json_encode($response->viewData('page')['props']))
        ->not->toContain('preferred_category_id')
        ->not->toContain('preferred_experience_level_id')
        ->not->toContain('preferred_workplace_arrangement_id');
});
