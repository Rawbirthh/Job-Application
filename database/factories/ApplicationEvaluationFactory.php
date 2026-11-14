<?php

namespace Database\Factories;

use App\Models\ApplicationEvaluation;
use App\Models\JobApplication;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ApplicationEvaluation>
 */
class ApplicationEvaluationFactory extends Factory
{
    protected $model = ApplicationEvaluation::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'application_id' => JobApplication::factory(),
            'evaluator_id' => User::factory(),
            'relevant_experience' => 4,
            'technical_skills' => 4,
            'communication' => 4,
            'interview_performance' => 4,
            'comment' => null,
        ];
    }
}
