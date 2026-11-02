<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\EmploymentType;
use App\Models\ExperienceLevel;
use App\Models\Job;
use App\Models\JobCategory;
use App\Models\PayPeriod;
use App\Models\WorkplaceArrangement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Job>
 */
class JobFactory extends Factory
{
    protected $model = Job::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->jobTitle();
        // Ensure title meets min/max constraints (3-150 chars)
        $title = mb_strlen($title) < 3 ? 'Senior '.$title : $title;
        $title = mb_strlen($title) > 150 ? mb_substr($title, 0, 150) : $title;

        return [
            'company_id' => Company::factory(),
            'title' => $title,
            'description' => fake()->paragraphs(3, true),
            'responsibilities' => fake()->optional(0.7)->paragraphs(2, true),
            'requirements' => fake()->optional(0.7)->paragraphs(2, true),
            'category_id' => JobCategory::factory(),
            'employment_type_id' => EmploymentType::factory(),
            'workplace_arrangement_id' => WorkplaceArrangement::factory(),
            'experience_level_id' => ExperienceLevel::factory(),
            'salary_min' => null,
            'salary_max' => null,
            'salary_currency' => null,
            'salary_negotiable' => false,
            'pay_period_id' => null,
            'location' => fake()->optional(0.8)->city(),
            'application_deadline' => fake()->optional(0.5)->dateTimeBetween('now', '+60 days'),
            'status' => 'draft',
        ];
    }

    /**
     * Indicate that the job has a salary range.
     */
    public function withSalary(): static
    {
        return $this->state(function (): array {
            $min = fake()->numberBetween(30000, 80000);
            $max = fake()->numberBetween($min + 10000, $min + 50000);

            return [
                'salary_min' => $min,
                'salary_max' => $max,
                'salary_currency' => 'USD',
                'salary_negotiable' => fake()->boolean(30),
                'pay_period_id' => PayPeriod::factory(),
            ];
        });
    }

    /**
     * Indicate that the job is in a specific status.
     */
    public function status(string $status): static
    {
        return $this->state(fn () => [
            'status' => $status,
        ]);
    }
}
