<?php

namespace Database\Factories;

use App\Models\CandidateExperience;
use App\Models\CandidateProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\CandidateExperience>
 */
class CandidateExperienceFactory extends Factory
{
    protected $model = CandidateExperience::class;

    public function definition(): array
    {
        return [
            'candidate_profile_id' => CandidateProfile::factory(),
            'company_name' => fake()->company(),
            'job_title' => fake()->jobTitle(),
            'location' => fake()->city(),
            'start_date' => now()->subYears(2)->toDateString(),
            'end_date' => now()->subYear()->toDateString(),
            'is_current' => false,
            'description' => fake()->sentence(),
        ];
    }
}
