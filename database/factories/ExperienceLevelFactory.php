<?php

namespace Database\Factories;

use App\Models\ExperienceLevel;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ExperienceLevel>
 */
class ExperienceLevelFactory extends Factory
{
    protected $model = ExperienceLevel::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->jobTitle();

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => fake()->sentence(),
            'sort_order' => 0,
            'is_active' => true,
        ];
    }

    /**
     * Indicate that the experience level is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn () => [
            'is_active' => false,
        ]);
    }
}
