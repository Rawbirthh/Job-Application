<?php

namespace Database\Factories;

use App\Enums\InterviewMode;
use App\Enums\InterviewStatus;
use App\Enums\JobApplicationStatus;
use App\Models\Interview;
use App\Models\JobApplication;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Interview>
 */
class InterviewFactory extends Factory
{
    protected $model = Interview::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'job_application_id' => JobApplication::factory()->state([
                'status' => JobApplicationStatus::InterviewScheduled,
            ]),
            'scheduled_by_id' => null,
            'scheduled_at' => now()->addDays(2)->startOfHour()->utc(),
            'duration_minutes' => 60,
            'timezone' => 'UTC',
            'mode' => InterviewMode::Online,
            'location_details' => 'https://meet.example.com/interview',
            'candidate_instructions' => null,
            'internal_notes' => null,
            'status' => InterviewStatus::Scheduled,
            'responded_at' => null,
            'requested_scheduled_at' => null,
            'requested_timezone' => null,
            'response_note' => null,
            'cancelled_at' => null,
            'cancellation_reason' => null,
        ];
    }

    /**
     * Indicate that the candidate accepted the interview.
     */
    public function accepted(): static
    {
        return $this->state(fn (): array => [
            'status' => InterviewStatus::Accepted,
            'responded_at' => now(),
        ]);
    }

    /**
     * Indicate that the candidate declined the interview.
     */
    public function declined(): static
    {
        return $this->state(fn (): array => [
            'status' => InterviewStatus::Declined,
            'responded_at' => now(),
        ]);
    }

    /**
     * Indicate that the candidate requested a new interview time.
     */
    public function rescheduleRequested(): static
    {
        return $this->state(fn (): array => [
            'status' => InterviewStatus::RescheduleRequested,
            'responded_at' => now(),
            'requested_scheduled_at' => now()->addDays(4)->startOfHour()->utc(),
            'requested_timezone' => 'UTC',
            'response_note' => 'Could we move this to a later time?',
        ]);
    }

    /**
     * Indicate that the interview has been cancelled.
     */
    public function cancelled(): static
    {
        return $this->state(fn (): array => [
            'status' => InterviewStatus::Cancelled,
            'cancelled_at' => now(),
            'cancellation_reason' => 'The interview was cancelled.',
        ]);
    }
}
