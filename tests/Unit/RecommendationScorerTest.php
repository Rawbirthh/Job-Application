<?php

use App\Services\Recommendation\RecommendationCandidate;
use App\Services\Recommendation\RecommendationJob;
use App\Services\Recommendation\RecommendationOutcome;
use App\Services\Recommendation\RecommendationScorer;

/**
 * @param  array<string, mixed>  $overrides
 */
function candidate(array $overrides = []): RecommendationCandidate
{
    return new RecommendationCandidate(
        $overrides['skills'] ?? [],
        $overrides['skillLabels'] ?? [],
        $overrides['preferredCategoryId'] ?? null,
        $overrides['targetExperienceLevelId'] ?? null,
        $overrides['targetExperienceRank'] ?? null,
        $overrides['targetExperienceLevelName'] ?? null,
        $overrides['locationCity'] ?? null,
        $overrides['preferredWorkplaceArrangementId'] ?? null,
    );
}

/**
 * @param  array<string, mixed>  $overrides
 */
function job(array $overrides = []): RecommendationJob
{
    return new RecommendationJob(
        $overrides['id'] ?? 1,
        $overrides['categoryId'] ?? 1,
        $overrides['experienceLevelId'] ?? null,
        $overrides['experienceLevelRank'] ?? null,
        $overrides['experienceLevelName'] ?? null,
        $overrides['locationCity'] ?? null,
        $overrides['workplaceArrangementId'] ?? null,
        $overrides['workplaceArrangementName'] ?? null,
        $overrides['categoryName'] ?? 'Software Engineering',
        $overrides['totalSkillCount'] ?? 0,
        $overrides['matchedSkillCount'] ?? 0,
        $overrides['matchedSkillLabels'] ?? [],
    );
}

/**
 * @return array<int, string>
 */
function reasonCodes(RecommendationOutcome $outcome): array
{
    return array_map(static fn ($reason): string => $reason->code, $outcome->reasons);
}

beforeEach(function (): void {
    $this->scorer = new RecommendationScorer;
});

test('the canonical fixture scores one hundred with four reasons', function (): void {
    $outcome = $this->scorer->score(
        candidate([
            'skills' => ['php', 'laravel', 'mysql', 'redis', 'docker'],
            'skillLabels' => ['php' => 'PHP', 'laravel' => 'Laravel', 'mysql' => 'MySQL', 'redis' => 'Redis', 'docker' => 'Docker'],
            'preferredCategoryId' => 1,
            'targetExperienceLevelId' => 4,
            'targetExperienceRank' => 4,
            'targetExperienceLevelName' => 'Senior',
            'locationCity' => 'pune',
            'preferredWorkplaceArrangementId' => 2,
        ]),
        job([
            'categoryId' => 1,
            'experienceLevelId' => 4,
            'experienceLevelRank' => 4,
            'experienceLevelName' => 'Senior',
            'locationCity' => 'pune',
            'workplaceArrangementId' => 2,
            'workplaceArrangementName' => 'Hybrid',
            'categoryName' => 'Software Engineering',
            'totalSkillCount' => 4,
            'matchedSkillCount' => 4,
            'matchedSkillLabels' => ['PHP', 'Laravel', 'MySQL', 'Redis'],
        ]),
    );

    expect($outcome)->not->toBeNull()
        ->and($outcome->score)->toBe(100)
        ->and(reasonCodes($outcome))->toBe([
            'skill_overlap',
            'category_match',
            'experience_level_match',
            'location_match',
            'workplace_match',
        ]);
});

test('partial skill overlap scales the skill signal proportionally', function (): void {
    $outcome = $this->scorer->score(
        candidate([
            'skills' => ['php', 'laravel'],
            'preferredCategoryId' => 1,
            'targetExperienceRank' => 4,
            'locationCity' => 'pune',
            'preferredWorkplaceArrangementId' => 2,
        ]),
        job([
            'categoryId' => 1,
            'experienceLevelRank' => 4,
            'locationCity' => 'pune',
            'workplaceArrangementId' => 2,
            'totalSkillCount' => 4,
            'matchedSkillCount' => 2,
        ]),
    );

    // 20 skill points + 20 category + 20 level + 10 location + 10 workplace = 80 of 100.
    expect($outcome)->not->toBeNull()->and($outcome->score)->toBe(80);
});

