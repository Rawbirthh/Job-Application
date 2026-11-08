<?php

namespace App\Models;

use App\Enums\InterviewMode;
use App\Enums\InterviewStatus;
use Carbon\CarbonImmutable;
use Database\Factories\InterviewFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $job_application_id
 * @property int|null $scheduled_by_id
 * @property CarbonImmutable $scheduled_at
 * @property int $duration_minutes
 * @property string $timezone
 * @property InterviewMode $mode
 * @property string $location_details
 * @property string|null $candidate_instructions
 * @property string|null $internal_notes
 * @property InterviewStatus $status
 * @property CarbonImmutable|null $responded_at
 * @property CarbonImmutable|null $requested_scheduled_at
 * @property string|null $requested_timezone
 * @property string|null $response_note
 * @property CarbonImmutable|null $cancelled_at
 * @property string|null $cancellation_reason
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read JobApplication $jobApplication
 * @property-read User|null $scheduledBy
 */
class Interview extends Model
{
    /** @use HasFactory<InterviewFactory> */
    use HasFactory;

    protected $fillable = [
        'job_application_id',
        'scheduled_by_id',
        'scheduled_at',
        'duration_minutes',
        'timezone',
        'mode',
        'location_details',
        'candidate_instructions',
        'internal_notes',
        'status',
        'responded_at',
        'requested_scheduled_at',
        'requested_timezone',
        'response_note',
        'cancelled_at',
        'cancellation_reason',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'duration_minutes' => 'integer',
        'mode' => InterviewMode::class,
        'status' => InterviewStatus::class,
        'responded_at' => 'datetime',
        'requested_scheduled_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<JobApplication, $this>
     */
    public function jobApplication(): BelongsTo
    {
        return $this->belongsTo(JobApplication::class, 'job_application_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function scheduledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'scheduled_by_id');
    }
}
