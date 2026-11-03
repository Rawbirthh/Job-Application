<?php

namespace Database\Factories;

use App\Models\CandidateProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CandidateProfile>
 */
class CandidateProfileFactory extends Factory
{
    protected $model = CandidateProfile::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'headline' => fake()->sentence(4),
            'summary' => fake()->paragraph(),
            'location' => fake()->city(),
            'skills' => ['php', 'laravel'],
        ];
    }
}