test('no skill overlap earns no skill points and emits no skill reason', function (): void {
    $outcome = $this->scorer->score(
        candidate([
            'skills' => ['php'],
            'preferredCategoryId' => 1,
        ]),
        job([
            'categoryId' => 1,
            'totalSkillCount' => 4,
            'matchedSkillCount' => 0,
        ]),
    );

    // The skill signal still applies, so it weighs 40 of the 60 applicable
    // points and the candidate earns none of them.
    expect($outcome)->not->toBeNull()
        ->and($outcome->score)->toBe(33)
        ->and(reasonCodes($outcome))->toBe(['category_match']);
});

test('the skill signal does not apply when the job lists no skills', function (): void {
    $outcome = $this->scorer->score(
        candidate([
            'skills' => ['php'],
            'preferredCategoryId' => 1,
        ]),
        job([
            'categoryId' => 1,
            'totalSkillCount' => 0,
            'matchedSkillCount' => 0,
        ]),
    );

    // The skill weight is excluded from the applicable sum, so the category
    // signal alone decides the score.
    expect($outcome)->not->toBeNull()
        ->and($outcome->score)->toBe(100)
        ->and(reasonCodes($outcome))->toBe(['category_match']);
});

test('the skill signal does not apply when the candidate lists no skills', function (): void {
    $outcome = $this->scorer->score(
        candidate(['preferredCategoryId' => 1]),
        job([
            'categoryId' => 1,
            'totalSkillCount' => 4,
            'matchedSkillCount' => 4,
        ]),
    );

    expect($outcome)->not->toBeNull()
        ->and($outcome->score)->toBe(100)
        ->and(reasonCodes($outcome))->toBe(['category_match']);
});

test('a category mismatch earns no points and no reason', function (): void {
    $outcome = $this->scorer->score(
        candidate(['preferredCategoryId' => 1]),
        job(['categoryId' => 2]),
    );

    expect($outcome)->not->toBeNull()
        ->and($outcome->score)->toBe(0)
        ->and($outcome->reasons)->toBe([]);
});

test('an exact experience band earns full points and an adjacent band half', function (): void {
    $exact = $this->scorer->score(
        candidate(['targetExperienceRank' => 4, 'targetExperienceLevelName' => 'Senior']),
        job(['experienceLevelRank' => 4, 'experienceLevelName' => 'Senior']),
    );

    $adjacent = $this->scorer->score(
        candidate(['targetExperienceRank' => 4, 'targetExperienceLevelName' => 'Senior']),
        job(['experienceLevelRank' => 3, 'experienceLevelName' => 'Mid-level']),
    );

    $far = $this->scorer->score(
        candidate(['targetExperienceRank' => 4, 'targetExperienceLevelName' => 'Senior']),
        job(['experienceLevelRank' => 1, 'experienceLevelName' => 'Entry-level']),
    );

    expect($exact)->not->toBeNull()->and($exact->score)->toBe(100)
        ->and($adjacent)->not->toBeNull()->and($adjacent->score)->toBe(50)
        ->and($far)->not->toBeNull()->and($far->score)->toBe(0);
});

test('the level signal does not apply when the job has no experience level', function (): void {
    $outcome = $this->scorer->score(
        candidate(['targetExperienceRank' => 4]),
        job(['experienceLevelRank' => null]),
    );

    expect($outcome)->toBeNull();
});

test('the level signal does not apply when the candidate has no target level', function (): void {
    $outcome = $this->scorer->score(
        candidate([]),
        job(['experienceLevelRank' => 4]),
    );

    expect($outcome)->toBeNull();
});

