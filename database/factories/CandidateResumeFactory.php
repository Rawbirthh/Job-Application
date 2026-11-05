<?php

namespace Database\Factories;

use App\Models\CandidateResume;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CandidateResume>
 */
class CandidateResumeFactory extends Factory
{
    protected $model = CandidateResume::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => null,
            'original_filename' => 'resume_'.Str::random(10).'.pdf',
            'path' => 'resumes/'.Str::random(32).'.pdf',
            'mime_type' => 'application/pdf',
            'size' => rand(1000, 5000000),
        ];
    }
}
