<?php

namespace Database\Factories;

use App\Models\ApplicationNote;
use App\Models\JobApplication;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ApplicationNote>
 */
class ApplicationNoteFactory extends Factory
{
    protected $model = ApplicationNote::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'application_id' => JobApplication::factory(),
            'author_id' => User::factory(),
            'body' => fake()->paragraph(),
            'edited_at' => null,
        ];
    }
}