test('location matches only when both sides resolve to the same city', function (): void {
    $matching = $this->scorer->score(
        candidate(['locationCity' => 'pune']),
        job(['locationCity' => 'pune']),
    );

    $different = $this->scorer->score(
        candidate(['locationCity' => 'pune']),
        job(['locationCity' => 'mumbai']),
    );

    expect($matching)->not->toBeNull()->and($matching->score)->toBe(100)
        ->and($different)->not->toBeNull()->and($different->score)->toBe(0);
});

test('the location signal does not apply to a job without a city', function (): void {
    $outcome = $this->scorer->score(
        candidate(['locationCity' => 'pune']),
        job(['locationCity' => null, 'workplaceArrangementId' => 2]),
    );

    expect($outcome)->toBeNull();
});

test('the workplace signal matches on the arrangement id', function (): void {
    $matching = $this->scorer->score(
        candidate(['preferredWorkplaceArrangementId' => 2]),
        job(['workplaceArrangementId' => 2, 'workplaceArrangementName' => 'Hybrid']),
    );

    $different = $this->scorer->score(
        candidate(['preferredWorkplaceArrangementId' => 2]),
        job(['workplaceArrangementId' => 1, 'workplaceArrangementName' => 'On-site']),
    );

    expect($matching)->not->toBeNull()->and($matching->score)->toBe(100)
        ->and($different)->not->toBeNull()->and($different->score)->toBe(0);
});

test('a job with no applicable signal cannot be recommended', function (): void {
    expect($this->scorer->score(candidate([]), job([])))->toBeNull();
});

test('distinct levels that share a sort order are not the same band', function (): void {
    // "1 year" and "3 year" both carry sort_order 0, which is what the admin
    // form leaves behind when the order field is not filled in. They are
    // different levels and must never be reported as the same band.
    $outcome = $this->scorer->score(
        candidate([
            'targetExperienceRank' => 3,
            'targetExperienceLevelName' => '3 year',
        ]),
        job([
            'experienceLevelRank' => 1,
            'experienceLevelName' => '1 year',
        ]),
    );

    expect($outcome)->not->toBeNull()
        ->and($outcome->score)->toBe(0)
        ->and($outcome->reasons)->toBe([]);
});

test('an unknown level has no rank', function (): void {
    expect($this->scorer->score(
        candidate(['targetExperienceRank' => null]),
        job(['experienceLevelRank' => 1]),
    ))->toBeNull();
});

test('every earned signal produces exactly one reason', function (): void {
    $outcome = $this->scorer->score(
        candidate([
            'skills' => ['php'],
            'preferredCategoryId' => 1,
            'targetExperienceRank' => 4,
            'locationCity' => 'pune',
            'preferredWorkplaceArrangementId' => 2,
        ]),
        job([
            'categoryId' => 1,
            'experienceLevelRank' => 4,
            'locationCity' => 'pune',
            'workplaceArrangementId' => 2,
            'totalSkillCount' => 2,
            'matchedSkillCount' => 2,
        ]),
    );

    expect($outcome)->not->toBeNull()
        ->and($outcome->reasons)->toHaveCount(5)
        ->and(array_unique(reasonCodes($outcome)))->toHaveCount(5);
});

test('the reason detail names the concrete matched values', function (): void {
    $outcome = $this->scorer->score(
        candidate([
            'skills' => ['php', 'laravel'],
            'skillLabels' => ['php' => 'PHP', 'laravel' => 'Laravel'],
        ]),
        job([
            'totalSkillCount' => 4,
            'matchedSkillCount' => 2,
            'matchedSkillLabels' => ['PHP', 'Laravel'],
        ]),
    );

    expect($outcome)->not->toBeNull()
        ->and($outcome->reasons[0]->detail)->toBe("Matches 2 of the job's 4 listed skills")
        ->and($outcome->reasons[0]->values)->toBe(['PHP', 'Laravel']);
});
