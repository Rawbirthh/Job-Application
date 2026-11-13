<?php

use App\Models\CandidateExperience;
use App\Models\CandidateProfile;
use App\Support\ExperienceCalculator;
use Carbon\CarbonImmutable;

/**
 * Build a candidate profile with the given work history, without touching the
 * database.
 *
 * @param  array<int, CandidateExperience>  $experiences
 */
function profileWithExperiences(array $experiences): CandidateProfile
{
    $profile = new CandidateProfile;
    $profile->setRelation('experiences', collect($experiences));

    return $profile;
}

function workExperience(?string $start, ?string $end = null, bool $isCurrent = false): CandidateExperience
{
    $experience = new CandidateExperience;
    $experience->start_date = $start;
    $experience->end_date = $end;
    $experience->is_current = $isCurrent;

    return $experience;
}

beforeEach(function (): void {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-01'));
});

afterEach(function (): void {
    CarbonImmutable::setTestNow();
});

test('it returns zero for a profile without work history', function (): void {
    expect(ExperienceCalculator::years(profileWithExperiences([])))->toBe(0.0);
});

test('it sums a single continuous role', function (): void {
    $profile = profileWithExperiences([
        workExperience('2021-06-01', null, true),
    ]);

    expect(ExperienceCalculator::years($profile))->toBe(5.4);
});

test('it sums sequential roles', function (): void {
    $profile = profileWithExperiences([
        workExperience('2019-01-01', '2021-01-01'),
        workExperience('2021-01-01', '2024-01-01'),
    ]);

    expect(ExperienceCalculator::years($profile))->toBe(5.0);
});

test('it merges overlapping roles instead of double counting them', function (): void {
    $profile = profileWithExperiences([
        workExperience('2021-06-01', null, true),
        workExperience('2023-01-01', '2023-09-01'),
    ]);

    // 2021-06-01 to 2026-11-01 merged, rather than 5.4 + 0.7 years.
    expect(ExperienceCalculator::years($profile))->toBe(5.4);
});

test('it ignores roles without a start date', function (): void {
    $profile = profileWithExperiences([
        workExperience(null, '2024-01-01'),
        workExperience('2024-01-01', '2026-01-01'),
    ]);

    expect(ExperienceCalculator::years($profile))->toBe(2.0);
});

test('it ignores roles that start in the future', function (): void {
    $profile = profileWithExperiences([
        workExperience('2027-01-01', null, true),
    ]);

    expect(ExperienceCalculator::years($profile))->toBe(0.0);
});

test('it ignores a role whose end date precedes its start date', function (): void {
    $profile = profileWithExperiences([
        workExperience('2024-01-01', '2023-01-01'),
    ]);

    expect(ExperienceCalculator::years($profile))->toBe(0.0);
});
