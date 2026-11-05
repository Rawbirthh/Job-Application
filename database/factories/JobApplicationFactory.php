<?php

namespace Database\Factories;

use App\Models\CandidateResume;
use App\Models\Job;
use App\Models\JobApplication;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JobApplication>
 */
class JobApplicationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'job_posting_id' => Job::factory(),
            'user_id' => User::factory()->jobSeeker(),
            'candidate_resume_id' => CandidateResume::factory(),
            'cover_letter' => $this->faker->optional()->paragraph(),
            'status' => 'submitted',
        ];
    }
}
