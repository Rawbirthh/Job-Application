<?php

namespace Database\Factories;

use App\Models\EmployerOnboardingRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmployerOnboardingRequest>
 */
class EmployerOnboardingRequestFactory extends Factory
{
    protected $model = EmployerOnboardingRequest::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'company_name' => fake()->company(),
            'company_website' => fake()->url(),
            'job_title' => fake()->jobTitle(),
            'message' => fake()->paragraph(),
            'submitted_at' => now(),
            'reviewed_at' => null,
            'reviewed_by_id' => null,
            'review_note' => null,
        ];
    }
}
