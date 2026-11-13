<?php

use App\Models\CandidateExperience;
use App\Models\CandidateProfile;
use App\Models\Company;
use App\Models\ExperienceLevel;
use App\Models\Job;
use App\Models\JobApplication;
use App\Models\JobCategory;
use App\Models\SavedJob;
use App\Models\User;
use App\Models\WorkplaceArrangement;
use App\Services\Recommendation\ExperienceLevelRanking;
use Database\Seeders\AuthorizationSeeder;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * A company owned by an approved employer that can publish jobs.
 */
function activeCompany(): Company
{
    $employer = User::factory()->approvedEmployer()->create();

    return Company::factory()->create([
        'owner_user_id' => $employer->id,
        'is_active' => true,
    ]);
}

function publishedJob(Company $company, array $attributes = []): Job
{
    return Job::factory()->for($company)->create([
        'status' => 'published',
        'application_deadline' => null,
        ...$attributes,
    ]);
}

/**
 * A candidate with the canonical profile from the specification.
 */
function candidateWithProfile(array $attributes = []): User
{
    $candidate = User::factory()->jobSeeker()->create();

    CandidateProfile::factory()->create([
        'user_id' => $candidate->id,
        'headline' => 'Backend Developer',
        'location' => 'Pune, India',
        'skills' => ['PHP', 'Laravel', 'MySQL', 'Redis', 'Docker'],
        ...$attributes,
    ]);

    return $candidate;
}

beforeEach(function (): void {
    $this->seed(AuthorizationSeeder::class);
    $this->withoutVite();
});

// ---------------------------------------------------------------- matching

