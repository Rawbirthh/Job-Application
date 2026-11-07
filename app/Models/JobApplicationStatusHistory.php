<?php

namespace App\Models;

use App\Enums\JobApplicationStatus;
use Database\Factories\JobApplicationStatusHistoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $job_application_id
 * @property int|null $actor_id
 * @property JobApplicationStatus $from_status
 * @property JobApplicationStatus $to_status
 * @property string|null $reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read JobApplication $jobApplication
 * @property-read User|null $actor
 */
class JobApplicationStatusHistory extends Model
{
    /** @use HasFactory<JobApplicationStatusHistoryFactory> */
    use HasFactory;

    protected $fillable = [
        'job_application_id',
        'actor_id',
        'from_status',
        'to_status',
        'reason',
    ];

    protected $casts = [
        'from_status' => JobApplicationStatus::class,
        'to_status' => JobApplicationStatus::class,
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
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
