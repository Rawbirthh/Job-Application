<?php

namespace Database\Factories;

use App\Models\CandidateEducation;
use App\Models\CandidateProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CandidateEducation>
 */
class CandidateEducationFactory extends Factory
{
    protected $model = CandidateEducation::class;

    public function definition(): array
    {
        return [
            'candidate_profile_id' => CandidateProfile::factory(),
            'institution' => fake()->company(),
            'degree' => 'BSc',
            'field_of_study' => 'Computer Science',
            'start_date' => now()->subYears(4)->toDateString(),
            'end_date' => now()->subYears(1)->toDateString(),
            'description' => fake()->sentence(),
        ];
    }
}