test('the canonical fixture scores one hundred with four reasons', function (): void {
    $company = activeCompany();

    $category = JobCategory::factory()->create(['name' => 'Software Engineering']);
    $level = ExperienceLevel::factory()->create(['name' => 'Senior', 'sort_order' => 4, 'min_years' => 5]);
    $workplace = WorkplaceArrangement::factory()->create(['name' => 'Hybrid']);

    $job = publishedJob($company, [
        'title' => 'Senior Backend Engineer',
        'category_id' => $category->id,
        'experience_level_id' => $level->id,
        'workplace_arrangement_id' => $workplace->id,
        'location' => 'Pune, India',
        'skills' => ['PHP', 'Laravel', 'MySQL', 'Redis'],
    ]);

    $candidate = candidateWithProfile([
        'preferred_category_id' => $category->id,
        'preferred_experience_level_id' => $level->id,
        'preferred_workplace_arrangement_id' => $workplace->id,
    ]);

    $this->actingAs($candidate)
        ->get(route('candidate.recommendations.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('candidate/recommendations')
            ->where('mode', 'matched')
            ->has('recommendations.data', 1)
            ->where('recommendations.data.0.id', $job->id)
            ->where('recommendations.data.0.match_score', 100)
            ->has('recommendations.data.0.reasons', 5)
            ->where('recommendations.data.0.reasons.0.code', 'skill_overlap')
            ->where('recommendations.data.0.reasons.0.detail', "Matches 4 of the job's 4 listed skills")
            // The matched skills are listed in canonical order, using the
            // candidate's own spelling.
            ->where('recommendations.data.0.reasons.0.values', ['Laravel', 'MySQL', 'PHP', 'Redis'])
            ->where('recommendations.data.0.reasons.1.code', 'category_match')
            ->where('recommendations.data.0.reasons.2.code', 'experience_level_match')
            ->where('recommendations.data.0.reasons.3.code', 'location_match')
            ->where('recommendations.data.0.reasons.4.code', 'workplace_match')
        );
});

test('partial skill overlap scales the skill signal proportionally', function (): void {
    $company = activeCompany();
    $category = JobCategory::factory()->create();

    publishedJob($company, [
        'category_id' => $category->id,
        'skills' => ['PHP', 'Laravel', 'MySQL', 'Redis'],
    ]);

    $candidate = candidateWithProfile([
        'skills' => ['PHP', 'Laravel'],
        'location' => null,
        'preferred_category_id' => $category->id,
    ]);

    $this->actingAs($candidate)
        ->get(route('candidate.recommendations.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('mode', 'matched')
            // 20 skill points + 20 category points out of 60 applicable points.
            ->where('recommendations.data.0.match_score', 67)
        );
});

test('a job with no overlapping skill earns no skill points and no skill reason', function (): void {
    $company = activeCompany();
    $category = JobCategory::factory()->create();

    publishedJob($company, [
        'category_id' => $category->id,
        'skills' => ['Rust', 'Elixir'],
    ]);

    $candidate = candidateWithProfile([
        'skills' => ['PHP'],
        'location' => null,
        'preferred_category_id' => $category->id,
    ]);

    $this->actingAs($candidate)
        ->get(route('candidate.recommendations.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('recommendations.data.0.reasons', 1)
            ->where('recommendations.data.0.reasons.0.code', 'category_match')
        );
});

test('a job that lists no skills has no skill signal', function (): void {
    $company = activeCompany();
    $category = JobCategory::factory()->create();

    publishedJob($company, [
        'category_id' => $category->id,
        'skills' => null,
    ]);

    $candidate = candidateWithProfile([
        'skills' => ['PHP'],
        'preferred_category_id' => $category->id,
    ]);

    $this->actingAs($candidate)
        ->get(route('candidate.recommendations.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('mode', 'matched')
            ->has('recommendations.data.0.reasons', 1)
            ->where('recommendations.data.0.reasons.0.code', 'category_match')
        );
});

test('a candidate without skills has no skill signal', function (): void {
    $company = activeCompany();
    $category = JobCategory::factory()->create();

    publishedJob($company, [
        'category_id' => $category->id,
        'skills' => ['PHP', 'Laravel'],
    ]);

    $candidate = candidateWithProfile([
        'skills' => [],
        'location' => null,
        'preferred_category_id' => $category->id,
    ]);

    $this->actingAs($candidate)
        ->get(route('candidate.recommendations.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('recommendations.data.0.reasons', 1)
            ->where('recommendations.data.0.reasons.0.code', 'category_match')
        );
});

test('an adjacent experience band earns half of the level signal', function (): void {
    $company = activeCompany();

    $target = ExperienceLevel::factory()->create(['name' => 'Senior', 'sort_order' => 4, 'min_years' => 5]);
    $adjacent = ExperienceLevel::factory()->create(['name' => 'Mid-level', 'sort_order' => 3, 'min_years' => 2]);

    publishedJob($company, ['experience_level_id' => $adjacent->id]);

    $candidate = candidateWithProfile([
        'location' => null,
        'preferred_experience_level_id' => $target->id,
    ]);

    $this->actingAs($candidate)
        ->get(route('candidate.recommendations.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('recommendations.data.0.match_score', 50)
            ->where('recommendations.data.0.reasons.0.code', 'experience_level_match')
        );
});

test('levels that share a sort order are still distinct bands', function (): void {
    // The admin form accepts any sort order and the factory defaults to 0, so
    // several levels can legitimately share one. They must not be reported as
    // the same band.
    $company = activeCompany();

    $oneYear = ExperienceLevel::factory()->create(['name' => '1 year', 'sort_order' => 0, 'min_years' => 0]);
    $twoYear = ExperienceLevel::factory()->create(['name' => '2 year', 'sort_order' => 0, 'min_years' => 1]);
    $threeYear = ExperienceLevel::factory()->create(['name' => '3 year', 'sort_order' => 0, 'min_years' => 2]);

    $job = publishedJob($company, ['experience_level_id' => $oneYear->id]);

    $candidate = candidateWithProfile([
        'location' => null,
        'preferred_experience_level_id' => $threeYear->id,
    ]);

    $this->actingAs($candidate)
        ->get(route('candidate.recommendations.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('mode', 'fallback')
            ->where('recommendations.data.0.match_score', null)
        );

    // The adjacent level does match, and the reason names the job's own level.
    publishedJob($company, ['experience_level_id' => $twoYear->id]);

    $this->actingAs($candidate)
        ->get(route('candidate.recommendations.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('mode', 'matched')
            ->has('recommendations.data', 1)
            ->where('recommendations.data.0.reasons.0.code', 'experience_level_match')
            ->where('recommendations.data.0.reasons.0.detail', '2 year — one level from your target (3 year)')
        );
});

test('the level ranking is deterministic for levels sharing a sort order', function (): void {
    ExperienceLevel::factory()->create(['name' => '1 year', 'sort_order' => 0]);
    ExperienceLevel::factory()->create(['name' => '2 year', 'sort_order' => 0]);
    ExperienceLevel::factory()->create(['name' => '3 year', 'sort_order' => 0]);

    $ranking = ExperienceLevelRanking::fromDatabase();

    expect($ranking->bandIds($ranking->rank(2)))->toBe([1, 2, 3])
        ->and($ranking->rank(999))->toBeNull()
        ->and($ranking->bandIds(null))->toBe([]);
});

test('a job more than one experience band away is not recommended', function (): void {
    $company = activeCompany();

    // Three levels so that "Entry-level" is genuinely two bands below the
    // candidate's target rather than merely the other level in the catalogue.
    $entry = ExperienceLevel::factory()->create(['name' => 'Entry-level', 'sort_order' => 1, 'min_years' => 0]);
    ExperienceLevel::factory()->create(['name' => 'Mid-level', 'sort_order' => 2, 'min_years' => 2]);
    $target = ExperienceLevel::factory()->create(['name' => 'Senior', 'sort_order' => 3, 'min_years' => 5]);

    $farJob = publishedJob($company, ['experience_level_id' => $entry->id]);

    $candidate = candidateWithProfile([
        'location' => null,
        'preferred_experience_level_id' => $target->id,
    ]);

    $this->actingAs($candidate)
        ->get(route('candidate.recommendations.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            // The job falls outside the target band, so it is never scored and
            // the candidate falls back to the recent-jobs list.
            ->where('mode', 'fallback')
            ->where('recommendations.data.0.match_score', null)
            ->has('recommendations.data.0.reasons', 0)
        );

    expect($farJob->id)->toBeInt();
});

test('the experience level is derived from the candidate years when no target is stated', function (): void {
    $company = activeCompany();

    $level = ExperienceLevel::factory()->create(['name' => 'Senior', 'sort_order' => 4, 'min_years' => 5]);

    publishedJob($company, ['experience_level_id' => $level->id]);

    $candidate = candidateWithProfile([
        'location' => null,
        'preferred_experience_level_id' => null,
    ]);

    $profile = CandidateProfile::query()->where('user_id', $candidate->id)->firstOrFail();

    CandidateExperience::factory()->create([
        'candidate_profile_id' => $profile->id,
        'company_name' => 'ZetaSoft',
        'job_title' => 'Backend Engineer',
        'start_date' => '2021-06-01',
        'is_current' => true,
    ]);

    $this->actingAs($candidate)
        ->get(route('candidate.recommendations.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('recommendations.data.0.match_score', 100)
            ->where('recommendations.data.0.reasons.0.code', 'experience_level_match')
            ->where('profileSummary.computed_years', fn ($years): bool => $years >= 5.0)
            ->where('profileSummary.has_experience_level', true)
        );
});

test('a candidate with no target level and no work history has no level signal', function (): void {
    $company = activeCompany();

    $level = ExperienceLevel::factory()->create(['name' => 'Senior', 'sort_order' => 4, 'min_years' => 5]);

    publishedJob($company, ['experience_level_id' => $level->id]);

    $candidate = candidateWithProfile([
        'location' => null,
        'preferred_experience_level_id' => null,
    ]);

    $this->actingAs($candidate)
        ->get(route('candidate.recommendations.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('mode', 'fallback')
            ->where('profileSummary.has_experience_level', false)
        );
});

test('a remote job is location agnostic', function (): void {
    $company = activeCompany();

    publishedJob($company, [
        'location' => 'Remote',
        'skills' => ['PHP', 'Laravel'],
    ]);

    $candidate = candidateWithProfile(['location' => 'Pune, India']);

    $this->actingAs($candidate)
        ->get(route('candidate.recommendations.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            // The location signal does not apply to a job without a city, so
            // only the skill signal contributes.
            ->has('recommendations.data.0.reasons', 1)
            ->where('recommendations.data.0.reasons.0.code', 'skill_overlap')
            ->where('recommendations.data.0.match_score', 100)
        );
});

test('a candidate without any signal receives the fallback state', function (): void {
    $company = activeCompany();
    publishedJob($company);

    $candidate = candidateWithProfile([
        'skills' => [],
        'location' => null,
        'preferred_category_id' => null,
        'preferred_experience_level_id' => null,
        'preferred_workplace_arrangement_id' => null,
    ]);

    $this->actingAs($candidate)
        ->get(route('candidate.recommendations.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('mode', 'fallback')
            ->where('profileSummary.signals_available', 0)
            ->where('recommendations.data.0.match_score', null)
            ->has('recommendations.data.0.reasons', 0)
        );
});

test('a candidate whose signals match nothing receives the fallback state', function (): void {
    $company = activeCompany();
    $otherCategory = JobCategory::factory()->create();

    publishedJob($company, ['category_id' => $otherCategory->id, 'skills' => ['Rust']]);

    $candidate = candidateWithProfile([
        'skills' => ['PHP'],
        'preferred_category_id' => JobCategory::factory()->create()->id,
    ]);

    $this->actingAs($candidate)
        ->get(route('candidate.recommendations.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('mode', 'fallback')
        );
});

// -------------------------------------------------------------- eligibility

test('only eligible jobs are recommended', function (): void {
    $company = activeCompany();
    $category = JobCategory::factory()->create();

    $draft = publishedJob($company, ['category_id' => $category->id, 'status' => 'draft']);
    $pending = publishedJob($company, ['category_id' => $category->id, 'status' => 'pending_review']);
    $rejected = publishedJob($company, ['category_id' => $category->id, 'status' => 'rejected']);
    $closed = publishedJob($company, ['category_id' => $category->id, 'status' => 'closed']);

    $eligible = publishedJob($company, ['category_id' => $category->id]);

    $candidate = candidateWithProfile([
        'skills' => [],
        'location' => null,
        'preferred_category_id' => $category->id,
    ]);

    $this->actingAs($candidate)
        ->get(route('candidate.recommendations.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('recommendations.data', 1)
            ->where('recommendations.data.0.id', $eligible->id)
        );

    expect([$draft->id, $pending->id, $rejected->id, $closed->id])->not->toContain($eligible->id);
});

test('jobs of an inactive company are never recommended', function (): void {
    $employer = User::factory()->approvedEmployer()->create();
    $company = Company::factory()->create([
        'owner_user_id' => $employer->id,
        'is_active' => false,
    ]);

    $category = JobCategory::factory()->create();
    publishedJob($company, ['category_id' => $category->id]);

    $candidate = candidateWithProfile([
        'skills' => [],
        'location' => null,
        'preferred_category_id' => $category->id,
    ]);

    $this->actingAs($candidate)
        ->get(route('candidate.recommendations.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('mode', 'fallback')
            ->has('recommendations.data', 0)
        );
});

test('jobs of an unapproved employer are never recommended', function (): void {
    $employer = User::factory()->employer()->create();
    $company = Company::factory()->create([
        'owner_user_id' => $employer->id,
        'is_active' => true,
    ]);

    $category = JobCategory::factory()->create();
    publishedJob($company, ['category_id' => $category->id]);

    $candidate = candidateWithProfile([
        'skills' => [],
        'location' => null,
        'preferred_category_id' => $category->id,
    ]);

    $this->actingAs($candidate)
        ->get(route('candidate.recommendations.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('mode', 'fallback')
            ->has('recommendations.data', 0)
        );
});

test('jobs past their application deadline are never recommended', function (): void {
    $company = activeCompany();
    $category = JobCategory::factory()->create();

    publishedJob($company, [
        'category_id' => $category->id,
        'application_deadline' => now()->subDay(),
    ]);

    $candidate = candidateWithProfile([
        'skills' => [],
        'location' => null,
        'preferred_category_id' => $category->id,
    ]);

    $this->actingAs($candidate)
        ->get(route('candidate.recommendations.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('mode', 'fallback')
            ->has('recommendations.data', 0)
        );
});

test('jobs with a future deadline remain recommended', function (): void {
    $company = activeCompany();
    $category = JobCategory::factory()->create();

    publishedJob($company, [
        'category_id' => $category->id,
        'application_deadline' => now()->addWeek(),
    ]);

    $candidate = candidateWithProfile([
        'skills' => [],
        'location' => null,
        'preferred_category_id' => $category->id,
    ]);

    $this->actingAs($candidate)
        ->get(route('candidate.recommendations.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('mode', 'matched')
            ->has('recommendations.data', 1)
        );
});

// ------------------------------------------------------------ authorization

test('guests are redirected to login', function (): void {
    $this->get(route('candidate.recommendations.index'))->assertRedirect(route('login'));
});

test('employers cannot access the recommendations page', function (): void {
    $employer = User::factory()->employer()->create();

    $this->actingAs($employer)
        ->get(route('candidate.recommendations.index'))
        ->assertForbidden();
});

test('platform administrators cannot access the recommendations page', function (): void {
    $admin = User::factory()->platformAdmin()->create();

    $this->actingAs($admin)
        ->get(route('candidate.recommendations.index'))
        ->assertForbidden();
});

test('job seekers can access the recommendations page', function (): void {
    $candidate = User::factory()->jobSeeker()->create();

    $this->actingAs($candidate)
        ->get(route('candidate.recommendations.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('candidate/recommendations')
            ->has('recommendations')
            ->has('mode')
            ->has('profileSummary')
        );
});

// ------------------------------------------------------------------ privacy

test('the recommendation payload exposes no candidate or private job data', function (): void {
    $company = activeCompany();
    $category = JobCategory::factory()->create();

    $job = publishedJob($company, [
        'category_id' => $category->id,
        'skills' => ['PHP'],
        'rejection_reason' => 'Internal moderation note',
    ]);

    $candidate = candidateWithProfile([
        'skills' => ['PHP'],
        'preferred_category_id' => $category->id,
    ]);

    $response = $this->actingAs($candidate)
        ->get(route('candidate.recommendations.index'))
        ->assertOk();

    $payload = $response->viewData('page')['props'];

    $encoded = json_encode($payload);

    expect($encoded)->not->toContain('Internal moderation note')
        ->and($encoded)->not->toContain('applications_count')
        ->and($encoded)->not->toContain('candidate_id')
        ->and($encoded)->not->toContain('user_id')
        ->and($encoded)->not->toContain('resume')
        ->and($payload['recommendations']['data'][0]['id'])->toBe($job->id);
});

test('another candidate profile does not influence this candidate page', function (): void {
    $company = activeCompany();
    $category = JobCategory::factory()->create();

    publishedJob($company, ['category_id' => $category->id, 'skills' => ['Rust']]);

    $other = candidateWithProfile([
        'skills' => ['Rust'],
        'preferred_category_id' => $category->id,
    ]);

    $candidate = candidateWithProfile([
        'skills' => ['PHP'],
        'preferred_category_id' => $category->id,
    ]);

    $this->actingAs($candidate)
        ->get(route('candidate.recommendations.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('profileSummary.has_skills', true)
            ->has('recommendations.data.0.reasons', 1)
            ->where('recommendations.data.0.reasons.0.code', 'category_match')
        );

    $this->actingAs($other)
        ->get(route('candidate.recommendations.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('recommendations.data.0.reasons.0.code', 'skill_overlap')
        );
});

// ------------------------------------------------------------- determinism

test('repeated requests return an identical ordering', function (): void {
    $company = activeCompany();
    $category = JobCategory::factory()->create();

    publishedJob($company, ['category_id' => $category->id, 'skills' => ['PHP', 'Laravel']]);
    publishedJob($company, ['category_id' => $category->id, 'skills' => ['PHP']]);

    $candidate = candidateWithProfile([
        'skills' => ['PHP', 'Laravel'],
        'preferred_category_id' => $category->id,
    ]);

    $first = $this->actingAs($candidate)->get(route('candidate.recommendations.index'));
    $second = $this->actingAs($candidate)->get(route('candidate.recommendations.index'));

    $firstIds = collect($first->viewData('page')['props']['recommendations']['data'])->pluck('id')->all();
    $secondIds = collect($second->viewData('page')['props']['recommendations']['data'])->pluck('id')->all();

    expect($firstIds)->toBe($secondIds)->toHaveCount(2);
});

test('the candidate pool is bounded', function (): void {
    $company = activeCompany();
    $category = JobCategory::factory()->create();

    for ($i = 0; $i < 205; $i++) {
        publishedJob($company, ['category_id' => $category->id]);
    }

    $candidate = candidateWithProfile([
        'skills' => [],
        'location' => null,
        'preferred_category_id' => $category->id,
    ]);

    $this->actingAs($candidate)
        ->get(route('candidate.recommendations.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('recommendations.data', 12)
            ->where('recommendations.total', 200)
            ->where('recommendations.last_page', 17)
        );
});

test('pagination returns the second slice of the same ranked pool', function (): void {
    $company = activeCompany();
    $category = JobCategory::factory()->create();

    for ($i = 0; $i < 14; $i++) {
        publishedJob($company, ['category_id' => $category->id]);
    }

    $candidate = candidateWithProfile([
        'skills' => [],
        'location' => null,
        'preferred_category_id' => $category->id,
    ]);

    $firstPage = $this->actingAs($candidate)->get(route('candidate.recommendations.index'));
    $secondPage = $this->actingAs($candidate)->get(route('candidate.recommendations.index', ['page' => 2]));

    $firstIds = collect($firstPage->viewData('page')['props']['recommendations']['data'])->pluck('id')->all();
    $secondIds = collect($secondPage->viewData('page')['props']['recommendations']['data'])->pluck('id')->all();

    expect($firstIds)->toHaveCount(12)
        ->and($secondIds)->toHaveCount(2)
        ->and(array_intersect($firstIds, $secondIds))->toBe([]);
});

// -------------------------------------------------------- applied and saved

test('an applied job stays visible and is flagged', function (): void {
    $company = activeCompany();
    $category = JobCategory::factory()->create();

    $job = publishedJob($company, ['category_id' => $category->id, 'skills' => ['PHP']]);

    $candidate = candidateWithProfile([
        'skills' => ['PHP'],
        'location' => null,
        'preferred_category_id' => $category->id,
    ]);

    JobApplication::factory()->create([
        'user_id' => $candidate->id,
        'job_posting_id' => $job->id,
    ]);

    $this->actingAs($candidate)
        ->get(route('candidate.recommendations.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('recommendations.data', 1)
            ->where('recommendations.data.0.id', $job->id)
            ->where('recommendations.data.0.has_applied', true)
            ->where('recommendations.data.0.match_score', 100)
        );
});

test('a saved job is flagged', function (): void {
    $company = activeCompany();
    $category = JobCategory::factory()->create();

    $job = publishedJob($company, ['category_id' => $category->id]);

    $candidate = candidateWithProfile([
        'skills' => [],
        'location' => null,
        'preferred_category_id' => $category->id,
    ]);

    SavedJob::query()->create([
        'user_id' => $candidate->id,
        'job_posting_id' => $job->id,
    ]);

    $this->actingAs($candidate)
        ->get(route('candidate.recommendations.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('recommendations.data.0.is_saved', true)
            ->where('recommendations.data.0.has_applied', false)
        );
});
